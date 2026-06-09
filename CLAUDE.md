# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Pintech OS** – ERP web para Pintech Colombia S.A.S (pinturas industriales).
Laravel 13 + React 19 + Inertia.js v3 + TypeScript + Tailwind CSS v4 + PostgreSQL 16.

## Common Commands

### Development

```bash
# Run all development servers (recommended)
composer dev

# Manual approach (3 terminals)
php artisan serve              # Backend
php artisan queue:listen       # Queue worker
npm run dev                    # Vite frontend
```

### Testing

```bash
./vendor/bin/pest                                                     # All tests
./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php            # Single file
php artisan test --compact --filter=testName                          # Filtered
```

### Code Quality

```bash
./vendor/bin/pint              # PHP lint fix
./vendor/bin/pint --test       # PHP lint check
npm run lint                   # JS/TS lint fix
npm run lint:check             # JS/TS lint check
npm run types:check            # TypeScript check
npm run format                 # Prettier fix
npm run format:check           # Prettier check
php artisan optimize:clear     # Clear all caches
```

### Build

```bash
npm run build        # Production build
npm run build:ssr    # Build with SSR
```

### Docker

```bash
# Development (uses compose.dev.yaml)
docker compose -f compose.dev.yaml up -d --build            # Build and start all services
docker compose -f compose.dev.yaml ps                       # Check service status
docker compose -f compose.dev.yaml logs -f php-fpm          # Follow PHP logs
docker compose -f compose.dev.yaml exec php-fpm bash        # Shell into PHP container
docker compose -f compose.dev.yaml exec workspace bash      # Shell into Node/CLI container
docker compose -f compose.dev.yaml down                     # Stop all services

# Production / Staging (uses compose.prod.yaml)
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec php-fpm php artisan migrate --force
docker compose -f compose.prod.yaml exec php-fpm php artisan config:cache
docker compose -f compose.prod.yaml exec php-fpm php artisan route:cache

# Run artisan/pest inside Docker (Development)
docker compose -f compose.dev.yaml exec php-fpm php artisan <command>
docker compose -f compose.dev.yaml exec php-fpm ./vendor/bin/pest
docker compose -f compose.dev.yaml exec php-fpm ./vendor/bin/pint --dirty
```

> **Note**: Docker dev uses `compose.dev.yaml` (`target: development`, Xdebug enabled, dynamic permissions). Prod uses `compose.prod.yaml` (`target: production`, optimized, no debug tools, uses `gosu` for privilege dropping and graceful queue shutdown).

## Architecture

### Tech Stack

- **Backend**: Laravel 13 (PHP 8.3+), Inertia Laravel v3, Laravel Fortify (auth), Spatie Permission (roles), Spatie Activity Log (audit), DOMPDF (PDF generation), Maatwebsite Excel (exports)
- **Frontend**: React 19, Inertia.js v3, TypeScript, Tailwind CSS v4, shadcn/ui, Vite
- **Database**: PostgreSQL 16 (SQLite for tests)
- **Queue**: Jobs asíncronos para recálculo de precios de referencia
- **Audit**: Retención de 180 días. Limpieza automática 03:00 AM
- **Testing**: Pest PHP v4

### Role-Based Access Control

Three roles (Spatie Permission):

- `admin`: Full access (users, config, costs, reports)
- `produccion`: Plant operations (inventory, formulas, production orders)
- `comercial`: Read-only availability/prices

Middleware `role:` enforces access. Controllers use Policies + `authorize()`. Routes in `routes/web.php` grouped by role middleware.

### Backend Structure

```
app/
├── Actions/
│   └── Fortify/                # Auth actions (CreateNewUser, UpdateUserProfileInformation, etc.)
├── Concerns/                   # Shared traits (DeterminesPriceRefresh, PasswordValidationRules, ProfileValidationRules)
├── Enums/                      # PHP Enums for DB enum fields (all have label() method)
├── Exports/                    # Excel exports via Maatwebsite
│   └── ProductionOrderExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              # AuditLogController
│   │   ├── Auth/               # Auth controllers
│   │   ├── Inventory/          # RawMaterialController, WarehouseController
│   │   ├── Production/         # LineAdjustmentController, PackagingPlanController
│   │   ├── Settings/           # ProfileController
│   │   └── (root)              # FormulaController, ProductController, ProductionOrderController,
│   │                           # ProductVariantController, InventoryMovementController,
│   │                           # UserController, DashboardController, etc.
│   └── Requests/               # Form Requests organized by domain
│       ├── Formulas/
│       ├── Inventory/
│       ├── Production/
│       ├── Products/
│       ├── RawMaterials/
│       ├── Settings/
│       └── Warehouses/
├── Jobs/
│   └── RecalculateRawMaterialReferencePrice.php  # Async job for weighted avg price
├── Listeners/
│   └── LogFailedLoginAttempt.php
├── Models/                     # 24 Eloquent models
│   └── Concerns/               # Model traits (ValidatesProductVariant)
├── Notifications/
│   └── ResetPasswordNotification.php
├── Policies/                   # 7 policies (Formula, InventoryMovement, PriceList,
│                               # Product, ProductionOrder, RawMaterial, Warehouse)
├── Providers/
├── Services/                   # Complex business logic — always inject via constructor
│   ├── InventoryService.php                      # FIFO costing, batch management, movements
│   ├── ProductionOrderService.php                # Order lifecycle, advisory locks, numbering
│   ├── ProductionCostRecalculationService.php    # Recalculates variant production costs
│   ├── RawMaterialReferencePriceService.php      # Weighted average reference price
│   ├── VariantPricingService.php                 # Final variant pricing
│   ├── FormulaService.php                        # Formula calculations
│   └── WarehouseContextService.php               # Resolves active warehouse per user
└── Support/                    # (reserved)
```

### Frontend Structure

```
resources/js/
├── pages/                      # Inertia pages (match controller names, PascalCase)
│   ├── Admin/
│   ├── Comercial/
│   ├── Formulas/
│   ├── Inventory/
│   │   ├── Movements/
│   │   ├── RawMaterials/
│   │   └── Warehouses/
│   ├── Production/
│   │   └── Orders/
│   ├── Products/
│   ├── auth/
│   ├── settings/
│   ├── dashboard.tsx
│   └── welcome.tsx
├── components/
│   └── ui/                     # shadcn/ui components (Radix-based)
├── layouts/                    # App layouts (sidebar, auth)
├── hooks/                      # React hooks
├── lib/                        # Utility functions
├── types/                      # TypeScript type definitions
│   ├── auth.ts, navigation.ts, ui.ts
│   └── index.ts, global.d.ts
├── actions/                    # Wayfinder-generated (auto, do NOT edit or lint)
├── routes/                     # Wayfinder route helpers (auto-generated)
└── wayfinder/                  # Wayfinder internals
```

### Database Domain

Key business entities (see `docs/MER.md`):

- `unit_of_measures`, `product_categories`, `raw_material_categories` — Catalogs
- `raw_materials`, `inventory_batches` — Raw materials (**NOTE: `raw_materials` has NO `name` field due to privacy policy; identification is exclusively by `code`**)
- `products`, `product_variants` — Finished goods with SKU variants
- `finished_inventories`, `finished_inventory_movements` — Stock tracking by warehouse
- `formulas`, `formula_details` — Product recipes
- `production_orders`, `production_order_details`, `production_order_packaging_plan`, `production_order_line_adjustments` — Manufacturing orders
- `inventory_movements` — Raw material stock transactions
- `transfers` — Inter-warehouse transfers of finished goods
- `price_lists`, `production_costs` — Pricing history
- `warehouses`, `warehouse_user` — Multi-warehouse with user assignments
- `qr_codes`, `qr_documents` — Product documentation QR system
- `alerts` — System alerts (low stock, expiry, price variation)
- `activity_log` — Audit log (Spatie)

### PHP Enums (`app/Enums/`)

All DB enum fields have a corresponding PHP Enum with `label()` method:
- `InventoryMovementType`, `WarehouseType`, `ProductionOrderStatus`, `TransferStatus`
- `AlertType`, `AlertSeverity`, `PriceUpdateType`, `ComponentSystem`, `QrDocumentType`

## Key Business Rules

### Inventory & Costing
- **FIFO costing**: Inventory batches are consumed strictly in FIFO order during production
- **Batch traceability**: Every inventory movement MUST link to an `InventoryBatch`; never record a movement without batch reference
- **Reference price**: Async job (`RecalculateRawMaterialReferencePrice`) recalculates weighted average prices; do NOT calculate synchronously on hot paths
- **No name on raw materials**: The `raw_materials` table has NO `name` column — identification is by `code` only (privacy policy)

### Production Orders
- Sequential numbering format: `OP-YYYY-XXXX` with year-based automatic resets
- PostgreSQL advisory locks prevent race conditions on concurrent order number generation
- Full lifecycle: `pending` → `in_progress` → `completed` (inventory deducted on completion)
- Line adjustments (`production_order_line_adjustments`) track real vs planned consumption
- Packaging plans (`production_order_packaging_plan`) define variant-level output quantities

### Pricing & Costs
- **Unit production cost formula**: `(Total Raw Material Cost / Total Gallons Produced) × Presentation Size + Packaging Cost`
- `VariantPricingService` calculates final sale price from production cost + margin
- `price_lists` maintains historical pricing per variant; never mutate past records
- `production_costs` stores the calculated cost snapshot at order completion time

### Alerts
- System generates alerts automatically: low stock, expiry warnings, significant price variation
- Alerts table uses `AlertType` and `AlertSeverity` enums

## Key Patterns

**Controllers**: Use `Inertia::render('Products/Index')` with PascalCase paths. Pass `can` array for UI permission hints. Thin logic — delegate to Services.

**Models**: Use PHPDoc `@property` annotations, `casts()` method, `scopeActive()` for `is_active` columns, Enum casting for DB enum fields.

**Form Requests**: Domain-organized under `app/Http/Requests/{Domain}/`. Validation + authorization via `authorize()`. Always use `$request->validated()`, never `$request->all()`.

**Policies**: Check roles with `hasAnyRole(['admin', 'produccion'])` or `hasRole('admin')`.

**Services**: Injected via constructor DI. Complex business logic lives here, not in controllers or models.

**Search & Pagination (Full Stack)**:
- Backend filter: `whereRaw('LOWER(campo) LIKE ?', ["%{$search}%"])` for DB portability
- Persistence: Always append `->withQueryString()` to `paginate()`
- Frontend: `useForm().get(route().url, { preserveState: true, replace: true })`

**User Management Dashboard**: Layout 9/3 (9 cols table, 3 cols "Recent Activity" widget). Shows last 5 audit events in real time.

## Coding Standards

- **Language**: All code in English (variables, methods, classes). DB tables: English, snake_case, plural.
- **PHP**: PSR-12 via Laravel Pint. Strict types where possible.
- **SQL**: Database-agnostic queries. Use `LOWER()` instead of `ILIKE` for case-insensitive search.
- **Git**: Conventional commits (`feat:`, `fix:`, `docs:`, `style:`, `refactor:`).
- **Branches**: `main` (prod), `develop` (integration), `feature/PT-XXX-desc`.
- **Naming**: PascalCase classes, camelCase variables/methods, snake_case DB columns.

### UI Standards (MANDATORY)

Use `TableActions` component for ALL table row actions:
- Icons only (lucide-react), Tooltips mandatory, `sr-only` text included
- Colors: View (outline), Edit (warning/amber), Delete (destructive/red)
- Order: View → Edit → Delete

## AI Rules (CRITICAL)

- ALWAYS use Inertia helpers (`useForm`, `Link`, `router` helpers)
- NEVER use generic React patterns (e.g. manual `useState` for forms) when Inertia provides a specialized solution
- NEVER use hardcoded URLs in the frontend (e.g. `href="/products"`). ALWAYS use Wayfinder route functions (e.g. `href={productsIndex().url}`)
- Initialize `useForm` directly with default values from props
- Seeders MUST be idempotent (use `updateOrCreate`). Use `app()->environment('local', 'testing')` for mock data generation
- Follow existing project patterns strictly — do not introduce new patterns without justification

## Demo Credentials (from seeds)

- Admin: `pintech.sistemas@gmail.com`
- Production: `pintech.auxiliar@gmail.com`
- Commercial: `pintech.comercial@gmail.com`
- Password: `Pintech_2026`

## Documentation

See `docs/` directory for full reference:
- `MER.md` — Database schema / data dictionary
- `STANDARDS.md` — Development standards
- `ESPECIFICACION.md` — Full product specification
- `PLAN_DESARROLLO.md` — Development plan
- `ARQUITECTURA.md` — Architecture overview
- `POLITICA_COSTOS_MATERIA_PRIMA.md` — Raw material costing policy
- `COMPONENTES_UI.md` — UI component catalog
- `DIAGRAMAS_CASOS_USO.md` — Use case diagrams
- `FLUJO_SISTEMA.md` — System flow documentation
- `SISTEMA_AUDITORIA.md` — Audit system details
