<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Nom de route attendu par le middleware auth (Passport OAuth).
Route::get('/login', fn () => redirect('/admin/login'))->name('login');
