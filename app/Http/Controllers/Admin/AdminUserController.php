<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Mail\AgentAccountApprovedMail;
use App\Models\PasswordResetCode;
use App\Models\PaystackTransaction;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AgentShopRegistrationPaymentService;
use App\Services\NotificationService;
use App\Services\PaystackService;
use App\Services\UserAccountPurgeService;
use App\Services\WalletService;
use App\Support\PaystackPaymentPurpose;
use App\Support\PaystackVerifyAmount;
use App\Support\TransactionReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UserAccountPurgeService $userAccountPurgeService,
        private readonly WalletService $walletService,
        private readonly PaystackService $paystackService,
        private readonly AgentShopRegistrationPaymentService $registrationPaymentService,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()
            ->with(['role', 'wallet'])
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', Role::SLUG_SUPPLIER));

        if ($request->filled('role')) {
            $slug = $request->string('role')->toString();
            $query->whereHas('role', fn ($q) => $q->where('slug', $slug));
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

    public function createAgent(): View
    {
        return view('admin.users.create-agent');
    }

    public function storeAgent(Request $request): RedirectResponse
    {
        $agentRole = Role::query()->where('slug', Role::SLUG_AGENT)->firstOrFail();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(User::class, 'username')],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'shop_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated, $agentRole): User {
            $slug = $this->uniqueShopSlug($validated['shop_name'], null);

            $user = User::query()->create([
                'username' => $validated['username'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role_id' => $agentRole->id,
                'agent_id' => null,
                'shop_name' => $validated['shop_name'],
                'shop_slug' => $slug,
                'status' => 'active',
            ]);

            Wallet::query()->create([
                'user_id' => $user->id,
                'balance' => 0,
                'is_frozen' => false,
            ]);

            return $user;
        });

        $this->notificationService->notify(
            $user->id,
            'Account ready',
            'Your agent account is active. Your shop slug is '.$user->shop_slug.'.',
            'account_approved',
        );

        Mail::to($user->email)->send(new AgentAccountApprovedMail($user, (string) $user->shop_slug));

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('Agent account created. They can log in with the username and password you set.'));
    }

    public function show(User $user): View
    {
        abort_if($user->isSupplier(), 404);

        $user->load('role', 'wallet', 'agent');

        $orders = $user->orders()->with('bundlePackage')->latest()->paginate(10, ['*'], 'orders_page');

        $ledger = $user->walletLedgers()->orderByDesc('id')->paginate(15, ['*'], 'ledger_page');

        $registrationTransaction = null;
        if ($user->role?->slug === Role::SLUG_AGENT) {
            $registrationTransaction = PaystackTransaction::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get()
                ->first(fn (PaystackTransaction $txn): bool => PaystackPaymentPurpose::storedKind($txn) === PaystackPaymentPurpose::AGENT_SHOP_REGISTRATION
                    || ($txn->status === 'pending' && $user->status === 'pending_payment'));
        }

        return view('admin.users.show', [
            'user' => $user,
            'orders' => $orders,
            'ledger' => $ledger,
            'registrationTransaction' => $registrationTransaction,
        ]);
    }

    public function confirmRegistrationPayment(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);

        if (! in_array($user->status, ['pending_payment', 'pending'], true)) {
            return back()->with('error', __('This agent is not waiting for a registration payment.'));
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $reference = trim((string) ($validated['reference'] ?? ''));
        if ($reference === '') {
            $reference = (string) (PaystackTransaction::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->value('reference') ?? '');
        }

        if ($reference === '') {
            return back()->with('error', __('Enter the Paystack reference from the agent’s receipt, or ask them to open the return link after payment.'));
        }

        try {
            $data = $this->paystackService->verifyPayment($reference);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (strtolower((string) ($data['status'] ?? '')) !== 'success') {
            return back()->with('error', __('Paystack has not marked this payment as successful yet. Try again shortly.'));
        }

        $amountGhs = PaystackVerifyAmount::ghsFromVerifyData($data);

        try {
            $this->registrationPaymentService->completeSuccessfulPayment($user->id, $reference, $amountGhs, $data);
        } catch (Throwable $e) {
            Log::error('admin_confirm_registration_payment_failed', [
                'user_id' => $user->id,
                'reference' => $reference,
                'exception' => $e,
            ]);

            return back()->with('error', __('Could not confirm payment: :message', ['message' => $e->getMessage()]));
        }

        return back()->with('status', __('Registration payment confirmed. The agent is now awaiting approval (status: pending).'));
    }

    public function approveAgent(User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);

        if ($user->status !== 'pending') {
            if ($user->status === 'pending_payment') {
                return back()->with('error', __('This agent has not finished paying the shop link registration fee. They move to pending approval only after Paystack confirms payment.'));
            }

            if ($user->status === 'active') {
                return back()->with('error', __('This agent is already approved.'));
            }

            return back()->with('error', __('This agent cannot be approved while their status is :status.', ['status' => $user->status]));
        }

        if (! is_string($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', __('This agent must have a valid email on file before you can approve them. An approval message is sent to that address.'));
        }

        $shopName = $user->shop_name ?: $user->name;
        if (trim($shopName) === '') {
            return back()->with('error', __('The agent must have a shop name or display name before approval.'));
        }

        $slug = is_string($user->shop_slug) && $user->shop_slug !== ''
            ? $user->shop_slug
            : $this->uniqueShopSlug($shopName, $user->id);

        if (User::query()->where('shop_slug', $slug)->where('id', '!=', $user->id)->exists()) {
            return back()->with('error', __('This shop code is already taken. Contact support.'));
        }

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

        try {
            Mail::to($user->email)->send(new AgentAccountApprovedMail($user, (string) $user->shop_slug));
        } catch (\Throwable $e) {
            Log::warning('agent_approval_email_failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            return back()->with('status', __('Agent approved. In-app notification was sent, but the approval email could not be sent. Configure MAIL_* in .env or check the server log.'));
        }

        return back()->with('status', __('Agent approved and notified by email.'));
    }

    public function declineAgent(User $user): RedirectResponse
    {
        abort_unless($user->role?->slug === Role::SLUG_AGENT, 404);

        if (! in_array($user->status, ['pending', 'pending_payment'], true)) {
            if ($user->status === 'active') {
                return back()->with('error', __('You cannot decline an agent who is already approved. Use hold or delete if needed.'));
            }

            return back()->with('error', __('This application cannot be declined while the status is :status.', ['status' => $user->status]));
        }

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

        $this->userAccountPurgeService->permanentlyDelete($user);

        return redirect()->route('admin.users.index')->with('status', __('User permanently removed. Their username and email can be used to register again.'));
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
            'waive_promotion_fee' => ['sometimes', 'boolean'],
        ]);

        $role = Role::query()->findOrFail($validated['role_id']);
        abort_if($role->slug === Role::SLUG_SUPPLIER, 403);

        if ((int) $user->role_id === (int) $role->id) {
            return back()->with('status', __('Role unchanged.'));
        }

        if ($role->requires_promotion_fee && ! $request->boolean('waive_promotion_fee')) {
            $fee = $role->promotionFeeAmount();
            if (bccomp($fee, '0', 2) > 0) {
                $this->ensureWalletExists($user);

                try {
                    $this->walletService->debit(
                        $user->id,
                        $fee,
                        'ROLE_PROMOTION',
                        'role_'.$role->id.'_'.str_replace('.', '', uniqid('', true)),
                        __('Promotion fee for role :role', ['role' => $role->name]),
                    );
                } catch (InsufficientBalanceException) {
                    return back()->withErrors([
                        'role_id' => __('User wallet needs :amount GHS for this role (or check “Waive promotion fee”).', [
                            'amount' => $fee,
                        ]),
                    ]);
                }
            }
        }

        $user->role_id = $role->id;
        if ($role->isCustomRole() && $user->status !== 'active') {
            $user->status = 'active';
        }
        $user->save();

        return back()->with('status', __('Role updated. Platform data prices now follow :role.', ['role' => $role->name]));
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

    public function creditWallet(Request $request, User $user): RedirectResponse
    {
        $this->assertWalletManageable($user);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureWalletExists($user);

        $admin = $request->user();
        $ref = 'admin_credit_'.$user->id.'_'.str_replace('.', '', uniqid('', true));
        $extra = trim((string) ($validated['note'] ?? ''));
        $ledgerNote = $extra !== ''
            ? __('Admin :admin — :note', ['admin' => $admin->username, 'note' => $extra])
            : __('Credit from platform admin :admin', ['admin' => $admin->username]);

        try {
            $ledger = $this->walletService->credit(
                $user->id,
                $validated['amount'],
                'ADMIN_CREDIT',
                $ref,
                $ledgerNote,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $this->notificationService->notify(
            $user->id,
            __('Wallet credited'),
            __(':amount GHS was added to your wallet by the platform.', ['amount' => number_format((float) $validated['amount'], 2)]),
            'wallet_admin_credit',
        );

        return back()
            ->with('status', __('Wallet credited.'))
            ->with('transaction_receipt', TransactionReceipt::fromWalletLedger($ledger, $user->username));
    }

    public function debitWallet(Request $request, User $user): RedirectResponse
    {
        $this->assertWalletManageable($user);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureWalletExists($user);

        $admin = $request->user();
        $ref = 'admin_debit_'.$user->id.'_'.str_replace('.', '', uniqid('', true));
        $extra = trim((string) ($validated['note'] ?? ''));
        $ledgerNote = $extra !== ''
            ? __('Admin :admin — :note', ['admin' => $admin->username, 'note' => $extra])
            : __('Debit by platform admin :admin', ['admin' => $admin->username]);

        try {
            $ledger = $this->walletService->debit(
                $user->id,
                $validated['amount'],
                'ADMIN_DEBIT',
                $ref,
                $ledgerNote,
            );
        } catch (InsufficientBalanceException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()
            ->with('status', __('Wallet debited.'))
            ->with('transaction_receipt', TransactionReceipt::fromWalletLedger($ledger, $user->username));
    }

    private function assertWalletManageable(User $user): void
    {
        abort_if($user->isSupplier(), 404);
    }

    private function ensureWalletExists(User $user): void
    {
        if ($user->wallet !== null) {
            return;
        }

        Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 0,
            'is_frozen' => false,
        ]);

        $user->load('wallet');
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
