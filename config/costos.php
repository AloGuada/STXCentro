<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Edit Lock TTL
    |--------------------------------------------------------------------------
    |
    | Tiempo en minutos durante el cual un lock de edición se considera
    | vigente. Al expirar, otro usuario puede tomarlo aunque el original
    | no haya liberado explícitamente (por cerrar pestaña, crash, etc.).
    |
    */
    'lock_ttl_minutes' => (int) env('COSTOS_LOCK_TTL_MINUTES', 15),
];
