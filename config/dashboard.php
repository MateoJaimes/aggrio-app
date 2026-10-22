<?php

return [
    // Activity forms and lists already identify all recorded costs as COP.
    'currency' => 'COP',

    // User-defined rule: sensors report every 15 minutes and become stale after 45.
    'sensor_stale_minutes' => (static function (): int {
        $minutes = env('DASHBOARD_SENSOR_STALE_MINUTES', 45);

        return is_numeric($minutes) && (int) $minutes > 0 ? (int) $minutes : 45;
    })(),
];
