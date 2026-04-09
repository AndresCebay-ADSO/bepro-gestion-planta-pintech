# Justificación Tecnológica — Sistema de Gestión de Planta Pintech

> **Versión 2.0 — Decisión Final**
> Fecha: 31 de marzo 2026
> Decisión tomada después de análisis comparativo y evaluación de riesgos

---

## 1. Resumen Ejecutivo

Después de evaluar múltiples alternativas y considerando el contexto del proyecto (práctica profesional ADSO, 3 meses de desarrollo, 16 requerimientos funcionales), se ha decidido adoptar el siguiente stack:

| Capa | Tecnología | Justificación Clave |
|------|------------|---------------------|
| **Backend** | Laravel 12 | Productividad, ecosistema maduro, mejor documentación en español |
| **Frontend** | React + Inertia.js | UX moderna para dashboards, sin separar backend/frontend |
| **Base de Datos** | PostgreSQL | Window functions para PEPS, replicación multi-sede |
| **Tiempo Real** | Laravel Echo + Reverb | WebSockets nativos, sin costos de terceros |
| **Estilos** | Tailwind CSS v4 | Ya configurado, utility-first |

**Estrategia de mitigación de riesgos:** Checkpoint obligatorio en Semana 3 (17 de abril). Si autenticación + inventario básico no funcionan con React, se migra a Blade + Alpine.js sin penalización.

---

## 2. Análisis de Requerimientos Clave

Del PT-ERS-01 se identifican necesidades críticas:

| Requerimiento | Implicación Tecnológica | Prioridad |
|--------------|------------------------|-----------|
| **Control PEPS** | Queries complejas con window functions (PostgreSQL) | Esencial |
| **Alertas tiempo real** | WebSockets para notificaciones push | Esencial |
| **Multi-bodega, multi-sede** | Arquitectura distribuible, replicación DB | Esencial |
| **Cálculo de costos** | Procesamiento batch, background jobs | Esencial |
| **QR por envase** | Generación y escaneo de códigos | Esencial |
| **Reportes y analytics** | Dashboards interactivos, exportación Excel | Ideal |
| **Curvas de consumo y proyecciones** | Análisis de datos (simplificado a historial) | Ideal |

---

## 3. Alternativas Evaluadas

### Opción A: TALL Stack (Laravel + Livewire + Alpine.js)

**Ventajas:**
- Curva de aprendizaje mínima
- Desarrollo más rápido inicialmente
- Un solo lenguaje (PHP)

**Desventajas:**
- UX limitada para dashboards complejos
- Menor escalabilidad si se requiere app móvil futura
- Livewire no ofrece ventaja significativa sobre React si igual hay que aprender algo nuevo

**Veredicto:** Opción de respaldo si el checkpoint de Semana 3 falla.

---

### Opción B: Laravel + Inertia.js + React (SELECCIONADA)

**Ventajas:**
- Backend robusto con Laravel (lógica PEPS, autenticación, roles)
- Frontend moderno sin API REST separada (Inertia simplifica)
- Dashboards interactivos reales (React)
- Escalabilidad progresiva (puede separarse en API pura después)
- Laravel Reverb incluido (WebSockets sin costos extras)

**Desventajas:**
- Curva de aprendizaje de React (mitigada con asistencia de IA)
- Setup inicial más complejo

**Estrategia de mitigación:** Checkpoint Semana 3 con fallback garantizado.

---

### Opción C: NestJS + React + PostgreSQL

**Ventajas:**
- TypeScript end-to-end
- Arquitectura enterprise
- Mejor performance para tiempo real

**Desventajas:**
- Curva de aprendizaje pronunciada para practicante ADSO
- 30-40% más lento en desarrollo
- Menor ecosistema en español

**Veredicto:** Descartado por complejidad innecesaria para el alcance.

---

## 4. Decisión Final y Justificación

### Stack Seleccionado

```
┌─────────────────────────────────────────┐
│  Laravel 12                             │
│  ├── Inertia.js (conexión React)        │
│  ├── React (UI Components)              │
│  ├── PostgreSQL 16 (Base de datos)      │
│  ├── Laravel Echo + Reverb (WebSockets) │
│  └── Tailwind CSS v4 (Estilos)          │
└─────────────────────────────────────────┘
```

### Razones de la Decisión

#### 1. Equilibrio Productividad vs. Modernidad
Laravel mantiene la velocidad de desarrollo en el backend (autenticación, roles, ORM), mientras React proporciona la experiencia de usuario que el área comercial necesita para consultar inventarios y ver reportes.

#### 2. Inertia.js como Puente
Inertia permite escribir aplicaciones de una sola página (SPA) sin construir una API REST. Los controllers Laravel normales devuelven `Inertia::render()` en lugar de `view()`, manteniendo la simplicidad de Laravel con la reactividad de React.

#### 3. PostgreSQL para PEPS
El método PEPS (Primeras Entradas, Primeras Salidas) requiere consultas complejas con window functions:

```sql
-- Ejemplo: Calcular lotes disponibles por PEPS
SELECT *,
       SUM(remaining_quantity) OVER (
           PARTITION BY raw_material_id
           ORDER BY entry_date, created_at
       ) as running_total
FROM inventory_batches
WHERE remaining_quantity > 0;
```

PostgreSQL soporta estas consultas nativamente y mejor que MySQL.

#### 4. Laravel Reverb para Tiempo Real
Laravel 12 incluye Reverb, un servidor WebSocket nativo. Esto permite:
- Alertas de stock bajo en tiempo real
- Notificaciones de vencimientos
- Actualización de dashboards sin recarga

Sin costos adicionales de Pusher o servicios externos.

#### 5. Asistencia de IA
El riesgo de la curva de aprendizaje de React se mitiga significativamente con Claude y otras IAs que aceleran la generación de componentes y resolución de errores.

---

## 5. Plan de Mitigación de Riesgos

### Checkpoint Semana 3 — Línea Roja

**Fecha:** 17 de abril 2026

**Criterios de Éxito (todos deben cumplirse):**
1. Autenticación completa funcionando (login, roles, middleware)
2. CRUD de materias primas operativo
3. Sin errores de JavaScript en consola
4. Navegación fluida (experiencia SPA)

**Si falla el checkpoint:**
- Se abandona React/Inertia inmediatamente
- Se migra el código funcional a Blade + Alpine.js
- Se continúa con el plan original sin penalización
- El objetivo es garantizar entrega funcional, no casarse con el stack

> **"No te cases con el stack, cásate con el resultado."**

---

## 6. Simplificaciones Aceptadas

Para garantizar entrega en 3 meses, se simplifican los siguientes requerimientos:

| Requerimiento Original | Simplificación | Justificación |
|------------------------|----------------|---------------|
| RF06: Proyecciones de re-compra con análisis predictivo | Solo historial de consumo + días de stock restantes | Complejidad de ML fuera de alcance temporal |
| RF15: Integración con Power BI | Exportación a Excel/PDF con Laravel Excel | Suficiente para MVP |
| RF16: Dashboard ejecutivo avanzado | Dashboard con 3-4 gráficos básicos (Chart.js) | Priorizar funcionalidad sobre sofisticación |
| TypeScript en React | JavaScript plano | Reducir complejidad de aprendizaje |

---

## 7. Arquitectura Propuesta

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENTE (Navegador)                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  React 18 + Inertia.js                               │   │
│  │  ├── Componentes reutilizables (tablas, formularios) │   │
│  │  ├── Chart.js (gráficos)                           │   │
│  │  └── Laravel Echo client (alertas tiempo real)     │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL 12 (Backend)                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  Controllers│  │   Services  │  │     Models          │  │
│  │  (Inertia)  │──│  (Negocio)  │──│  (Eloquent/PEPS)    │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  Auth/Roles │  │   Queues    │  │   Events            │  │
│  │  (Spatie)   │  │  (Database) │  │  (Reverb/WS)        │  │
│  └─────────────┘  └─────────────┘  └─────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    POSTGRESQL 16                            │
│  - Inventario PEPS (window functions)                       │
│  - Replicación lógica Neiva ↔ Cali (futuro)                │
│  - Índices optimizados para consultas frecuentes           │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. Paquetes y Dependencias

### Backend (Composer)
```json
{
  "require": {
    "laravel/framework": "^12.0",
    "inertiajs/inertia-laravel": "^1.0",
    "spatie/laravel-permission": "^6.0",
    "simplesoftwareio/simple-qrcode": "~4",
    "maatwebsite/excel": "^3.1",
    "barryvdh/laravel-dompdf": "^3.0"
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "laravel/pint": "^1.24"
  }
}
```

### Frontend (NPM)
```json
{
  "dependencies": {
    "@inertiajs/react": "^1.0",
    "react": "^18.0",
    "react-dom": "^18.0",
    "laravel-echo": "^1.15",
    "pusher-js": "^8.0",
    "chart.js": "^4.0",
    "react-chartjs-2": "^5.0"
  }
}
```

---

## 9. Comparativa de Tecnologías

| Criterio | TALL Stack | Laravel+React (Seleccionado) | NestJS+React |
|----------|------------|------------------------------|--------------|
| **Velocidad inicial** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Curva aprendizaje** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **UX/Dashboards** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Tiempo real** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Queries PEPS** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Escalabilidad** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Ecosistema** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Recursos español** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ |

---

## 10. Conclusión

La decisión de adoptar **Laravel 12 + Inertia.js + React + PostgreSQL** se basa en:

1. **Viabilidad técnica:** El stack puede entregar todos los requerimientos esenciales en 3 meses.
2. **Mitigación de riesgos:** El checkpoint de Semana 3 garantiza que, si React se vuelve un obstáculo, hay un plan B probado (Blade + Alpine.js).
3. **Valor de aprendizaje:** El practicante adquiere experiencia en React, tecnología altamente demandada, sin sacrificar el proyecto.
4. **Alineación con negocio:** Pintech obtiene un sistema moderno, usable y escalable que resuelve sus problemas reales de gestión de planta.
5. **Costos controlados:** Uso de herramientas open-source sin dependencias de servicios pagos (WebSockets nativos, generación de QR local, exportación Excel).

El éxito del proyecto se medirá no por la tecnología utilizada, sino por la adopción real del sistema por parte de los usuarios de Pintech Colombia S.A.S.

---

## Historial de Versiones

| Versión | Fecha | Cambios | Autor |
|---------|-------|---------|-------|
| 1.0 | 31/03/2026 | Análisis inicial de stacks | Andrés Cebay |
| 2.0 | 31/03/2026 | Decisión final, estrategia checkpoint, simplificaciones | Andrés Cebay |

---

## Referencias

- PT-PP-01: Planteamiento del Problema — Pintech Colombia S.A.S (2026)
- PT-ERS-01: Especificación de Requerimientos de Software — Pintech Colombia S.A.S (2026)
- PLAN_DESARROLLO.md: Plan semana a semana del proyecto
- Laravel Documentation: https://laravel.com/docs/12.x
- Inertia.js Documentation: https://inertiajs.com/
- React Documentation: https://react.dev/learn
