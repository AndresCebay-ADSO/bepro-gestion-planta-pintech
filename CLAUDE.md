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

# Testing (Pest on SQLite :memory: in phpunit.xml; dev/prod uses PostgreSQL)
./vendor/bin/pest                                          # All tests
./vendor/bin/pest tests/Feature/Quotations                 # Specific directory
./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php # Single file
./vendor/bin/pest --filter="creates a production order"    # Filter by name
php artisan test --compact

# Build & Routes
npm run build                  # Production build (triggers Wayfinder vite plugin)
php artisan wayfinder:generate # Regenerate typed route helpers if dev server is off

# Docker
docker compose -f compose.dev.yaml up -d
docker compose -f compose.dev.yaml exec php-fpm ./vendor/bin/pest
docker compose -f compose.dev.yaml down
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
5. **Timezone Handling**:
   - Application & DB timezone is UTC. Plant timezone is `config('app.plant_timezone')` (`America/Bogota`).
   - Use `TimezoneService` for server-side user-facing exports (PDF, Excel) and `FormattedDate` / `date-time-helpers` in the frontend.
6. **Frontend Navigation & Forms**:
   - Never hardcode URLs in React. Always import typed Wayfinder helpers from `@/routes` or `@/actions` (e.g. `href={productsIndex().url}`).
   - Always use Inertia helpers (`useForm`, `<Link>`, `router`). Initialize `useForm` directly with props.

## Coding Standards

- **Language**: Code in English (classes, methods, variables, DB columns). User-facing strings, UI text, and inline comments in Spanish (`lang/es.json`, `lang/es/`).
- **PHP**: PHP 8.3+, PSR-12 via Laravel Pint. Include `declare(strict_types=1);` at the top of new PHP files.
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

- `MER.md` — Complete database schema and entity-relationship dictionary.
- `STANDARDS.md` & `ARQUITECTURA.md` — Core architectural guidelines, patterns, and conventions.
- `FLUJO_SISTEMA.md` & `SOFTWARE_OVERVIEW.md` — End-to-end plant workflows and functional overview.
- `POLITICA_COSTOS_MATERIA_PRIMA.md` — FIFO costing rules, reference pricing, and valuation formulas.
- `MATRIZ_RBAC.md` & `PLAN_FASE_2_RBAC.md` — Role and permission matrix, plus current RBAC implementation roadmap.
- `COMPONENTES_UI.md` & `SISTEMA_TEMAS_UI.md` — Design system, themes (dark/light), and reusable UI components.
- `SISTEMA_AUDITORIA.md` — Spatie activity log integration, audit trails, and security events.
- `LOGOS.md` & `production-exports.md` — Brand identity rules, PDF & Excel export technical specifications.
