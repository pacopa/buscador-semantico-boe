<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DemoController::class, 'index'])->name('demo.index');
Route::post('/ingest', [DemoController::class, 'ingest'])->name('demo.ingest');
Route::match(['get', 'post'], '/alerts', [DemoController::class, 'alerts'])->name('demo.alerts');
