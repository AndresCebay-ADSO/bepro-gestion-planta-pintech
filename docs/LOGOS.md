# Pintech Brand Logo Assets

> **Nota importante:** Los logos oficiales fueron proporcionados por el departamento de diseño.
> "AL MOMENTO DE APROBAR EL DISEÑO VERIFIQUE DATOS, MEDIDAS, ORTOGRAFÍA, ETC.
> DESPUÉS DE APROBADO DAREMOS POR ENTENDIDO QUE ES RESPONSABILIDAD TOTAL DEL CLIENTE"

## 📁 Ubicación de Archivos

Los logos oficiales de Pintech Colombia S.A.S se encuentran en:

```
public/images/
├── logo-pintech.png    # Logo principal en formato PNG (507 KB)
└── logo-pintech.svg    # Logo principal en formato SVG vectorial (165 KB)
```

## 📦 Archivos de Origen

Los archivos originales entregados por diseño se mantienen en:

```
docs/logos/
├── logo vector pintech LINEAS 2026.png    # Archivo original PNG
└── logo vector pintech LINEAS 2026.svg    # Archivo original SVG
```

## 🎨 Identidad Cromática

El logo utiliza la siguiente paleta de colores:

| Color       | Uso                        | Valor Aproximado     |
| ----------- | -------------------------- | -------------------- |
| Gris oscuro | Texto principal            | `#373435`            |
| Gris medio  | Elementos secundarios      | `#727376`, `#848688` |
| Blanco      | Fondos, espacios en blanco | `#FEFEFE`            |

## 🚀 Uso en la Aplicación

### Componente Logo Principal (Sidebar)

```tsx
import AppLogo from '@/components/app-logo';

// En tu layout o componente
<AppLogo />;
```

### Componente Icono de Logo

```tsx
import AppLogoIcon from '@/components/app-logo-icon';

// Usar como imagen con props adicionales
<AppLogoIcon className="h-8 w-auto" />;
```

### Uso Directo en HTML/Blade

```html
<!-- Logo PNG -->
<img src="/images/logo-pintech.png" alt="Pintech logo" />

<!-- Logo SVG -->
<img src="/images/logo-pintech.svg" alt="Pintech logo" />
```

## 🔄 Lugares donde se utiliza el logo

| Ubicación          | Archivo                                             | Uso                            |
| ------------------ | --------------------------------------------------- | ------------------------------ |
| Sidebar            | `resources/js/components/app-sidebar.tsx`           | Logo principal                 |
| Sidebar Header     | `resources/js/components/app-sidebar-header.tsx`    | Logo en header                 |
| Login              | `resources/js/pages/auth/login.tsx`                 | Logo en panel de autenticación |
| Forgot Password    | `resources/js/pages/auth/forgot-password.tsx`       | Logo en recuperación           |
| Auth Split Layout  | `resources/js/layouts/auth/auth-split-layout.tsx`   | Logo en layouts de auth        |
| Auth Simple Layout | `resources/js/layouts/auth/auth-simple-layout.tsx`  | Logo en layouts de auth        |
| Auth Card Layout   | `resources/js/layouts/auth/auth-card-layout.tsx`    | Logo en layouts de auth        |
| Email Header       | `resources/views/vendor/mail/html/header.blade.php` | Logo en emails                 |
| App Header         | `resources/js/components/app-header.tsx`            | Logo en header de app          |
| User Menu          | `resources/js/components/user-menu-content.tsx`     | Logo en menú de usuario        |

## 📋 Especificaciones Técnicas

- **Formato PNG**: 27.94 cm × 21.59 cm, fondo transparente
- **Formato SVG**: Vectorial escalable, compatible con CorelDRAW
- **Diseño**: Logo LÍNEAS 2026 versión oficial

## 🔧 Modificaciones Realizadas (2026-04-14)

A continuación se detallan los cambios realizados para implementar el logo oficial 2026:

### 1. Archivos de Logo Reemplazados

| Archivo                          | Razón del Cambio                                                                                                                                                 |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `public/images/logo-pintech.png` | Reemplazado versión anterior (34 KB) por logo oficial 2026 (507 KB). El archivo anterior era temporal y no representaba la identidad visual oficial de la marca. |
| `public/images/logo-pintech.svg` | **NUEVO** - Agregado logo vectorial oficial para usos donde se requiera escalabilidad sin pérdida de calidad.                                                    |

### 2. Componentes de React Modificados

| Archivo                                            | Cambio Realizado                                                                                                             | Razón                                                                                           |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `resources/js/components/app-logo.tsx`             | Agregada documentación JSDoc con referencia a este documento y ubicación de archivos.                                        | Mejorar mantenibilidad y rastreabilidad del asset.                                              |
| `resources/js/components/app-logo-icon.tsx`        | **Refactorizado completamente**: cambiado de SVG inline genérico a componente que renderiza imagen PNG del logo oficial.     | El SVG anterior era un placeholder genérico, no el logo de Pintech. Ahora usa el asset oficial. |
| `resources/js/layouts/auth/auth-split-layout.tsx`  | Actualizada referencia de `/favicon-logo.png` a `/images/logo-pintech.png` y ajustado tamaño de `h-10 w-10` a `h-16 w-auto`. | Unificar el uso del logo oficial en todos los layouts de autenticación con tamaño apropiado.    |
| `resources/js/layouts/auth/auth-simple-layout.tsx` | Actualizada referencia de `/favicon-logo.png` a `/images/logo-pintech.png` y ajustado tamaño de `h-40 w-40` a `h-24 w-auto`. | Logo demasiado grande para el contexto; se redujo manteniendo proporción con `w-auto`.          |
| `resources/js/layouts/auth/auth-card-layout.tsx`   | Actualizada referencia de `/favicon-logo.png` a `/images/logo-pintech.png` y ajustado tamaño de `h-10 w-10` a `h-16 w-auto`. | Consistencia visual con otros layouts de autenticación.                                         |

### 3. Vistas de Email Modificadas

| Archivo                                             | Cambio Realizado                                                                                                                        | Razón                                                                                                     |
| --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `resources/views/vendor/mail/html/header.blade.php` | Actualizada ruta de `/favicon-logo.png` a `/images/logo-pintech.png` y cambiado `width:64px` por `width:auto` para mantener proporción. | Los emails deben mostrar el logo oficial de la marca, no el favicon. El ancho fijo distorsionaba el logo. |

### 4. Archivos de Favicon/Iconos Actualizados

| Archivo                       | Cambio Realizado                                | Razón                                                                                     |
| ----------------------------- | ----------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `public/favicon-logo.png`     | **Reemplazado** - Copia del nuevo logo oficial. | Mantener compatibilidad con referencias existentes mientras se transiciona al nuevo path. |
| `public/apple-touch-icon.png` | **Reemplazado** - Copia del nuevo logo oficial. | Icono para dispositivos iOS debe usar el logo oficial de la marca.                        |
| `public/favicon.ico`          | **Eliminado**                                   | Archivo obsoleto, no se referencia en la aplicación.                                      |
| `public/favicon.svg`          | **Eliminado**                                   | Archivo obsoleto, no se referencia en la aplicación.                                      |

## 🗑️ Archivos Eliminados

Los siguientes archivos anteriores fueron reemplazados:

- `public/images/logo-pintech.png` (versión anterior, 34 KB) → Reemplazado por versión 2026
- `public/favicon.ico` → Eliminado, no utilizado
- `public/favicon.svg` → Eliminado, no utilizado
- `public/apple-touch-icon.png` (versión anterior) → Reemplazado por versión 2026

## 📝 Historial de Cambios

| Fecha      | Cambio                        | Responsable  |
| ---------- | ----------------------------- | ------------ |
| 2026-04-14 | Migración a logo oficial 2026 | Andrés Cebay |

## ⚠️ Notas de Mantenimiento

1. **No modificar los archivos SVG manualmente** - Fueron creados con CorelDRAW
2. **Para actualizaciones de marca**, contactar al departamento de diseño
3. **Verificar ortografía y datos** antes de aprobar cualquier cambio en los logos
4. **Todos los cambios aprobados** son responsabilidad del cliente según comunicación oficial

---

**Pintech Colombia S.A.S** - Manual de Marca Digital
