# Port de prepsim → módulo Cotización (`cotiz_`) en el mono

Port completo de la app **prepsim** (PPU de estructura metálica: Electrobun + React + SQLite WASM + AG-Grid) al
monorepo Laravel 12 + Inertia/React 19. prepsim vive en `C:\Users\desarrollo.ti\Documents\dev\prepsim`.

## Decisiones de arquitectura (cerradas)

| Tema | Decisión |
|---|---|
| Prefijo de tabla | `cotiz_` |
| Namespace | `Cotiz` (`App\Models\Cotiz`, `App\Http\Controllers\Admin\Cotiz`, `App\Http\Requests\Admin\Cotiz`, `App\Services\Cotiz`, `App\Enums\Cotiz`) |
| Rutas | `/admin/cotiz/...`, nombres `admin.cotiz.*` |
| Roles | **Dos niveles:** `usuario-cotiz` (trabajo por obra) y `admin-cotiz` (catálogos globales + todo). Permisos `cotiz.{recurso}.{accion}`. Corte exacto entre niveles se define en Fase 6 |
| Obra | **Tabla propia** `cotiz_obras` (NO reutiliza `obras` del mono). Campos extra: `op`, `factor_contratista` (default 1.15), `num_grupos` |
| Cálculo | **Autoritativo en backend PHP** (no client-side) |
| Evaluador de fórmulas | `symfony/expression-language`, **aislado tras** `App\Services\Cotiz\FormulaEvaluator`. Registrar `ceil`/`floor`/`abs`/`round`/`roundup`. Semántica de error "cae a 0/null" vía `try/catch`. Normalizar `^`→`**` al portar seeds si aparece potencia |
| Derivados (GENERATED + vistas `v_*`) | **Calcular en PHP** (accessors Eloquent para fila; servicios de derivación para los 8 agregados). Sin columnas generadas ni vistas de BD → paridad SQLite(dev)/PostgreSQL(prod) |
| Grid editable | **AG-Grid Community (MIT)** en el mono (`ag-grid-community` + `ag-grid-react`). Sin features Enterprise. Listados simples siguen con el `DataTable` existente |
| Multiusuario | **Lock pesimista ESTRICTO por tarjeta/generadora** (unidad de concurrencia = la tarjeta, no la celda). Nadie más edita hasta que se libere; auto-liberación por TTL de heartbeat; override solo para `admin-cotiz`. Sin CRDT/co-edición |
| Tiempo real | **Awareness solo a nivel obra/resumen** (no dentro de la tarjeta, que es mono-usuario por el lock). Polling de Inertia v2 para MVP → Laravel Reverb después (presencia + push instantáneo) sin rehacer nada |
| Versiones | **Snapshots nombrados de inputs** (modelo B por etapas): primero copia inmutable restaurable/comparable del árbol de inputs; luego bitácora append-only que sirve a la vez a auditoría multiusuario y al feed de tiempo real. Sin event sourcing |
| FKs | **Normalizar a convención del mono** (`tarjeta_id`, `generadora_id`, …). prepsim usa nombres inconsistentes (`tarjeta`, `generadora`) — se renombran al portar |
| Legacy NO portado | `insumo_categorias`, `insumos.categoria_id`, `tarjeta_registros.solo_exterior`, `generadora_registros.tipo_merma_override`, `obra_insumo_precios` (deuda técnica de prepsim). **Nota:** la clasificación `categoria_tarjeta_id` de cada insumo (M035) deriva del legacy `categoria_id→insumo_categorias→CASE`; se **pre-resuelve al generar el seed** y se hornea directo en `cotiz_insumos.categoria_tarjeta_id` (nullable). Factores se clasifican por `codigo`. Categorías tarjeta (8): ESTRUCTURA(1), CONSUMIBLES PLANTA(2), CONSUMIBLES OBRA(3), PINTURA(4), TORNILLERÍA(5), LÁMINA(6), MEZZANINE(7), MISC(8) |

## Dependencias a aprobar / instalar

- **Backend:** `composer require symfony/expression-language`
- **Frontend:** `npm i ag-grid-community ag-grid-react` (solo Community/MIT)

## Inventario de tablas (prepsim → cotiz_)

### Catálogos globales
| prepsim | mono |
|---|---|
| `unidades` | `cotiz_unidades` |
| `mermas` | `cotiz_mermas` |
| `pintura_formulas` | `cotiz_pintura_formulas` |
| `categorias_tarjeta` | `cotiz_categorias_tarjeta` |
| `centros_costos` | `cotiz_centros_costos` |
| `insumos` | `cotiz_insumos` |
| `kilos_reales_categorias` | `cotiz_kilos_reales_categorias` |
| `factores` | `cotiz_factores` |
| `cuadrillas` | `cotiz_cuadrillas` |
| `personal_categorias` | `cotiz_personal_categorias` |
| `fases_montaje` | `cotiz_fases_montaje` |
| `fletes_viaticos_catalogo` | `cotiz_fletes_viaticos_catalogo` |
| `resumen_filas` | `cotiz_resumen_filas` |
| `resumen_bloque_colores` | `cotiz_resumen_bloque_colores` |

### Por obra / trabajo
| prepsim | mono |
|---|---|
| `obras` | `cotiz_obras` |
| `generadoras` | `cotiz_generadoras` |
| `generadora_registros` | `cotiz_generadora_registros` |
| `tarjetas` | `cotiz_tarjetas` |
| `tarjeta_generadoras` | `cotiz_tarjeta_generadoras` |
| `tarjeta_registros` | `cotiz_tarjeta_registros` |
| `tarjeta_factores` | `cotiz_tarjeta_factores` |
| `tarjeta_estructuras` | `cotiz_tarjeta_estructuras` |
| `tarjeta_categorias_kilos` | `cotiz_tarjeta_categorias_kilos` |
| `tarjeta_kilos_reales` | `cotiz_tarjeta_kilos_reales` |
| `tarjeta_insumo_precio` | `cotiz_tarjeta_insumo_precio` |
| `obra_insumo_override` | `cotiz_obra_insumo_override` |
| `obra_factor_override` | `cotiz_obra_factor_override` |
| `secciones_montaje` | `cotiz_secciones_montaje` |
| `seccion_personal` | `cotiz_seccion_personal` |
| `seccion_fase_rendimiento` | `cotiz_seccion_fase_rendimiento` |
| `obra_cuadrilla_global` | `cotiz_obra_cuadrilla_global` |
| `obra_flete_estandar` | `cotiz_obra_flete_estandar` |
| `obra_fletes_viaticos` | `cotiz_obra_fletes_viaticos` |
| `resumen_columnas` | `cotiz_resumen_columnas` |
| `resumen_columna_tarjetas` | `cotiz_resumen_columna_tarjetas` |
| `obra_resumen_coeficientes` | `cotiz_obra_resumen_coeficientes` |
| `obra_resumen_celda_override` | `cotiz_obra_resumen_celda_override` |

### Derivados portados a PHP (NO van a BD)
- Por fila (accessors): `t_ml_m2 = ancho*largo*cantidad*cant_pzas`, `kilos_reales = t_ml_m2 * peso_lineal`.
- Agregados (servicios de derivación, reemplazan las 8 vistas): `v_seccion_fase_dias`, `v_seccion_fase_nomina`,
  `v_seccion_fase_importe`, `v_seccion_importe`, `v_obra_fletes_viaticos_subtotal`, `v_obra_cuadrilla_global`,
  `v_obra_semanas_montaje`, `v_obra_flete_estandar`. Guardar explícitamente contra división por cero
  (PostgreSQL lanza error; no replicar el hack `* 1.0` de SQLite).

---

## Multiusuario, tiempo real y versiones (cross-cutting)

Tres problemas separados; no acoplarlos. El cálculo autoritativo en PHP (derivados no persistidos) hace el versionado barato: una versión solo captura **inputs**, restaurar = copiar inputs + recalcular.

### Lock pesimista estricto (concurrencia)
- **Unidad de lock:** la **tarjeta** y la **generadora** (no la celda/fila). Editar dos la misma tarjeta a la vez es raro → un guardia basta, no fusión.
- **Estricto:** mientras A tiene el lock, los demás solo pueden abrir en **solo-lectura**; no hay "tomar el control". Solo `admin-cotiz` puede forzar la liberación.
- **Campos** (en la tarjeta/generadora o tabla `cotiz_*_locks`): `locked_by` (FK usuario UUID), `locked_at`, `lock_heartbeat_at`.
- **Heartbeat + TTL:** el cliente refresca `lock_heartbeat_at` cada ~30 s; si pasa > N min sin latido, el lock se considera muerto y se puede tomar. Liberación explícita al guardar/salir.
- **UI:** badge "🔒 Editando: {usuario} — hace X" en la lista de tarjetas; al abrir una bloqueada → "La está editando {usuario}. ¿Abrir en solo-lectura?".
- **No depende de WebSocket:** funciona desde el MVP con polling; Reverb solo lo hace instantáneo.

### Tiempo real
- Dentro de la tarjeta NO hay tiempo real (es mono-usuario por el lock).
- A nivel **obra/resumen**: al guardar una tarjeta se difunde el cambio → la lista y el resumen se refrescan en los demás.
- **MVP:** polling de Inertia v2. **Después:** Laravel Reverb (canal de presencia por obra: quién está y en qué tarjeta). Migrar no rehace lock ni snapshot. Ojo nginx prod (filtro de métodos `pb-svr`) para el upgrade WebSocket cuando toque Reverb.

### Versiones (modelo B por etapas)
- **Etapa B1 — Snapshots nombrados:** tabla `cotiz_obra_versiones` (id, obra, nombre, nota, creado_por, creado_at) + copia inmutable del árbol de **inputs** de la obra (generadoras, registros, tarjetas y sus subrecursos, overrides, secciones de montaje, fletes, coeficientes de resumen). Restaurar = copiar inputs de vuelta + recalcular. Comparar = diff de inputs.
  - **Restaurar = ramificar** (crear versión nueva a partir de una previa), no sobrescribir destructivamente la actual. (confirmar al implementar)
- **Etapa B2 — Bitácora append-only:** log de cada guardado (quién/cuándo/entidad/old→new) para auditoría multiusuario; doble uso como feed de tiempo real. Granularidad por entidad guardada (no por campo) salvo que se pida más detalle.

> Ubicación en fases: el **lock** se implementa junto a Generadoras (Fase 1) y Tarjetas (Fase 3); **versiones B1** en una fase propia tras Resumen (Fase 5.5); **B2 + Reverb** en Pulido (Fase 6).

---

## Fase 0 — Cimientos + catálogos globales

### Backend

#### Dependencias
- [x] `composer require symfony/expression-language` (v8.1, constraint `^8.1`)

#### Servicios
- [x] `App\Services\Cotiz\FormulaEvaluator` — envuelve ExpressionLanguage; registra `roundup`/`ceil`/`floor`/`abs`/`round`;
      métodos `evaluar(string $formula, array $vars): ?float` (null si falla) y `validar(string): ?string`. **14 tests verdes.**
- [x] `App\Services\Cotiz\FactorResolver` — resolvedor DAG multi-pasada (5 pasadas), port de `evaluarFactores`. **9 tests verdes.**

#### Enums (`app/Enums/Cotiz/`)
- [x] `TipoCorte` (TIRAS, RAZ, KG, CNX)
- [x] `ResumenBloque` (MO_FAB, MO_MONTAJE, EXTRAS, TOTALES)
- [x] `ResumenTipoFormula` (materiales, por_kg, por_m2_pintura, mo_fab_subgrupo, flete_kg_prorrateado, viatico_m2_prorrateado, subtotal, margen, total)
- [x] `GrupoFlete` (VIATICOS, SUPERV_MONTAJE, ENERGIA, VARIOS, FLETES, GRUAS, PLATAFORMAS, LABORATORIO, TOPOGRAFIA)
- [x] `TipoPintura` (auto, no_pinta, placa, tira, hss, ipr)

#### Migraciones (catálogos globales)
- [x] `cotiz_unidades`, `cotiz_centros_costos`, `cotiz_mermas`, `cotiz_pintura_formulas`, `cotiz_categorias_tarjeta`
- [x] `cotiz_insumos` (FK a unidad/centro_costo/categoria_tarjeta; `peso_lineal`, `peso_default`, `codigo_stumis`)
- [x] `cotiz_kilos_reales_categorias`, `cotiz_factores` (formula nullable), `cotiz_cuadrillas`
- [x] `cotiz_personal_categorias`, `cotiz_fases_montaje`, `cotiz_fletes_viaticos_catalogo`
- [x] `cotiz_resumen_filas`, `cotiz_resumen_bloque_colores`

#### Modelos + Factories
- [x] Un modelo por tabla en `app/Models/Cotiz/` con `$table`, `casts()`, relaciones tipadas.
- [x] Factory por modelo en `database/factories/Cotiz/`.

#### Seeders (`database/seeders/` → `CotizCatalogosSeeder` orquestador)
- [x] Portar `seed.sql` + `*-seed.sql` de prepsim vía replay en SQLite efímero: unidades, centros de costo, 3 mermas,
      5 fórmulas de pintura, 8 categorías de tarjeta, **248 insumos** (246 CSV + 2 del seed de factores), **19 factores**,
      cuadrillas, personal, fases, fletes/viáticos, resumen_filas. Idempotente. `categoria_tarjeta_id` pre-resuelto (0 NULL).

#### Form Requests + Controladores + Rutas
- [x] CRUD `Admin\Cotiz\` para los 12 catálogos (Insumos, Mermas, Factores, CentrosCosto, CategoriasTarjeta,
      PinturaFormulas, KilosRealesCategorias, Cuadrillas, Personal, FasesMontaje, FletesViaticosCatalogo, ResumenFilas). 24 Form Requests.
- [x] Grupo de rutas `Route::prefix('cotiz')->name('cotiz.')` en `routes/admin.php` con `->parameters()` en español (84 rutas). Test de contrato Inertia verde.

#### Permisos
- [x] Bloque `cotiz.*` (48 permisos de catálogo) y roles `usuario-cotiz` (solo `.ver`) y `admin-cotiz` (todo) en `RolesAndPermissionsSeeder` (corte fino en Fase 6).

### Frontend
- [x] `npm i ag-grid-community ag-grid-react` (v35.3.1, Community/MIT). Theming API (sin CSS imports).
- [x] Componente base `components/cotiz/editable-grid.tsx` (wrapper AG-Grid, sin Enterprise; tema claro/oscuro vía `useAppearance`).
- [x] Tipos en `resources/js/types/models.ts` (Cotiz*).
- [x] Páginas `pages/admin/cotiz/{catalogo}/index|create|edit.tsx` para los 12 catálogos. **Decisión: AG-Grid editable inline en TODOS** (no DataTable) — listar/editar en el grid (clic en celda → PUT) + create/edit como apoyo. index sirve la colección completa + catálogos FK/enum.
- [x] Sección "Cotización" en `app-drawer-layout.tsx` (Catálogos con 12 items gated por `cotiz.{recurso}.ver`).

### Verificación
- [x] `php artisan migrate` · seeders · `php artisan test --filter=Cotiz` (47 verdes) · `vendor/bin/pint` · `npm run build` (OK).
- [x] Tests del `FormulaEvaluator` (incluye `roundup`, división por cero → null, vars faltantes) y `FactorResolver` (DAG). **Fase 0 COMPLETA.**

---

## Fase 1 — Obras + Generadoras

### Backend
- [x] Migraciones `cotiz_obras` (op, factor_contratista 1.15, num_grupos), `cotiz_generadoras` (+ lock), `cotiz_generadora_registros`.
- [x] Modelos con accessors `tMlM2` y `kilosReales` (derivados en PHP, appended). `merma_id`, `validado` por registro.
- [x] `ObraController` (resource) + `GeneradoraController` (index por obra, edit con registros+kg_con_merma+lock, reorder) + `GeneradoraRegistroController` (store/update/destroy/validar).
- [x] `App\Services\Cotiz\MermaCalculator` (port de `evaluarMerma`/`kilosConMerma`; vars null→0, error→0).
- [x] **Lock estricto**: se **reusó el trait `HasEditLock`** del mono (`locked_by`/`locked_at`, TTL 15 min vía `config('costos.lock_ttl_minutes')`, `locked_at` hace de heartbeat) + `EditLockController` cotiz (`{type}=generadora`, 423 si ajeno, override forzado para `admin-cotiz`/`super-admin`). No se creó `LockManager` nuevo.
- [x] Rutas + permisos `cotiz.obras.*`, `cotiz.generadoras.*` (asignados a `usuario-cotiz` y `admin-cotiz`).

### Frontend
- [x] `ObrasIndex/Create/Edit` (DataTable + forms), `GeneradorasIndex` (cards por obra con indicador de lock), `GeneradoraEdit` (grid AG-Grid editable inline: insumo, dims, merma, validado + columnas read-only T ML/M²/kg reales/kg c-merma + totales).
- [x] Hook `use-cotiz-edit-lock.ts` (toma/heartbeat 5min/libera; solo-lectura si lo tiene otro). Navegación obra→generadoras como equivalente al `activeObra` de prepsim.

### Verificación
- [x] Tests (90 verdes en total `--filter=Cotiz`): accessors de kg por registro, aplicación de merma, validado por registro, lock estricto + override admin. **Fase 1 COMPLETA.**

---

## Fase 2 — Overrides de catálogo por obra ✅ COMPLETA

### Backend
- [x] Migraciones `cotiz_obra_insumo_override`, `cotiz_obra_factor_override` (campos nullable, UNIQUE(obra,insumo|factor)). `categoria_id` del legacy NO portado (referenciaba `insumo_categorias`).
- [x] Modelos `ObraInsumoOverride`/`ObraFactorOverride` con `estaVacio()` + relaciones; relaciones `insumoOverrides`/`factorOverrides` en `Obra`.
- [x] `App\Services\Cotiz\OverrideResolver` — `resolverInsumo`/`precioInsumo`/`resolverFactor` con prioridad
      `tarjeta > obra > global` (port de las cadenas `COALESCE` de `tarjetaTotales.ts`; el nivel tarjeta entra como param opcional para Fase 3). `obra_insumo_precios` legacy NO portado.
- [x] `CatalogoObraController` (index con insumos+factores global/override; updateInsumo/Factor con upsert+cleanup-on-empty; destroyInsumo/Factor para reset de fila). 2 Form Requests. Rutas `obras/{obra}/catalogo*`.

### Frontend
- [x] `catalogo-obra/index.tsx` — una página con tabs Insumos/Factores (AG-Grid, vista diff Global vs Proyecto, edición inline clic→PUT, badge de diffs, toggle "solo cambios", reset de fila). Tipos en models.ts. Enlace "Overrides" en `obras/index`.

### Verificación
- [x] `OverrideResolverTest` (prioridad + `estaVacio`) y `CatalogoObraTest` (upsert, limpieza, no-crear-vacío, sin duplicar, destroy). **97 tests `--filter=Cotiz` verdes, pint pass, build OK. Fase 2 COMPLETA.**

---

## Fase 3 — Tarjetas (núcleo del cálculo) ← fase más pesada

> **Troceada en 3a→3b→3c** (decisión 2026-06-11). 3a esquema+CRUD+vínculo; 3b motor de cálculo (namespace plano); 3c M046 + grilla densa.

### Fase 3a — Esquema + CRUD + vínculo con generadoras ✅ COMPLETA (2026-06-11)
- [x] 8 migraciones: `cotiz_tarjetas` (+cache `importe_materiales`/`kilos_reales` +lock), `cotiz_tarjeta_generadoras` (N:N UNIQUE), `cotiz_tarjeta_estructuras`, `cotiz_tarjeta_registros` (gen|manual, `tipo_pintura`, UNIQUE en `generadora_registro_id`; invariante gen|manual validada en app, no CHECK por paridad SQLite/PG), `cotiz_tarjeta_factores` (`formula_override`, UNIQUE), `cotiz_tarjeta_insumo_precio` (UNIQUE), `cotiz_tarjeta_categorias_kilos` (`porcentual`), `cotiz_tarjeta_kilos_reales`. Legacy `solo_exterior` NO portado. FKs normalizadas (`tarjeta_id`, `categoria_id`, `estructura_id`).
- [x] 8 modelos + factories. `Tarjeta` con `HasEditLock` y relaciones (generadoras BelongsToMany, registros/factores/estructuras/categoriasKilos/kilosReales/insumoPrecios HasMany).
- [x] `TarjetaController` (index por obra, store +vincular opcional, edit shell, update, destroy, vincularGeneradora, desvincularGeneradora). Import de registros = los de la generadora con `material_origen_id` y no ya importados (UNIQUE lo garantiza). 3 Form Requests.
- [x] Lock `tarjeta` añadido a `EditLockController` + hook `use-cotiz-edit-lock` (`'generadora'|'tarjeta'`). Rutas + permisos `cotiz.tarjetas.*`.
- [x] Front: `tarjetas/index` (tabla + alta con generadora inicial opcional), `tarjetas/edit` (shell: cabecera, lock banner, vincular/desvincular generadoras, placeholder de grilla 3c). Enlace "Ver tarjetas" en obras/index.
- [x] `TarjetaTest` (CRUD, import al vincular, no-doble-import, desvincular, lock). **109 tests `--filter=Cotiz` verdes, pint, build OK.**

### Fase 3b — Motor de cálculo (namespace plano) ✅ COMPLETA (2026-06-11)
- [x] `PinturaCalculator` (port de `pintura.ts`): `inferirTipo` (heurística galv/no-estructural/hss/ipr/placa), `extraerLadoPulgadas`/`extraerPeralteMetros` (parseo de descripción), `area` (clave→fórmula, vars kg/peso_lineal/lado/peralte/patin; error→0).
- [x] `KilosRealesCalculator` (port de `calcularKgPorTipoCorte`): filas fijas suman directo; porcentuales aportan % × Σ fijas. `total()`.
- [x] `TarjetaCalculator` (port de `tarjetaTotales.ts`): resuelve cada registro (insumo efectivo, cantidad con merma vía `cantidadConMerma`, P.U. `tarjeta>obra>global` con `OverrideResolver`+`tarjeta_insumo_precio`, categoría, unidad); `importe_<slug>` (slug con `Normalizer` NFD), `kg_fab`, `area_pintura`, kg por tipo de corte; factores vía `FactorResolver` **SIN expandir** (namespace plano: kg_fab/area_pintura/kg_<corte>/importe_<slug> + refs entre factores); `total_importe`/`kg_reales_total`. `refrescarCache()` escribe M039 solo si difiere (`saveQuietly`).
- [x] Controlador: index/edit refrescan cache y exponen totales; panel de totales en `tarjetas/edit`.
- [x] Tests: `PinturaCalculatorTest`+`KilosRealesCalculatorTest` (unit) y `TarjetaCalculatorTest` (e2e: registro manual, prioridad P.U. tarjeta>obra>global, factor con fórmula, factor manual, DAG entre factores, importe persistido, kg reales, refrescarCache, slug). **134 tests `--filter=Cotiz` verdes, pint, build OK.** El hook `$expandir` (M046) queda para 3c.

### Fase 3c-1 — Direccionamiento semántico M046 (backend) ✅ COMPLETA (2026-06-11)
- [x] Subsistema `App\Services\Cotiz\Variables\`: `Direccion` (DTO), `Catalogo` (dominios/columnas/filtros; `seccion`/`resumen`/`cuadrilla.importe` marcados `resoluble:false`), `Parser` (regex anclado a dominios, `parse`/`extraer` con offsets de byte), `Expandir` (direcciones→`__vN`), `ContextoEval` (memo/pila/factores), `Registry` (resolución raíz con memo + detección de ciclos + `expandirConContexto`), `Validar` (dirección + sintaxis), interfaz `ResolvedorDominio`.
- [x] Resolvedores: `ResolvedorTarjeta` (self vía precargados: importe/importe[cc]/kg/area/kg_real/kg_real[corte]/factor[cod]; todas-las-tarjetas vía cache; instancia concreta vía closure), `ResolvedorGeneradora` (port DB: kg con merma + peso_porcentual, kg_real, filtro marca), `ResolvedorCuadrilla` (rendimiento). `seccion` diferido a Fase 4.
- [x] Conectado al `TarjetaCalculator`: `construirExpandir()` arma el Registry+contexto self y lo pasa al `FactorResolver` como `$expandir`; `datosTarjeta()` para instancias concretas. **Todas las fórmulas sembradas usan solo direcciones self de tarjeta** — ese camino está cubierto end-to-end.
- [x] Tests: `Variables/ParserTest`, `Variables/ValidarTest` (unit) y `TarjetaVariablesTest` (e2e: total.tarjeta.kg/area/kg_real[corte]/importe[cc], tarjeta.factor[cod=X], factor inexistente→0). **159 tests `--filter=Cotiz` verdes, pint OK.**

### Fase 3c-2 — Grilla densa + subrecursos ✅ COMPLETA (2026-06-11)
- [x] `TarjetaDetalleController`: subrecursos de la grilla — registros manuales (store/update/destroy), P.U. por tarjeta (set/clear `tarjeta_insumo_precio`), factores (vincular/update `formula_override`/`cantidad_manual`/`importe`/`validado`/desvincular), estructuras (CRUD), kilos reales (categoría store/update/destroy + celda upsert), y `validar-formula` (usa `Validar`). 2 Form Requests.
- [x] `TarjetaController::edit` enriquecido: expone `registros`/`factores` resueltos (del `TarjetaCalculator`), `estructuras`, `categoriasKilos`, `celdas`, `preciosOverride`, `totales` y catálogos (insumos, factores, krCategorias, tiposPintura).
- [x] Frontend `tarjetas/edit.tsx` reescrito: grilla de registros (AG-Grid: cantidad [manual], P.U. con override marcado, importe, tipo de pintura [select], validado, eliminar; orden por categoría) + alta manual; grilla de factores (fórmula editable con validación en vivo vía endpoint, validado, quitar) + vincular; matriz de kilos reales (estructuras=columnas, categorías=filas con % y celdas editables) + altas; secciones de totales y generadoras. `EditableGrid` extendido para reportar la columna editada (P.U. → endpoint distinto). M045: el ✓ de un registro de generadora muta el registro origen.
- [x] Lock estricto en tarjetas ya integrado en 3a; badge/solo-lectura/heartbeat funcionando.
- [x] `TarjetaDetalleTest` (14 tests: registros, P.U. set/clear, factores, estructuras+celdas KR, validación de fórmula). **173 tests `--filter=Cotiz` verdes, pint, build OK. FASE 3 COMPLETA.**

> Nota: 1 fila por registro (sin el merge por insumo ni la back-propagación de importe del `TarjetaEditPage` original de prepsim — refinamiento UX diferible). Caso Tekpark como verificación de referencia: diferido a Fase 6 (plantilla).

---

## Fase 4 — Análisis MO/Montaje + Fletes/Viáticos ✅ COMPLETA (2026-06-12)

### Backend
- [x] 6 migraciones: `cotiz_secciones_montaje`, `cotiz_seccion_personal` (UNIQUE sección+fase+categoría),
      `cotiz_seccion_fase_rendimiento`, `cotiz_obra_cuadrilla_global` (UNIQUE obra+categoría),
      `cotiz_obra_flete_estandar` (UNIQUE obra+tarjeta+método), `cotiz_obra_fletes_viaticos`. FKs normalizadas
      (`seccion_id`, `fase_id`, `categoria_id`). Enum `MetodoFleteEstandar` (por_kg/por_piezas). 6 modelos + factories + relaciones en `Obra`.
- [x] **Servicios de derivación** (reemplazan las 8 vistas; toda la aritmética en PHP, guardas explícitas contra ÷0):
      `MontajeDerivations` (días/nómina/importe por fase, importe+semanas por sección, semanas requeridas y mo_montaje de obra, agregados por fase),
      `CuadrillaGlobalDerivations` (×num_grupos, personas para viáticos sin CABO, +15% contratista literal),
      `FleteEstandarDerivations` (volumen por_kg vía `KilosRealesCalculator` = fijas×(1+Σpct), por_piezas = Σ cantidad de registros, override; camiones ROUNDUP; agregado por grupo-slug),
      `FletesViaticosSubtotales` (Σ cantidad×p_unit por grupo).
- [x] `FletesViaticosCalculator` (port de `fletesViaticos.ts`): `construirContexto` (grupos/personas/semanas/meses/días/kg_obra/mo_montaje/camiones_<GRUPO>/dias_fase_<COD>), `evaluarItems` (DAG 5 pasadas, cross-refs `cantidad_/p_unit_/importe_<clave>`, irresolubles conservan valor), `recalcular` (persiste cantidad/p_unit, patrón cache).
- [x] `AnalisisMoController` (index con las 4 secciones de datos; recalcula fletes al entrar), `SeccionMontajeController` (secciones + detalle: matriz personal upsert, rendimientos CRUD), `CuadrillaGlobalController` (celda + num_grupos), `FleteViaticoObraController` (CRUD + importar plantilla + ƒ del catálogo + recalcular, valida sintaxis de fórmula), `FleteEstandarController`. 11 Form Requests. Rutas por-obra bajo `obras.fletes-*` (evita choque con catálogo `fletes-viaticos.*`). Permiso `cotiz.analisis-mo.*` a `usuario-cotiz`/`admin-cotiz`.

### Frontend
- [x] `analisis-mo/index.tsx` (4 pestañas AG-Grid: zonas, montaje global [cuadrilla + matriz consolidada], fletes/viáticos [grid + fórmulas con validación + variables + subtotales + importar/recalcular], fletes estándar [grid + camiones]).
- [x] `analisis-mo/seccion-edit.tsx` (m²/nombre inline, matriz personal × fase con footer nómina/días/semanas/importe, rendimientos por fase). Tipos en models.ts. Enlace "Análisis MO" en `obras/index`.

### Verificación
- [x] `MontajeDerivationsTest`, `FletesViaticosCalculatorTest`, `AnalisisMoTest` (28 nuevos): días/nómina/importe vs fórmula de la vista, ×num_grupos sin cabo, volumen por_kg/por_piezas/override + camiones ROUNDUP, subtotales por grupo, DAG de fórmulas, recálculo persistido, CRUD de los controladores. **201 tests `--filter=Cotiz` verdes (627 assertions), pint pass, build OK. FASE 4 COMPLETA.**

---

## Fase 5 — Resumen + Carátula (auto-derivadas) ✅ COMPLETA (2026-06-12)

### Backend
- [x] 4 migraciones: `cotiz_resumen_columnas` (sueldo_mo_pza), `cotiz_resumen_columna_tarjetas` (UNIQUE tarjeta → 1:1),
      `cotiz_obra_resumen_coeficientes` (UNIQUE obra+fila), `cotiz_obra_resumen_celda_override` (UNIQUE obra+fila+columna). FKs normalizadas (`columna_id`, `fila_id`). 4 modelos + factories + relaciones en `Obra`.
- [x] `ResumenCalculator` (port de `resumen.ts`): `coefEfectivo` (celda→fila→default), `calcularMatriz` (pasada 1 bases + pasadas 2-4 subtotal/margen/total acumulando costo directo), `importeBase` (los 9 tipos de fórmula), y `calcular(Obra)` que ensambla columnas (kg/m²/importe vía `TarjetaCalculator`), extras (subtotales de Fletes/Viáticos mapeados por `EXPL_MO_F<row>`→grupo) y overrides.
- [x] `ResumenColumnaSync` (servicio compartido: 1 columna por tarjeta, borra huérfanas, renombra). `ResumenController` (index sincroniza+calcula; updateCelda/updateCoeficiente upsert+delete-on-empty; updateColumnaSueldo). `CaratulaController` (resumen ejecutivo read-only). 3 Form Requests. Rutas + permiso `cotiz.resumen.*`.
- [x] Export PDF de la carátula vía `barryvdh/laravel-dompdf` (ya estaba en el mono — sin dependencia nueva): `CaratulaController::pdf` + vista `pdf/cotiz/caratula.blade.php` + botón "Descargar PDF". Export a **XLSX queda diferido** (no hay necesidad concreta aún).

### Frontend
- [x] `resumen/index.tsx` (AgGridReact directo: grupos por nave con 3 subcolumnas Tarifa/$kg/Importe, edición inline por celda → override, footer TOTALES pinned, colores por bloque, filas bloqueadas, panel de sueldo M.O. FAB por columna).
- [x] `caratula/index.tsx` (tabla read-only: columnas con kg/m²/importe + total de venta). Enlaces "Resumen" y "Carátula" en `obras/index`. Tipos reusan `CotizResumenBloque`/`CotizResumenTipoFormula`.

### Verificación
- [x] `ResumenCalculatorTest` (matriz vs caso conocido: materiales/mo_fab/subtotal/flete prorrateado/margen/total, override de celda, prorrateo por kg entre columnas) y `ResumenTest` (sync 1-columna-por-tarjeta, borrado de huérfanas, overrides celda/fila/sueldo, carátula). **210 tests `--filter=Cotiz` verdes (686 assertions), pint pass, build OK. FASE 5 COMPLETA (exports diferidos).**

---

## Fase 5.5 — Versiones (snapshots de inputs, etapa B1) ✅ COMPLETA (2026-06-12)

> **Modelo LINEAL (decisión 2026-06-12):** el equipo de ingeniería NO maneja sistemas ramificados. Solo
> **snapshots continuos** en una línea de tiempo. Restaurar **sobrescribe** los inputs actuales de la obra
> (NO crea rama); antes de sobrescribir se captura el estado actual como snapshot automático ("Antes de
> restaurar …") para no perder nada. Historial append-only, sin árbol, sin `parent_version_id`.
> Ver memoria `project_cotiz_versiones_lineal`.

### Backend
- [x] Migración `cotiz_obra_versiones` (obra, nombre, nota, `auto` bool, `creado_por` UUID→usuarios, `snapshot` JSON, timestamps). Sin parentesco. Modelo + factory + relación `Obra::versiones()`.
- [x] `App\Services\Cotiz\VersionManager`: `capturar(obra)` (serializa el árbol de inputs de ~20 tablas vía `getAttributes`, excluye cache/locks/timestamps), `crear`, `comparar(a,b)` (diff por grupo: conteo + `cambio` ignorando IDs/orden + cambios de campos de obra), `restaurar(version)` → auto-snapshot + borra inputs (cascada) + **reinserta remapeando IDs** (`forceFill`, mapas `viejo→nuevo` por grupo: generadora_registro_id, tarjeta_id, estructura_id, seccion_id, columna_id…) + recálculo al leer. Lineal, sin ramas.
- [x] `VersionController` (index con diff opcional `?a=&b=`, store, restaurar con guardia de obra, destroy). Form Request. Rutas + permiso `cotiz.versiones.*`.

### Frontend
- [x] `versiones/index.tsx`: crear versión (nombre+nota), comparar dos (diff de obra + tabla por grupo), historial cronológico con restaurar (confirm "se sobrescribe; se guarda respaldo automático") y eliminar. Enlace "Versiones" en `obras/index`.

### Verificación
- [x] `VersionManagerTest` (captura solo inputs sin cache/locks; restaurar remapea FKs y **reproduce el importe** vía recálculo sobre la tarjeta restaurada; respaldo auto + sin columna de parentesco; diff por grupo) y `VersionControllerTest` (lista, store con autor, restaurar deja respaldo, diff por query, guardia cross-obra). **220 tests `--filter=Cotiz` verdes (749 assertions), pint pass, build OK. FASE 5.5 COMPLETA.**

---

## Fase 6 — Pulido

- [ ] Plantilla Tekpark (opcional): port de `tekpark-template.ts` como seeder/acción para sembrar obra demo.
- [ ] **Definir el corte de permisos entre `usuario-cotiz` y `admin-cotiz`** (permiso por permiso) y asignarlos en `RolesAndPermissionsSeeder`. El usuario especificará qué puede cada rol.
- [ ] **Versiones etapa B2 — bitácora append-only** (quién/cuándo/entidad/old→new) para auditoría multiusuario; doble uso como feed de tiempo real.
- [ ] **Tiempo real con Laravel Reverb**: canal de presencia por obra (quién está y en qué tarjeta), push instantáneo de lock y de cambios al resumen; reemplaza el polling del MVP. Ajustar nginx prod (`pb-svr`) para el upgrade WebSocket.
- [ ] Revisión de permisos por acción, navegación, breadcrumbs.
- [ ] `vendor/bin/pint` global, `npm run build`, suite `--filter=Cotiz` en verde.

---

## Riesgos / notas
- **Paridad SQLite/PostgreSQL** (memoria `project_sqlite_pgsql_divergence`): toda la aritmética en PHP; cuidar tipos `decimal`, NULL y división por cero. Los morphs/UUID no aplican aquí (módulo usa IDs propios).
- **Direccionamiento semántico de variables (M046)** es el subsistema más intrincado del motor (`lib/variables/`, ~474 líneas) — portarlo con sus propios tests antes de la grilla de tarjeta.
- **AG-Grid + DaisyUI**: cuadrar tema visual; no usar features Enterprise (agrupación se simula ordenando, igual que prepsim).
- **Validación de fórmulas de usuario**: el `FormulaEvaluator` no debe permitir acceso a objetos/métodos — solo variables escalares y funciones registradas.
- **`%` y `^`**: en Symfony `%`=módulo (igual que expr-eval) y `^`=XOR (expr-eval usaba `^` para potencia). Auditar seeds y normalizar `^`→`**` si aparece.
