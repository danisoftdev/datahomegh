<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetCode;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function showRequestForm(): View
    {
        $supplier = $this->primarySupplier();

        return view('auth.password-reset-request', [
            'supplier' => $supplier,
        ]);
    }

    public function requestReset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::exists(User::class, 'username')],
        ]);

        $user = User::query()->where('username', $validated['username'])->firstOrFail();

        $outcome = DB::transaction(function () use ($user): string {
            PasswordResetCode::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->whereNotNull('code_hash')
                ->where('expires_at', '<=', now())
                ->update(['used_at' => now()]);

            if (PasswordResetCode::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->whereNull('code_hash')
                ->exists()) {
                return 'already_pending';
            }

            if (PasswordResetCode::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->whereNotNull('code_hash')
                ->where('expires_at', '>', now())
                ->exists()) {
                return 'active_code';
            }

            PasswordResetCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => null,
                'expires_at' => null,
                'used_at' => null,
                'attempts' => 0,
                'created_at' => now(),
            ]);

            return 'ok';
        });

        if ($outcome === 'already_pending') {
            return back()->with('status', __('You already have a pending reset request. Please contact the admin.'));
        }

        if ($outcome === 'active_code') {
            return back()->with('status', __('You already have an active reset code. Use the reset form or wait for it to expire.'));
        }

        $username = $user->username;
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->cursor()
            ->each(function (User $supplier) use ($username): void {
                $this->notificationService->notify(
                    $supplier->id,
                    'Password reset requested',
                    "{$username} has requested a password reset.",
                    'password_reset_requested',
                );
            });

        return back()->with('status', __('Request submitted. Please contact admin now.'));
    }

    public function showResetForm(): View
    {
        return view('auth.password-reset');
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::exists(User::class, 'username')],
            'reset_code' => ['required', 'string', 'max:32'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->where('username', $validated['username'])->firstOrFail();

        /** @var PasswordResetCode|null $record */
        $record = PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->whereNotNull('code_hash')
            ->orderByDesc('id')
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'reset_code' => __('No active reset code for this username. Request a reset first.'),
            ]);
        }

        if ($record->expires_at === null || $record->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'reset_code' => __('This reset code has expired. Request a new reset from the admin.'),
            ]);
        }

        if ($record->attempts >= 3) {
            throw ValidationException::withMessages([
                'reset_code' => __('Too many attempts. Contact the admin for a new code.'),
            ]);
        }

        $code = strtoupper(trim($validated['reset_code']));
        $hash = hash('sha256', $code);

        if (! hash_equals((string) $record->code_hash, $hash)) {
            $record->increment('attempts');
            $record->refresh();

            if ($record->attempts >= 3) {
                throw ValidationException::withMessages([
                    'reset_code' => __('Too many invalid attempts. Contact the admin for a new code.'),
                ]);
            }

            throw ValidationException::withMessages([
                'reset_code' => __('Invalid reset code.'),
            ]);
        }

        DB::transaction(function () use ($user, $record, $validated): void {
            $user->password = $validated['new_password'];
            $user->save();

            $record->forceFill(['used_at' => now()])->save();
        });

        Auth::login($user);

        $this->notificationService->notify(
            $user->id,
            'Password reset',
            'Password reset successfully.',
            'password_reset_success',
        );

        return redirect()->route('dashboard')->with('status', __('Password reset successfully.'));
    }

    private function primarySupplier(): ?User
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_SUPPLIER))
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }
}
