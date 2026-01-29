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
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Activates whenever referencing backend routes in frontend components. Use when importing from @/actions or @/routes, calling Laravel routes from TypeScript, or working with Wayfinder route functions.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
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
Sistema integrador empresarial que centraliza múltiples módulos: autenticación con roles/permisos (Spatie), intranet corporativa y sistema de soporte TI (STI). Arquitectura de base de datos única con prefijos de tabla por módulo.

## Dependencias Requeridas
- `spatie/laravel-permission` - Sistema de roles y permisos
- Ejecutar: `composer require spatie/laravel-permission`
- Publicar config: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`

## Arquitectura de Base de Datos

### Estrategia
- **Una sola base de datos** con prefijos de tabla por módulo
- Prefijos: `intra_` (intranet), `sti_` (soporte TI), sin prefijo (core/auth)

### Convenciones de Nomenclatura
- Tablas en español y snake_case: `sti_tickets`, `intra_documentos`
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
| `sti_mantenimientos` | Programación de mantenimientos |
| `sti_status` | Catálogo de estados |
| `sti_ticket_historial` | Auditoría de cambios de estado |

#### Compartidas (sin prefijo)
| Tabla | Descripción |
|-------|-------------|
| `media` | Archivos adjuntos (polimórfica) |
| `tags` | Etiquetas (polimórfica) |

## Spatie Permissions

### Configuración del Modelo Usuario
```php
// app/Models/Usuario.php
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use HasRoles;

    protected $table = 'usuarios';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }
}
```

### Convención de Nombres de Permisos
Formato: `{módulo}.{recurso}.{acción}`

```php
// Ejemplos:
'sti.tickets.ver'
'sti.tickets.crear'
'sti.tickets.editar'
'sti.tickets.eliminar'
'sti.equipos.ver'
'sti.mantenimientos.programar'
'intra.documentos.subir'
'intra.areas.administrar'
```

### Roles Sugeridos
```php
// Roles base del sistema
'super-admin'      // Acceso total
'admin-sti'        // Administrador de soporte TI
'tecnico-sti'      // Técnico de soporte
'admin-intranet'   // Administrador de intranet
'empleado'         // Usuario básico
```

## Estructura de Carpetas por Módulo

```
app/
├── Models/
│   ├── Usuario.php
│   ├── Departamento.php
│   ├── Obra.php
│   ├── Media.php
│   ├── Tag.php
│   ├── Intra/
│   │   ├── SeccionEstatica.php
│   │   ├── Area.php
│   │   └── Documento.php
│   └── Sti/
│       ├── Equipo.php
│       ├── Tecnico.php
│       ├── Ticket.php
│       ├── Mantenimiento.php
│       ├── Status.php
│       └── TicketHistorial.php
├── Http/Controllers/
│   ├── Intra/
│   │   ├── SeccionEstaticaController.php
│   │   ├── AreaController.php
│   │   └── DocumentoController.php
│   └── Sti/
│       ├── EquipoController.php
│       ├── TecnicoController.php
│       ├── TicketController.php
│       └── MantenimientoController.php
├── Policies/
│   ├── Intra/
│   └── Sti/
└── Services/
    ├── Intra/
    └── Sti/

database/migrations/
├── core/           # Migraciones sin prefijo
├── intra/          # Migraciones intra_*
└── sti/            # Migraciones sti_*
```

## Convenciones de Modelos

### UUIDs en Usuario
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Usuario extends Authenticatable
{
    use HasUuids, HasRoles;
    // ...
}
```

### Relaciones Polimórficas (Media)
```php
// En cualquier modelo que tenga archivos adjuntos
public function media(): MorphMany
{
    return $this->morphMany(Media::class, 'mediable');
}

// En Media.php
public function mediable(): MorphTo
{
    return $this->morphTo();
}
```

### Relaciones Polimórficas (Tags)
```php
// En modelos con etiquetas
public function tags(): MorphMany
{
    return $this->morphMany(Tag::class, 'statusable');
}
```

### Áreas Jerárquicas (Self-Reference)
```php
// app/Models/Intra/Area.php
public function parent(): BelongsTo
{
    return $this->belongsTo(Area::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(Area::class, 'parent_id');
}
```

## Convenciones de Controladores

### Verificación de Permisos con Spatie
```php
public function index(): Response
{
    $this->authorize('sti.tickets.ver');
    // o
    if (!auth()->user()->can('sti.tickets.ver')) {
        abort(403);
    }
}
```

### Middleware de Permisos en Rutas
```php
Route::middleware(['permission:sti.tickets.ver'])->group(function () {
    Route::resource('sti/tickets', TicketController::class);
});

// O por rol
Route::middleware(['role:admin-sti|tecnico-sti'])->group(function () {
    // rutas STI
});
```

## Migraciones

### Nomenclatura de Archivos
```
YYYY_MM_DD_HHMMSS_create_sti_equipos_table.php
YYYY_MM_DD_HHMMSS_create_intra_areas_table.php
```

### Ejemplo de Migración con Prefijo
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
tests/
├── Feature/
│   ├── Intra/
│   │   ├── AreaTest.php
│   │   └── DocumentoTest.php
│   └── Sti/
│       ├── TicketTest.php
│       └── MantenimientoTest.php
└── Unit/
    ├── Intra/
    └── Sti/
```

### Test con Permisos Spatie
```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
3. Ejecutar tests del módulo: `php artisan test --filter=Sti` o `--filter=Intra`
4. Verificar formato: `vendor/bin/pint --dirty`
