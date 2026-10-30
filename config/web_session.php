<?php

return [
    'origins' => array_values(array_filter(array_map('trim', explode(',', env('WEB_AUTH_ORIGINS', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:4200'))))))),
    'access_minutes' => (int) env('WEB_ACCESS_TOKEN_TTL', 15),
];
