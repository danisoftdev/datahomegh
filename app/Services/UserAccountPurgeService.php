<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserAccountPurgeService
{
    /**
     * Hard-delete the user row and dependent data so username/email/shop_slug can be reused.
     * Detaches linked buyers and clears agent_id on orders so CASCADE does not delete other users.
     */
    public function permanentlyDelete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $id = (int) $user->id;

            User::query()->where('agent_id', $id)->update(['agent_id' => null]);

            Order::query()->where('agent_id', $id)->update(['agent_id' => null]);

            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            if ($user->logo) {
                Storage::disk('public')->delete($user->logo);
            }

            $user->tokens()->delete();

            DB::table('sessions')->where('user_id', $id)->delete();

            $user->forceDelete();
        });
    }
}
