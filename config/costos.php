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

    /*
    |--------------------------------------------------------------------------
    | Bloqueo duro de sobregiro presupuestal (Fase 14)
    |--------------------------------------------------------------------------
    |
    | Cuando es `true`, las operaciones que excedan el presupuesto disponible
    | de un obra_rubro lanzan SobregiroPresupuestalException y abortan la
    | transacción (OC store, SolicitudPago aprobar, Requisicion generar OC).
    | Cuando es `false`, solo se persiste sobre_giro=true en RubroAfectado y
    | se dispara el evento PresupuestoExcedido (alertas vía notificación).
    |
    */
    'bloquear_sobregiro' => (bool) env('COSTOS_BLOQUEAR_SOBREGIRO', false),

    /*
    |--------------------------------------------------------------------------
    | Umbral de alerta crítica (% del presupuesto consumido)
    |--------------------------------------------------------------------------
    |
    | Cuando un obra_rubro alcanza este porcentaje de consumo (sin haberse
    | excedido aún), se dispara el evento PresupuestoEnUmbralCritico para
    | notificar a los aprobadores antes de que se sobregire.
    |
    */
    'umbral_alerta_porcentaje' => (int) env('COSTOS_UMBRAL_ALERTA_PORCENTAJE', 90),
];
