<?php

return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMP_UPLOAD_DISK', 'local'),
        'rules' => ['required', 'file', 'max:102400'],
        'directory' => env('LIVEWIRE_TEMP_UPLOAD_DIRECTORY', 'livewire-tmp'),
        'middleware' => env('LIVEWIRE_TEMP_UPLOAD_MIDDLEWARE', 'throttle:60,1'),
        'preview_mimes' => [
            'png',
            'gif',
            'bmp',
            'svg',
            'wav',
            'mp4',
            'mov',
            'avi',
            'wmv',
            'mp3',
            'm4a',
            'jpg',
            'jpeg',
            'mpga',
            'webp',
            'wma',
            'pdf',
            'zip',
        ],
        'max_upload_time' => (int) env('LIVEWIRE_TEMP_UPLOAD_MAX_UPLOAD_TIME', 20),
        'cleanup' => true,
    ],
];
