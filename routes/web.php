<?php
use Illuminate\Support\Facades\Route;

Route::get('/auth/redirect', \LittleGreenMan\Earhart\Controllers\AuthRedirectController::class)->name('auth.redirect');
Route::get('/auth/account', \LittleGreenMan\Earhart\Controllers\AuthAccountController::class)->name('auth.account');

Route::get('/auth/settings/{organisation_id}', \LittleGreenMan\Earhart\Controllers\AuthAccountSettingsController::class)->name('auth.settings');
Route::get('/auth/org/create', \LittleGreenMan\Earhart\Controllers\AuthOrgCreateController::class)->name('auth.org.create');
Route::get('/auth/org/members/{organisation_id}', \LittleGreenMan\Earhart\Controllers\AuthOrgMembersController::class)->name('auth.org.members');
Route::get('/auth/org/settings/{organisation_id}', \LittleGreenMan\Earhart\Controllers\AuthOrgSettingsController::class)->name('auth.org.settings');
