<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'banxico' => [
        'token' => env('BANXICO_TOKEN'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434/api/generate'),
        'model' => env('OLLAMA_MODEL', 'deepseek-v2'),
        'timeout' => env('OLLAMA_TIMEOUT', 180),
        'max_retries' => env('OLLAMA_MAX_RETRIES', 3),
        'temperature' => env('OLLAMA_TEMPERATURE', 0.3),
    ],

    // Servicio aparte que convierte el IFC de Tekla en marcas con sus cordones
    // de soldadura (ifc-service/). La conversión es asíncrona: el job pregunta
    // por el estado cada `poll_segundos`.
    'ifc' => [
        'url' => env('IFC_SERVICE_URL', 'http://127.0.0.1:8011'),
        'timeout' => env('IFC_SERVICE_TIMEOUT', 120),
        'poll_segundos' => env('IFC_SERVICE_POLL', 20),
    ],

    // Servicio aparte que reconoce texto en imágenes y PDF (ocr-service/). El
    // modelo tarda en cargar y come memoria, así que vive en su propio proceso
    // y se levanta una vez, no por petición.
    'paddle_ocr' => [
        'url' => env('OCR_SERVICE_URL', 'http://127.0.0.1:8800'),
        'timeout' => env('OCR_SERVICE_TIMEOUT', 120),
    ],

];
