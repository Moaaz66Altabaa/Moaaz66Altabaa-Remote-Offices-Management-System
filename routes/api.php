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

// Offices Image Routes
Route::post('/offices/{office}/images', [Controllers\OfficeImageController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}/images/{image:id}', [Controllers\OfficeImageController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

// User Reservations Routes
Route::get('/reservations', [Controllers\UserReservationsController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
Route::post('/reservations', [Controllers\UserReservationsController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/reservations/{reservation}', [Controllers\UserReservationsController::class, 'cancel'])->middleware(['auth:sanctum', 'verified']);

// Host Reservations Routes
Route::get('/host/reservations', [Controllers\HostReservationsController::class, 'index'])->middleware(['auth:sanctum', 'verified']);
