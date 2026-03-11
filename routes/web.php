<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PowerPointController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MesasPazController;
use App\Http\Controllers\MesasPazSupervisionController;
use App\Http\Controllers\TemporaryModuleController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\CanvaController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/mi-perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/mi-perfil/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('profile.password.update');

    Route::get('/mesas-paz', [MesasPazController::class, 'index'])->name('mesas-paz')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz', [MesasPazController::class, 'store'])->name('mesas-paz.store')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/asistencia-municipio', [MesasPazController::class, 'guardarMunicipio'])->name('mesas-paz.guardar-municipio')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/acuerdo-hoy', [MesasPazController::class, 'guardarAcuerdoHoy'])->name('mesas-paz.guardar-acuerdo-hoy')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/evidencia-hoy', [MesasPazController::class, 'guardarEvidenciaHoy'])->name('mesas-paz.guardar-evidencia-hoy')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/evidencia-hoy/eliminar', [MesasPazController::class, 'eliminarEvidenciaHoy'])->name('mesas-paz.eliminar-evidencia-hoy')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/importar-excel', [MesasPazController::class, 'importarExcel'])->name('mesas-paz.importar-excel')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/vaciar-microrregion', [MesasPazController::class, 'vaciarMicrorregion'])->name('mesas-paz.vaciar-microrregion')->middleware('can:Mesas-Paz');
    Route::get('/mesas-paz/evidencia/preview', [MesasPazController::class, 'previewEvidencia'])->name('mesas-paz.evidencia.preview');
    Route::post('/ppt/generar-presentacion', [PowerPointController::class, 'generarPresentacion'])->name('ppt.generar-presentacion');
    Route::get('/mesas-paz/historial-detalle', [MesasPazController::class, 'detallePorFecha'])->name('mesas-paz.historial-detalle')->middleware('can:Mesas-Paz');
    Route::get('/mesas-paz/evidencias', [MesasPazSupervisionController::class, 'evidencias'])->name('mesas-paz.evidencias')->middleware('can:Tableros-incidencias');
    Route::get('/mesas-paz/evidencias/pdf', [MesasPazSupervisionController::class, 'descargarPdf'])->name('mesas-paz.evidencias.pdf')->middleware('can:Tableros-incidencias');

    Route::prefix('admin/configuracion')->middleware('can:Modulos-Temporales-Admin')->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/imagenes/migrar', [AdminSettingsController::class, 'migrateImages'])->name('admin.settings.images.migrate');
    });

    Route::delete('/notificaciones', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if ($user) {
            $user->notifications()->delete();
        }

        return back();
    })->name('notifications.clear');

    Route::prefix('modulos-temporales')->group(function () {
        Route::prefix('admin')->middleware('can:Modulos-Temporales-Admin')->group(function () {
            Route::get('/', [TemporaryModuleController::class, 'adminIndex'])->name('temporary-modules.admin.index');
            Route::get('/registros', [TemporaryModuleController::class, 'adminRecords'])->name('temporary-modules.admin.records');
            Route::get('/crear', [TemporaryModuleController::class, 'create'])->name('temporary-modules.admin.create');
            Route::post('/', [TemporaryModuleController::class, 'store'])->name('temporary-modules.admin.store');
            Route::get('/{module}/editar', [TemporaryModuleController::class, 'edit'])
                ->whereNumber('module')
                ->name('temporary-modules.admin.edit');
            Route::put('/{module}', [TemporaryModuleController::class, 'update'])
                ->whereNumber('module')
                ->name('temporary-modules.admin.update');
            Route::get('/{module}/exportar-excel', [TemporaryModuleController::class, 'exportExcel'])
                ->whereNumber('module')
                ->name('temporary-modules.admin.export');
            Route::get('/exportaciones/{file}', [TemporaryModuleController::class, 'downloadExport'])
                ->where('file', '[A-Za-z0-9_\-]+\.xlsx')
                ->name('temporary-modules.admin.exports.download');
            Route::delete('/{module}/registros', [TemporaryModuleController::class, 'clearEntries'])
                ->whereNumber('module')
                ->name('temporary-modules.admin.clear-entries');
            Route::delete('/{module}', [TemporaryModuleController::class, 'destroy'])
                ->whereNumber('module')
                ->name('temporary-modules.admin.destroy');
        });

        Route::get('/subir-informacion', [TemporaryModuleController::class, 'delegateIndex'])
            ->middleware('can:Modulos-Temporales')
            ->name('temporary-modules.upload');

        Route::get('/ver-mis-registros', [TemporaryModuleController::class, 'delegateIndex'])
            ->middleware('can:Modulos-Temporales')
            ->name('temporary-modules.records');

        Route::get('/', [TemporaryModuleController::class, 'delegateIndex'])
            ->middleware('can:Modulos-Temporales')
            ->name('temporary-modules.index');

        Route::get('/{module}', [TemporaryModuleController::class, 'show'])
            ->middleware('can:Modulos-Temporales')
            ->whereNumber('module')
            ->name('temporary-modules.show');

        Route::post('/{module}/registros', [TemporaryModuleController::class, 'submit'])
            ->middleware('can:Modulos-Temporales')
            ->whereNumber('module')
            ->name('temporary-modules.submit');

        Route::get('/registros/{entry}/archivo/{fieldKey}', [TemporaryModuleController::class, 'previewEntryFile'])
            ->middleware('can:Modulos-Temporales')
            ->whereNumber('entry')
            ->where('fieldKey', '[A-Za-z0-9_\-]+')
            ->name('temporary-modules.entry-file.preview');
    });

    Route::get('/canva/auth', [CanvaController::class, 'authRedirect'])->name('canva.auth');
    Route::post('/canva/generar-documento', [CanvaController::class, 'generarDocumento'])->name('canva.generar-documento');
});
