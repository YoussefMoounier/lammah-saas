<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShiftCloseRequest;
use App\Http\Requests\Api\V1\ShiftStartRequest;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\Shift;
use App\Services\Operations\StaffShiftService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffShiftController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(Request $request, string $merchant): AnonymousResourceCollection
    {
        $this->authorizeMerchant($request, $merchant, 'shifts.manage');
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ShiftResource::collection(
            Shift::query()
                ->withCount('attributions')
                ->where('merchant_id', $merchant)
                ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($validated['user_id'] ?? null, fn ($query, string $userId) => $query->where('user_id', $userId))
                ->orderByDesc('starts_at')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function start(ShiftStartRequest $request, string $merchant, StaffShiftService $shiftService): ShiftResource
    {
        $merchantModel = $this->authorizeMerchant($request, $merchant, 'shifts.manage');

        return new ShiftResource($shiftService->startShift($merchantModel, $request->user(), $request->validated()));
    }

    public function close(ShiftCloseRequest $request, string $merchant, string $shift, StaffShiftService $shiftService): ShiftResource
    {
        $this->authorizeMerchant($request, $merchant, 'shifts.manage');
        $shiftModel = Shift::query()->where('merchant_id', $merchant)->findOrFail($shift);

        return new ShiftResource($shiftService->closeShift($shiftModel, $request->validated()));
    }
}
