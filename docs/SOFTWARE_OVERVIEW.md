# Pintech OS - Visión General del Software

Este documento proporciona una descripción exhaustiva del sistema Pintech OS, su arquitectura, funcionalidades implementadas y estado actual del desarrollo (Versión 1.4 - Mayo 2026).

---

## Resumen del Proyecto

Pintech OS es una plataforma empresarial integral para la gestión de operaciones de producción de pinturas y recubrimientos. Diseñado para empresas del sector, unifica la gestión de inventarios de materias primas y productos terminados, producción, catálogo de productos con variantes SKU, y auditoría completa de operaciones.

### Características Clave

- **Arquitectura Inertia.js**: Desarrollo frontend con React sin necesidad de recargas de página
- **Gestión de Inventario Inteligente**: Soporte multi-bodega con lotes y precios FIFO
- **Catálogo de Productos con Variantes**: Separación entre producto base y referencias comerciales (SKU)
- **Auditoría Completa**: Trazabilidad de todos los cambios en el sistema
- **Seguridad RBAC**: Control de acceso por roles (admin, produccion, comercial)
- **Operaciones Optimizadas**: Flujos variantes-primero para transición gradual

---

---

## Cambios Recientes

### v1.5 - Sistema de Formateo y UX (Abril 2026)

- **Nuevo componente FormattedDate**: Formateo consistente de fechas en todo el sistema (`15 ene 2024`, `15 ene 2024, 10:30`)
- **Mejora en FormattedNumber**: Soporte para `maxDecimals` y `trimTrailingZeros` según contexto (tablas vs detalle)
- **Paginación optimizada**: Todos los controladores actualizados con `onEachSide(1)` para mostrar máximo 7 botones de paginación
- **Corrección de decimales**: Tablas muestran 2 decimales (resumen), vistas de detalle muestran precisión completa (4 decimales)
- **Documentación de componentes**: Nuevo documento `COMPONENTES_UI.md` con guías de uso

### v1.4 - Mejoras en Gestión de Variantes (Mayo 2026)

- **Reestructuración SKU**: Separación completa entre producto base y referencias comerciales (SKU), permitiendo gestión independiente de definiciones técnicas y presentaciones de mercado.
- **Optimización de Inventario**: Backend optimizado para operaciones variantes-primero, con validaciones de integridad referencial en la carga de stock.
- **Mejora en Migraciones**: Consolidación de scripts de migración para eliminar redundancias y mejorar el rendimiento.
- **Validación de Backfill**: Implementación de verificación de datos existentes antes de migrar a la nueva estructura de variantes.

---

## 1. Arquitectura y Stack Tecnológico

Pintech OS está construido bajo una arquitectura moderna de Aplicación de Página Única (SPA) utilizando el stack **Inertia.js**, que permite desarrollar interfaces reactivas con la robustez de un backend tradicional.

- **Backend**: [Laravel 13](https://laravel.com) (PHP 8.3+)
- **Frontend**: [React 19](https://react.dev) con [Typescript](https://www.typescriptlang.org/)
- **Comunicación**: [Inertia.js v3](https://inertiajs.com) (Protocolo de datos sin API REST intermedia expuesta)
- **Estilos**: [Tailwind CSS v4](https://tailwindcss.com/)
- **Base de Datos**: PostgreSQL 16
- **Autenticación**: [Laravel Fortify](https://laravel.com/docs/11.x/fortify) (Backend agnóstico)
- **Control de Acceso**: [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- **Auditoría**: [Spatie Laravel Activitylog](https://spatie.be/docs/laravel-activitylog)

---

## 2. Módulos y Funcionalidades

### 2.1 Gestión de Usuarios y Seguridad
- **Autenticación Completa**: Login, registro (opcional), recuperación de contraseña con notificaciones personalizadas.
- **Control de Acceso Basado en Roles (RBAC)**:
    - `admin`: Acceso total al sistema y configuraciones.
    - `produccion`: Gestión de inventarios, órdenes y fórmulas.
    - `comercial`: Consulta de disponibilidad y catálogos.
- **Perfiles de Usuario**: Edición de información personal y preferencias de seguridad.

### 2.2 Gestión de Inventario (Muestras Primas)
- **Materias Primas**: Registro detallado con códigos únicos, precios actuales/anteriores, precio histórico y stock mínimo.
- **Productos Terminados**: Inventario de productos finales con soporte por lotes, precios FIFO y fechas de vencimiento.
- **Unidades de Medida**: Catálogo normalizado para conversiones y precisión decimal en cantidades.
- **Gestión de Bodegas**: Soporte multi-bodega con asignación de usuarios específicos por bodega.
- **Lotes de Inventario**: Trazabilidad completa por fecha de entrada, precio de compra, fecha de vencimiento y origen.
- **Alertas Inteligentes**: Notificaciones visuales de stock bajo, vencimientos próximos y alertas de stock negativo.
- **Operaciones por Variante**: Todos los lotes están vinculados a `product_variant_id` para trazabilidad precisa.

### 2.3 Producción (En Desarrollo)
- **Fórmulas/Recetas**: Definición de composición de productos terminados por versiones.
- **Órdenes de Producción**: Seguimiento del ciclo de vida (Pendiente -> En Proceso -> Finalizada).
- **Cálculo de Costos**: Integración con precios de materias primas para determinación de márgenes.

### 2.4 Catálogo de Productos con Variantes SKU
- **Arquitectura Producto Base + Variantes**: Separación clara entre el producto base (definición técnica) y sus referencias comerciales (SKU) para cada presentación.
- **Atributos de Variantes**: Soporte completo para: presentación, color, acabado, tipo de base, sistema de componentes (`1K`, `2K`, `KIT`) y viscosidad.
- **Flujo Variante-Primero**: Operaciones de inventario, traslados y gestión de precios están diseñadas primero por variante, permitiendo una carga directa y precisa del stock.
- **Transición Gradual**: Sistema compatible con operaciones híbridas que mantienen referencias al `product_id` mientras se adoptan las variantes (`product_variant_id`), permitiendo migración progresiva.
- **CRUD Completo**: Creación, edición, listado y eliminación de variantes con validaciones de consistencia.
- **Backend Optimizado**: Endpoints especializados para gestión de variantes con validaciones de integridad referencial.

### 2.5 Sistema de Auditoría y Seguridad (v1.4-v1.5)
- **Trazabilidad Total**: Registro completo de quién, cuándo y qué cambió en todos los modelos del sistema (`User`, `RawMaterial`, `ProductVariant`, `Warehouse`, `InventoryTransaction`).
- **Registro de Seguridad**: Auditoría automática de inicios de sesión fallidos, cambios de roles y actividades sospechosas.
- **Políticas de Retención**: Limpieza automática de registros mayores a 180 días para optimización de rendimiento.
- **Widget de Actividad**: Componente de UI que muestra las 5 actividades recientes del usuario con filtros por tipo y fecha.
- **Panel Administrativo**: Vistas detalladas con filtros avanzados, exportación a Excel y visualización de tendencias.
- **Historial de Cambios**: Registro del valor anterior y nuevo para cada operación de actualización.
- **Mejora v1.5**: Fechas formateadas consistentemente en logs de auditoría (`dd MMM yyyy, HH:mm`)

---

## 3. Interfaz de Usuario (UX/UI)

- **Diseño Premium**: Interfaz limpia, industrial, orientada a la eficiencia operativa.
- **Navegación Inteligente**: Barra lateral dinámica que se ajusta según el rol del usuario.
- **Modo Oscuro/Claro**: Soporte nativo para preferencias del sistema.
- **Multi-tasking**: Componentes optimizados para carga rápida y manejo de grandes volúmenes de datos.
- **Selector de Bodega Global**: Permite al usuario cambiar su contexto de trabajo actual de forma instantánea.

---

## 4. Estándares y Mantenibilidad

- **Consolidación de Migraciones**: Esquema de base de datos optimizado sin redundancias históricas.
- **Pruebas Automatizadas**: Suite de tests con [Pest PHP](https://pestphp.com) para validación de lógica crítica.
- **Internacionalización**: Aplicación totalmente traducida al español (es).
- **Documentación Viva**: Mantención de actas de actualización y manuales técnicos en el directorio `docs/`.
- **Componentes UI Documentados**: Guía de uso de `FormattedNumber`, `FormattedDate`, `TableActions` en `docs/COMPONENTES_UI.md`.
- **Paginación Estandarizada**: Todos los listados usan `->paginate(15)->onEachSide(1)->withQueryString()` para UX consistente.

---

## 5. Contacto y Soporte

Desarrollado para **Pintech Colombia S.A.S**.  
*Versión del Documento: 1.5*  
*Fecha de última actualización: Abril, 2026*
