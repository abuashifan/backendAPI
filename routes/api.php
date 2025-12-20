<?php

use App\Http\Controllers\Api\JournalController;
use Illuminate\Support\Facades\Route;

Route::apiResource('journals', JournalController::class)
    ->only(['index', 'show', 'store']);
