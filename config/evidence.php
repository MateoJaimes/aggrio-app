<?php

return [
    'disk' => env('EVIDENCE_DISK', env('AWS_BUCKET') ? 's3' : 'local'),
    'file_limit' => 10 * 1024 * 1024,
    'video_limit' => 50 * 1024 * 1024,
    'max_files' => 10,
    'user_quota' => (int) env('EVIDENCE_USER_QUOTA_BYTES', 1073741824),
    'mime_extensions' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'video/mp4' => 'mp4', 'application/pdf' => 'pdf'],
];
