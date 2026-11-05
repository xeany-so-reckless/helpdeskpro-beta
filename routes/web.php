<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDetailController;
use App\Http\Controllers\TicketActionController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketAttachmentController;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\ReportController;

Schedule::command('tickets:auto-close')->dailyAt('01:00');

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
    Route::patch('tickets/{ticket}/assign', [TicketActionController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/claim', [TicketActionController::class, 'claim'])->name('tickets.claim');
    Route::patch('tickets/{ticket}/priority', [TicketActionController::class, 'priority'])->name('tickets.priority');
    Route::patch('tickets/{ticket}/status', [TicketActionController::class, 'status'])->name('tickets.status');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
    Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
    Route::get('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'download'])->name('tickets.attachments.download');
    Route::delete('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('tickets.attachments.destroy');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});

require __DIR__.'/settings.php';
