# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working with Pintech OS.

## Project Overview

**Pintech OS** – Web ERP for Pintech Colombia S.A.S (industrial coatings & paint manufacturing).
- **Stack**: Laravel 13 (PHP 8.3+) + React 19 + Inertia.js v3 + TypeScript + Tailwind CSS v4 + PostgreSQL 16.
- **Scope**: End-to-end plant operations: raw material inventory (FIFO), formulas, production orders (OP), finished goods inventory, costing/pricing, quotations, sales orders, clients, R&D requests, and QR technical sheets.

## Common Commands

```bash
# Development & Quality
composer dev                   # server + queue:listen + pail + vite (recommended)
composer ci:check              # Full gate: eslint + prettier + tsc + pint + tests
composer lint                  # pint --parallel (PHP format fix)
npm run lint / lint:check      # ESLint fix / check
npm run types:check            # tsc --noEmit
npm run format / format:check  # Prettier fix / check
php artisan optimize:clear     # Clear all application caches
php artisan roles:audit        # Custom roles with reserved permissions or broken dependencies (run on deploy)

# Testing (Pest on SQLite :memory: in phpunit.xml; dev/prod uses PostgreSQL 16)
./vendor/bin/pest                                          # All tests
./vendor/bin/pest --parallel                               # Same suite, ~6x faster (paratest)
./vendor/bin/pest tests/Feature/Quotations                 # Specific directory
./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php # Single file
./vendor/bin/pest --filter="creates a production order"    # Filter by name
php artisan test --compact

# Testing against PostgreSQL. CI runs the suite on both engines: SQLite has no CHECK constraints,
# no advisory locks and no partial indexes, so those paths are only exercised here.
docker compose -f compose.dev.yaml up -d postgres
docker compose -f compose.dev.yaml exec -T postgres psql -U postgres -c "CREATE DATABASE pintech_erp_test"
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=pintech_erp_test DB_USERNAME=postgres \
  DB_PASSWORD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" ./vendor/bin/pest   # env vars override phpunit.xml

# Build & Routes
npm run build                  # Production build (triggers Wayfinder vite plugin)
php artisan wayfinder:generate --with-form   # Regenerate typed route helpers if dev server is off.
                               # Needs a reachable DB: Wayfinder types route params from the schema and falls back
                               # to the model docblock otherwise (Spatie's Role declares `int|string $id` -> string,
                               # which breaks tsc). `.env` uses DB_HOST=postgres, which only resolves inside Docker:
                               # from the host run `DB_HOST=127.0.0.1 php artisan wayfinder:generate --with-form`
                               # (same for `npm run dev` / `npm run build`, whose Vite plugin regenerates the routes).
                               # Missing `resources/js/actions` also shows up as ESLint import/order errors.

# Docker
docker compose -f compose.dev.yaml up -d
docker compose -f compose.dev.yaml exec php-fpm ./vendor/bin/pest
docker compose -f compose.dev.yaml down

# `vendor/` and `node_modules/` are named volumes (compose.dev.yaml), NOT the host folders: installing on the host
# leaves the containers behind and boot fails ("Class ... ServiceProvider not found"). Each lock file has its own
# workflow, and neither install runs by itself: `workspace` only runs `npm ci` on boot while
# `node_modules/.package-lock.json` is missing, so a package-lock.json change installs nothing.
docker compose -f compose.dev.yaml exec workspace composer install   # composer.lock changed
docker compose -f compose.dev.yaml restart php-fpm
# package-lock.json changed. npm lives in nvm under the `www` user, so root's PATH has no npm:
docker compose -f compose.dev.yaml exec -u www workspace bash -lc "source /home/www/.nvm/nvm.sh && npm ci"
docker compose -f compose.dev.yaml restart workspace
```

## Architecture & Request Flow

```
Route (routes/web.php, role: middleware)
  └─ Form Request  (rules() + authorize())
       └─ Controller (thin: authorize, delegate, Inertia::render)
            ├─ Filter   (QueryFilter: applySearch LOWER LIKE, applyDateRange, etc.)
            ├─ Action   (single write use case wrapped in DB::transaction)
            └─ Service  (reusable domain logic injected via constructor)
                 └─ DecimalCalculator (bcmath) for all money & quantity math
```

### Full-Stack Patterns

- **Thin Controllers**: Constructor-inject `private readonly` Actions/Services. Return `Inertia::render('PascalCase/Path')`. Pass a `can` authorization array and `EnumOptions::for(...)` for `<Select>` props.
- **Listing Pattern (MANDATORY)**:
  1. `Index{Domain}Request`: validates allowed query parameters in `rules()`.
  2. `{Domain}Filter extends QueryFilter`: declares `protected array $filterable`. Use helper methods `applySearch()` (`LOWER() LIKE`, supports `relation.column`), `applyDateRange()`, `applyExact()`. Never write raw `ILIKE`.
  3. Controller: `(new XFilter($request))->apply(Model::query())->with(...)->paginate(15)->withQueryString()`. Pass `filters => $request->validated()` to Inertia.
  4. Frontend: `useFilters({ routeUrl, initialFilters })` + `<DataTableFilters />`.
- **UI Table Actions (MANDATORY)**:
  - Use `<TableActions />` component for all table row actions: icons only (lucide-react), tooltips & `sr-only` text mandatory.
  - Colors: View (outline), Edit (warning/amber), Delete (destructive/red). Order: View → Edit → Delete.

## Critical Business Invariants (DO NOT VIOLATE)

1. **Decimal Precision (CRITICAL)**:
   - **Never compute money, costs, prices, or quantities using PHP floats.**
   - Always use `App\Services\DecimalCalculator` (`bcmath`, string in/string out, default scale 4). `ext-bcmath` is mandatory.
2. **Raw Materials Privacy**:
   - `raw_materials` table has **NO `name` column** (industrial formula privacy policy). Identification is **strictly by `code`**. Search filters must target `code`.
3. **Inventory & FIFO Traceability**:
   - Raw material consumption is strictly FIFO via `FifoStockAllocatorService`.
   - Every inventory movement MUST reference an `InventoryBatch`. Deactivation is logical (`is_active`); never hard-delete records referenced by costs.
   - Reference prices are recalculated asynchronously (`RecalculateRawMaterialReferencePrice`), never on hot request paths.
4. **PostgreSQL Advisory Locks vs SQLite Tests**:
   - Numbering sequences (`OP-YYYY-XXXX`, `COT-YYYY-XXXX`) use `pg_advisory_xact_lock` for concurrency safety.
   - Always guard advisory locks with `if (DB::connection()->getDriverName() === 'pgsql')` so SQLite in-memory tests pass.
   - Test data must satisfy PostgreSQL too: `order_number` is `varchar(20)`, `quotation_number` is an integer, and
     `inventory_batches` has CHECK constraints (`remaining_quantity <= initial_quantity`) that SQLite ignores.
   - Introspect the schema with `Schema::` (portable), never with `PRAGMA`.
5. **Timezone Handling**:
   - Application & DB timezone is UTC. Plant timezone is `config('app.plant_timezone')` (`America/Bogota`).
   - Use `TimezoneService` for server-side user-facing exports (PDF, Excel) and `FormattedDate` / `date-time-helpers` in the frontend.
6. **Frontend Navigation & Forms**:
   - Never hardcode URLs in React. Always import typed Wayfinder helpers from `@/routes` or `@/actions` (e.g. `href={productsIndex().url}`).
   - Always use Inertia helpers (`useForm`, `<Link>`, `router`). Initialize `useForm` directly with props.

## Coding Standards

- **Language**: Code in English (classes, methods, variables, DB columns). User-facing strings, UI text, and inline comments in Spanish (`lang/es.json`, `lang/es/`).
- **PHP**: PHP 8.3+, PSR-12 via Laravel Pint. Include `declare(strict_types=1);` at the top of new PHP files.
- **Deletion**: never `SoftDeletes`. Master data is deactivated (`is_active`) and only hard-deleted when unused;
  business documents are cancelled by status; ledger mistakes are fixed by a manual opposite movement with a note
  (no "reverse" action). FKs to history are `RESTRICT`, never `SET NULL`.
  See `docs/POLITICA_ELIMINACION.md`.
- **Authorization**: decide by permission (`$user->can(Permission::X->value)`, `can:` middleware, policies), never by
  role name. The only role checks live in `User` (`isSuperAdmin()`, `superAdmins()`), enforced by a test. Role names
  are written as `SystemRole::X->value`, never as string literals.
- **Listings**: order by a date and always break ties with `latest('id')`. Without it, rows sharing a timestamp come
  back in arbitrary order on PostgreSQL and pagination can repeat or skip them (`StableListingOrderTest`).
- **SQL**: Database-agnostic (`LOWER()` instead of Postgres-specific `ILIKE`).
- **Seeders**: Must be idempotent (`updateOrCreate` / `firstOrCreate`). Gate test/mock data with `app()->environment('local', 'testing')`.
- **Git**: Conventional Commits (`feat:`, `fix:`, `docs:`, `style:`, `refactor:`, `test:`). Main branch: `main`; development: `develop`.

## Demo Credentials (local / testing seeds only)

- **Admin**: `pintech.sistemas@gmail.com`
- **Production**: `pintech.auxiliar@gmail.com`
- **Commercial**: `pintech.comercial@gmail.com`
- **Operator**: `pintech.operador@gmail.com`
- **Password**: Configurable via `SEED_USER_PASSWORD` in `.env` (default defined in `UserSeeder.php`).

## Active Documentation Index (`docs/`)

`docs/` holds only the rules that code must satisfy. Plans, sprints, backlog and business overviews live in the
Obsidian vault (see below), so nothing here goes stale on its own.

- `MER.md` — Complete database schema and entity-relationship dictionary.
- `STANDARDS.md` & `ARQUITECTURA.md` — Core architectural guidelines, patterns, and conventions.
- `POLITICA_COSTOS_MATERIA_PRIMA.md` — FIFO costing rules, reference pricing, and valuation formulas.
- `POLITICA_ELIMINACION.md` — Deletion policy: deactivate vs delete per entity, FK delete rules (no soft deletes).
- `MATRIZ_RBAC.md` — Role and permission matrix. §8 holds the implementation decisions the code cites.
- `COMPONENTES_UI.md` & `SISTEMA_TEMAS_UI.md` — Design system, themes (dark/light), and reusable UI components.
- `SISTEMA_AUDITORIA.md` — Spatie activity log integration, audit trails, and security events.
- `LOGOS.md` & `production-exports.md` — Brand identity rules, PDF & Excel export technical specifications.

## Project Management (outside this repository)

Roadmap, sprints, pending work, technical debt backlog and decision records live in the owner's Obsidian vault, at
`~/documents/Obsidian/001-Proyectos/Pintech Colombia S.A.S/`. What is pending is answered by
`Documentation/Project Management/Estado y Pendientes.md` there — never from this repository or from chat history.
