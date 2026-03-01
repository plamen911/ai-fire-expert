<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('chat/history', 'pages::chat.history')->name('chat.history');
    Route::livewire('chat/{conversationId?}', 'pages::chat.index')->name('chat.index');
    Route::livewire('documents', 'pages::documents.index')->name('documents.index');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
});

require __DIR__.'/settings.php';
