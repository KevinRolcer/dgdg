<?php

use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\GilroyFontController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MesasPazController;
use App\Http\Controllers\MesasPazSupervisionController;
use App\Http\Controllers\MicroregionesController;
use App\Http\Controllers\PowerPointController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemporaryModuleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/fonts/gilroy/{file}', GilroyFontController::class)
    ->where('file', '[A-Za-z0-9\-\.]+\.otf')
    ->name('fonts.gilroy');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['signed', 'throttle:120,1'])->group(function () {
    Route::get('/ppt/preview-archivo/{token}', [PowerPointController::class, 'previewArchivo'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('ppt.preview-archivo');
    Route::get('/ppt/preview-pdf/{token}', [PowerPointController::class, 'previewPdf'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('ppt.preview-pdf');
    Route::get('/ppt/preview-chart/{token}', [PowerPointController::class, 'previewChart'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('ppt.preview-chart');
});

Route::middleware('auth')->group(function () {
    Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf.refresh');
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/microregiones', [MicroregionesController::class, 'index'])->name('microregiones.index');
    Route::get('/microregiones/data', [MicroregionesController::class, 'data'])->name('microregiones.data');
    Route::get('/microregiones/boundaries', [MicroregionesController::class, 'boundaries'])->name('microregiones.boundaries');
    Route::get('/microregiones/search', [MicroregionesController::class, 'search'])->name('microregiones.search');
    // Rutas alias (menos probables de bloqueo WAF / reglas “/data” en hosting)
    Route::get('/microregiones/mapa-datos', [MicroregionesController::class, 'data'])->name('microregiones.map-datos');
    Route::get('/microregiones/lim-mun', [MicroregionesController::class, 'boundaries'])->name('microregiones.map-limits');
    Route::get('/microregiones/buscar-map', [MicroregionesController::class, 'search'])->name('microregiones.map-search');
    Route::get('/geo/mr-lugares', [MicroregionesController::class, 'search'])->name('microregiones.geo-lookup');
    Route::post('/mr/q', [MicroregionesController::class, 'searchPost'])
        ->middleware('throttle:120,1')
        ->name('microregiones.search-post');

    Route::get('/poller/export/{exportRequest}', [TemporaryModuleController::class, 'exportStatus'])
        ->where('exportRequest', '[a-f0-9\-]+')
        ->middleware('can:Modulos-Temporales-Admin')
        ->name('temporary-modules.export-poll');
    Route::get('/mi-perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/ajustes', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/ajustes/apariencia', [SettingsController::class, 'apariencia'])->name('settings.apariencia');
    Route::get('/ajustes/importacion-exportacion', [SettingsController::class, 'importacionExportacion'])
        ->middleware('can:Modulos-Temporales-Admin')
        ->name('settings.importacion-exportacion');
    Route::post('/ajustes/imagenes/migrar', [AdminSettingsController::class, 'migrateImages'])
        ->middleware('can:Modulos-Temporales-Admin')
        ->name('settings.images.migrate');
    Route::post('/ajustes/microrregiones/distribuir-excel', [AdminSettingsController::class, 'distribuirMunicipiosExcel'])
        ->middleware('can:Modulos-Temporales-Admin')
        ->name('settings.microrregiones.distribuir-excel');

    Route::get('/ajustes/chats-whatsapp-autenticador', [SettingsController::class, 'whatsappTotpReset'])
        ->middleware('can:Chats-WhatsApp-Sensible')
        ->name('settings.whatsapp-totp-reset');
    Route::post('/ajustes/chats-whatsapp-autenticador', [SettingsController::class, 'whatsappTotpResetSubmit'])
        ->middleware(['can:Chats-WhatsApp-Sensible', 'throttle:8,1'])
        ->name('settings.whatsapp-totp-reset.post');
    Route::post('/mi-perfil/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('profile.password.update');

    /* Mesas: consulta = Gate mesas-paz-ver; escritura = Gate Mesas-Paz */
    Route::get('/mesas-paz', [MesasPazController::class, 'index'])->name('mesas-paz')->middleware('can:mesas-paz-ver');
    Route::post('/mesas-paz', [MesasPazController::class, 'store'])->name('mesas-paz.store')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/asistencia-municipio', [MesasPazController::class, 'guardarMunicipio'])->name('mesas-paz.guardar-municipio')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/acuerdo-hoy', [MesasPazController::class, 'guardarAcuerdoHoy'])->name('mesas-paz.guardar-acuerdo-hoy')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/evidencia-hoy', [MesasPazController::class, 'guardarEvidenciaHoy'])->name('mesas-paz.guardar-evidencia-hoy')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/evidencia-hoy/eliminar', [MesasPazController::class, 'eliminarEvidenciaHoy'])->name('mesas-paz.eliminar-evidencia-hoy')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/importar-excel', [MesasPazController::class, 'importarExcel'])->name('mesas-paz.importar-excel')->middleware('can:Mesas-Paz');
    Route::post('/mesas-paz/vaciar-microrregion', [MesasPazController::class, 'vaciarMicrorregion'])->name('mesas-paz.vaciar-microrregion')->middleware('can:Mesas-Paz');
    Route::get('/mesas-paz/evidencia/preview', [MesasPazController::class, 'previewEvidencia'])->name('mesas-paz.evidencia.preview')->middleware('can:mesas-paz-ver');

    Route::post('/ppt/generar-presentacion', [PowerPointController::class, 'generarPresentacion'])
        ->name('ppt.generar-presentacion')
        ->middleware('can:Tableros-incidencias');

    Route::post('/ppt/vista-previa/preparar', [PowerPointController::class, 'prepararVistaPrevia'])
        ->name('ppt.vista-previa.preparar')
        ->middleware('can:Tableros-incidencias');
    Route::get('/ppt/vista-previa/{token}', [PowerPointController::class, 'vistaPrevia'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('ppt.vista-previa')
        ->middleware('can:Tableros-incidencias');
    Route::get('/ppt/vista-previa/{token}/descargar', [PowerPointController::class, 'descargarVistaPrevia'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('ppt.vista-previa.descargar')
        ->middleware('can:Tableros-incidencias');
    Route::get('/ppt/vista-previa/{token}/descargar-pdf', [PowerPointController::class, 'descargarVistaPreviaPdf'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('ppt.vista-previa.descargar-pdf')
        ->middleware('can:Tableros-incidencias');

    Route::get('/mesas-paz/historial-detalle', [MesasPazController::class, 'detallePorFecha'])->name('mesas-paz.historial-detalle')->middleware('can:mesas-paz-ver');
    Route::get('/mesas-paz/evidencias', [MesasPazSupervisionController::class, 'evidencias'])->name('mesas-paz.evidencias')->middleware('can:Tableros-incidencias');
    Route::get('/mesas-paz/evidencias/pdf', [MesasPazSupervisionController::class, 'descargarPdf'])->name('mesas-paz.evidencias.pdf')->middleware('can:Tableros-incidencias');
    Route::get('/mesas-paz/evidencias/registros-bruto', [MesasPazSupervisionController::class, 'registrosBruto'])->name('mesas-paz.evidencias.registros-bruto')->middleware('can:Tableros-incidencias');
    Route::delete('/mesas-paz/evidencias/eliminar-rango', [MesasPazSupervisionController::class, 'eliminarRango'])->name('mesas-paz.evidencias.eliminar-rango')->middleware('can:Tableros-incidencias');

    Route::redirect('/admin/configuracion', '/ajustes/importacion-exportacion')->name('admin.settings.index');

    Route::delete('/notificaciones', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if ($user) {
            $user->notifications()->delete();
        }

        return back();
    })->name('notifications.clear');

    Route::delete('/notificaciones/{id}', function (\Illuminate\Http\Request $request, string $id) {
        $user = $request->user();
        if ($user) {
            $user->notifications()->where('id', $id)->delete();
        }

        return back();
    })->name('notifications.destroy');

    Route::prefix('modulos-temporales')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::get('/', [TemporaryModuleController::class, 'adminIndex'])->name('temporary-modules.admin.index')->middleware('can:modulos-temporales-admin-ver');
            Route::get('/registros', [TemporaryModuleController::class, 'adminRecords'])->name('temporary-modules.admin.records')->middleware('can:modulos-temporales-admin-ver');
            Route::get('/crear', [TemporaryModuleController::class, 'create'])->middleware('can:Modulos-Temporales-Admin')->name('temporary-modules.admin.create');
            Route::get('/crear-desde-excel', [TemporaryModuleController::class, 'createFromExcel'])->middleware('can:Modulos-Temporales-Admin')->name('temporary-modules.admin.create-from-excel');
            Route::post('/semilla-preview', [TemporaryModuleController::class, 'seedPreview'])->middleware('can:Modulos-Temporales-Admin')->name('temporary-modules.admin.seed-preview');
            Route::post('/semilla-guardar', [TemporaryModuleController::class, 'seedStore'])->middleware('can:Modulos-Temporales-Admin')->name('temporary-modules.admin.seed-store');
            Route::get('/export-status/{exportRequest}', [TemporaryModuleController::class, 'exportStatus'])
                ->where('exportRequest', '[a-f0-9\-]+')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.export-status');
            Route::get('/exportaciones/{file}', [TemporaryModuleController::class, 'downloadExport'])
                ->where('file', '[A-Za-z0-9_\-.]+\.(xlsx|docx|pdf)')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.exports.download');
            Route::get('/{module}/campos', [TemporaryModuleController::class, 'fieldsJson'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.fields-json');
            Route::get('/{module}/export-preview-structure', [TemporaryModuleController::class, 'exportPreviewStructure'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.export-preview-structure');
            Route::get('/{module}/exportacion-configuracion', [TemporaryModuleController::class, 'exportUserConfigShow'])
                ->whereNumber('module')
                ->middleware('can:modulos-temporales-admin-ver')
                ->name('temporary-modules.admin.export-user-config.show');
            Route::put('/{module}/exportacion-configuracion', [TemporaryModuleController::class, 'exportUserConfigUpdate'])
                ->whereNumber('module')
                ->middleware('can:modulos-temporales-admin-ver')
                ->name('temporary-modules.admin.export-user-config.update');
            Route::delete('/{module}/exportacion-configuracion', [TemporaryModuleController::class, 'exportUserConfigDestroy'])
                ->whereNumber('module')
                ->middleware('can:modulos-temporales-admin-ver')
                ->name('temporary-modules.admin.export-user-config.destroy');
            Route::post('/', [TemporaryModuleController::class, 'store'])->middleware('can:Modulos-Temporales-Admin')->name('temporary-modules.admin.store');
            Route::get('/{module}/editar', [TemporaryModuleController::class, 'edit'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.edit');
            Route::put('/{module}', [TemporaryModuleController::class, 'update'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.update');
            Route::match(['get', 'post'], '/{module}/exportar-excel', [TemporaryModuleController::class, 'exportExcel'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.export');
            Route::post('/{module}/toggle-active', [TemporaryModuleController::class, 'toggleActive'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.toggle-active');
            Route::post('/{module}/normalizar-municipio', [TemporaryModuleController::class, 'normalizeMunicipioField'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.normalize-municipio');
            Route::post('/{module}/registro-desde-log-semilla', [TemporaryModuleController::class, 'registerSeedDiscardRow'])
                ->whereNumber('module')
                ->middleware(['can:Modulos-Temporales-Admin', 'throttle:60,1'])
                ->name('temporary-modules.admin.seed-discard-register');
            Route::get('/{module}/buscar-municipios-log-semilla', [TemporaryModuleController::class, 'searchSeedDiscardMunicipios'])
                ->whereNumber('module')
                ->middleware(['can:Modulos-Temporales-Admin', 'throttle:120,1'])
                ->name('temporary-modules.admin.seed-discard-search-municipios');
            Route::get('/{module}/analisis-preview', [TemporaryModuleController::class, 'analysisPreviewJson'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.analysis-preview');
            Route::post('/{module}/exportar-analisis-word', [TemporaryModuleController::class, 'exportAnalysisWord'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.export-analysis-word');
            Route::delete('/{module}/registros/{entry}', [TemporaryModuleController::class, 'adminDestroyEntry'])
                ->whereNumber('module')
                ->whereNumber('entry')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.entry.destroy');
            Route::delete('/{module}/registros', [TemporaryModuleController::class, 'clearEntries'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.clear-entries');
            Route::delete('/{module}', [TemporaryModuleController::class, 'destroy'])
                ->whereNumber('module')
                ->middleware('can:Modulos-Temporales-Admin')
                ->name('temporary-modules.admin.destroy');
        });

        Route::get('/subir-informacion', [TemporaryModuleController::class, 'delegateIndex'])->middleware('can:modulos-temporales-ver')->name('temporary-modules.upload');
        Route::get('/ver-mis-registros', [TemporaryModuleController::class, 'delegateIndex'])->middleware('can:modulos-temporales-ver')->name('temporary-modules.records');
        Route::get('/', [TemporaryModuleController::class, 'delegateIndex'])->middleware('can:modulos-temporales-ver')->name('temporary-modules.index');
        Route::get('/fragmento/modulos', [TemporaryModuleController::class, 'delegatePartialUpload'])->middleware('can:modulos-temporales-ver')->name('temporary-modules.fragment.upload');
        Route::get('/fragmento/registros', [TemporaryModuleController::class, 'delegatePartialRecords'])->middleware('can:modulos-temporales-ver')->name('temporary-modules.fragment.records');
        Route::get('/fragmento/registros-edicion-datos', [TemporaryModuleController::class, 'delegateBulkEditData'])->middleware('can:modulos-temporales-ver')->name('temporary-modules.fragment.bulk-edit-data');
        Route::get('/{module}/estado', [TemporaryModuleController::class, 'moduleStatus'])->middleware('can:modulos-temporales-ver')->whereNumber('module')->name('temporary-modules.module-status');
        Route::post('/{module}/importar-excel-preview', [TemporaryModuleController::class, 'importExcelPreview'])->middleware('can:Modulos-Temporales')->whereNumber('module')->name('temporary-modules.import-excel-preview');
        Route::post('/{module}/importar-excel', [TemporaryModuleController::class, 'importExcel'])->middleware('can:Modulos-Temporales')->whereNumber('module')->name('temporary-modules.import-excel');
        Route::post('/{module}/actualizar-desde-excel', [TemporaryModuleController::class, 'updateExistingFromExcel'])->middleware('can:Modulos-Temporales')->whereNumber('module')->name('temporary-modules.update-from-excel');
        Route::post('/{module}/importar-fila', [TemporaryModuleController::class, 'importSingleRow'])->middleware('can:Modulos-Temporales')->whereNumber('module')->name('temporary-modules.import-single-row');
        Route::get('/{module}', [TemporaryModuleController::class, 'show'])->middleware('can:modulos-temporales-ver')->whereNumber('module')->name('temporary-modules.show');
        Route::post('/{module}/registros', [TemporaryModuleController::class, 'submit'])->middleware('can:Modulos-Temporales')->whereNumber('module')->name('temporary-modules.submit');
        Route::delete('/{module}/registros/{entry}', [TemporaryModuleController::class, 'destroyEntry'])->middleware('can:Modulos-Temporales')->whereNumber('module')->whereNumber('entry')->name('temporary-modules.entry.destroy');
        Route::delete('/{module}/registros-masivo', [TemporaryModuleController::class, 'bulkDestroyEntries'])->middleware('can:Modulos-Temporales')->whereNumber('module')->name('temporary-modules.entries.bulk-destroy');
        Route::get('/{module}/plantilla', [TemporaryModuleController::class, 'downloadTemplate'])->middleware('can:modulos-temporales-ver')->whereNumber('module')->name('temporary-modules.download-template');
        Route::get('/plantillas/{file}', [TemporaryModuleController::class, 'downloadTemplateFile'])->where('file', '[A-Za-z0-9._\-]+\.xlsx')->middleware('can:modulos-temporales-ver')->name('temporary-modules.plantilla.download');
        Route::get('/{module}/registros/{entry}/archivo/{fieldKey}', [TemporaryModuleController::class, 'previewEntryFile'])->middleware('can:modulos-temporales-ver')->whereNumber('entry')->where('fieldKey', '[A-Za-z0-9_\-]+')->name('temporary-modules.entry-file.preview');
    });

    Route::middleware(['agenda.access'])->group(function () {
        Route::get('agenda/modulo/enlaces', [AgendaController::class, 'moduloEnlaces'])->name('agenda.modulo.enlaces');
        Route::get('agenda/seguimiento', [\App\Http\Controllers\AgendaSeguimientoController::class, 'index'])->name('agenda.seguimiento.index');
        Route::get('agenda/calendario', [AgendaController::class, 'calendar'])->name('agenda.calendar');
        Route::post('agenda/calendario/fichas-pdf', [AgendaController::class, 'calendarFichasPdf'])->name('agenda.calendar.fichas-pdf');
        Route::get('agenda/calendario/fichas-export/{file}', [AgendaController::class, 'downloadFichasExport'])->where('file', '[A-Za-z0-9._\-]+')->name('agenda.calendar.fichas-export.download');
        Route::get('agenda/{agenda}/ficha-pdf', [AgendaController::class, 'downloadSingleFichaPdf'])->name('agenda.ficha.download');
        Route::post('agenda/{agenda}/ficha-pdf', [AgendaController::class, 'queueSingleFichaPdf'])->name('agenda.ficha.queue');
        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::get('agenda/{agenda}', [AgendaController::class, 'show'])->name('agenda.show');
    });

    Route::middleware(['agenda.access.escritura'])->group(function () {
        Route::post('agenda/modulo/asignar', [AgendaController::class, 'moduloAsignar'])->name('agenda.modulo.asignar');
        Route::post('agenda/modulo/quitar', [AgendaController::class, 'moduloQuitar'])->name('agenda.modulo.quitar');
        Route::post('agenda/seguimiento/{agenda}/pasar-gira', [\App\Http\Controllers\AgendaSeguimientoController::class, 'pasarGira'])->name('agenda.seguimiento.pasar-gira');
        Route::post('agenda/seguimiento/{agenda}/actualizacion', [\App\Http\Controllers\AgendaSeguimientoController::class, 'actualizacion'])->name('agenda.seguimiento.actualizacion');
        Route::get('agenda/create', [AgendaController::class, 'create'])->name('agenda.create');
        Route::post('agenda', [AgendaController::class, 'store'])->name('agenda.store');
        Route::get('agenda/{agenda}/edit', [AgendaController::class, 'edit'])->name('agenda.edit');
        Route::put('agenda/{agenda}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::patch('agenda/{agenda}', [AgendaController::class, 'update']);
        Route::delete('agenda/{agenda}', [AgendaController::class, 'destroy'])->name('agenda.destroy');
    });

    Route::prefix('admin/usuarios')->middleware('can:Administrar-Usuarios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('admin.usuarios.index');
        Route::get('/crear', [\App\Http\Controllers\Admin\UserManagementController::class, 'create'])->name('admin.usuarios.create');
        Route::post('/crear', [\App\Http\Controllers\Admin\UserManagementController::class, 'store'])->name('admin.usuarios.store');
        Route::get('/{id}/editar', [\App\Http\Controllers\Admin\UserManagementController::class, 'edit'])->name('admin.usuarios.edit');
        Route::post('/{id}/editar', [\App\Http\Controllers\Admin\UserManagementController::class, 'update'])->name('admin.usuarios.update');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\UserManagementController::class, 'destroy'])->name('admin.usuarios.destroy');
        Route::post('/{id}/toggle-status', [\App\Http\Controllers\Admin\UserManagementController::class, 'toggleStatus'])->name('admin.usuarios.toggle-status');
    });

    Route::prefix('admin/whatsapp-chats')->middleware(['auth', 'can:Chats-WhatsApp-Sensible', \App\Http\Middleware\WhatsAppNoStoreResponse::class])->group(function () {
        Route::get('/desbloqueo', function (\Illuminate\Http\Request $request) {
            return redirect()->route('whatsapp-chats.admin.totp', $request->query());
        });
        Route::get('/totp', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'totpForm'])->name('whatsapp-chats.admin.totp');
        Route::post('/totp', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'totpSubmit'])->middleware('throttle:12,1')->name('whatsapp-chats.admin.totp.post');
        Route::middleware([\App\Http\Middleware\ConfirmWhatsAppSensitiveAccess::class])->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'index'])->name('whatsapp-chats.admin.index');
            Route::post('/', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'store'])->name('whatsapp-chats.admin.store');
            Route::get('/browser', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'browser'])->name('whatsapp-chats.admin.browser');
            Route::patch('/{chat}', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'update'])
                ->whereNumber('chat')
                ->name('whatsapp-chats.admin.update');
            Route::post('/folder-upload', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'storeFolderFile'])
                ->middleware('throttle:whatsapp-folder-upload')
                ->name('whatsapp-chats.admin.folder-upload');
            Route::post('/folder-upload-chunk', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'storeFolderChunk'])
                ->middleware('throttle:whatsapp-folder-upload')
                ->name('whatsapp-chats.admin.folder-upload-chunk');
            Route::post('/folder-finalize', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'finalizeFolderUpload'])
                ->middleware('throttle:whatsapp-folder-finalize')
                ->name('whatsapp-chats.admin.folder-finalize');
            Route::get('/{chat}/import-status', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'importStatus'])
                ->whereNumber('chat')
                ->middleware('throttle:whatsapp-import-status-poll')
                ->name('whatsapp-chats.admin.import-status');
            Route::get('/{chat}', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'show'])->whereNumber('chat')->name('whatsapp-chats.admin.show');
            Route::get('/{chat}/media', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'media'])->whereNumber('chat')->name('whatsapp-chats.admin.media');
            Route::delete('/{chat}', [\App\Http\Controllers\Admin\WhatsAppChatArchiveController::class, 'destroy'])->whereNumber('chat')->name('whatsapp-chats.admin.destroy');
        });
    });

    Route::group(['prefix' => 'personal-agenda', 'as' => 'personal-agenda.'], function () {
        Route::get('/', [\App\Http\Controllers\PersonalNoteController::class, 'index'])->name('index');
        Route::delete('/trash', [\App\Http\Controllers\PersonalNoteController::class, 'emptyTrash'])->name('trash.empty');
        Route::post('/', [\App\Http\Controllers\PersonalNoteController::class, 'store'])->name('store');
        Route::put('/{note}', [\App\Http\Controllers\PersonalNoteController::class, 'update'])->name('update');
        Route::delete('/{note}', [\App\Http\Controllers\PersonalNoteController::class, 'destroy'])->name('destroy');
        Route::post('/{note}/decrypt', [\App\Http\Controllers\PersonalNoteController::class, 'decrypt'])->name('decrypt');
        Route::post('/{note}/archive', [\App\Http\Controllers\PersonalNoteController::class, 'archive'])->name('archive');
        Route::post('/{id}/restore', [\App\Http\Controllers\PersonalNoteController::class, 'restore'])->name('restore');
        Route::post('/{note}/move', [\App\Http\Controllers\PersonalNoteController::class, 'moveToFolder'])->name('move');
        Route::delete('/attachments/{attachment}', [\App\Http\Controllers\PersonalNoteController::class, 'deleteAttachment'])->name('attachments.destroy');
        Route::get('/attachments/{attachment}/serve', [\App\Http\Controllers\PersonalNoteController::class, 'serveAttachment'])->name('attachments.serve');

        Route::post('/folders', [\App\Http\Controllers\PersonalNoteController::class, 'storeFolder'])->name('folders.store');
        Route::put('/folders/{folder}', [\App\Http\Controllers\PersonalNoteController::class, 'updateFolder'])->name('folders.update');
        Route::post('/folders/{folder}/archive', [\App\Http\Controllers\PersonalNoteController::class, 'archiveFolder'])->name('folders.archive');
        Route::post('/folders/{folderId}/restore', [\App\Http\Controllers\PersonalNoteController::class, 'restoreFolder'])->whereNumber('folderId')->name('folders.restore');
        Route::post('/folders/{folder}/pin', [\App\Http\Controllers\PersonalNoteController::class, 'toggleFolderPin'])->name('folders.pin');
        Route::delete('/folders/{folder}', [\App\Http\Controllers\PersonalNoteController::class, 'destroyFolder'])->name('folders.destroy');
    });
});
