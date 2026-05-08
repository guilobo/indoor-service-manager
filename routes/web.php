<?php

use App\Http\Controllers\Gel5FileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/media/{path}', Gel5FileController::class)
    ->where('path', '.*')
    ->name('media.show');
