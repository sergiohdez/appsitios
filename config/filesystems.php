<?php

return [
    'default' => env('FILESYSTEM_DISK', 'private'),
    'disks' => [
        'private' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => false, 'throw' => true, 'report' => false],
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => false, 'throw' => true],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
