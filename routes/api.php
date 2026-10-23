<?php

use Illuminate\Support\Facades\Route;

// T-01 · GET /health es público (CA-01.3)
Route::get('/health', fn () => response()->json(['status' => 'ok']));
