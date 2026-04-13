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

- **Backend**: Laravel 13 (PHP 8.3+), Inertia Laravel, Laravel Fortify (auth), Spatie Permission (roles), Spatie Activity Log (audit)
- **Frontend**: React 19, Inertia.js, TypeScript, Tailwind CSS v4, Vite
- **Database**: PostgreSQL 16 (SQLite for tests)
- **Log Management**: Auditoría con retención de 180 días. Limpieza automática 03:00 AM.
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
- `activity_log` - Registro de auditoría del sistema (Spatie)

### Key UI Layouts

**User Management Dashboard**: Rediseño con layout 9/3 (9 columnas para tabla, 3 columnas para el widget de "Actividad Reciente"). Muestra últimos 5 eventos de auditoría en tiempo real.

### Key Patterns

**Controllers**: Use `Inertia::render('Products/Index')` with PascalCase paths. Pass `can` array for UI permission hints.

**Form Requests**: Domain-organized under `app/Http/Requests/{Domain}/`. Validation rules + authorization via `authorize()`.

**Policies**: Check roles with `hasAnyRole(['admin', 'produccion'])` or `hasRole('admin')`.

**Frontend Types**: Inferred from props via Inertia. Use Wayfinder route functions (`route().url`) for URLs.

**Search & Pagination (Full Stack)**:
- **Backend Filter**: Use `whereRaw('LOWER(campo) LIKE ?', ["%{$search}%"])` for portability.
- **Persistence**: Always append `->withQueryString()` to the `paginate()` call.
- **Frontend Submission**: Use `useForm().get(route().url, { preserveState: true, replace: true })`.

## Coding Standards

- **Language**: All code in English (variables, methods, classes). Database tables in English, snake_case, plural.
- **PHP**: PSR-12 via Laravel Pint. Strict types where possible.
- **SQL**: Prefer database-agnostic queries. Use `LOWER()` instead of `ILIKE` for case-insensitive search compatibility.
- **Git**: Conventional commits (`feat:`, `fix:`, `docs:`, `style:`, `refactor:`).
- **Branches**: `main` (prod), `develop` (integration), `feature/PT-XXX-desc`.
- **Naming**: PascalCase classes, camelCase variables/methods, snake_case DB columns.
- **UI Standards**: Use `TableActions` component for all table row actions. 
  - Rules: Icons only (lucide-react), Tooltips mandatory, `sr-only` text included.
  - Colors: View (outline), Edit (warning/amber), Delete (destructive/red).
  - Order: View, Edit, Delete.

### UI/UX Rules (MANDATORY)
Para acciones en tablas, usar **obligatoriamente** el componente `TableActions` con iconos, tooltips y sr-only (Ver `docs/STANDARDS.md`).

---

## AI Rules (CRITICAL)

When generating code:
- ALWAYS use Inertia helpers (useForm, Link, router helpers).
- NEVER use generic React patterns (like manual useState for forms) when Inertia provides a specialized solution.
- NEVER use hardcoded URLs in the frontend (e.g., `href="/products"`). ALWAYS use Wayfinder route functions (e.g., `href={productsIndex().url}`).
- Initialize `useForm` directly with default values from props.
- Seeders MUST be idempotent (use `updateOrCreate`). Use `app()->environment('local', 'testing')` for mock data generation.
- Follow existing project patterns strictly.
- Do not introduce new patterns without justification.
  - Rules: Icons only (lucide-react), Tooltips mandatory, `sr-only` text included.
  - Colors: View (outline), Edit (warning/amber), Delete (destructive/red).
  - Order: View, Edit, Delete.

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-react-development` — Develops Inertia.js v3 React client-side applications. Activates when creating React pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

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

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
