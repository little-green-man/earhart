<?php

use Illuminate\Support\Facades\Route;
use LittleGreenMan\Earhart\Controllers\AuthAccountController;
use LittleGreenMan\Earhart\Controllers\AuthAccountSettingsController;
use LittleGreenMan\Earhart\Controllers\AuthOrgCreateController;
use LittleGreenMan\Earhart\Controllers\AuthOrgMembersController;
use LittleGreenMan\Earhart\Controllers\AuthOrgSettingsController;
use LittleGreenMan\Earhart\Controllers\AuthRedirectController;

Route::group(['middleware' => ['web']], function () {
    Route::get('/auth/redirect', AuthRedirectController::class)->name('auth.redirect');
    Route::get('/auth/account', AuthAccountController::class)->name('auth.account');

    Route::get('/auth/settings/{organisation_id}', AuthAccountSettingsController::class)->name('auth.settings');
    Route::get('/auth/org/create', AuthOrgCreateController::class)->name('auth.org.create');
    Route::get('/auth/org/members/{organisation_id}', AuthOrgMembersController::class)->name('auth.org.members');
    Route::get('/auth/org/settings/{organisation_id}', AuthOrgSettingsController::class)->name('auth.org.settings');
});
