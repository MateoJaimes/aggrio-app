<?php

use App\Http\Controllers\Api\AuthApiController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\FincaApiController;
use App\Http\Controllers\Api\LoteApiController;
use App\Http\Controllers\Api\ActividadApiController;
use App\Http\Controllers\Api\MultimediaApiController;
use App\Http\Controllers\Api\LecturaIotApiController;
use App\Http\Controllers\Api\PresignedUrlController;
use App\Http\Controllers\Api\AccessRequestApiController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\TwoFactorController;

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
Route::middleware(['auth:sanctum', 'abilities:token:refresh'])
    ->post('/auth/refresh', [AuthApiController::class, 'refresh']);

// RUTA PARA EL SENSOR (POST): El hardware envía los datos aquí de forma directa
Route::post('/iot/lecturas', [LecturaIotApiController::class, 'store']);

// RUTAS PROTEGIDAS (Requieren Token Bearer)
// Todo lo que esté dentro de este grupo exige que Flutter envíe un token válido.
// "abilities:api:access" impide que un refresh token se use como token normal.
Route::middleware(['auth:sanctum', 'abilities:api:access'])->group(function () {

    // -- Módulo de Usuario y Sesión --
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::post('/logout-all', [AuthApiController::class, 'logoutAll']);

    // -- Gestión del doble factor ya autenticado (RF003) --
    Route::get('/2fa/status', [TwoFactorController::class, 'status']);
    Route::post('/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);

    // -- MinIO Presigned URL (para subida directa desde Flutter) --
    Route::post('/minio/presigned-url', [PresignedUrlController::class, 'generate']);

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

    // -- Módulo Superadmin: Evidencia multimedia (Panel Angular / SA-HU004 RF004) --
    Route::get('/admin/archivos-multimedia', [MultimediaApiController::class, 'indexAdmin']);
    Route::get('/admin/archivos-multimedia/{id}', [MultimediaApiController::class, 'showAdmin']);
    Route::put('/admin/archivos-multimedia/{id}', [MultimediaApiController::class, 'updateAdmin']);
    Route::delete('/admin/archivos-multimedia/{id}', [MultimediaApiController::class, 'destroyAdmin']);

    // -- Módulo Admin: Solicitudes de acceso --
    Route::get('/admin/access-requests', [AccessRequestApiController::class, 'index']);
    Route::patch('/admin/access-requests/{accessRequest}/status', [AccessRequestApiController::class, 'updateStatus']);

    // NUEVA: El usuario solicita vincular una finca adicional
    Route::post('/fincas/solicitar', [FincaApiController::class, 'solicitar']);
    
    // Flutter envía el GPS, hectáreas y tipo de suelo
    Route::put('/fincas/{id}/completar', [FincaApiController::class, 'completarPerfil']); 

    // Flutter envía archivos multimedia al Data Lake
    Route::post('/fincas/{id}/multimedia', [FincaApiController::class, 'subirMultimedia']);

    // Rutas para Lotes y Actividades (NUEVAS)
    //traer los lotes de una finca para mostrar en el dashboard
    Route::get('/fincas/{finca_id}/lotes', [LoteApiController::class, 'index']);
    // Flutter envía los datos del nuevo lote (nombre, hectáreas, cultivo, etc.)
    Route::post('/fincas/{finca_id}/lotes', [LoteApiController::class, 'store']);
    // Flutter cambia el estado de un lote (disponible, en_uso, no_disponible)
    Route::patch('/lotes/{lote_id}/estado', [LoteApiController::class, 'updateEstado']);
    // Flutter solicita las actividades de un lote para mostrar en el dashboard
    Route::get('/lotes/{lote_id}/actividades', [ActividadApiController::class, 'index']); 
    // Flutter envía los datos de la nueva actividad (nombre, fecha, descripción, etc.)
    Route::post('/lotes/{lote_id}/actividades', [ActividadApiController::class, 'store']);

    Route::post('/multimedia/subir', [MultimediaApiController::class, 'store']);

    // Flutter consulta las métricas del lote para dibujar las gráficas
    Route::get('/lotes/{lote_id}/lecturas', [LecturaIotApiController::class, 'index']);

});
