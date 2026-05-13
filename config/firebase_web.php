<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Web / FCM client (browser)
    |--------------------------------------------------------------------------
    |
    | Used by the Blade FCM init component and firebase-messaging-sw.js (via fetch).
    | Set these in .env from your Firebase project settings (Project settings → General).
    |
    */

    'api_key' => env('FIREBASE_WEB_API_KEY'),
    'auth_domain' => env('FIREBASE_WEB_AUTH_DOMAIN'),
    'project_id' => env('FIREBASE_WEB_PROJECT_ID'),
    'storage_bucket' => env('FIREBASE_WEB_STORAGE_BUCKET'),
    'messaging_sender_id' => env('FIREBASE_WEB_MESSAGING_SENDER_ID'),
    'app_id' => env('FIREBASE_WEB_APP_ID'),
    'measurement_id' => env('FIREBASE_WEB_MEASUREMENT_ID'),

    /** Web Push certificates (Cloud Messaging → Web configuration) */
    'vapid_public_key' => env('FIREBASE_WEB_VAPID_PUBLIC_KEY'),
];
