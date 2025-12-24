<?php

use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\Audit\AuditIssueController;
use App\Http\Controllers\Api\Audit\JournalAuditController;
use App\Http\Controllers\Api\System\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('journals', JournalController::class)
        ->only(['index', 'show', 'store']);

    // Phase 2 — Step 19: Audit Flag & Resolution Logic (no approval workflow)
    Route::post('journals/{journal}/audit/check', [JournalAuditController::class, 'check']);
    Route::post('journals/{journal}/audit/flag', [JournalAuditController::class, 'flag']);
    Route::post('journals/{journal}/audit/resolve', [JournalAuditController::class, 'resolve']);

    Route::get('audits/issues', [AuditIssueController::class, 'index']);

    // Phase 2 — Step 18: User-centric permission assignment
    Route::put('users/{user}/permissions', [UserPermissionController::class, 'sync']);
    Route::post('users/{user}/permissions/copy-from/{source}', [UserPermissionController::class, 'copyFrom']);
});
