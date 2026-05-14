<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AgentAccountApprovedMail;
use App\Models\PasswordResetCode;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()
            ->with('role')
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [Role::SLUG_AGENT, Role::SLUG_BUYER]));

        if ($request->filled('role')) {
            $slug = $request->string('role')->toString();
            if (in_array($slug, [Role::SLUG_AGENT, Role::SLUG_BUYER], true)) {
                $query->whereHas('role', fn ($q) => $q->where('slug', $slug));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($s): void {
                $q->where('username', 'like', $s)
                    ->orWhere('name', 'like', $s)
                    ->orWhere('phone', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhere('shop_name', 'like', $s);
            });
        }

        $users = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function show(User $user): View
    {
        abort_unless(
            in_array($user->role?->slug, [Role::SLUG_AGENT, Role::SLUG_BUYER], true),
            404
        );

        $user->load('role', 'wallet', 'agent');

        $orders = $user->orders()->with('bundlePackage')->latest()->paginate(10, ['*'], 'orders_page');

        $ledger = $user->walletLedgers()->orderByDesc('id')->paginate(15, ['*'], 'ledger_page');

        return view('admin.users.show', [
            'user' => $user,
            'orders' => $orders,
            'ledger' => $ledger,
        ]);
    }

    public function approveAgent(User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);
        abort_unless($user->status === 'pending', 422);

        if (! is_string($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', __('This agent must have a valid email on file before you can approve them. An approval message is sent to that address.'));
        }

        $shopName = $user->shop_name ?: $user->name;
        abort_if(trim($shopName) === '', 422, 'Agent must have a shop name or display name before approval.');

        $slug = $this->uniqueShopSlug($shopName, $user->id);

        $user->status = 'active';
        $user->shop_slug = $slug;
        if (empty($user->shop_name)) {
            $user->shop_name = $shopName;
        }
        $user->save();

        $this->notificationService->notify(
            $user->id,
            'Account approved',
            'Your agent account is active. Your shop slug is '.$slug.'.',
            'account_approved',
        );

        Mail::to($user->email)->send(new AgentAccountApprovedMail($user, $slug));

        return back()->with('status', __('Agent approved and notified by email.'));
    }

    public function declineAgent(User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);
        abort_unless($user->status === 'pending', 422);

        $user->status = 'declined';
        $user->save();

        return back()->with('status', __('Agent application declined.'));
    }

    public function holdAgent(User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);

        $user->status = 'held';
        $user->save();

        return back()->with('status', __('Agent placed on hold.'));
    }

    public function releaseAgent(User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);

        $user->status = 'active';
        $user->save();

        return back()->with('status', __('Agent released from hold.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);
        abort_unless(in_array($user->role?->slug, [Role::SLUG_AGENT, Role::SLUG_BUYER], true), 404);

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', __('User removed.'));
    }

    public function issueResetCode(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);

        $plain = strtoupper(Str::random(8));
        $hash = hash('sha256', $plain);

        try {
            DB::transaction(function () use ($user, $hash): void {
                PasswordResetCode::query()
                    ->where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->whereNotNull('code_hash')
                    ->update(['used_at' => now()]);

                $pending = PasswordResetCode::query()
                    ->where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->whereNull('code_hash')
                    ->orderBy('id')
                    ->firstOrFail();

                $pending->update([
                    'code_hash' => $hash,
                    'expires_at' => now()->addMinutes(20),
                    'attempts' => 0,
                ]);
            });
        } catch (\Throwable) {
            return back()->with('error', __('No pending password reset request for this user. Ask them to submit a request first.'));
        }

        return back()->with('reset_code_plain', $plain)->with('status', __('Reset code generated. Copy it now; it will not be shown again.'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = Role::query()->findOrFail($validated['role_id']);
        abort_if($role->slug === Role::SLUG_SUPPLIER, 403);

        $user->role_id = $role->id;
        $user->save();

        return back()->with('status', __('Role updated.'));
    }

    public function setDailyLimit(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'daily_order_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $user->daily_order_limit = $validated['daily_order_limit'] ?? null;
        $user->save();

        return back()->with('status', __('Daily order limit saved.'));
    }

    private function uniqueShopSlug(string $shopName, ?int $exceptUserId = null): string
    {
        $base = Str::slug($shopName);
        if ($base === '') {
            $base = 'shop';
        }

        $slug = $base;
        $i = 2;

        while (User::query()
            ->where('shop_slug', $slug)
            ->when($exceptUserId !== null, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
