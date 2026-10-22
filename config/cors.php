<?php

return [
    // Sin 'sanctum/csrf-cookie': no se usa el modo SPA/cookie de Sanctum (RF006).
    'paths'                    => ['api/*'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => ['*'],   // 🔒 Después restringimos por IP/puerto
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];