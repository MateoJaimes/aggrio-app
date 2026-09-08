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

// RUTA PÚBLICA (No requiere Token)
// Aquí es donde Flutter envía el correo y la contraseña para obtener acceso.
Route::post('/login', [AuthApiController::class, 'login']);

// RUTA PARA EL SENSOR (POST): El hardware envía los datos aquí de forma directa
Route::post('/iot/lecturas', [LecturaIotApiController::class, 'store']);

// RUTAS PROTEGIDAS (Requieren Token Bearer)
// Todo lo que esté dentro de este grupo exige que Flutter envíe un token válido.
Route::middleware('auth:sanctum')->group(function () {
    
    // -- Módulo de Usuario y Sesión --
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::post('/logout-all', [AuthApiController::class, 'logoutAll']);

    // -- MinIO Presigned URL (para subida directa desde Flutter) --
    Route::post('/minio/presigned-url', [PresignedUrlController::class, 'generate']);

    // -- Módulo de Producción Agrícola (Data Warehouse) --
    // Flutter pide el cascarón de la finca
    Route::get('/mis-fincas', [FincaApiController::class, 'misFincas']); 

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