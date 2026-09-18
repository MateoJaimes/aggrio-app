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
