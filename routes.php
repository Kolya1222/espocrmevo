<?php

use roilafx\Espocrmevo\Controllers\CrmController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CrmController::class, 'index'])->name('espocrmevo.index');