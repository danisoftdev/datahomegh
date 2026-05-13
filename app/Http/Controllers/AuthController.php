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

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(User::class, 'username')],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agent_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
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

        $agent = null;
        if (! empty($data['agent_slug'])) {
            $agent = User::query()
                ->where('shop_slug', $data['agent_slug'])
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_AGENT))
                ->first();
        }

        $user = DB::transaction(function () use ($data, $buyerRole, $agent) {
            $user = User::query()->create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => ! empty($data['email']) ? $data['email'] : null,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role_id' => $buyerRole->id,
                'agent_id' => $agent?->id,
                'status' => 'pending',
            ]);

            Wallet::query()->create([
                'user_id' => $user->id,
                'balance' => 0,
                'is_frozen' => false,
            ]);

            return $user;
        });

        $this->notifyRegistrationApprovers($user, $agent);

        return redirect()->route('pending-approval')
            ->with('status', __('Registration submitted. Await approval.'));
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

    private function notifyRegistrationApprovers(User $buyer, ?User $agent): void
    {
        $title = 'New buyer registration';
        $message = "{$buyer->name} (@{$buyer->username}) registered and is awaiting approval.";
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->cursor()
            ->each(function (User $supplier) use ($title, $message): void {
                $this->notificationService->notify($supplier->id, $title, $message, 'buyer_registered');
            });

        if ($agent !== null) {
            $this->notificationService->notify($agent->id, $title, $message, 'buyer_registered');
        }
    }
}
