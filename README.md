# F3A8 Pintech Colombia S.A.S — Sistema de Gestión de Planta

Sistema web de gestión de planta desarrollado para **Pintech Colombia S.A.S**, 
empresa dedicada a la fabricación y comercialización de pinturas industriales, 
automotrices y arquitectónicas con sedes en Neiva (Huila) y Cali (Valle del Cauca).

## F4CB Descripción

El sistema busca centralizar y automatizar los procesos de la planta de producción,
brindando visibilidad en tiempo real sobre inventarios, costos, precios y 
disponibilidad de producto terminado para el área comercial.

## F680 Módulos del Sistema

- ✅ Autenticación y gestión de usuarios
- 🔧 Inventario de materia prima (método PEPS)
- 🔧 Producto terminado por bodega
- 🔧 Cálculo de costos por formulación
- 🔧 Actualización automática de precios
- 🔧 Alertas de stock, vencimientos y variaciones de costo
- 🔧 Gestión de formulaciones
- 🔧 Órdenes de producción
- 🔧 Generación de códigos QR por envase
- 🔧 Reportes y analytics

## 👥 Roles del Sistema

| Rol | Nivel de Acceso |
|-----|-----------------|
| **Administrador** | Acceso total al sistema |
| **Asistente de Producción** | Acceso operativo de planta |
| **Comercial** | Consulta de disponibilidad de producto (solo lectura) |

## � Control de Acceso y Roles

El sistema implementa **Spatie Laravel Permission v7.2.2** para gestión centralizada de roles y permisos:

- **Tablas de control:** `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- **Modelo User:** Configurado con trait `HasRoles` para acceso a métodos como `hasRole()`, `assignRole()`, `can()`
- **Middleware:** `role` para proteger rutas según rol (ej: `Route::middleware('role:admin')`)

**Instalación ya completada:**
✅ Librería Spatie instalada (`composer require spatie/laravel-permission`)
✅ Configuración publicada (`config/permission.php`)
✅ Migraciones ejecutadas (tablas de roles/permisos creadas)
✅ Modelo User actualizado con `HasRoles` trait

**Próximos pasos:**
- Crear roles: Admin, Producción, Comercial
- Crear usuario admin inicial
- Definir permisos por operación (ver, crear, editar, eliminar)

## �🚀 Tecnología

- **Backend:** Laravel 12 + PHP 8.2+
- **Frontend:** React 18 + Inertia.js + Tailwind CSS v4
- **Database:** PostgreSQL 16
- **Auth & Roles:** Spatie Laravel Permission v7.2.2
- **Real-time:** Laravel Echo + Reverb (WebSockets para alertas)
- **Testing:** Pest PHP
- **Code Style:** Laravel Pint (PSR-12)
- **Queue:** Database driver
- **Cache:** Database store

## ✅ Requisitos

- PHP 8.2+
- Composer 2.x
- Node.js 18+
- PostgreSQL 16
- npm o pnpm

## 📦 Instalación

### 1. Clonar el repositorio
```bash
git clone https://github.com/AndresCebay-ADSO/bepro-gestion-planta-pintech.git
cd bepro-gestion-planta-pintech
```

### 2. Instalar dependencias PHP
```bash
composer install
```

### 3. Instalar dependencias Node
```bash
npm install
```

### 4. Configurar variables de entorno
```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` y configura PostgreSQL:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pintech_erp
DB_USERNAME=postgres
DB_PASSWORD=
```

### 5. Configurar base de datos PostgreSQL (macOS con Homebrew)
```bash
# Iniciar PostgreSQL
brew services start postgresql@16

# Crear base de datos
psql -U postgres -c "CREATE DATABASE pintech_erp;"

# Ejecutar migraciones
php artisan migrate

# Opcional: Seeders de prueba
php artisan db:seed
```

### 6. Construir assets frontend
```bash
npm run build
```

## 💻 Desarrollo

### Iniciar todos los servicios (recomendado)
```bash
composer dev
```
Esto inicia simultáneamente:
- Laravel server (http://127.0.0.1:8000)
- Queue worker para procesos en segundo plano
- Vite dev server con HMR (Hot Module Replacement)

### Servicios individuales
```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:listen --tries=1

# Terminal 3: Vite dev server
npm run dev

# Terminal 4: WebSocket server (para alertas en tiempo real)
php artisan reverb:start
```

## 📋 Scripts Disponibles

### Frontend
| Comando | Descripción |
|---------|-------------|
| `npm run dev` | Inicia Vite en modo desarrollo con HMR |
| `npm run build` | Construye assets para producción |
| `npm run build:ssr` | Construye con soporte SSR |
| `npm run lint` | Ejecuta ESLint y arregla errores |
| `npm run lint:check` | Verifica errores sin arreglar |
| `npm run format` | Formatea código con Prettier |
| `npm run format:check` | Verifica formato |
| `npm run types:check` | Verifica tipos TypeScript |

### Backend
| Comando | Descripción |
|---------|-------------|
| `composer dev` | Inicia todos los servicios de desarrollo |
| `php artisan migrate` | Ejecuta migraciones |
| `php artisan migrate:fresh --seed` | Resetea DB y ejecuta seeders |
| `php artisan db:seed` | Ejecuta seeders |
| `php artisan tinker` | Consola interactiva de Laravel |
| `php artisan pail` | Monitoreo de logs en tiempo real |
| `php artisan reverb:start` | Servidor WebSocket para alertas |
| `./vendor/bin/pest` | Ejecuta pruebas con Pest |
| `./vendor/bin/pint` | Verifica estilo de código PHP |
| `./vendor/bin/pint --fix` | Corrige estilo de código PHP |

## 🌐 Acceso al Sistema

- **App:** http://127.0.0.1:8000
- **Vite Dev Server:** http://localhost:5173
- **Laravel Telescope:** http://127.0.0.1:8000/telescope (debugging)

## 📁 Estructura del Proyecto

```
├── app/
│   ├── Actions/          # Acciones reutilizables (Fortify)
│   ├── Events/           # Eventos para WebSockets
│   ├── Http/
│   │   ├── Controllers/  # Controladores con Inertia
│   │   ├── Middleware/   # Middleware personalizado
│   │   └── Requests/     # Form Request validation
│   ├── Models/           # Modelos Eloquent + Factories
│   └── Services/         # Lógica de negocio (PEPS, cálculos)
├── config/               # Configuración de la aplicación
├── database/
│   ├── factories/        # Factories para testing
│   ├── migrations/       # Migraciones PostgreSQL
│   └── seeders/          # Seeders de datos iniciales
├── docs/                 # Documentación del proyecto
├── resources/
│   ├── css/              # Tailwind CSS v4
│   └── js/
│       ├── Components/   # Componentes React reutilizables
│       ├── Layouts/        # Layouts compartidos
│       ├── Pages/          # Páginas Inertia (una por ruta)
│       ├── Hooks/          # Custom React hooks
│       └── types/          # Definiciones TypeScript
├── routes/
│   ├── web.php           # Rutas web con Inertia
│   └── console.php       # Comandos artisan
├── tests/
│   ├── Feature/          # Tests de endpoints Inertia
│   └── Unit/             # Tests de lógica (PEPS, costos)
└── storage/              # Logs, caché, uploads
```

## 🔐 Autenticación

El proyecto incluye autenticación completa con Laravel Fortify:
- ✅ Registro de usuarios
- ✅ Login/Logout
- ✅ Recuperación de contraseña
- ✅ Autenticación de dos factores (2FA)
- ✅ Verificación de email

## 💾 Base de Datos (PostgreSQL)

### Características principales:
- **PEPS (FIFO):** Método Primero en Entrar, Primero en Salir para inventario
- **Window Functions:** Para cálculos de stock y costos
- **JSONB:** Para campos flexibles (atributos de productos)
- **Full-text search:** Para búsquedas rápidas

### Tablas principales:
- `users` - Usuarios con roles
- `raw_materials` - Materias primas
- `inventory_batches` - Lotes de inventario (PEPS)
- `finished_products` - Productos terminados
- `formulations` - Formulaciones/recetas
- `production_orders` - Órdenes de producción
- `warehouses` - Bodegas

## 🔧 Testing

Ejecutar todas las pruebas:
```bash
./vendor/bin/pest
```

Pruebas específicas:
```bash
./vendor/bin/pest tests/Feature
./vendor/bin/pest tests/Unit
./vendor/bin/pest --filter=InventoryTest
```

Configuración: `phpunit.xml` usa PostgreSQL para tests precisos con window functions.

## 📤 Git Workflow

### Branches
- `main` - Código en producción, siempre estable
- `develop` - Branch de integración
- `feature/PT-XXX-descripcion` - Features (ej: `feature/PT-PP-01-inventario`)

### Commits (Conventional Commits)
```
feat: nueva funcionalidad
fix: corrección de bug
docs: documentación
style: formato de código
refactor: reestructuración
```

## 🚀 Deployment

### Preparar para producción
```bash
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Variables de entorno producción
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generado>
DB_CONNECTION=pgsql
QUEUE_CONNECTION=database
CACHE_STORE=database
REVERB_APP_ID=<tu-app-id>
REVERB_APP_KEY=<tu-key>
REVERB_APP_SECRET=<tu-secret>
```

## 📚 Documentación

La documentación del proyecto se encuentra en la carpeta `docs/`:

- `PT-PP-01.md` — Planteamiento del Problema
- `PT-ERS-01.md` — Especificación de Requerimientos
- `PT-ECU-01.md` — Especificación de Casos de Uso
- `JUSTIFICACION.md` — Justificación tecnológica
- `STANDARDS.md` — Estándares de desarrollo
- `CLAUDE.md` — Guía para Claude Code

## 👨‍💻 Desarrollador

**Andrés Stiven Cebay Ceballos**  
Practicante ADSO — 2026

## � Dependencias Instaladas

Seguimiento de librerías externas instaladas y su propósito:

| Librería | Versión | Fecha Instalación | Propósito |
|----------|---------|-------------------|-----------|
| **Spatie Laravel Permission** | ^7.2.2 | 06/04/2026 | Gestión centralizada de roles y permisos |

**Notas de instalación:**
- Spatie Laravel Permission: Se publicaron archivos de configuración en `config/permission.php`. Migraciones propias creadas automáticamente. Modelo `User` actualizado con trait `HasRoles`.

### Seeders de Datos Base

Se han creado seeders automáticos para datos maestros iniciales:

| Seeder | Datos | Creado |
|--------|-------|--------|
| `UnitsOfMeasureSeeder` | 11 unidades estándar (kg, lt, gal, ml, u, m³, g, mg, lb, gal_imp, bbl) | ✅ |

**Ejecutar seeders:**
```bash
php artisan db:seed
# O específico:
php artisan db:seed --class=UnitsOfMeasureSeeder
```

---

## 📊 Migraciones de Base de Datos

### Estado actual: ✅ TODAS EJECUTADAS (06/04/2026)

**18 tablas de negocio creadas** según MER (Modelo Entidad-Relación):

| # | Tabla | Descripción | Creada |
|---|-------|-------------|--------|
| 0 | `units_of_measure` | Unidades estándar (kg, lt, gal, ml, etc.) | ✅ |
| 1 | `product_categories` | Categorías de productos (Industrial, Automotriz, Arquitectónico) | ✅ |
| 2 | `warehouses` | Bodegas (Neiva, Cali) | ✅ |
| 3 | `raw_materials` | Catálogo de materias primas | ✅ |
| 4 | `inventory_batches` | Lotes de MP (método PEPS) | ✅ |
| 5 | `inventory_movements` | Movimientos entrada/salida de MP | ✅ |
| 6 | `products` | Catálogo de productos terminados | ✅ |
| 7 | `finished_inventory` | Stock PT por producto × bodega | ✅ |
| 8 | `finished_inventory_movements` | Movimientos entrada/salida PT | ✅ |
| 9 | `formulas` | Formulaciones activas e históricas | ✅ |
| 10 | `formula_details` | Ingredientes por formulación | ✅ |
| 11 | `production_orders` | Órdenes de producción (OP) | ✅ |
| 12 | `production_order_details` | Consumo de lotes por OP | ✅ |
| 13 | `production_costs` | Historial de costos calculados | ✅ |
| 14 | `price_list` | Historial de precios vigentes | ✅ |
| 15 | `qr_codes` | Códigos QR por producto | ✅ |
| 16 | `qr_documents` | Documentos técnicos por QR | ✅ |
| 17 | `alerts` | Alertas automáticas del sistema | ✅ |

**Además (creadas por frameworks):**
- Tablas Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- Tabla Laravel: `users`

### Características implementadas:
✅ **PEPS:** Trazabilidad completa lote × orden de producción  
✅ **Historial:** Costos y precios con campos `valid_from`/`valid_to`  
✅ **Auditoría:** Campos `created_by`, `updated_by` en operaciones críticas  
✅ **Índices:** Optimizados para consultas de PEPS, alertas, búsquedas  
✅ **Restricciones:** FK con `onDelete` apropiados, unicidad enforcement  
✅ **Unidades:** Tabla centralizada con conversiones de peso/volumen (kg, lt, gal, ml, etc.)  

---

## �📜 Licencia

Propietario — Pintech Colombia S.A.S

## 🛠️ Herramientas de Desarrollo

- **Laravel Telescope:** Debugging en `/telescope`
- **Laravel Pail:** Logs en tiempo real (`php artisan pail`)
- **React DevTools:** Extensión del navegador
- **Laravel Reverb:** WebSocket para alertas en tiempo real

## 🔍 Notas Importantes

### PEPS (Método FIFO)
Las consultas de inventario usan PostgreSQL window functions:
```php
InventoryBatch::where('raw_material_id', $id)
    ->where('remaining_quantity', '>', 0)
    ->orderBy('entry_date')
    ->orderBy('created_at')
    ->first();
```

### Inertia.js Data Flow
Los datos fluyen del Controller al componente React automáticamente:
```php
// Controller
return Inertia::render('Inventory/Index', ['materials' => $materials]);

// React
export default function Index({ materials }) { ... }
```
