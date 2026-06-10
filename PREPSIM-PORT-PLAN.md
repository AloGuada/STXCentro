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
- [ ] Migraciones `cotiz_obras` (op, factor_contratista, num_grupos), `cotiz_generadoras`, `cotiz_generadora_registros`.
- [ ] Modelos con accessors `tMlM2` y `kilosReales` (derivados en PHP). `merma_id`, `validado` por registro.
- [ ] `GeneradoraController` (+ registros): CRUD, reordenar, validar por registro.
- [ ] `App\Services\Cotiz\MermaCalculator` (port de `evaluarMerma`).
- [ ] **Lock estricto en generadoras**: campos `locked_by`/`locked_at`/`lock_heartbeat_at`, endpoints `lock`/`heartbeat`/`unlock`, `App\Services\Cotiz\LockManager` (tomar/refrescar/liberar/forzar, TTL).
- [ ] Rutas + permisos `cotiz.obras.*`, `cotiz.generadoras.*`.

### Frontend
- [ ] `ObrasIndex/Create/Edit`, `GeneradorasIndex`, `GeneradoraEdit` (grid editable AG-Grid: insumo, dims, merma, kilos).
- [ ] Selector de obra activa (equivalente a `activeObra` de prepsim) — en estado de página/sesión.

### Verificación
- [ ] Tests: cálculo de kg por registro, aplicación de merma, validado por registro.

---

## Fase 2 — Overrides de catálogo por obra

### Backend
- [ ] Migraciones `cotiz_obra_insumo_override`, `cotiz_obra_factor_override` (todos los campos nullable, UNIQUE(obra,insumo|factor)).
- [ ] `App\Services\Cotiz\OverrideResolver` — resuelve P.U./fórmula efectivos con prioridad
      `tarjeta > obra > global` (port de las cadenas `COALESCE` de `tarjetaTotales.ts`). Helper para limpiar fila cuando todo queda NULL.
- [ ] `CatalogoObraController` (tabs Insumos/Factores).

### Frontend
- [ ] `CatalogoObraInsumos`, `CatalogoObraFactores` (vista diff Global vs Proyecto; lápiz de override).

### Verificación
- [ ] Tests de resolución de prioridad y limpieza de overrides nulos.

---

## Fase 3 — Tarjetas (núcleo del cálculo) ← fase más pesada

### Backend
- [ ] Migraciones: `cotiz_tarjetas` (cache `importe_materiales`, `kilos_reales`), `cotiz_tarjeta_generadoras` (N:N),
      `cotiz_tarjeta_registros` (gen | manual + CHECK), `cotiz_tarjeta_factores` (formula_override),
      `cotiz_tarjeta_insumo_precio`, `cotiz_tarjeta_estructuras`, `cotiz_tarjeta_categorias_kilos` (porcentual),
      `cotiz_tarjeta_kilos_reales`.
- [ ] **Port completo del motor** a `App\Services\Cotiz\`:
  - `TarjetaCalculator` (port de `tarjetaTotales.ts`): registros (gen+manual), cantidad con merma, importe por categoría (`importe_<slug>`), kg_fab, area_pintura, totales.
  - `PinturaCalculator` (port de `pintura.ts`: inferir tipo, parseo lado/peralte/patín, fórmulas por clave).
  - `KilosRealesCalculator` (port de `calcularKgPorTipoCorte`, incluye filas porcentuales).
  - `VariableResolver` (port de `lib/variables/`: direccionamiento semántico M046 — catálogo, parser, registry, dominios, expandir, validar).
- [ ] `TarjetaController` (+ subrecursos: vincular/desvincular generadora, registros manuales, factores, estructuras, análisis kg reales, validar).
- [ ] Refrescar cache (`importe_materiales`, `kilos_reales`) al recalcular (equivalente M039).
- [ ] **Lock estricto en tarjetas** (reutiliza `LockManager`): badge "editando por X", apertura en solo-lectura si está bloqueada, override solo `admin-cotiz`, heartbeat + TTL.

### Frontend
- [ ] `TarjetasIndex`, `TarjetaEdit` (grid AG-Grid denso: agrupación por categoría, P.U. con override, fórmula de factor, importe, %, pintura).
- [ ] Modal "Análisis de kilos reales" (matriz categorías × estructuras, filas %).

### Verificación
- [ ] Tests del cálculo end-to-end de una tarjeta contra un caso conocido (idealmente derivado del proyecto Tekpark) — comparar importe/kg con valores esperados.

---

## Fase 4 — Análisis MO/Montaje + Fletes/Viáticos

### Backend
- [ ] Migraciones: `cotiz_secciones_montaje`, `cotiz_seccion_personal`, `cotiz_seccion_fase_rendimiento`,
      `cotiz_obra_cuadrilla_global`, `cotiz_obra_flete_estandar`, `cotiz_obra_fletes_viaticos`.
- [ ] **Servicios de derivación** (reemplazan las 8 vistas; guardar contra división por cero):
      `MontajeDerivations` (días, nómina, importe por sección/fase, importe por sección, semanas requeridas),
      `CuadrillaGlobalDerivations`, `FleteEstandarDerivations`, `FletesViaticosSubtotales`.
- [ ] `FletesViaticosCalculator` (port de `fletesViaticos.ts`: fórmulas `formula_cantidad`/`formula_p_unit`, cross-refs `importe_<clave>`).
- [ ] `AnalisisMoController`, `SeccionMontajeController`, controladores de fletes/cuadrilla por obra.

### Frontend
- [ ] `AnalisisMo` (tabs: cuadrillas globales, por sección, personal montaje, fletes/viáticos) — AG-Grid.
- [ ] `SeccionMontajeEdit` (m², personal, fases con rendimientos).

### Verificación
- [ ] Tests de cada derivación vs el resultado de la vista original (mismos inputs).

---

## Fase 5 — Resumen + Carátula (auto-derivadas)

### Backend
- [ ] Migraciones: `cotiz_resumen_columnas` (sync con tarjetas), `cotiz_resumen_columna_tarjetas` (1:1),
      `cotiz_obra_resumen_coeficientes`, `cotiz_obra_resumen_celda_override`.
- [ ] `ResumenCalculator` (port de `resumen.ts`: coef efectivo celda→fila→default; pasadas para subtotal/margen/total).
- [ ] `ResumenController` (sincroniza columnas↔tarjetas), `CaratulaController`.
- [ ] Exports PDF/XLSX — **decisión de dependencia diferida** (p.ej. `barryvdh/laravel-dompdf`, `maatwebsite/excel`), pedir aprobación al llegar aquí.

### Frontend
- [ ] `ResumenProyecto` (matriz filas × columnas, colores por bloque, candado de fila), `CaratulaCotizacion`.

### Verificación
- [ ] Tests del resumen (subtotal/margen/total) contra caso conocido.

---

## Fase 5.5 — Versiones (snapshots de inputs, etapa B1)

### Backend
- [ ] Migración `cotiz_obra_versiones` (obra, nombre, nota, creado_por, creado_at) + almacenamiento del árbol de inputs (filas tagueadas con `version_id` **o** blob JSON normalizado — decidir al implementar).
- [ ] `App\Services\Cotiz\VersionManager`: `crear(obra, nombre)` (snapshot de inputs), `comparar(v1, v2)` (diff de inputs), `restaurar(version)` → **ramifica** a versión nueva + recálculo.
- [ ] `VersionController` (listar, crear, ver, comparar, restaurar).

### Frontend
- [ ] Sección "Versiones" en la obra: lista de snapshots, crear versión nombrada, ver diff entre dos, restaurar (ramificar).

### Verificación
- [ ] Tests: snapshot captura solo inputs; restaurar reproduce importes/kg idénticos vía recálculo; diff detecta cambios.

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
