<?php

use App\Http\Controllers\Api\AccessRequestApiController;
use App\Http\Controllers\Api\AccountAdminController;
use App\Http\Controllers\Api\ActividadApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CampoApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\DispositivoIotApiController;
use App\Http\Controllers\Api\FincaApiController;
use App\Http\Controllers\Api\LecturaIotApiController;
use App\Http\Controllers\Api\LoteApiController;
use App\Http\Controllers\Api\MultimediaApiController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PresignedUrlController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\PublicAccessController;
use App\Http\Controllers\Api\SecuritySettingsController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\WebSessionController;
use App\Models\UploadTicket;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::post('/access-requests', [PublicAccessController::class, 'store'])->middleware('throttle:5,1');

Route::prefix('web/auth')->name('web.auth.')->middleware('web.session')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login'])->name('login');
    Route::post('/2fa/setup', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('verify');
    Route::get('/csrf', [WebSessionController::class, 'csrf'])->name('csrf')->middleware('throttle:60,1');
    Route::post('/refresh', [WebSessionController::class, 'refresh'])->name('refresh')->middleware('throttle:60,1');
    Route::post('/logout', [WebSessionController::class, 'logout'])->name('logout');
    Route::post('/logout-all', [WebSessionController::class, 'logout'])->name('logout-all');
    Route::put('/password', [AuthApiController::class, 'changePassword'])->name('password')->middleware(['auth:sanctum', 'abilities:api:access', 'approved.account', 'throttle:5,1']);
});

Route::put('/uploads/{ticket}', function (Request $r, string $ticket) {
    return DB::transaction(function () use ($r, $ticket) {
        $t = UploadTicket::whereKey($ticket)->lockForUpdate()->firstOrFail();
        abort_if($t->storage_disk !== 'local' || $t->consumed_at || $t->expires_at->isPast(), 403);
        $disk = Storage::disk('local');
        abort_if($disk->exists($t->file_key), 409, 'El archivo ya fue cargado.');
        $input = $r->getContent(true);
        $buffer = fopen('php://temp/maxmemory:1048576', 'w+b');
        try {
            $size = stream_copy_to_stream($input, $buffer, $t->size_bytes + 1);
            abort_if($size < 1 || $size > $t->size_bytes, 422, 'Tamaño de carga inválido.');
            rewind($buffer);
            abort_unless($disk->put($t->file_key, $buffer, ['visibility' => 'private']), 503);
        } finally {
            fclose($buffer);
            if (is_resource($input)) {
                fclose($input);
            }
        }

        return response()->json(['success' => true]);
    });
})->name('evidence.upload')->middleware(['signed', 'throttle:30,1']);

// RUTA PÚBLICA (No requiere Token)
// Aquí es donde Flutter envía el correo y la contraseña para obtener acceso.
Route::post('/login', [AuthApiController::class, 'login']);

// -- Doble factor del login (RF003) --
// Públicas, pero exigen el challenge_token que sólo entrega /login tras validar
// la contraseña. Ese token no sirve para autenticarse contra el resto de la API.
Route::post('/login/2fa/setup', [TwoFactorController::class, 'setup']);
Route::post('/login/2fa/confirm', [TwoFactorController::class, 'confirm']);
Route::post('/login/2fa/verify', [TwoFactorController::class, 'verify']);

// -- Recuperación de contraseña (RF005) --
Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);

// -- Renovación de sesión (RF004) --
// Sólo la acepta el refresh token, gracias a la ability "token:refresh".
Route::middleware(['auth:sanctum', 'abilities:token:refresh', 'approved.account'])
    ->post('/auth/refresh', [AuthApiController::class, 'refresh']);

// RUTA PARA EL SENSOR (POST): El hardware envía los datos aquí de forma directa.
// No usa Sanctum: el sensor se identifica con su X-Device-Key (RF002).
Route::middleware(['throttle:iot', 'iot.device'])
    ->post('/iot/lecturas', [LecturaIotApiController::class, 'store']);

// RUTAS PROTEGIDAS (Requieren Token Bearer)
// Todo lo que esté dentro de este grupo exige que Flutter envíe un token válido.
// "abilities:api:access" impide que un refresh token se use como token normal.
Route::middleware(['auth:sanctum', 'abilities:api:access', 'approved.account'])->group(function () {
    Route::prefix('admin/settings/security')->controller(SecuritySettingsController::class)->group(function () {
        Route::get('/', 'show');
        Route::post('/authenticators', 'start')->middleware('throttle:5,1');
        Route::post('/authenticators/{id}/confirm', 'confirm')->whereUuid('id')->middleware('throttle:5,1');
        Route::delete('/authenticators/{id}', 'remove')->where('id', 'principal|[0-9a-fA-F-]{36}')->middleware('throttle:5,1');
        Route::delete('/trusted-devices/{id}', 'forget')->where('id', 'all|[0-9a-fA-F-]{36}')->middleware('throttle:15,1');
        Route::post('/recovery-codes', 'recovery')->middleware('throttle:5,1');
    });
    Route::prefix('produccion')->middleware('idempotent.write')->controller(ProductionController::class)->group(function () {
        Route::get('/inicio', 'bootstrap');
        Route::get('/reporte', 'report');
        Route::get('/exportar', 'export');
        Route::get('/exportar/{type}', 'exportRecords');
        Route::post('/guia', 'guide');
        Route::post('/siembras', 'planting');
        Route::put('/parametros/{type}/{id}', 'parameters')->where('type', 'finca|lote');
        Route::get('/historial/{type}/{id}', 'history')->where('type', 'finca|lote|registro');
        Route::post('/registros/{type}', 'save');
        Route::put('/registros/{type}/{id}', 'save');
        Route::delete('/registros/{id}', 'archive');
    });
    Route::get('/admin/accounts', [AccountAdminController::class, 'index']);
    Route::get('/admin/accounts/{user}/history', [AccountAdminController::class, 'history']);
    Route::post('/admin/accounts/{user}/actions', [AccountAdminController::class, 'change'])->middleware(['throttle:15,1', 'idempotent.write']);

    Route::prefix('campo')->middleware('idempotent.write')->group(function () {
        Route::match(['get', 'put'], '/perfil', [CampoApiController::class, 'perfil']);
        Route::get('/fincas', [CampoApiController::class, 'fincas']);
        Route::get('/dashboard', [CampoApiController::class, 'dashboard']);
        Route::get('/fincas/{finca}/lotes', [CampoApiController::class, 'lotes']);
        Route::post('/fincas/{finca}/lotes', [CampoApiController::class, 'guardarLote']);
        Route::put('/fincas/{finca}/lotes/{id}', [CampoApiController::class, 'guardarLote']);
        Route::delete('/lotes/{id}', [CampoApiController::class, 'archivarLote']);
        Route::get('/lotes/{lote}/actividades', [CampoApiController::class, 'actividades']);
        Route::post('/lotes/{lote}/actividades', [CampoApiController::class, 'guardarActividad']);
        Route::get('/lotes/{lote}/lecturas', [CampoApiController::class, 'lecturas']);
        Route::post('/lotes/{lote}/lecturas', [CampoApiController::class, 'lecturaManual']);
        Route::get('/evidencia/{tipo}/{id}', [CampoApiController::class, 'evidencia']);
        Route::get('/archivos/{id}/descargar', [CampoApiController::class, 'descargar']);
        Route::post('/multimedia/archivo', function (Request $r, EvidenceService $service) {
            return response()->json(['success' => true, 'data' => $service->upload($r)], 201);
        })->middleware('throttle:20,1');
    });

    Route::get('/admin/productores', function (Request $r) {
        abort_unless($r->user()->isSuperAdmin(), 403);

        return response()->json(['success' => true, 'data' => User::where('role', 'productor')->whereExists(function ($q) {
            $q->selectRaw('1')->from('access_requests')->whereColumn('access_requests.email', 'users.email')->where('access_requests.status', 'approved');
        })->orderBy('name')->get(['id', 'name', 'email'])]);
    });

    // Dashboard global (HU005): el controlador exige rol Superadmin.
    Route::get('/admin/dashboard', [DashboardApiController::class, 'show']);

    // -- Módulo de Usuario y Sesión --
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::put('/auth/password', [AuthApiController::class, 'changePassword'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::post('/logout-all', [AuthApiController::class, 'logoutAll']);

    // -- Gestión del doble factor ya autenticado (RF003) --
    Route::get('/2fa/status', [TwoFactorController::class, 'status']);
    Route::post('/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:5,1');

    // -- MinIO Presigned URL (para subida directa desde Flutter) --
    Route::post('/minio/presigned-url', [PresignedUrlController::class, 'generate'])->middleware('throttle:30,1');

    // -- Módulo de Producción Agrícola (Data Warehouse) --
    // Flutter pide el cascarón de la finca
    Route::get('/mis-fincas', [FincaApiController::class, 'misFincas']);

    // -- Módulo Admin: Fincas (Panel Angular / HU003) --
    Route::get('/admin/fincas', [FincaApiController::class, 'indexAdmin']);
    Route::patch('/admin/fincas/{id}/estado', [FincaApiController::class, 'updateEstadoAdmin']);
    Route::post('/admin/fincas', [FincaApiController::class, 'storeAdmin']);
    Route::put('/admin/fincas/{id}', [FincaApiController::class, 'updateAdmin']);

    // -- Módulo Superadmin: Lotes (Panel Angular / SA-HU004 RF001) --
    Route::get('/admin/lotes', [LoteApiController::class, 'indexAdmin']);
    Route::post('/admin/lotes', [LoteApiController::class, 'storeAdmin']);
    Route::get('/admin/lotes/{id}', [LoteApiController::class, 'showAdmin']);
    Route::put('/admin/lotes/{id}', [LoteApiController::class, 'updateAdmin']);
    Route::delete('/admin/lotes/{id}', [LoteApiController::class, 'destroyAdmin']);

    // -- Módulo Superadmin: Actividades agrícolas (Panel Angular / SA-HU004 RF002) --
    Route::get('/admin/actividades', [ActividadApiController::class, 'indexAdmin']);
    Route::post('/admin/actividades', [ActividadApiController::class, 'storeAdmin']);
    Route::get('/admin/actividades/{id}', [ActividadApiController::class, 'showAdmin']);
    Route::put('/admin/actividades/{id}', [ActividadApiController::class, 'updateAdmin']);
    Route::delete('/admin/actividades/{id}', [ActividadApiController::class, 'destroyAdmin']);

    // -- Módulo Superadmin: Lecturas IoT (Panel Angular / SA-HU004 RF003) --
    Route::get('/admin/lecturas-iot', [LecturaIotApiController::class, 'indexAdmin']);
    Route::post('/admin/lecturas-iot', [LecturaIotApiController::class, 'storeAdmin']);
    Route::get('/admin/lecturas-iot/{id}', [LecturaIotApiController::class, 'showAdmin']);
    Route::put('/admin/lecturas-iot/{id}', [LecturaIotApiController::class, 'updateAdmin']);
    Route::delete('/admin/lecturas-iot/{id}', [LecturaIotApiController::class, 'destroyAdmin']);

    // -- Módulo Superadmin: Dispositivos IoT y sus API keys (Panel Angular / RF002) --
    Route::get('/admin/dispositivos-iot', [DispositivoIotApiController::class, 'indexAdmin']);
    Route::post('/admin/dispositivos-iot', [DispositivoIotApiController::class, 'storeAdmin']);
    Route::get('/admin/dispositivos-iot/{id}', [DispositivoIotApiController::class, 'showAdmin']);
    Route::put('/admin/dispositivos-iot/{id}', [DispositivoIotApiController::class, 'updateAdmin']);
    Route::post('/admin/dispositivos-iot/{id}/regenerar-key', [DispositivoIotApiController::class, 'regenerarKeyAdmin']);
    Route::delete('/admin/dispositivos-iot/{id}', [DispositivoIotApiController::class, 'destroyAdmin']);

    // -- Módulo Superadmin: Evidencia multimedia (Panel Angular / SA-HU004 RF004) --
    Route::get('/admin/archivos-multimedia', [MultimediaApiController::class, 'indexAdmin']);
    Route::get('/admin/archivos-multimedia/{id}', [MultimediaApiController::class, 'showAdmin']);
    Route::put('/admin/archivos-multimedia/{id}', [MultimediaApiController::class, 'updateAdmin']);
    Route::delete('/admin/archivos-multimedia/{id}', [MultimediaApiController::class, 'destroyAdmin']);

    // -- Módulo Admin: Solicitudes de acceso --
    Route::get('/admin/access-requests', [AccessRequestApiController::class, 'index']);
    Route::patch('/admin/access-requests/{accessRequest}/status', [AccessRequestApiController::class, 'updateStatus']);
    Route::post('/admin/access-requests/{accessRequest}/invitation', [AccessRequestApiController::class, 'resend'])->middleware('throttle:5,1');
    Route::get('/admin/access-requests/{accessRequest}/invitation-preview', [AccessRequestApiController::class, 'preview']);

    // NUEVA: El usuario solicita vincular una finca adicional
    Route::post('/fincas/solicitar', [FincaApiController::class, 'solicitar'])->middleware('idempotent.write');

    // Flutter envía el GPS, hectáreas y tipo de suelo
    Route::put('/fincas/{id}/completar', [FincaApiController::class, 'completarPerfil']);

    // Flutter envía archivos multimedia al Data Lake
    Route::post('/fincas/{id}/multimedia', [FincaApiController::class, 'subirMultimedia']);

    // Rutas para Lotes y Actividades (NUEVAS)
    // traer los lotes de una finca para mostrar en el dashboard
    Route::get('/fincas/{finca_id}/lotes', [LoteApiController::class, 'index']);
    // Flutter envía los datos del nuevo lote (nombre, hectáreas, cultivo, etc.)
    Route::post('/fincas/{finca_id}/lotes', [LoteApiController::class, 'store']);
    // Flutter cambia el estado de un lote (disponible, en_uso, no_disponible)
    Route::patch('/lotes/{lote_id}/estado', [LoteApiController::class, 'updateEstado']);
    // Flutter solicita las actividades de un lote para mostrar en el dashboard
    Route::get('/lotes/{lote_id}/actividades', [ActividadApiController::class, 'index']);
    // Flutter envía los datos de la nueva actividad (nombre, fecha, descripción, etc.)
    Route::post('/lotes/{lote_id}/actividades', [ActividadApiController::class, 'store']);

    Route::post('/multimedia/subir', [MultimediaApiController::class, 'store'])->middleware('idempotent.write');

    // Flutter consulta las métricas del lote para dibujar las gráficas
    Route::get('/lotes/{lote_id}/lecturas', [LecturaIotApiController::class, 'index']);

});
