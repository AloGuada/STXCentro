<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.2
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Activates whenever referencing backend routes in frontend components. Use when importing from @/actions or @/routes, calling Laravel routes from TypeScript, or working with Wayfinder route functions.
- `pest-testing` — Tests applications using the Pest 3 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, architecture testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `inertia-react-development` — Develops Inertia.js v2 React client-side applications. Activates when creating React pages, forms, or navigation; using &lt;Link&gt;, &lt;Form&gt;, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

=== inertia-laravel/v2 rules ===

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scrolling (merging props + `WhenVisible`), lazy loading on scroll, polling, prefetching.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Wayfinder generates TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

- IMPORTANT: Activate `wayfinder-development` skill whenever referencing backend routes in frontend components.
- Invokable Controllers: `import StorePost from '@/actions/.../StorePostController'; StorePost()`.
- Parameter Binding: Detects route keys (`{post:slug}`) — `show({ slug: "my-post" })`.
- Query Merging: `show(1, { mergeQuery: { page: 2, sort: null } })` merges with current URL, `null` removes params.
- Inertia: Use `.form()` with `<Form>` component or `form.submit(store())` with useForm.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.
</laravel-boost-guidelines>

# Proyecto Integrador Mono

## Descripción del Proyecto
Sistema integrador empresarial que centraliza 8 módulos: autenticación con roles/permisos (Spatie), intranet corporativa, soporte TI (STI), producción (Prod), costos, cobranza (Cob), infraestructura (Infra) y portal de proveedores. Arquitectura de base de datos única con prefijos de tabla por módulo.

## Dependencias Requeridas
- `spatie/laravel-permission` - Sistema de roles y permisos

## Arquitectura de Base de Datos

### Estrategia
- **Una sola base de datos** con prefijos de tabla por módulo
- Prefijos: `intra_`, `sti_`, `prod_`, `costos_`, `cob_`, `infra_`, sin prefijo (core/auth/compartidas)

### Convenciones de Nomenclatura
- Tablas en español y snake_case: `sti_tickets`, `cob_estimaciones`
- Claves foráneas: `{tabla_singular}_id` → `tecnico_id`, `equipo_id`
- Tablas pivote: `{tabla1}_{tabla2}` en orden alfabético
- Polimórficas: `{nombre}able_id`, `{nombre}able_type`

### Estructura de Tablas por Módulo

#### Core/Auth (sin prefijo)
| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Usuarios del sistema (UUID como PK) |
| `roles` | Definición de roles (Spatie) |
| `permissions` | Permisos del sistema (Spatie) |
| `model_has_roles` | Pivote usuario-rol (Spatie) |
| `model_has_permissions` | Pivote usuario-permiso (Spatie) |
| `role_has_permissions` | Pivote rol-permiso (Spatie) |
| `departamentos` | Departamentos organizacionales |
| `obras` | Proyectos/obras de construcción |
| `conceptos` | Conceptos de obra (importables por CSV) |
| `clientes` | Clientes del sistema |
| `proveedores` | Proveedores del sistema |

#### Intranet (prefijo: `intra_`)
| Tabla | Descripción |
|-------|-------------|
| `intra_seccion_estatica` | Secciones de contenido estático |
| `intra_area` | Áreas jerárquicas (self-reference) |
| `intra_documentos` | Documentos por área |

#### STI - Soporte TI (prefijo: `sti_`)
| Tabla | Descripción |
|-------|-------------|
| `sti_equipos` | Inventario de equipos |
| `sti_tecnicos` | Técnicos de soporte |
| `sti_tickets` | Tickets de soporte |
| `sti_ticket_historial` | Auditoría de cambios de estado |
| `sti_ticket_comentarios` | Comentarios en tickets |
| `sti_mantenimientos` | Programación de mantenimientos |
| `sti_costos_mantenimientos` | Costos de mantenimientos |
| `sti_status` | Catálogo de estados |
| `sti_planes` | Planes de mantenimiento |
| `sti_checks` | Checks de un plan |
| `sti_check_ejecuciones` | Ejecución de checks en mantenimiento |
| `sti_items` | Inventario de items/activos |
| `sti_items_tipos` | Tipos de items |
| `sti_items_historial` | Historial de movimientos de items |
| `sti_grupos` | Grupos de equipos |
| `sti_asignacion_activos` | Asignación de activos a usuarios |

#### Prod - Producción (prefijo: `prod_`)
| Tabla | Descripción |
|-------|-------------|
| `prod_grupos_precio` | Grupos de precios por obra |
| `prod_grupo_precio_conceptos` | Pivote grupo-precio ↔ concepto |
| `prod_grupos_trabajo` | Grupos de trabajo |
| `prod_grupo_empleados` | Empleados asignados a grupo |
| `prod_registros` | Registros diarios de producción (dentro de un destajo por rango de fechas) |
| `prod_tipos` | Tipos de pago extra (catálogo) |
| `prod_pagos_extra` | Pagos extra (por destajo y grupo) |
| `prod_destajos` | Destajo semanal (máx. 52 por año; unique anio+semana) |
| `prod_liquidaciones` | Liquidaciones de destajo (por grupo, inmutables al cerrar) |
| `prod_liquidacion_detalle` | Detalle de liquidación |
| `prod_liquidacion_empleados` | Liquidación por empleado |

#### Costos (prefijo: `costos_`)
| Tabla | Descripción |
|-------|-------------|
| `costos_tipo_rubros` | Tipos de rubros (catálogo) |
| `costos_rubros` | Rubros de costos |
| `costos_obra_rubros` | Presupuesto: rubro asignado a obra |
| `costos_rubros_afectados` | Rubros afectados por afectación presupuestal |
| `costos_tipo_solicitud` | Tipos de solicitud (catálogo) |
| `costos_permisos` | Permisos de aprobación |
| `costos_aprobacion_departamento` | Pivote permiso ↔ departamento |
| `costos_solicitudes_pago` | Solicitudes de pago |
| `costos_solicitudes_pago_detalle` | Detalle de solicitud de pago |
| `costos_solicitud_archivos` | Archivos adjuntos de solicitud |
| `costos_aprobaciones_solicitud` | Flujo de aprobación digital |
| `costos_ordenes_compra` | Órdenes de compra |
| `costos_ordenes_compra_detalle` | Detalle de orden de compra |
| `costos_facturas` | Facturas de proveedor |
| `costos_entregas` | Entregas de material |
| `costos_entrega_detalle` | Detalle de entrega |
| `costos_pagos` | Pagos a proveedores |
| `costos_afectaciones_presupuestales` | Afectaciones presupuestales |
| `costos_afectaciones_detalle` | Detalle de afectación |
| `costos_afectaciones_historial` | Historial de afectaciones |
| `costos_documentos` | Documentos de costos |

#### Cob - Cobranza (prefijo: `cob_`)
| Tabla | Descripción |
|-------|-------------|
| `cob_tipos_retenciones` | Tipos de retenciones (catálogo) |
| `cob_contactos` | Contactos de clientes |
| `cob_partidas` | Partidas de obra |
| `cob_estimaciones` | Estimaciones de cobro |
| `cob_estimaciones_pagos` | Pagos de estimaciones |
| `cob_estimacion_estado_historial` | Historial de estados de estimación |
| `cob_documentos_estimacion` | Documentos de estimación |
| `cob_retenciones` | Retenciones aplicadas |
| `cob_anticipos` | Anticipos de obra |
| `cob_adendas` | Adendas de contrato |
| `cob_comparativos` | Comparativos de obra |
| `cob_deducciones` | Deducciones de obra |
| `cob_disputas` | Disputas de cobro |
| `cob_penalizaciones` | Penalizaciones de obra |
| `cob_eventos` | Eventos/bitácora de obra |
| `cob_configuracion_documentos` | Configuración de documentos requeridos |

#### Infra - Infraestructura (prefijo: `infra_`)
| Tabla | Descripción |
|-------|-------------|
| `infra_compresores` | Compresores de gas |
| `infra_bombas` | Bombas de agua |
| `infra_transformadores` | Transformadores eléctricos |
| `infra_tanques` | Tanques de almacenamiento |
| `infra_ptar` | Plantas de tratamiento de aguas residuales |
| `infra_turnos` | Turnos de operación |
| `infra_turnos_dia` | Días de turno (detalle) |

#### Compartidas (sin prefijo)
| Tabla | Descripción |
|-------|-------------|
| `media` | Archivos adjuntos (polimórfica) |
| `tags` | Etiquetas (polimórfica) |

## Spatie Permissions

### Configuración del Modelo Usuario
```php
// app/Models/Usuario.php - UUID con HasRoles
// app/Models/User.php - Alias de Usuario para Fortify (ver MEMORY.md sobre desajuste model_type)
```

### Convención de Nombres de Permisos
Formato: `{módulo}.{recurso}.{acción}`

```php
// Ejemplos:
'sti.tickets.ver'
'sti.tickets.crear'
'prod.registros.ver'
'costos.solicitudes.ver'
'cob.estimaciones.ver'
'infra.recorridos.ver'
'intra.documentos.subir'
```

### Roles del Sistema
```php
'super-admin'       // Acceso total
'admin-sti'         // Administrador de soporte TI
'tecnico-sti'       // Técnico de soporte
'admin-intranet'    // Administrador de intranet
'admin-prod'        // Administrador de producción
'admin-costos'      // Administrador de costos
'admin-cob'         // Administrador de cobranza
'admin-infra'       // Administrador de infraestructura
'empleado'          // Usuario básico
```

## Estructura de Carpetas por Módulo

```
app/
├── Models/
│   ├── Usuario.php, User.php, Departamento.php, Obra.php
│   ├── Concepto.php, Cliente.php, Proveedor.php
│   ├── Media.php, Tag.php
│   ├── Intra/        # SeccionEstatica, Area, Documento
│   ├── Sti/          # Equipo, Tecnico, Ticket, Mantenimiento, Plan, Check, Item, ...
│   ├── Prod/         # GrupoPrecio, GrupoTrabajo, Registro, Corte, Liquidacion, ...
│   ├── Costos/       # Rubro, SolicitudPago, OrdenCompra, Factura, Pago, Afectacion, ...
│   ├── Cob/          # Estimacion, Anticipo, Adenda, Partida, Disputa, ...
│   └── Infra/        # Compresor, Bomba, Transformador, Tanque, Ptar, Turno, TurnoDia
├── Http/Controllers/
│   ├── Admin/
│   │   ├── UsuarioController, RoleController, ObraController, ...
│   │   ├── Intra/    # SeccionEstaticaController, AreaController, DocumentoController
│   │   ├── Sti/      # EquipoController, TicketController, MantenimientoController, PlanController, ItemController, DashboardController, ...
│   │   ├── Prod/     # ConceptoController, GrupoPrecioController, GrupoTrabajoController, RegistroController, CorteController, ...
│   │   ├── Costos/   # RubroController, SolicitudPagoController, OrdenCompraController, FacturaAdminController, PagoController, ...
│   │   ├── Cob/      # EstimacionController, AnticipoController, ObraCobranzaController, DashboardController, ...
│   │   └── Infra/    # RecorridoController, TurnoController
│   └── Portal/       # PortalAuthController, PortalDashboardController, PortalFacturaController, ...
├── Http/Requests/Admin/
│   ├── Sti/, Prod/, Costos/, Cob/, Infra/, Intra/
├── Services/
│   └── Infra/        # DashboardService
└── Policies/

database/
├── factories/        # Mismo espejo que Models/ (~70 factories)
├── seeders/          # RolesAndPermissionsSeeder, StiStatusSeeder, StiDevSeeder, InfraDevSeeder, ProdTipoSeeder
└── migrations/

resources/js/
├── pages/admin/      # cob/, costos/, infra/, intra/, prod/, sti/, + core (usuarios, roles, departamentos, obras, proveedores, media, tags)
├── types/models.ts   # ~80 interfaces TypeScript
└── hooks/            # use-can.ts, use-form-cache.ts, ...

tests/Feature/        # Auth/, Intra/, Sti/, Prod/, Costos/, Cob/, Infra/, Portal/
```

## Rutas (routes/)

| Archivo | Descripción |
|---------|-------------|
| `routes/admin.php` | Todas las rutas admin con prefijo `/admin` (incluye sub-grupos por módulo) |
| `routes/sti.php` | Rutas públicas de tickets STI |
| `routes/intra.php` | Rutas públicas de intranet |
| `routes/portal.php` | Portal de proveedores (auth propio, facturas, OCs, pagos) |
| `routes/web.php` | Homepage y auth |

### Convención de Rutas con Recursos en Español
```php
// Siempre usar .parameters() para evitar mala pluralización
Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);
Route::resource('planes', PlanController::class)->parameters(['planes' => 'plan']);
```

## Convenciones de Modelos

### UUIDs en Usuario
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Usuario extends Authenticatable
{
    use HasUuids, HasRoles;
}
```

### Relaciones Polimórficas (Media)
```php
// En cualquier modelo que tenga archivos adjuntos
public function media(): MorphMany
{
    return $this->morphMany(Media::class, 'mediable');
}
```

### Relaciones Polimórficas (Tags)
```php
public function tags(): MorphMany
{
    return $this->morphMany(Tag::class, 'statusable');
}
```

## Convenciones de Controladores

### Verificación de Permisos con Spatie
```php
public function index(): Response
{
    $this->authorize('sti.tickets.ver');
}
```

### Middleware de Permisos en Rutas
```php
Route::middleware(['permission:sti.tickets.ver'])->group(function () {
    Route::resource('sti/tickets', TicketController::class);
});
```

## Migraciones

### Nomenclatura
```
YYYY_MM_DD_HHMMSS_create_sti_equipos_table.php
YYYY_MM_DD_HHMMSS_create_cob_estimaciones_table.php
```

### Ejemplo
```php
Schema::create('sti_tickets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tecnico_id')->nullable()->constrained('sti_tecnicos');
    $table->foreignId('equipo_id')->constrained('sti_equipos');
    $table->foreignId('departamento_id')->constrained('departamentos');
    $table->timestamps();
});
```

## Testing por Módulo

### Estructura de Tests
```
tests/Feature/
├── Auth/     # Autenticación, sesiones
├── Intra/    # Áreas, documentos, secciones, público
├── Sti/      # Equipos, tickets, mantenimientos, planes, items, asignaciones
├── Prod/     # Conceptos, grupos precio, grupos trabajo, registros, cortes
├── Costos/   # Rubros, solicitudes, OCs, facturas, pagos, afectaciones, aprobaciones, presupuestos
├── Cob/      # Clientes, estimaciones, anticipos, adendas, comparativos, disputas, penalizaciones, ...
├── Infra/    # Recorridos, turnos, dashboard
└── Portal/   # Auth portal, facturas, OCs, pagos
```

### Test con Permisos Spatie
```php
it('permite ver tickets con permiso correcto', function () {
    $permission = Permission::create(['name' => 'sti.tickets.ver']);
    $user = Usuario::factory()->create();
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('sti.tickets.index'))
        ->assertOk();
});
```

## Verificación de Cambios

1. Ejecutar migraciones: `php artisan migrate`
2. Ejecutar seeders de roles/permisos: `php artisan db:seed --class=RolesAndPermissionsSeeder`
3. Ejecutar tests del módulo: `php artisan test --filter=Sti` o `--filter=Cob` o `--filter=Costos`, etc.
4. Verificar formato: `vendor/bin/pint --dirty`
