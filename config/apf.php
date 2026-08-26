<?php

return [
    'currency' => env('APF_CURRENCY', 'CAD'),
    'countries' => array_filter(array_map('trim', explode(',', env('APF_COUNTRIES', 'CA,US')))),
    'digital_access_days' => (int) env('APF_DIGITAL_ACCESS_DAYS', 365),
    'support_email' => env('APF_SUPPORT_EMAIL', 'info@apfpress.com'),
    'vite_app_origin' => env('VITE_APP_ORIGIN', 'http://localhost:8080'),
    'vite_dev_origin' => env('VITE_DEV_ORIGIN', 'http://localhost:'.env('VITE_DEV_PORT', 5174)),
    'owner_email' => env('APF_OWNER_EMAIL'),
    'owner_password' => env('APF_OWNER_PASSWORD'),
    'import_download_media' => (bool) env('APF_IMPORT_DOWNLOAD_MEDIA', false),
];
