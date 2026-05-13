<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class FirebaseWebConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'apiKey' => config('firebase_web.api_key'),
            'authDomain' => config('firebase_web.auth_domain'),
            'projectId' => config('firebase_web.project_id'),
            'storageBucket' => config('firebase_web.storage_bucket'),
            'messagingSenderId' => config('firebase_web.messaging_sender_id'),
            'appId' => config('firebase_web.app_id'),
            'measurementId' => config('firebase_web.measurement_id'),
            'vapidKey' => config('firebase_web.vapid_public_key'),
        ]);
    }
}
