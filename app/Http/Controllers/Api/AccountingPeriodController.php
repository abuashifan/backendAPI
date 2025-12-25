<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Services\Accounting\Period\ClosePeriodService;
use App\Services\Accounting\Period\OpenPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly ClosePeriodService $closePeriodService,
        private readonly OpenPeriodService $openPeriodService,
    ) {
    }

    /**
     * POST /periods/{period}/close
     */
    public function close(Request $request, AccountingPeriod $period): JsonResponse
    {
        $this->authorize('period.close');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $closed = $this->closePeriodService->close($period, $actor);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $closed,
        ]);
    }

    /**
     * POST /periods/{period}/open
     */
    public function open(Request $request, AccountingPeriod $period): JsonResponse
    {
        $this->authorize('period.open');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $opened = $this->openPeriodService->open($period, $actor);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $opened,
        ]);
    }
}
