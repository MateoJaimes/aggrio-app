<?php

use Illuminate\Support\Facades\Route;

// Landing pública. Las acciones de acceso llevan al panel Angular.
Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/auth/login');
})->name('login');
