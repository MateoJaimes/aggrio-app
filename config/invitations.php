<?php

return [
    // Temporary local testing aid. SMTP always takes precedence and production never prints credentials.
    'console_enabled' => (bool) env('INVITATION_CONSOLE_ENABLED', false),
];
