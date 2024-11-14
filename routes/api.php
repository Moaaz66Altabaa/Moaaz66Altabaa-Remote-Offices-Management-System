<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

// Tags Routes
Route::get('/tags', Controllers\TagController::class);

// Offices Routes
Route::get('/offices', [Controllers\OfficeController::class, 'index']);
Route::get('/offices/{office}', [Controllers\OfficeController::class, 'show']);
Route::post('/offices', [Controllers\OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::put('/offices/{office}', [Controllers\OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}', [Controllers\OfficeController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

Route::post('/offices/{office}/images', [Controllers\OfficeImageController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}/images/{image}', [Controllers\OfficeImageController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);
