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

    /*
    |--------------------------------------------------------------------------
    | Tasa de IVA
    |--------------------------------------------------------------------------
    |
    | Tasa aplicada al calcular el total de una orden de compra a partir del
    | subtotal de sus líneas. Las facturas NO usan este valor: su IVA proviene
    | del CFDI (XML) del proveedor.
    |
    */
    'iva_rate' => (float) env('COSTOS_IVA_RATE', 0.16),

    /*
    |--------------------------------------------------------------------------
    | Tasas de retención (ISR / IVA)
    |--------------------------------------------------------------------------
    |
    | Tasas aplicadas automáticamente en el desglose de retenciones de la OC,
    | según el régimen/tipo de persona del proveedor y el tipo fiscal de cada
    | partida. El cálculo es informativo y se corrobora al recibir la factura.
    |
    */
    'retenciones' => [
        'isr_resico' => (float) env('COSTOS_RET_ISR_RESICO', 0.0125),
        'isr_fletes' => (float) env('COSTOS_RET_ISR_FLETES', 0.04),
        'isr_honorarios' => (float) env('COSTOS_RET_ISR_HONORARIOS', 0.10),
        'iva_honorarios' => (float) env('COSTOS_RET_IVA_HONORARIOS', 0.1067),
        'iva_renta' => (float) env('COSTOS_RET_IVA_RENTA', 0.1067),
    ],
];
