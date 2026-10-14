<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URL base del enlace de firma remota
    |--------------------------------------------------------------------------
    |
    | Direccion con la que el empleado alcanza el sistema desde su propio
    | dispositivo (dominio publico o IP de la red), p. ej.
    | `https://centro.empresa.com` o `http://192.168.8.47:8000`. Sin ella el
    | enlace se arma con el host desde el que TI abre el admin, y si ese host
    | es `localhost` el enlace solo abre en esa misma computadora.
    |
    */
    'firma_url_base' => env('STI_FIRMA_URL_BASE'),
];
