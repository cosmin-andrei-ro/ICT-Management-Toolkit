<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AttachmentController;
use Modules\Core\Http\Controllers\ExportController;
use Modules\Core\Http\Controllers\HomeController;
use Modules\Core\Http\Controllers\LogoutController;
use Modules\Core\Http\Controllers\ManualController;
use Modules\Core\Http\Controllers\SamlController;
use Modules\Core\Http\Controllers\SearchController;
use Modules\Core\Http\Controllers\SsoController;
use Modules\Core\Http\Controllers\TagController;
use Modules\Core\Http\Livewire\Account\AccountSecurity;
use Modules\Core\Http\Livewire\Account\ApiTokens;
use Modules\Core\Http\Livewire\Account\Invitations;
use Modules\Core\Http\Livewire\Audit\AuditLogIndex;
use Modules\Core\Http\Livewire\Auth\ForgotPassword;
use Modules\Core\Http\Livewire\Auth\Login;
use Modules\Core\Http\Livewire\Auth\ResetPassword;
use Modules\Core\Http\Livewire\Auth\TwoFactorChallenge;
use Modules\Core\Http\Livewire\Contracts\ContractForm;
use Modules\Core\Http\Livewire\Contracts\ContractIndex;
use Modules\Core\Http\Livewire\Contracts\ShowContract;
use Modules\Core\Http\Livewire\Suppliers\ShowSupplier;
use Modules\Core\Http\Livewire\Suppliers\SupplierForm;
use Modules\Core\Http\Livewire\Suppliers\SupplierIndex;

// Public: needed most by people who cannot sign in.
Route::get('/saml/{provider}/metadata', [SamlController::class, 'metadata'])->whereUuid('provider')->name('login.saml.metadata');
Route::get('/saml/{provider}/logout', [SamlController::class, 'singleLogout'])->whereUuid('provider')->middleware('throttle:60,1')->name('login.saml.slo');
Route::get('/manual', [ManualController::class, 'index'])->name('manual.index');
Route::get('/manual/{page}', [ManualController::class, 'show'])->where('page', '[a-z0-9-]+')->name('manual.show');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/login/code', TwoFactorChallenge::class)->name('login.challenge');
    Route::get('/login/sso/{provider}', [SsoController::class, 'redirect'])->whereUuid('provider')->middleware('throttle:20,1')->name('login.sso');
    Route::get('/login/sso/{provider}/callback', [SsoController::class, 'callback'])->whereUuid('provider')->middleware('throttle:20,1')->name('login.sso.callback');
    Route::get('/login/saml/{provider}', [SamlController::class, 'redirect'])->whereUuid('provider')->middleware('throttle:20,1')->name('login.saml');
    // Posted by the identity provider: exempt from CSRF in bootstrap/app.php.
    Route::post('/login/saml/{provider}/acs', [SamlController::class, 'consume'])->whereUuid('provider')->middleware('throttle:20,1')->name('login.saml.acs');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
    Route::livewire('/welcome/{token}', ResetPassword::class)->defaults('invitation', true)->name('invitation.accept');
});

Route::middleware('auth')->group(function (): void {
    // Without "organization": a user removed from every organization must still be able to log out.
    Route::post('/logout', LogoutController::class)->name('logout');
});

// Without "mfa": where people who must use two-factor authentication set it up.
Route::middleware(['auth', 'auth.session', 'organization'])->group(function (): void {
    Route::livewire('/account/security', AccountSecurity::class)->name('account.security');
});

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('core.search');
    Route::get('/tags/{tag}', TagController::class)->whereUuid('tag')->name('core.tags.show');
    Route::get('/attachments/{attachment}', AttachmentController::class)->name('core.attachments.download');
    Route::get('/exports/{export}', ExportController::class)->whereUuid('export')->name('core.exports.download');

    Route::livewire('/suppliers', SupplierIndex::class)->name('core.suppliers.index');
    Route::livewire('/suppliers/new', SupplierForm::class)->name('core.suppliers.create');
    Route::livewire('/suppliers/{supplier}', ShowSupplier::class)->whereUuid('supplier')->name('core.suppliers.show');
    Route::livewire('/suppliers/{supplier}/edit', SupplierForm::class)->whereUuid('supplier')->name('core.suppliers.edit');

    Route::livewire('/audit-log', AuditLogIndex::class)->name('core.audit-log.index');
    Route::livewire('/account/api-tokens', ApiTokens::class)->name('account.api-tokens');
    Route::livewire('/account/invitations', Invitations::class)->name('account.invitations');

    Route::livewire('/contracts', ContractIndex::class)->name('core.contracts.index');
    Route::livewire('/contracts/new', ContractForm::class)->name('core.contracts.create');
    Route::livewire('/contracts/{contract}', ShowContract::class)->whereUuid('contract')->name('core.contracts.show');
    Route::livewire('/contracts/{contract}/edit', ContractForm::class)->whereUuid('contract')->name('core.contracts.edit');
});
