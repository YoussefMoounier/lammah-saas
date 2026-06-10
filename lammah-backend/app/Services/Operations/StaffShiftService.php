<?php

namespace App\Services\Operations;

use App\Models\Merchant;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

class StaffShiftService
{
    public function startShift(Merchant $merchant, User $user, array $data): Shift
    {
        Shift::query()
            ->where('merchant_id', $merchant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'closed',
                'ends_at' => now(),
                'notes' => 'Automatically closed when a new shift was started.',
            ]);

        return Shift::create([
            'merchant_id' => $merchant->id,
            'user_id' => $user->id,
            'starts_at' => isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now(),
            'status' => 'active',
            'opening_cash' => $data['opening_cash'] ?? null,
            'currency' => strtoupper($data['currency'] ?? $merchant->default_currency ?? 'SAR'),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function closeShift(Shift $shift, array $data): Shift
    {
        $shift->forceFill([
            'status' => 'closed',
            'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : now(),
            'closing_cash' => $data['closing_cash'] ?? $shift->closing_cash,
            'notes' => $data['notes'] ?? $shift->notes,
        ])->save();

        return $shift;
    }
}
