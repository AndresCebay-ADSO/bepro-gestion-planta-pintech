# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Pintech OS** - ERP web for Pintech Colombia S.A.S (industrial paints). Laravel 13 + React 18 + Inertia.js + TypeScript + Tailwind v4 + PostgreSQL 16.

## Common Commands

### Development

```bash
# Run all development servers (recommended)
composer dev

# Manual approach (3 terminals)
php artisan serve              # Backend
php artisan queue:work       # Queue worker
npm run dev                  # Vite frontend
```

### Testing

```bash
# Run all tests with Pest
./vendor/bin/pest

# Run single test file
./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php

# Run tests via Artisan
php artisan test
```

### Code Quality

```bash
# PHP linting (Laravel Pint)
./vendor/bin/pint              # Fix
./vendor/bin/pint --test       # Check only

# JavaScript/TypeScript linting
npm run lint                   # Fix
npm run lint:check             # Check only

# Type checking
npm run types:check

# Prettier formatting
npm run format                 # Fix
npm run format:check           # Check only

# Clear all caches
php artisan optimize:clear
```

### Build

```bash
# Production build
npm run build

# Build with SSR
npm run build:ssr
```

## Architecture

### Tech Stack

- **Backend**: Laravel 13 (PHP 8.3+), Inertia Laravel, Laravel Fortify (auth), Spatie Permission (roles)
- **Frontend**: React 19, Inertia.js, TypeScript, Tailwind CSS v4, Vite
- **Database**: PostgreSQL 16 (SQLite for tests)
- **Testing**: Pest PHP

### Role-Based Access Control

Three roles defined in `config/permission.php`:

- `admin`: Full access (users, config, costs, reports)
- `produccion`: Plant operations (inventory, formulas, orders)
- `comercial`: Read-only availability/prices

Middleware `role:` enforces access. Controllers use Policies + `authorize()` method. Routes in `routes/web.php` grouped by role middleware.

### Backend Structure

```
app/
├── Http/Controllers/         # Resource controllers, thin logic
├── Http/Requests/            # Form Request classes per domain
│   ├── Products/
│   ├── Inventory/
│   └── Settings/
├── Policies/                 # Authorization rules
├── Services/                 # Complex business logic
└── Models/                   # Eloquent, relationships
```

Controllers use Form Requests for validation, delegate to Services for complex logic. Policies check `hasRole()` for granular access.

### Frontend Structure

```
resources/js/
├── pages/                    # Inertia pages (match routes)
│   ├── Admin/
│   ├── Production/
│   ├── Comercial/
│   ├── Products/
│   ├── Inventory/Movements/
│   └── auth/
├── components/
│   └── ui/                   # shadcn/ui components (Radix-based)
├── layouts/                  # App layouts (sidebar, auth)
├── hooks/                    # React hooks
└── actions/                  # Wayfinder-generated (auto, ignore in lint)
```

Pages use PascalCase folders matching controller resource names. Components use `resources/js/components/ui/` for base UI primitives.

### Database Domain

Key business entities (see `docs/MER.md`):

- `units_of_measure`, `product_categories` - Catalogs
- `raw_materials`, `inventory_batches` - Raw materials with lot tracking (PEPS/FIFO)
- `products`, `finished_inventory` - Finished goods
- `formulas`, `formula_details` - Product recipes
- `production_orders`, `production_order_details` - Manufacturing orders
- `inventory_movements` - Stock transactions
- `price_lists`, `production_costs` - Pricing history

### Key Patterns

**Controllers**: Use `Inertia::render('Products/Index')` with PascalCase paths. Pass `can` array for UI permission hints.

**Form Requests**: Domain-organized under `app/Http/Requests/{Domain}/`. Validation rules + authorization via `authorize()`.

**Policies**: Check roles with `hasAnyRole(['admin', 'produccion'])` or `hasRole('admin')`.

**Frontend Types**: Inferred from props via Inertia. Use Ziggy `route()` helper for URLs.

## Coding Standards

- **Language**: All code in English (variables, methods, classes). Database tables in English, snake_case, plural.
- **PHP**: PSR-12 via Laravel Pint. Strict types where possible.
- **Git**: Conventional commits (`feat:`, `fix:`, `docs:`, `style:`, `refactor:`).
- **Branches**: `main` (prod), `develop` (integration), `feature/PT-XXX-desc`.
- **Naming**: PascalCase classes, camelCase variables/methods, snake_case DB columns.

## Demo Credentials (from seeds)

- Admin: `pintech.sistemas@gmail.com`
- Production: `pintech.auxiliar@gmail.com`
- Commercial: `pintech.comercial@gmail.com`
- Password: `Pintech_2026`

## Documentation

- `docs/MER.md` - Database schema / data dictionary
- `docs/STANDARDS.md` - Development standards
- `docs/ESPECIFICACION.md` - Specification
- `docs/PLAN_DESARROLLO.md` - Development plan
