<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home');
Route::view('/test', 'test');
Route::get('/share/{token}', fn (string $token) => view('share', ['token' => $token]));

