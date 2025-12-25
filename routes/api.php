<?php

use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\AccountingPeriodController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\Audit\AuditIssueController;
use App\Http\Controllers\Api\Audit\JournalAuditController;
use App\Http\Controllers\Api\Accounting\AP\PurchaseOrderController;
use App\Http\Controllers\Api\Accounting\AP\VendorInvoiceController;
use App\Http\Controllers\Api\Accounting\AP\VendorPaymentController;
use App\Http\Controllers\Api\System\UserPermissionController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Phase 3 — Step 27: Vendor & Customer master data
    Route::apiResource('vendors', VendorController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    // Phase 3 — Step 33: Purchasing (AP)
    Route::apiResource('purchase-orders', PurchaseOrderController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);

    Route::apiResource('vendor-invoices', VendorInvoiceController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('vendor-invoices/{vendorInvoice}/approve', [VendorInvoiceController::class, 'approve']);
    Route::post('vendor-invoices/{vendorInvoice}/post', [VendorInvoiceController::class, 'post']);

    Route::apiResource('vendor-payments', VendorPaymentController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('vendor-payments/{vendorPayment}/approve', [VendorPaymentController::class, 'approve']);
    Route::post('vendor-payments/{vendorPayment}/post', [VendorPaymentController::class, 'post']);

    Route::apiResource('journals', JournalController::class)
        ->only(['index', 'show', 'store']);

    // Phase 2 — Step 21/22: Journal lifecycle
    Route::post('journals/{journal}/approve', [JournalController::class, 'approve']);
    Route::post('journals/{journal}/post', [JournalController::class, 'post']);
    Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse']);

    // Phase 2 — Step 23: Period lock (open/close)
    Route::post('periods/{period}/close', [AccountingPeriodController::class, 'close']);
    Route::post('periods/{period}/open', [AccountingPeriodController::class, 'open']);

    // Phase 2 — Step 19: Audit Flag & Resolution Logic (no approval workflow)
    Route::post('journals/{journal}/audit/check', [JournalAuditController::class, 'check']);
    Route::post('journals/{journal}/audit/flag', [JournalAuditController::class, 'flag']);
    Route::post('journals/{journal}/audit/resolve', [JournalAuditController::class, 'resolve']);

    Route::get('audits/issues', [AuditIssueController::class, 'index']);

    // Phase 2 — Step 18: User-centric permission assignment
    Route::put('users/{user}/permissions', [UserPermissionController::class, 'sync']);
    Route::post('users/{user}/permissions/copy-from/{source}', [UserPermissionController::class, 'copyFrom']);
});
