<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaborParameterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

// Contracts, workers and settlements are addressed by random UUID, never by sequential id.
foreach (['contract', 'worker', 'settlement'] as $parameter) {
    Route::pattern($parameter, '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.submit')->middleware('throttle:5,1');
    Route::get('olvide-contrasena', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('olvide-contrasena', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('restablecer-contrasena/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('restablecer-contrasena', [PasswordResetController::class, 'update'])->name('password.update')->middleware('throttle:5,1');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('perfil/contrasena', [ProfileController::class, 'password'])->name('profile.password')->middleware('throttle:6,1');
    Route::delete('perfil/sesiones', [ProfileController::class, 'destroySessions'])->name('profile.sessions')->middleware('throttle:6,1');
    Route::get('notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notificaciones/leer', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notificaciones/{notification}/abrir', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('buscar', SearchController::class)->name('search')->middleware('throttle:60,1');
    Route::get('trabajadores', [WorkerController::class, 'index'])->name('workers.index');
    // Collaborators register people while creating contracts; deleting stays with the administrator.
    Route::get('trabajadores/nuevo/crear', [WorkerController::class, 'create'])->name('workers.create');
    Route::post('trabajadores', [WorkerController::class, 'store'])->name('workers.store');
    Route::get('trabajadores/{worker}', [WorkerController::class, 'show'])->name('workers.show');
    Route::get('trabajadores/{worker}/editar', [WorkerController::class, 'edit'])->name('workers.edit');
    Route::put('trabajadores/{worker}', [WorkerController::class, 'update'])->name('workers.update');
    Route::get('empresas', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('contratos', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('contratos/nuevo', [ContractController::class, 'create'])->name('contracts.create');
    Route::post('contratos', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('contratos/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('contratos/{contract}/editar', [ContractController::class, 'edit'])->name('contracts.edit');
    Route::put('contratos/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::get('contratos/{contract}/liquidacion', [SettlementController::class, 'show'])->name('settlements.show');
    Route::post('contratos/{contract}/liquidacion', [SettlementController::class, 'store'])->name('settlements.store');
    Route::get('contratos/{contract}/excel', [SettlementController::class, 'export'])->name('settlements.export');
    Route::get('contratos/{contract}/liquidaciones/{settlement}', [SettlementController::class, 'history'])->name('settlements.history');
    Route::get('contratos/{contract}/liquidaciones/{settlement}/excel', [SettlementController::class, 'exportHistory'])->name('settlements.history.export');
    Route::prefix('ia')->name('ai.')->middleware('throttle:40,1')->group(function () {
        Route::get('resumen', [AiController::class, 'dashboardInsights'])->name('insights');
        Route::post('agente', [AiController::class, 'agent'])->name('agent');
        Route::post('contratos/buscar', [AiController::class, 'interpretSearch'])->name('contracts.search');
        Route::post('contratos/extraer', [AiController::class, 'extractContract'])->name('contracts.extract');
        Route::get('contratos/{contract}/revision', [AiController::class, 'reviewContract'])->name('contracts.review');
        Route::get('contratos/{contract}/explicacion', [AiController::class, 'explainSettlement'])->name('settlements.explain');
        Route::post('contratos/{contract}/preguntar', [AiController::class, 'answerSettlement'])->name('settlements.answer');
        Route::post('contratos/{contract}/carta', [AiController::class, 'draftLetter'])->name('settlements.letter');
    });
    Route::middleware('admin')->group(function () {
        Route::resource('parametros-laborales', LaborParameterController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['parametros-laborales' => 'laborParameter'])->names('labor-parameters');
        Route::delete('trabajadores/{worker}', [WorkerController::class, 'destroy'])->name('workers.destroy');
        Route::delete('contratos/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
        Route::get('empresas/nueva/crear', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('empresas', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('empresas/{company}/editar', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('empresas/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('empresas/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/nuevo/crear', [UserController::class, 'create'])->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('usuarios/{user}/estado', [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('usuarios/{user}/enlace', [UserController::class, 'sendResetLink'])->name('users.reset-link')->middleware('throttle:10,1');
        Route::get('catalogos/{type}', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('catalogos/{type}', [CatalogController::class, 'store'])->name('catalogs.store');
        Route::put('catalogos/{type}/{id}', [CatalogController::class, 'update'])->name('catalogs.update');
        Route::delete('catalogos/{type}/{id}', [CatalogController::class, 'destroy'])->name('catalogs.destroy');
    });
});
