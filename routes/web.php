<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| ForgeFlow React Single Page Application mount point
|
*/

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
