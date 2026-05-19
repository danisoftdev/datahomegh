<?php

use App\Http\Controllers\Admin\AdminBundleController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminFulfillmentApiController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Agent\AgentBundleController;
use App\Http\Controllers\Agent\AgentBuyerController;
use App\Http\Controllers\Agent\AgentCheckoutController;
use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Agent\AgentOrderController;
use App\Http\Controllers\Agent\AgentProfileController;
use App\Http\Controllers\Agent\AgentResalePlanController;
use App\Http\Controllers\AgentRegistrationFeeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\BuyerProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WalletController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pending-approval', [AuthController::class, 'pendingApproval'])
    ->name('pending-approval');

Route::middleware('guest')->group(function (): void {
    Route::get('/register/{agentSlug?}', [AuthController::class, 'showRegisterForm'])
        ->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/register/agent-fee/confirm', [AgentRegistrationFeeController::class, 'showConfirmForm'])
        ->name('register.agent-fee.confirm-form');
    Route::match(['get', 'post'], '/register/agent-fee/callback', [AgentRegistrationFeeController::class, 'callback'])
        ->name('register.agent-fee.callback');

    Route::get('/login', [AuthController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/password-reset/request', [PasswordResetController::class, 'showRequestForm'])
        ->name('password.request');
    Route::post('/password-reset/request', [PasswordResetController::class, 'requestReset'])
        ->name('password.request.submit');
    Route::get('/password-reset', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset.form');
    Route::post('/password-reset', [PasswordResetController::class, 'reset'])
        ->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::post('/wallet/paystack/webhook', [WalletController::class, 'webhook'])
    ->name('wallet.paystack.webhook');

Route::match(['get', 'post'], '/wallet/topup/callback', [WalletController::class, 'callback'])
    ->name('wallet.topup.callback');

Route::middleware('auth')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::middleware('supplier')->post('/notifications/admin-broadcast', [NotificationController::class, 'adminBroadcast'])
        ->name('notifications.admin-broadcast');

    Route::middleware('role:'.Role::SLUG_AGENT)->prefix('agent')->name('agent.')->group(function (): void {
        Route::get('/', [AgentController::class, 'dashboard'])->name('dashboard');

        Route::get('profile/edit', [AgentProfileController::class, 'edit'])->name('profile.edit');
        Route::match(['put', 'patch'], 'profile', [AgentProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/close-account', [AgentProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('orders', [AgentOrderController::class, 'index'])->name('orders.index');
        Route::middleware('wallet')->group(function (): void {
            Route::get('orders/create', [AgentCheckoutController::class, 'create'])->name('orders.create');
            Route::post('orders', [AgentCheckoutController::class, 'store'])->name('orders.store');
        });
        Route::post('orders/bulk-update', [AgentOrderController::class, 'bulkUpdate'])->name('orders.bulk-update');
        Route::get('orders/{order}', [AgentOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [AgentOrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/notes', [AgentOrderController::class, 'addNote'])->name('orders.notes');

        Route::get('buyers', [AgentBuyerController::class, 'index'])->name('buyers.index');
        Route::get('buyers/{buyer}', [AgentBuyerController::class, 'show'])->name('buyers.show');
        Route::post('buyers/{buyer}/approve', [AgentBuyerController::class, 'approve'])->name('buyers.approve');
        Route::post('buyers/{buyer}/wallet-credit', [AgentBuyerController::class, 'creditWallet'])->name('buyers.wallet-credit');
        Route::delete('buyers/{buyer}', [AgentBuyerController::class, 'destroy'])->name('buyers.destroy');

        Route::resource('bundles', AgentBundleController::class)->except(['show']);
        Route::post('bundles/{bundle}/stock', [AgentBundleController::class, 'updateStock'])->name('bundles.stock');
        Route::patch('bundles/{bundle}/availability', [AgentBundleController::class, 'toggleAvailability'])->name('bundles.availability');

        Route::resource('bundles.resale-plans', AgentResalePlanController::class)->except(['show']);
    });

    Route::middleware('role:'.Role::SLUG_AGENT)->get('/agent/dashboard', fn () => redirect()->route('agent.dashboard'));

    Route::middleware(['auth', 'role:'.Role::SLUG_BUYER])->prefix('buyer')->name('buyer.')->group(function (): void {
        Route::get('profile/edit', [BuyerProfileController::class, 'edit'])->name('profile.edit');
        Route::match(['put', 'patch'], 'profile', [BuyerProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/close-account', [BuyerProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::middleware(['auth', 'role:'.Role::SLUG_BUYER, 'wallet'])->prefix('buyer')->name('buyer.')->group(function (): void {
        Route::get('/', [BuyerController::class, 'dashboard'])->name('dashboard');

        Route::get('orders/repeat-last', [BuyerOrderController::class, 'repeatLast'])->name('orders.repeat-last');
        Route::get('orders', [BuyerOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [BuyerOrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [BuyerOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [BuyerOrderController::class, 'show'])->name('orders.show');
    });

    Route::middleware('role:'.Role::SLUG_BUYER)->get('/buyer/dashboard', fn () => redirect()->route('buyer.dashboard'));

    Route::middleware('role:'.Role::SLUG_BUYER)->group(function (): void {
        Route::get('/orders', fn () => redirect()->route('buyer.orders.index'));
        Route::get('/orders/create', fn () => redirect()->route('buyer.orders.create'));
        Route::get('/orders/repeat-last', fn () => redirect()->route('buyer.orders.create'));
    });

    Route::prefix('wallet')->name('wallet.')->group(function (): void {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::middleware('wallet')->group(function (): void {
            Route::post('/topup', [WalletController::class, 'initializeTopup'])->name('topup.initialize');
        });
    });

    Route::middleware('supplier')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        Route::get('profile/edit', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::match(['put', 'patch'], 'profile', [AdminProfileController::class, 'update'])->name('profile.update');

        Route::get('orders/export', [AdminOrderController::class, 'export'])->name('orders.export');
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::post('orders/bulk-update', [AdminOrderController::class, 'bulkUpdate'])->name('orders.bulk-update');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/notes', [AdminOrderController::class, 'addNote'])->name('orders.notes');
        Route::post('orders/{order}/refresh-provider-status', [AdminOrderController::class, 'refreshProviderStatus'])
            ->name('orders.refresh-provider-status');
        Route::post('orders/{order}/dispatch-to-provider', [AdminOrderController::class, 'dispatchToProvider'])
            ->name('orders.dispatch-to-provider');

        Route::get('fulfillment-apis', [AdminFulfillmentApiController::class, 'index'])->name('fulfillment-apis.index');
        Route::get('fulfillment-apis/{fulfillmentApiProfile}/edit', [AdminFulfillmentApiController::class, 'edit'])->name('fulfillment-apis.edit');
        Route::post('fulfillment-apis', [AdminFulfillmentApiController::class, 'store'])->name('fulfillment-apis.store');
        Route::patch('fulfillment-apis/{fulfillmentApiProfile}', [AdminFulfillmentApiController::class, 'update'])->name('fulfillment-apis.update');
        Route::post('fulfillment-apis/{fulfillmentApiProfile}/activate', [AdminFulfillmentApiController::class, 'activate'])->name('fulfillment-apis.activate');
        Route::delete('fulfillment-apis/{fulfillmentApiProfile}', [AdminFulfillmentApiController::class, 'destroy'])->name('fulfillment-apis.destroy');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create-agent', [AdminUserController::class, 'createAgent'])->name('users.create-agent');
        Route::post('users/create-agent', [AdminUserController::class, 'storeAgent'])->name('users.store-agent');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/approve-agent', [AdminUserController::class, 'approveAgent'])->name('users.approve-agent');
        Route::post('users/{user}/decline-agent', [AdminUserController::class, 'declineAgent'])->name('users.decline-agent');
        Route::post('users/{user}/hold-agent', [AdminUserController::class, 'holdAgent'])->name('users.hold-agent');
        Route::post('users/{user}/release-agent', [AdminUserController::class, 'releaseAgent'])->name('users.release-agent');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/reset-code', [AdminUserController::class, 'issueResetCode'])->name('users.reset-code');
        Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        Route::patch('users/{user}/daily-limit', [AdminUserController::class, 'setDailyLimit'])->name('users.daily-limit');
        Route::post('users/{user}/wallet-credit', [AdminUserController::class, 'creditWallet'])->name('users.wallet-credit');
        Route::post('users/{user}/wallet-debit', [AdminUserController::class, 'debitWallet'])->name('users.wallet-debit');
        Route::post('users/{user}/confirm-registration-payment', [AdminUserController::class, 'confirmRegistrationPayment'])
            ->name('users.confirm-registration-payment');

        Route::resource('bundles', AdminBundleController::class)->except(['show']);
        Route::post('bundles/{bundle}/stock', [AdminBundleController::class, 'updateStock'])->name('bundles.stock');
        Route::patch('bundles/{bundle}/availability', [AdminBundleController::class, 'toggleAvailability'])->name('bundles.availability');

        Route::get('wallet', [AdminWalletController::class, 'index'])->name('wallet.index');
        Route::post('wallet/credit', [WalletController::class, 'adminCredit'])->name('wallet.credit');
        Route::post('wallet/debit', [WalletController::class, 'adminDebit'])->name('wallet.debit');

        Route::get('roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [AdminRoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [AdminRoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [AdminRoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
        Route::post('roles/{role}/toggle', [AdminRoleController::class, 'toggle'])->name('roles.toggle');

        Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::delete('notifications/{notification}', [AdminNotificationController::class, 'destroy'])->name('notifications.destroy');
    });

    Route::middleware('supplier')->get('/admin/dashboard', fn () => redirect()->route('admin.dashboard'));

    Route::middleware('agent_or_supplier')->group(function (): void {
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('/orders/bulk-status', [OrderController::class, 'bulkUpdateStatus'])->name('orders.bulk-update-status');
    });

    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('/dashboard', function () {
        $user = auth()->user()?->loadMissing('role');

        return match ($user?->role?->slug) {
            Role::SLUG_SUPPLIER => redirect()->route('admin.dashboard'),
            Role::SLUG_AGENT => redirect()->route('agent.dashboard'),
            Role::SLUG_BUYER => redirect()->route('buyer.dashboard'),
            default => redirect('/'),
        };
    })->name('dashboard');
});

Route::get('/{agentSlug}', [ShopController::class, 'show'])
    ->where('agentSlug', '[A-Za-z0-9][A-Za-z0-9\-]*')
    ->name('shop.show');
