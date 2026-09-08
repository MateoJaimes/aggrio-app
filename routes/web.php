<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\FormController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/formulario', function () {
    return view('formulario');
});

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('login');

Route::post('/solicitud', [FormController::class, 'store'])->name('solicitud.store');