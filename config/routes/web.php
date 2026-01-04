<?php

Route::prefix('console')->name('console.')->group(function () {
    Route::get('/', [ConsoleController::class, 'index'])->name('index');

    // AJAX
    Route::get('/list', [ConsoleController::class, 'ajaxList'])->name('list');
    Route::post('/store', [ConsoleController::class, 'ajaxStore'])->name('store');
    Route::delete('/delete/{id}', [ConsoleController::class, 'ajaxDelete'])->name('delete');
});
