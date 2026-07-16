<?php

use roilafx\Espocrmevo\Controllers\OIDCController;
use Illuminate\Support\Facades\Route;

Route::get('/oidc/authorize', [OIDCController::class, 'authorize']);
Route::post('/oidc/token', [OIDCController::class, 'token']);
Route::get('/oidc/userinfo', [OIDCController::class, 'userinfo']);
Route::get('/oidc/jwks', [OIDCController::class, 'jwks']);