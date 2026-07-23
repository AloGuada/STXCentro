# Tipo de cambio real en Costos: dos momentos (referencia + reconciliación)

## Contexto / Problema

Hoy la "moneda" en Costos es solo una **etiqueta nominal** (`mxn|usd|eur`) sin ningún tipo
de cambio efectivo:

- `costos_pagos.tipo_cambio` existe (default `1`) pero **nunca se lee en ningún cálculo**.
- Todos los cargos al presupuesto (`RubroAfectado.monto`, `costos_obra_rubros.acumulado` y
  `apartado`) se guardan con el **número crudo de la moneda del documento**. Una OC de
  1,000 USD hoy resta **1,000** (no ~18,700) del presupuesto en MXN → **bug latente**.
- El presupuesto (`presupuestado`, `acumulado`, `apartado`) siempre está en MXN. Mezclar
  divisas crudas contra MXN descuadra el disponible.

## Objetivo

El tipo de cambio se captura en **dos momentos**, con MXN como moneda base:

1. **Al reservar (Requisición/OC):** se toma el **TC de referencia del día** y el presupuesto
   se aparta/ejerce en **MXN = monto_extranjero × TC_ref**. Fuentes:
   - **USD → Banxico** (FIX oficial, serie `SF43718`).
   - **EUR → ECB** (XML diario `eurofxref-daily.xml`, MXN por EUR directo).
   - Respaldo: si Banxico no responde/no hay token, USD se calcula por cross-rate del ECB
     (`MXN_por_EUR ÷ USD_por_EUR`).
2. **Al comprobar el pago:** al subir el comprobante en `PagoController::uploadComprobante`
   se captura **manualmente el monto real en MXN** (lo que efectivamente salió del banco).
   Se calcula `delta = real − referencia` y se **prorratea entre los rubros afectados** del
   documento, ajustando el ledger (`AcumuladoLedger`) para que `acumulado` quede en gasto real.

> Nota: el "comprobante de pago" (`uploadComprobante` → `PagoProcessor::completar`, marca el
> pago como `pagado`) es distinto del "comprobante de recepción" que libera la factura a
> aprobación. Este refactor toca el **de pago**.

## Decisión de diseño

- **MXN es la base.** El presupuesto siempre pesa en MXN. La divisa vive en el documento y en
  el `RubroAfectado`, pero el ledger (`acumulado`) siempre recibe MXN.
- **`RubroAfectado` guarda el rastro de la conversión:** `moneda`, `monto_origen` (en divisa)
  y `tipo_cambio`. `monto` sigue siendo el peso en MXN (lo que valida `ValidadorPresupuesto`).
- **El TC de referencia se cachea por día** en `costos_tipos_cambio` (una fila por
  `(fecha, moneda)`), poblada on-demand por `TipoCambioService`. Fin de semana / día sin dato
  → último hábil disponible.
- **La reconciliación solo corre si la moneda del pago ≠ mxn.** Para MXN, `monto_real = monto`
  y el delta es 0 (no-op).
- **Backfill:** los `RubroAfectado` históricos en USD/EUR se **recomputan con el TC de hoy**
  (decisión del negocio: no se busca el TC de la fecha original).

---

## Backend

### Infraestructura de tipo de cambio ✅ (hecho)
- [x] Migración `create_costos_tipos_cambio_table` — `id`, `date fecha`, `string moneda`
      (usd/eur), `string fuente` (banxico/ecb), `decimal tasa (14,6)`, timestamps,
      `unique(fecha, moneda)`.
- [x] Modelo `App\Models\Costos\TipoCambio` (casts: `fecha` date, `tasa` decimal:6).
- [x] Factory `TipoCambioFactory` (estados `usd()` / `eur()`).
- [x] `config/services.php` → bloque `banxico` (`token` = env `BANXICO_TOKEN`).
- [x] `config/costos.php` → bloque `tipo_cambio` (serie Banxico `SF43718`, URL Banxico SIE,
      URL ECB, moneda base `mxn`).
- [x] Servicio `App\Services\Costos\TipoCambioService`:
    - `mxnPorUnidad(string $moneda, ?CarbonInterface $fecha = null): float` — devuelve MXN por
      1 unidad de la divisa. `mxn` → `1.0`. Lee de cache (tabla); si falta, consulta la fuente
      y persiste.
    - USD → Banxico SIE (`GET .../series/SF43718/datos/oportuno`, header `Bmx-Token`).
    - EUR → ECB XML (`simplexml_load_string` + xpath con namespace `eurofxref`).
    - Respaldo USD → cross-rate ECB si Banxico falla o no hay token.
    - Sin dato del día → última tasa cacheada (stale) antes de fallar.
- [ ] (Opcional) Command `costos:sync-tipos-cambio` + schedule diario (routes/console.php).
- [x] Tests: `TipoCambioServiceTest` (6 pasan) — Banxico, cross-rate ECB, EUR directo, cache,
      respaldo stale, mxn no-op.

### Momento 1 — snapshot de referencia + presupuesto en MXN ✅ (hecho)
- [x] Migración `add_moneda_tipo_cambio_to_costos_rubros_afectados` — `string moneda`
      default `mxn`, `decimal monto_origen (14,2) nullable`, `decimal tipo_cambio (14,6)`
      default `1`.
- [x] `ApartadoPresupuestal::aplicarCargo()` — acepta `moneda`, convierte el `monto` a MXN con
      el TC de referencia y persiste `moneda`/`monto_origen`/`tipo_cambio` en el `RubroAfectado`
      (choke point único; `apartado` y `acumulado` siempre quedan en MXN).
- [x] Ajustados los puntos que cargan presupuesto para pasar la divisa:
    - `RequisicionController` apartar + re-apartar (moneda por partida, `cotizacionPrecio.moneda`).
    - `SolicitudPagoController` enviar + re-apartar (moneda del doc, `tipo_moneda`).
    - `AfectaPresupuesto::aplicarImpactoPresupuestal` (trait OC/SP) vía `monedaAfectacion()`.
    - `EntregaController` ajuste de PU en recepción (moneda de la OC).
- [x] Determinismo en tests: `Pest.php` liga un `TipoCambioService` falso (usd=18.5, eur=20.0);
      `TipoCambioServiceTest` re-liga el real.
- [x] Tests `PresupuestoDivisaTest` (3 pasan): Aplicado USD, Apartado EUR, mxn no-op.
- [ ] (Pendiente) Snapshot del TC en columnas propias de OC/SolicitudPago (para display) —
      se hará junto con el frontend; el rastro ya vive en `RubroAfectado`.
- [ ] Nota preexistente (ajena a este refactor): `EditLockTest > _version obsoleto recibe 409`
      falla en `main` por orden de validación (`monto_total` required antes del lock).

### Momento 2 — reconciliación al comprobante de pago ✅ (hecho)
- [x] Migración `add_monto_mxn_to_costos_pagos` + `Pago` fillable/casts (`monto_mxn`).
- [x] `AbonoComprobanteRequest` — `monto_real_mxn` `required` si la moneda del pago ≠ `mxn`.
- [x] Servicio `App\Services\Costos\ReconciliacionCambioPago::reconciliar(Pago, float)`:
    - Resuelve la entidad presupuestal (OC vía Factura/anticipo, o SolicitudPago directa) y
      sus `RubroAfectado` `Aplicado`.
    - `delta = montoRealMxn − Σ monto`, prorrateado por peso vía `AcumuladoLedger`; el último
      rubro absorbe el residuo de redondeo (Σ deltas == delta exacto).
    - Actualiza `RubroAfectado.monto`/`tipo_cambio` reales y sella `pago.monto_mxn`/`tipo_cambio`.
- [x] `PagoController::uploadComprobante` llama la reconciliación tras `PagoProcessor::completar`.
- [x] `PagoFactory` default `moneda='mxn'` (evita romper tests de comprobante con divisa random).
- [x] Tests `ReconciliacionCambioPagoTest` (3 pasan): ajuste USD, prorrateo 2 rubros, mxn no-op.
- [x] Pagos parciales / múltiples facturas por OC: el delta se calcula por pago
      (real − monto_pago × TC de referencia), prorrateado por rubro. Cada comprobante ajusta
      solo su porción; no sobre-concilia la entidad.

**Suite completo de Costos:** 792 pasan; 6 fallos preexistentes en `main` ajenos a este
refactor (2 EditLock flaky, 1 OrdenCompraComprobante 403, 3 SolicitudPagoDesdeOc).

### Diseño refinado — el TC es un campo del documento ✅ (hecho)
- [x] Columna `tipo_cambio` en `costos_requisiciones`, `costos_solicitudes_pago`,
      `costos_ordenes_compra` + modelos (fillable/cast). El documento **guarda** su TC.
- [x] `ApartadoPresupuestal::aplicarCargo(..., ?float $tc)` usa el TC **guardado del documento**;
      `TipoCambioService` queda solo como sugerencia/fallback.
- [x] Controllers (Requisición/SP) y trait pasan el TC del doc; `OrdenCompraGenerator` hereda el
      TC de la requisición a la OC. Store/Update requests aceptan `tipo_cambio`.
- [x] Endpoint `GET admin/costos/tipo-cambio/{moneda}` (`TipoCambioController`) como sugerencia.
- [x] Test: el apartado usa el TC guardado (17.0), no el del servicio (18.5).

### Backfill / recálculo ✅ (hecho)
- [x] Command `costos:recalcular-tipo-cambio` con **dos modos**:
    - Objetivo: `--solicitud=ID` / `--requisicion=ID` → recalcula esos documentos con **su TC
      guardado** (re-aplicar tras editar el TC). Requisición resuelve a su(s) OC.
    - Global: sin objetivos → backfill de cargos en crudo con el **TC de hoy** (`--moneda=`).
  Ambos sellan `moneda`/`monto_origen`/`tipo_cambio`, ponen `monto` en MXN y ajustan `acumulado`
  por el delta vía ledger. Idempotente. `--dry-run` para previsualizar.
- [x] Tests `RecalcularTipoCambioTest` (4 pasan): crudo global, dry-run, objetivo con TC del doc,
      idempotencia.
- Uso: `php artisan costos:recalcular-tipo-cambio --solicitud=123` (usa el TC guardado de la SP),
  o `--moneda=usd --dry-run` (global). Requiere las migraciones ya corridas.

---

## Frontend

### Types (resources/js/types/models.ts)
- [ ] Agregar `tipo_cambio` / `monto_origen` / `moneda` donde aplique (OC, SolicitudPago,
      RubroAfectado, Pago). `TIPO_MONEDA_LABELS` ya existe.

### Páginas
- [x] SolicitudPago `create.tsx`: campo **"Tipo de cambio"** (visible si moneda ≠ mxn,
      autollenado del endpoint al cambiar moneda, editable, botón "Sugerir") + **Total en MXN**
      en vivo. Se envía `tipo_cambio`. TS compila limpio.
- [ ] SolicitudPago `edit.tsx`: mismo campo de TC.
- [ ] Requisición create/edit: campo de TC a nivel documento.
- [ ] `pagos/show.tsx` (modal de comprobante): campo **"Monto real pagado (MXN)"** cuando la
      moneda ≠ mxn, con el TC implícito y la diferencia contra la referencia.
- [ ] Vistas OC / SolicitudPago / Pago: mostrar TC guardado y, tras el comprobante, el TC real
      + el ajuste aplicado al presupuesto.

### Navegación
- [ ] (Sin cambios de sidebar; el TC vive dentro de los documentos existentes.)

---

## Verificación
- [ ] Prerrequisito: `BANXICO_TOKEN` en `.env` (token gratuito del SIE de Banxico). Sin él,
      USD usa el respaldo del ECB.
- [ ] `php artisan migrate`
- [ ] `php artisan test --compact --filter=TipoCambio` (y filtros de apartado / reconciliación)
- [ ] `vendor/bin/pint --dirty`
- [ ] `npm run build`

Rama: `feat/costos-tipo-cambio`.
