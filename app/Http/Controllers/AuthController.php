<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function showRegisterForm(Request $request, ?string $agentSlug = null): View
    {
        $agentSlug = $agentSlug !== null && $agentSlug !== ''
            ? $agentSlug
            : (string) $request->query('agent', '');

        $agent = null;

        if ($agentSlug !== '') {
            $agent = User::query()
                ->where('shop_slug', $agentSlug)
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
                ->first();
        }

        return view('auth.register', [
            'agent' => $agent,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $buyerRole = Role::query()->where('slug', Role::SLUG_BUYER)->firstOrFail();
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $viaAgentShop = $request->boolean('via_agent_shop');

        $accountTypeForRules = $viaAgentShop
            ? 'buyer'
            : (string) $request->input('account_type', 'buyer');
        if (! in_array($accountTypeForRules, ['buyer', 'agent'], true)) {
            $accountTypeForRules = 'buyer';
        }

        $emailRules = $accountTypeForRules === 'agent'
            ? ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')]
            : ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')];

        $validator = Validator::make($request->all(), [
            'account_type' => $viaAgentShop
                ? ['sometimes', 'nullable', 'in:buyer,agent']
                : ['required', 'in:buyer,agent'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(User::class, 'username')],
            'name' => ['required', 'string', 'max:100'],
            'email' => $emailRules,
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agent_slug' => ['nullable', 'string', 'max:255'],
            'shop_name' => [Rule::requiredIf($accountTypeForRules === 'agent'), 'nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request, $accountTypeForRules): void {
            if ($accountTypeForRules === 'agent') {
                return;
            }

            $slug = $request->input('agent_slug');
            if (! $slug) {
                return;
            }

            $exists = User::query()
                ->where('shop_slug', $slug)
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
                ->exists();

            if (! $exists) {
                $validator->errors()->add('agent_slug', __('The selected agent shop is invalid.'));
            }
        });

        $data = $validator->validate();

        $accountType = $viaAgentShop ? 'buyer' : (string) ($data['account_type'] ?? 'buyer');
        if (! in_array($accountType, ['buyer', 'agent'], true)) {
            $accountType = 'buyer';
        }

        $linkedAgent = null;
        if ($accountType === 'buyer' && ! empty($data['agent_slug'])) {
            $linkedAgent = User::query()
                ->where('shop_slug', $data['agent_slug'])
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
                ->first();
        }

        $roleId = $accountType === 'agent' ? $agentRole->id : $buyerRole->id;
        $status = $accountType === 'agent' ? 'pending' : 'active';

        $user = DB::transaction(function () use ($data, $roleId, $status, $linkedAgent, $accountType) {
            $user = User::query()->create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => ! empty($data['email']) ? $data['email'] : null,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role_id' => $roleId,
                'agent_id' => $accountType === 'buyer' ? $linkedAgent?->id : null,
                'shop_name' => $accountType === 'agent' ? ($data['shop_name'] ?? null) : null,
                'status' => $status,
            ]);

            Wallet::query()->create([
                'user_id' => $user->id,
                'balance' => 0,
                'is_frozen' => false,
            ]);

            return $user;
        });

        if ($accountType === 'agent') {
            $this->notifyAgentRegistrationApprovers($user);

            return redirect()->route('login')
                ->with('status', __('Your agent application was submitted. You will receive an email at :email when it is approved.', ['email' => $user->email]));
        }

        Auth::login($user);

        return redirect()->route('buyer.dashboard')
            ->with('status', __('Welcome! Your account is ready.'));
    }

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            return back()->withErrors([
                'username' => __('These credentials do not match our records.'),
            ])->onlyInput('username');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('role');

        if ($user->status === 'declined') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => __('Your agent application was not approved.'),
            ])->onlyInput('username');
        }

        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => __('Your account is not active yet.'),
            ])->onlyInput('username');
        }

        if (! $user->role || ! $user->role->is_enabled) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => __('Your role is disabled. Contact support.'),
            ])->onlyInput('username');
        }

        return redirect()->intended($this->dashboardPathForUser($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function pendingApproval(): View
    {
        return view('auth.pending', [
            'whatsappUrl' => config('datahomegh.admin_whatsapp_url'),
        ]);
    }

    private function dashboardPathForUser(User $user): string
    {
        return match ($user->role?->slug) {
            Role::SLUG_SUPPLIER => route('admin.dashboard', [], false),
            Role::SLUG_AGENT => route('agent.dashboard', [], false),
            Role::SLUG_BUYER => route('buyer.dashboard', [], false),
            default => '/',
        };
    }

    private function notifyAgentRegistrationApprovers(User $agentUser): void
    {
        $title = 'New agent registration';
        $message = "{$agentUser->name} (@{$agentUser->username}) applied as an agent and awaits approval.";
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->cursor()
            ->each(function (User $supplier) use ($title, $message): void {
                $this->notificationService->notify($supplier->id, $title, $message, 'agent_registered');
            });
    }
}
