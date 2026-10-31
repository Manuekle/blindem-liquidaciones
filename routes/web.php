<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaborParameterController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.submit')->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
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
    Route::delete('contratos/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
    Route::get('contratos/{contract}/liquidacion', [SettlementController::class, 'show'])->name('settlements.show');
    Route::post('contratos/{contract}/liquidacion', [SettlementController::class, 'store'])->name('settlements.store');
    Route::get('contratos/{contract}/excel', [SettlementController::class, 'export'])->name('settlements.export');
    Route::get('contratos/{contract}/liquidaciones/{settlement}', [SettlementController::class, 'history'])->name('settlements.history');
    Route::get('contratos/{contract}/liquidaciones/{settlement}/excel', [SettlementController::class, 'exportHistory'])->name('settlements.history.export');
    Route::middleware('admin')->group(function () {
        Route::resource('parametros-laborales', LaborParameterController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['parametros-laborales' => 'laborParameter'])->names('labor-parameters');
        Route::delete('trabajadores/{worker}', [WorkerController::class, 'destroy'])->name('workers.destroy');
        Route::get('empresas/nueva/crear', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('empresas', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('empresas/{company}/editar', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('empresas/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('empresas/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
        Route::get('catalogos/{type}', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('catalogos/{type}', [CatalogController::class, 'store'])->name('catalogs.store');
        Route::put('catalogos/{type}/{id}', [CatalogController::class, 'update'])->name('catalogs.update');
        Route::delete('catalogos/{type}/{id}', [CatalogController::class, 'destroy'])->name('catalogs.destroy');
    });
});
