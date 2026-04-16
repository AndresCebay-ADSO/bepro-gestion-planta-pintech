# Componentes UI Reutilizables

Documentación de componentes React reutilizables para mantener consistencia visual y funcional en Pintech OS.

---

## FormattedNumber

Componente para mostrar números formateados según estándar colombiano (puntos para miles, coma para decimales).

**Ubicación:** `resources/js/components/formatted-number.tsx`

### Props

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `value` | `number \| string \| null` | - | Valor a formatear |
| `currency` | `boolean \| string` | `false` | Mostrar como moneda (símbolo $) |
| `maxDecimals` | `number` | `4` | Máximo de decimales a mostrar |
| `trimTrailingZeros` | `boolean` | `false` | Eliminar ceros al final (vistas detalle) |
| `percent` | `boolean` | `false` | Mostrar como porcentaje |
| `emptyValue` | `string` | `'-'` | Texto cuando el valor es null |
| `align` | `'left' \| 'right' \| 'center'` | `'left'` | Alineación del texto |
| `colorize` | `boolean` | `false` | Colorear según valor (positivo/negativo) |
| `bold` | `boolean` | `false` | Texto en negrita |
| `size` | `'sm' \| 'base' \| 'lg'` | `'base'` | Tamaño de fuente |

### Uso recomendado

```tsx
// Tablas (resumen) - máximo 2 decimales
<FormattedNumber value={price} currency maxDecimals={2} />

// Vistas de detalle - máxima precisión con trim
<FormattedNumber value={cost} currency maxDecimals={4} trimTrailingZeros />

// Cantidades con color según valor
<FormattedNumber value={stock} maxDecimals={2} colorize bold />

// Porcentajes
<FormattedNumber value={0.1543} percent maxDecimals={1} />
```

### Convención: Decimales según contexto

| Contexto | maxDecimals | trimTrailingZeros |
|----------|-------------|-------------------|
| Tablas (resumen) | `2` | No |
| Vistas detalle individual | `4` | Sí |
| Inputs de formulario | `4` | Sí |

---

## FormattedDate

Componente para mostrar fechas formateadas según estándar colombiano.

**Ubicación:** `resources/js/components/formatted-date.tsx`

### Props

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `value` | `string \| null` | - | Fecha en formato ISO o string |
| `format` | `'short' \| 'long' \| 'datetime' \| 'date'` | `'short'` | Formato de salida |
| `emptyValue` | `string` | `'-'` | Texto cuando no hay fecha |

### Formatos disponibles

| Formato | Ejemplo | Uso recomendado |
|---------|---------|-----------------|
| `short` | `15 ene 2024` | Tablas, listados |
| `long` | `15 enero 2024` | Vistas detalle |
| `datetime` | `15 ene 2024, 10:30` | Logs, auditoría |
| `date` | `15/01/2024` | Reportes formales |

### Uso

```tsx
// Tabla de movimientos
<FormattedDate value={movement.movement_date} />

// Fecha de vencimiento (puede ser null)
<FormattedDate value={batch.expiry_date} />

// Logs con hora
<FormattedDate value={log.created_at} format="datetime" />
```

---

## TableActions

Componente estandarizado para acciones de fila en tablas.

**Ubicación:** `resources/js/components/table-actions.tsx`

### Props

| Prop | Tipo | Descripción |
|------|------|-------------|
| `permissions` | `{ view?: boolean; edit?: boolean; delete?: boolean; }` | Permisos disponibles |
| `onView` | `() => void` | Handler para ver detalle |
| `onEdit` | `() => void` | Handler para editar |
| `onDelete` | `() => void` | Handler para eliminar |

### Convenciones

- Siempre usar en columnas de acción de tablas
- Nunca usar botones con texto visible
- Orden fijo: Ver → Editar → Eliminar
- Colores: Ver (neutral), Editar (amber), Eliminar (destructive)

### Uso

```tsx
<TableActions
    permissions={{ view: true, edit: can.update, delete: can.delete }}
    onView={() => router.get(route('items.show', item))}
    onEdit={() => router.get(route('items.edit', item))}
    onDelete={() => handleDelete(item)}
/>
```

---

## Guías de implementación

### 1. Siempre usar componentes formateadores

❌ **Incorrecto:**
```tsx
<td>{product.price}</td>
<td>{batch.entry_date}</td>
```

✅ **Correcto:**
```tsx
<td><FormattedNumber value={product.price} currency maxDecimals={2} /></td>
<td><FormattedDate value={batch.entry_date} /></td>
```

### 2. Consistencia en tablas vs detalle

| Dato | Tabla (listado) | Detalle individual |
|------|-----------------|-------------------|
| Precio | `maxDecimals={2}` | `maxDecimals={4} trimTrailingZeros` |
| Cantidad | `maxDecimals={2}` | `maxDecimals={4} trimTrailingZeros` |
| Fecha | `format="short"` | `format="long"` o `"datetime"` |

### 3. Evitar formateo manual

No usar `toLocaleString()`, `new Date()`, o concatenación de strings directamente en JSX. Siempre usar los componentes formateadores para mantener consistencia.

---

## Mantenimiento

Al agregar nuevos componentes formateadores:
1. Actualizar este documento
2. Agregar ejemplos de uso
3. Documentar props con JSDoc en el componente
4. Seguir el patrón de aceptar `null/undefined` con `emptyValue`
