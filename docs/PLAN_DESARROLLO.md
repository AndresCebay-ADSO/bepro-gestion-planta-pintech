# Plan de Desarrollo - Sistema de Gestión de Planta Pintech

## Información General

- **Proyecto**: Sistema de Gestión de Planta - Pintech Colombia S.A.S
- **Desarrollador**: Andrés Stiven Cebay Ceballos (Practicante ADSO)
- **Duración**: 12 semanas (3 meses)
- **Fecha de inicio**: 31 de marzo 2026
- **Fecha de entrega objetivo**: 23 de junio 2026
- **Stack tecnológico**: Laravel 12 + Inertia.js + React + PostgreSQL + Laravel Echo/Reverb

## Checkpoints Críticos

| Checkpoint | Fecha | Estado | Decisión si falla |
|------------|-------|--------|-------------------|
| **Checkpoint Alfa** | 17 de abril (Semana 3) | 🔴 Pendiente | Si no hay auth + inventario básico funcionando, volver a Blade |
| **Checkpoint Beta** | 15 de mayo (Semana 7) | 🔴 Pendiente | Si faltan módulos esenciales, simplificar RF06 y posponer RF13-16 |
| **Checkpoint Final** | 23 de junio (Semana 12) | 🔴 Pendiente | Entregar lo que esté funcional, documentar pendientes |

---

## Fase 1: Fundamentos y Setup (Semanas 1-3)

### Semana 1: Setup y Arquitectura Base
**Objetivo**: Tener el proyecto configurado con React + Inertia y PostgreSQL funcionando

#### Tareas Técnicas
- [ ] Migrar base de datos de MySQL a PostgreSQL
- [ ] Instalar y configurar Inertia.js con React
- [ ] Configurar Vite para React (verificar HMR funcione)
- [ ] Crear layout base con Tailwind CSS + componentes React
- [ ] Configurar Laravel Echo + Reverb para tiempo real
- [ ] Instalar paquetes esenciales (Spatie Permission, Laravel Excel, simple-qrcode)
- [ ] Configurar roles de usuario (Admin, Producción, Comercial)

#### Entregables
- [ ] Proyecto corre con `composer dev` sin errores
- [ ] Página de inicio renderiza componente React básico
- [ ] Conexión a PostgreSQL estable
- [ ] Sistema de roles funcionando (tablas creadas)

#### Riesgos y Mitigación
| Riesgo | Probabilidad | Mitigación |
|--------|--------------|------------|
| Configuración Inertia falla | Media | Tener proyecto Blade de respaldo listo |
| PostgreSQL no instala local | Baja | Usar Docker o Laravel Sail |
| Reverb no funciona | Media | Dejar alertas para semana 6, usar polling temporal |

---

### Semana 2: Autenticación Completa
**Objetivo**: Sistema de login, registro y gestión de usuarios 100% funcional

#### Tareas Técnicas
- [ ] Implementar autenticación con Laravel Breeze/Jetstream adaptado a Inertia
- [ ] Crear vistas React: Login, Register, Forgot Password
- [ ] Implementar middleware de roles (spatie/laravel-permission)
- [ ] Crear dashboard inicial por rol (vistas vacías pero accesibles)
- [ ] Seeders para usuarios de prueba (1 admin, 2 producción, 1 comercial)
- [ ] Proteger rutas según rol

#### Entregables
- [ ] Login funcional con validación
- [ ] Redirección post-login según rol
- [ ] Usuarios de prueba creados con seeders
- [ ] Middleware protegiendo rutas administrativas

#### Pruebas de Aceptación
```
DADO que soy un usuario no autenticado
CUANDO intento acceder a /dashboard
ENTONCES soy redirigido a /login

DADO que soy un usuario Comercial
CUANDO inicio sesión
ENTONCES veo el dashboard de comercial
Y NO veo opciones de administración
```

---

### Semana 3: CRUD Materias Primas (CHECKPOINT ALFA)
**Objetivo**: CRUD completo de materias primas funcionando (RF01, RF02 parcial)

#### Tareas Técnicas
- [ ] Crear migración: tabla `raw_materials`
- [ ] Modelo `RawMaterial` con relaciones
- [ ] Controller `RawMaterialController` (API para Inertia)
- [ ] Vistas React: Listar, Crear, Editar, Eliminar materias primas
- [ ] Validaciones de formularios (Form Requests)
- [ ] Implementar búsqueda y filtros básicos
- [ ] Paginación de resultados

#### Entregables
- [ ] CRUD materias primas funcional
- [ ] Validaciones frontend y backend
- [ ] Mensajes de éxito/error con toast notifications
- [ ] Tests básicos (Pest) para endpoints

#### Checkpoint Alfa - Decisión
**Fecha**: 17 de abril 2026

**Criterios de éxito (todos deben cumplirse)**:
1. [ ] Puedo crear, editar, listar y eliminar materias primas
2. [ ] El sistema de autenticación funciona sin errores
3. [ ] Los roles restringen acceso correctamente
4. [ ] No hay errores de JavaScript en consola
5. [ ] La navegación es fluida (SPA feeling)

**Si falla el checkpoint**:
- No hay drama. Se abandona React/Inertia.
- Se migra el código funcional a Blade + Alpine.js.
- Se continúa el proyecto con tecnología conocida.
- Objetivo: garantizar entrega funcional en 3 meses.

---

## Fase 2: Módulos Core (Semanas 4-7)

### Semana 4: Bodegas y Ubicaciones
**Objetivo**: Gestión de multi-bodega y ubicaciones (RF03, RF04)

#### Tareas Técnicas
- [ ] CRUD bodegas (Neiva, Cali, sub-bodegas)
- [ ] Modelo `Warehouse` con relación a usuarios
- [ ] CRUD ubicaciones físicas dentro de bodegas
- [ ] Asignación de usuarios a bodegas específicas
- [ ] Selector de bodega en header (contexto global)

#### Entregables
- [ ] Admin puede crear bodegas y ubicaciones
- [ ] Usuario de Producción solo ve su bodega asignada
- [ ] Cambio de bodega actualiza contexto de la aplicación

---

### Semana 5-6: Inventario PEPS - Entradas
**Objetivo**: Registrar entradas de materia prima con lote, fecha vencimiento, PEPS (RF05, RF07)

#### Tareas Técnicas
- [ ] Crear migración: tabla `inventory_batches` (lote, cantidad, fecha_venc, costo_unitario)
- [ ] Modelo `InventoryBatch` con scopes PEPS
- [ ] Vista: Registrar entrada de materia prima
- [ ] Cálculo automático de costo promedio ponderado
- [ ] Generación automática de número de lote
- [ ] Alerta temprana de fechas de vencimiento

#### Query PEPS a implementar
```php
// Primeras entradas, primeras salidas
InventoryBatch::where('raw_material_id', $id)
    ->where('remaining_quantity', '>', 0)
    ->orderBy('entry_date')
    ->orderBy('created_at')
    ->get();
```

#### Entregables
- [ ] Formulario de entrada de inventario funcional
- [ ] Lotes visibles en detalle de materia prima
- [ ] Indicador visual de productos próximos a vencer
- [ ] Cálculo de costo promedio actualizado

---

### Semana 7: Formulaciones (CHECKPOINT BETA)
**Objetivo**: CRUD de formulaciones de pinturas (RF08, RF09)

#### Tareas Técnicas
- [ ] Migraciones: `formulations`, `formulation_items`
- [ ] Modelo `Formulation` con items (materias primas + cantidades)
- [ ] Vista: Crear formulación (selección dinámica de materias)
- [ ] Cálculo automático de costo de formulación
- [ ] Historial de versiones de formulaciones
- [ ] Duplicar formulación existente

#### Entregables
- [ ] CRUD formulaciones completo
- [ ] Cálculo automático de costo por litro/kg
- [ ] Visualización de árbol de materias en formulación
- [ ] Tests de cálculo de costos

#### Checkpoint Beta - Decisión
**Fecha**: 15 de mayo 2026

**Criterios**:
1. [ ] Inventario PEPS funcional (entradas)
2. [ ] Formulaciones calculan costos correctamente
3. [ ] Sistema multi-bodega operativo

**Si hay retraso**:
- Simplificar RF06 a "historial de consumo" (sin proyecciones)
- Posponer RF13 (órdenes de producción) para fase 3 si hay tiempo
- Dejar RF15/RF16 (Power BI) como exportación Excel simple

---

## Fase 3: Operación y Alertas (Semanas 8-10)

### Semana 8: Órdenes de Producción (RF13)
**Objetivo**: Crear órdenes de producción con consumo automático PEPS

#### Tareas Técnicas
- [ ] Migraciones: `production_orders`, `production_consumptions`
- [ ] Lógica de salida PEPS (asignar lotes automáticamente)
- [ ] Vista: Crear orden de producción
- [ ] Generación de producto terminado con costo calculado
- [ ] Estados de orden: Pendiente, En Proceso, Completada, Cancelada

#### Lógica PEPS Crítica (compleja)
```php
// Al crear orden de producción:
// 1. Calcular materiales necesarios
// 2. Asignar lotes por PEPS
// 3. Descontar de lotes más antiguos primero
// 4. Si no hay stock suficiente, rechazar orden
```

#### Entregables
- [ ] Crear orden de producción funcionando
- [ ] Descuento automático de inventario por PEPS
- [ ] Visualización de lotes utilizados en orden
- [ ] QR generado por orden (para seguimiento)

---

### Semana 9: Producto Terminado + QR (RF10, RF11, RF12)
**Objetivo**: Gestión de producto terminado y códigos QR

#### Tareas Técnicas
- [ ] Migración: `finished_products`, `containers`
- [ ] CRUD productos terminados
- [ ] Generación de códigos QR por envase
- [ ] Escaneo de QR (vista para móvil simple)
- [ ] Historial de movimientos de producto terminado

#### Entregables
- [ ] QR generado con información del lote
- [ ] Al escanear, muestra trazabilidad completa
- [ ] Consulta de stock por bodega para rol Comercial

---

### Semana 10: Alertas en Tiempo Real (RF14)
**Objetivo**: Sistema de alertas con Laravel Echo/Reverb

#### Tareas Técnicas
- [ ] Configurar eventos Laravel: `LowStockAlert`, `ExpirationAlert`
- [ ] Integrar Laravel Echo en componentes React
- [ ] Notificaciones toast en tiempo real
- [ ] Configurar umbrales de alerta por materia prima
- [ ] Panel de alertas pendientes

#### Tipos de Alertas
- Stock bajo (umbral configurable)
- Próximo vencimiento (30, 15, 7 días)
- Variación de costo > X%
- Orden de producción completada

#### Entregables
- [ ] Alertas visibles en tiempo real
- [ ] Contador de alertas en navbar
- [ ] Panel de alertas con acciones (ignorar, resolver)

---

## Fase 4: Cierre y Entrega (Semanas 11-12)

### Semana 11: Reportes y Exportación
**Objetivo**: Generación de reportes básicos (simplificación de RF15, RF16)

#### Tareas Técnicas
- [ ] Reporte de inventario actual (PDF/Excel)
- [ ] Reporte de movimientos por rango de fechas
- [ ] Reporte de costos por formulación
- [ ] Dashboard con gráficos básicos (Chart.js)
- [ ] Exportación Excel con Laravel Excel

#### Simplificación RF06
En vez de "proyecciones de re-compra con ML":
- Mostrar "consumo promedio mensual"
- Calcular "días de stock restantes"
- Sugerir "cantidad recomendada de compra"

#### Entregables
- [ ] 3 reportes exportables funcionando
- [ ] Dashboard con al menos 2 gráficos
- [ ] Filtros por fecha, bodega, producto

---

### Semana 12: Testing, Documentación y Deploy
**Objetivo**: Entregar sistema estable y documentado

#### Tareas
- [ ] Tests de funcionalidad crítica (Pest)
- [ ] Documentación técnica (cómo instalar)
- [ ] Manual de usuario por rol
- [ ] Deploy en servidor de producción (o demo)
- [ ] Limpieza de datos de prueba
- [ ] Presentación final

#### Entregables Finales
- [ ] Repositorio con código limpio y documentado
- [ ] Manual de instalación (`README.md` actualizado)
- [ ] Manual de usuario por rol (PDF o web)
- [ ] Demo funcional accesible
- [ ] Documentación de decisiones técnicas

---

## Calendario Visual

```
Marzo 2026                Abril 2026                Mayo 2026
┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│ Semana 1            │  │ Semana 4            │  │ Semana 7            │
│ - Setup Inertia     │  │ - Bodegas           │  │ - Checkpoint Beta   │
│ - PostgreSQL        │  │ - Ubicaciones       │  │ - Formulaciones     │
│ - Configuración     │  │                     │  │                     │
├─────────────────────┤  ├─────────────────────┤  ├─────────────────────┤
│ Semana 2            │  │ Semana 5            │  │ Semana 8            │
│ - Autenticación     │  │ - Inventario PEPS   │  │ - Órdenes Producción│
│ - Roles             │  │ - Entradas          │  │                     │
├─────────────────────┤  ├─────────────────────┤  ├─────────────────────┤
│ Semana 3            │  │ Semana 6            │  │ Semana 9            │
│ - CRUD Materias     │  │ - PEPS (cont.)      │  │ - Producto Terminado│
│ - CHECKPOINT ALFA   │  │ - Costos            │  │ - QR Codes          │
└─────────────────────┘  └─────────────────────┘  └─────────────────────┘

Junio 2026
┌─────────────────────┐
│ Semana 10           │
│ - Alertas Tiempo Real│
├─────────────────────┤
│ Semana 11           │
│ - Reportes          │
│ - Dashboard         │
├─────────────────────┤
│ Semana 12           │
│ - Testing           │
│ - Documentación     │
│ - ENTREGA           │
└─────────────────────┘
```

---

## Gestión de Riesgos

### Riesgos Técnicos

| Riesgo | Impacto | Probabilidad | Estrategia |
|--------|---------|--------------|------------|
| React/Inertia más complejo de lo esperado | Alto | Media | Checkpoint Semana 3, fallback a Blade |
| PEPS muy complejo para implementar | Alto | Baja | Consultar con tutor, simplificar lógica |
| PostgreSQL problemas en producción | Medio | Baja | Mantener MySQL como plan B |
| Reverb no funciona en hosting compartido | Medio | Alta | Usar Pusher gratuito o polling |

### Riesgos de Proyecto

| Riesgo | Impacto | Probabilidad | Estrategia |
|--------|---------|--------------|------------|
| Tiempo insuficiente | Alto | Media | Priorizar RF Esenciales, simplificar Ideales |
| Cambios de requerimientos | Medio | Alta | Documentar cambios, ajustar scope |
| Problemas de integración | Medio | Media | Commits frecuentes, probar en staging |

---

## Métricas de Éxito

### Métricas Técnicas
- [ ] Cobertura de tests > 60%
- [ ] Tiempo de carga de página < 2 segundos
- [ ] 0 errores críticos en producción
- [ ] 100% de RF Esenciales implementados

### Métricas de Negocio
- [ ] Usuarios pueden registrar entrada de materia en < 30 segundos
- [ ] Alertas se muestran en tiempo real (< 3 segundos)
- [ ] Generación de reporte < 10 segundos
- [ ] Sistema adoptado por al menos 3 usuarios de Pintech

---

## Notas de Implementación

### Decisión Tecnológica
**Stack seleccionado**: Laravel 12 + Inertia.js + React + PostgreSQL

**Justificación**: Equilibrio entre productividad (Laravel) y UX moderna (React) para dashboards y reportes. Inertia.js reduce la complejidad de separar frontend/backend.

**Restricción**: Checkpoint Semana 3 determina si se mantiene o se vuelve a Blade.

### Recursos de Aprendizaje
- Inertia.js: https://inertiajs.com/
- React básico: https://react.dev/learn
- Laravel + React: https://laravel.com/docs/12.x/frontend
- PostgreSQL: https://www.postgresql.org/docs/16/

### Comunicación
- Issues de GitHub para tareas semanales
- Commits con Conventional Commits (`feat:`, `fix:`, etc.)
- Rama `develop` para integración, `feature/PT-XXX` para funcionalidades

---

## Historial de Cambios

| Fecha | Versión | Cambios | Autor |
|-------|---------|---------|-------|
| 31/03/2026 | 1.0 | Creación inicial del plan | Andrés Cebay |

---

**Nota**: Este documento es una guía viva. Se debe actualizar semanalmente con el progreso real y ajustar fechas si es necesario.
