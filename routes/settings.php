<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Settings\Catalogs\UnitOfMeasureController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['patch', 'post'], 'settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('settings/appearance', 'Settings/Appearance')->name('appearance.edit');
});

// Catálogos del sistema (docs/MATRIZ_RBAC.md §3): viven dentro de Configuración.
Route::middleware(['auth', 'verified'])->prefix('settings/catalogs')->name('catalogs.')->group(function () {
    Route::resource('units-of-measure', UnitOfMeasureController::class)
        ->except('show')
        ->parameters(['units-of-measure' => 'unit_of_measure'])
        ->middlewareFor('index', 'can:'.Permission::CatalogsView->value)
        ->middlewareFor(['create', 'store'], 'can:'.Permission::CatalogsCreate->value)
        ->middlewareFor(['edit', 'update'], 'can:'.Permission::CatalogsEdit->value)
        ->middlewareFor('destroy', 'can:'.Permission::CatalogsDelete->value);
});
