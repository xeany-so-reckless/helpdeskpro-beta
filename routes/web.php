<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDetailController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('categories/{category}/status', [CategoryController::class, 'updateStatus'])->name('categories.status');
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::get('tickets/{ticket}', [TicketDetailController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket}', [TicketDetailController::class, 'update'])->name('tickets.update');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
});

require __DIR__.'/settings.php';
