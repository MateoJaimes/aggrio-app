<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API key obligatoria para los dispositivos (RF002)
    |--------------------------------------------------------------------------
    |
    | En false, POST /api/iot/lecturas sigue aceptando lecturas sin la cabecera
    | X-Device-Key (quedan registradas en el log como "sin API key"); una key
    | inválida se rechaza siempre. Pasar a true sólo cuando el firmware de todos
    | los ESP32 envíe su key: desde ese momento las lecturas sin key reciben 401.
    |
    */

    'require_device_key' => (bool) env('IOT_REQUIRE_DEVICE_KEY', false),

    /*
    |--------------------------------------------------------------------------
    | Límite de peticiones de ingesta
    |--------------------------------------------------------------------------
    |
    | Lecturas por minuto que acepta POST /api/iot/lecturas. Se cuenta por API key
    | cuando el dispositivo la envía y por IP cuando no; varios sensores detrás
    | del mismo router de una finca comparten ese contador por IP.
    |
    */

    'rate_limit_per_minute' => (int) env('IOT_RATE_LIMIT_PER_MINUTE', 120),

];
