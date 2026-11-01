<?php

return [
    'calendar_id' => env('GOOGLE_CALENDAR_ID'),
    'credentials_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    'token_uri' => env('GOOGLE_OAUTH_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
    'api_base_uri' => 'https://www.googleapis.com/calendar/v3',
];
