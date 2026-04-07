# Sistema de Temas UI (Light / Dark)

## Objetivo
Mantener una interfaz consistente, legible y cómoda para uso prolongado en Pintech OS (incluyendo turnos nocturnos), evitando regresiones visuales por estilos hardcodeados.

## Estado actual (implementado)
- Tema global por clase: `html.dark`
- Tokens centralizados en CSS variables (`resources/css/app.css`)
- Persistencia de preferencia en `localStorage` + cookie
- Soporte SSR para evitar flash de tema incorrecto
- Refactor de pantallas clave a clases semánticas (layout, auth, dashboards, usuarios, settings)
- Auditoría de visibilidad de texto para evitar contenido invisible en light/dark

## Principios de diseño
- No usar blanco/negro puros en superficies principales
- Contraste suave pero claro (ergonomía visual en jornadas largas)
- Jerarquía evidente entre:
  - fondo base (`background`)
  - superficie de trabajo (`card`)
  - navegación lateral (`sidebar`)
  - navegación superior (`header`)
- Tokens semánticos como norma, colores directos como excepción justificada

## Arquitectura

### 1. Fuente de verdad de color
Archivo: `resources/css/app.css`

Tokens definidos en `:root` (light) y `.dark` (dark):
- Base: `--background`, `--foreground`
- Superficies: `--card`, `--popover`
- UI: `--border`, `--input`, `--ring`, `--accent`, `--muted`
- Estado/acción: `--primary`, `--destructive`
- Navegación: `--sidebar`, `--sidebar-*`

Mapeo a utilidades Tailwind v4 vía `@theme`.

### 2. Activación y persistencia
Archivo: `resources/js/hooks/use-appearance.tsx`

`initializeTheme()`:
- Lee `appearance` desde `localStorage`
- Persiste en cookie para SSR
- Aplica clase `dark` en `<html>`
- Define `data-theme` (`light | dark`) y `color-scheme`

### 3. SSR sin flash visual
Archivo: `resources/views/app.blade.php`

Se precarga la apariencia inicial en servidor + script inline para evitar parpadeo al hidratar.

## Jerarquía visual estándar
- Fondo app: `bg-background`
- Cards/containers: `bg-card border border-border shadow-sm`
- Header sticky: `bg-background/95 border-b border-border/80`
- Sidebar: tokens `bg-sidebar text-sidebar-foreground`
- Texto principal: `text-foreground`
- Texto secundario: `text-muted-foreground`

## Reglas de implementación

### Regla 1: texto siempre semántico (CRÍTICO)
Usar:
- `text-foreground` (contenido principal)
- `text-muted-foreground` (meta, hints, descripciones)

No usar en app pages/components:
- `text-black`, `text-white`, `text-gray-*`, `text-slate-*`

### Regla 2: superficies semánticas
Usar:
- `bg-background`, `bg-card`, `border-border`

Evitar:
- `bg-white`, `dark:bg-white`, `bg-gray-*` para contenedores principales

### Regla 3: formularios y tablas
- Inputs/select: `bg-background border-input text-foreground placeholder:text-muted-foreground`
- Focus: `focus:border-ring focus:ring-ring/...`
- Tablas: cabecera con `bg-muted/..`, filas con `hover:bg-muted/...`, texto sin hardcodes

### Regla 4: estados y feedback
- Error: `text-destructive`, `bg-destructive/10`, `border-destructive/25`
- Éxito/confirmación: preferir `text-primary` o esquema semántico equivalente

### Regla 5: shadcn primero
Priorizar componentes shadcn y resolver variaciones desde tokens, no con overrides aislados.

## Pulido visual aplicado
- Sidebar/Header refinados para contraste suave y consistencia
- Auth (`login`, `forgot-password`) migrado a surfaces y tipografía semántica
- Dashboards por rol alineados al sistema de tokens
- CRUD de usuarios con tablas/forms legibles en ambos modos
- Settings layout ajustado (spacing, panel lateral, contenedor de contenido)
- Overlays (`Dialog`, `Sheet`) suavizados para uso nocturno

## Checklist obligatorio para PRs UI
- No introduce colores de texto hardcodeados en páginas/componentes
- No introduce `bg-white`/`dark:bg-white` en superficies principales
- Usa `text-foreground` o `text-muted-foreground` según jerarquía
- Formularios mantienen contraste en light/dark
- Tablas mantienen legibilidad en light/dark
- Focus states visibles con `ring`
- Revisado visualmente en ambos modos

## QA rápido recomendado
1. Cambiar tema varias veces desde el toggle (light/dark/system)
2. Revisar:
   - login / forgot-password
   - sidebar / header
   - dashboards
   - CRUD usuarios (tabla + forms)
   - dialogs/sheets
3. Confirmar que ningún texto se “pierde” por contraste
4. Ejecutar:
   - `npm run types:check`
   - `npx eslint <archivos_modificados>`

## Errores comunes a evitar
- Mezclar token semántico con colores directos en el mismo componente
- Forzar modo claro en pantallas auth
- Usar `text-white` sobre fondos no controlados
- Ajustar cada componente manualmente en lugar de corregir tokens base

## Mantenimiento
Cuando cambie branding o se quiera recalibrar contraste:
1. Ajustar primero `resources/css/app.css`
2. Validar impacto en light/dark en pantallas clave
3. Evitar cambios masivos por componente si el problema es de token base
