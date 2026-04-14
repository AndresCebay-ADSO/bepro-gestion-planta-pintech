# Pintech OS - Visión General del Software

Este documento proporciona una descripción exhaustiva del sistema Pintech OS, su arquitectura, funcionalidades implementadas y estado actual del desarrollo (Versión 1.3 - Abril 2026).

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
- **Materias Primas**: Registro detallado con códigos únicos, precios actuales/anteriores y stock mínimo.
- **Unidades de Medida**: Catálogo normalizado para conversiones y precisión decimal en cantidades.
- **Gestión de Bodegas**: Soporte multi-bodega con asignación de usuarios específicos por bodega.
- **Lotes de Inventario**: Trazabilidad por fecha de entrada, precio de compra y fecha de vencimiento.
- **Alertas**: Notificaciones visuales de stock bajo y vencimientos próximos.

### 2.3 Producción (En Desarrollo)
- **Fórmulas/Recetas**: Definición de composición de productos terminados por versiones.
- **Órdenes de Producción**: Seguimiento del ciclo de vida (Pendiente -> En Proceso -> Finalizada).
- **Cálculo de Costos**: Integración con precios de materias primas para determinación de márgenes.

### 2.4 Catálogo de Productos (Variantes SKU)
- **Producto Base + Variantes**: Se separa el producto base de sus referencias comerciales (SKU).
- **SKU por Presentación**: Soporte para presentación, color, acabado, tipo de base y sistema de componentes (`1K`, `2K`, `KIT`).
- **Compatibilidad Progresiva**: Flujos operativos permiten transición gradual, manteniendo `product_id` y agregando `product_variant_id`.
- **Operación Variante-Primero**: Inventario terminado, traslados y precios soportan carga directa por variante.

### 2.5 Sistema de Auditoría (Nuevo v1.2)
- **Trazabilidad Total**: Registro de quién, cuándo y qué cambió en modelos críticos (`User`, `RawMaterial`, `Warehouse`).
- **Registro de Seguridad**: Auditoría de inicios de sesión fallidos y cambios de roles.
- **Políticas de Retención**: Limpieza automática de registros mayores a 180 días para optimización de base de datos.
- **Visualización**: Widgets de actividad reciente en Dashboards y panel administrativo completo.

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

---

## 5. Contacto y Soporte

Desarrollado para **Pintech Colombia S.A.S**.  
*Versión del Documento: 1.3*  
*Fecha de última actualización: 14 de Abril, 2026*
