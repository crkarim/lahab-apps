<?php

use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [UpdateController::class, 'update_software_index'])->name('index');
Route::post('update-system', [UpdateController::class, 'update_software'])->name('update-system');

Route::fallback(function () {
    return redirect('/');
});

