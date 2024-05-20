<?php

return [
    'application_name' => env('GOOGLE_APPLICATION_NAME'),
    'service_account_credentials_json' => env('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS_JSON'),
    'scopes' => [
        'https://www.googleapis.com/auth/webmasters',
        'https://www.googleapis.com/auth/webmasters.readonly'
    ],
];
