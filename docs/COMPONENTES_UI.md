# Componentes UI Reutilizables

Documentación de componentes React reutilizables para mantener consistencia visual y funcional en Pintech OS.

---

## DecimalInput e IntegerInput (campos numéricos)

Todo número que escribe el usuario usa uno de estos dos componentes; nunca `<Input type="number">`. Las reglas viven
en `resources/js/lib/numeric-input.ts` (con sus pruebas en `numeric-input.test.ts`) y los eventos en
`resources/js/hooks/use-numeric-field.ts`. El contrato visual está en `DESIGN.md` §7.

| Componente | Para | Teclado en el celular |
|------------|------|-----------------------|
| `DecimalInput` | Cantidades, costos, precios, porcentajes | Decimal (`allowNegative`: completo, porque el de iOS no trae el signo menos) |
| `IntegerInput` | Unidades, envases, etiquetas | Numérico |

### Comportamiento

- **El formulario guarda texto sin miles** (`"1200,5"`), nunca un `number`: así no aparecen errores de punto
  flotante (`0.1 + 0.2`). Se convierte una sola vez al enviar, con `toDecimalPayload` / `withDecimalPayloads`.
- **El campo muestra los miles mientras se escribe** (`1.200,5`). El punto que se teclea es **decimal** y entra como
  coma; los puntos de miles los pone el campo.
- **Pegar:** lo pegado viene de algo ya formateado, así que una agrupación de miles bien formada se lee como miles
  (`1.250` → 1250, `1.250.000,75`, `1,250,000.75`). Un separador suelto es decimal (`1250000.75`, `0.125`). Una
  mezcla mal formada (`1.5.2`) queda escrita con error: nunca se quitan separadores adivinando. Un solo grupo con
  punto (`1.250`) se registra como miles y el campo lo avisa, por si era un decimal (salvo en campos sin decimales). Lo pegado sin texto (una imagen)
  no hace nada.
- **Límites:** los decimales y dígitos de su columna (`maxDecimals`, `maxIntegerDigits`, `maxDigits`). Lo que no cabe
  (letras, un decimal de más) se rechaza completo. Un valor guardado que no cabe se ve tal cual, con aviso, y se
  puede borrar.
- **Errores:** un segundo separador (o uno en un entero) queda escrito y marcado; nunca se borra un carácter para que
  el número «quepa», porque eso cambiaría su valor sin aviso.
- **Aviso:** `1.250` tecleado se registra como 1,25 y el campo lo dice con el número en palabras.
- **Ceros a la izquierda** (`0125`): se ven sin agrupar, porque `0.125` parecería un decimal.
- **`onValueChange` actualiza el estado en el mismo evento** (`form.setData`): el campo pone el cursor antes de que
  React vuelva a pintar. Con `debounce` o `startTransition`, el cursor saltaría.
- **Casos límite aceptados:** deshacer con Ctrl+Z no funciona dentro del campo; un punto tecleado justo donde se ve
  el de miles en un entero se ve igual, pero marcado con error.

```tsx
<DecimalInput
    id="quantity"
    value={form.data.quantity}
    onValueChange={(value) => form.setData('quantity', value)}
    maxDecimals={4}
    placeholder="0,00"
/>
```

### Validación en backend (PHP/Laravel)

El servidor vuelve a validar tipo y escala (`NumberType`), con los mensajes en español: el campo ayuda, pero no es
la validación.

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

## DataListing y RowActions (listados)

Todo listado es un `<DataListing>` con sus acciones de fila en `<RowActions>`. Las reglas (papel de cada columna en la
tarjeta del celular, enlace del identificador, acciones bloqueadas, marco de página) están en `DESIGN.md` §9, que es
la fuente; aquí solo la ubicación.

**Ubicación:** `resources/js/components/data-listing.tsx`, `resources/js/components/row-actions.tsx` y la lógica pura
en `resources/js/lib/data-listing.ts` (con sus pruebas de Vitest).

- Las columnas se describen una sola vez: la tabla se pinta desde `md` y una tarjeta por fila debajo.
- El identificador abre el detalle (o la edición si no hay detalle) con `rowHref`; el menú «⋯» no repite esa acción.
- `resources/js/design-contract.test.ts` falla si aparece un `<table>` en `pages/` fuera de las excepciones que lista
  (las tablas de detalle que aún no usan `<DetailTable>`).

---

## DetailTable (tablas de detalle)

Las líneas de **un** registro (los productos de un pedido o una cotización, los ingredientes de una fórmula) no son un
listado: en el celular siguen siendo tabla, se desplazan a lo ancho dentro de su caja y la primera columna queda fija.
Reglas y ejemplo en `DESIGN.md` §9.

**Ubicación:** `resources/js/components/detail-table.tsx`.

- Columnas como las de `DataListing`, sin papel de tarjeta: `key`, `header`, `cell`, `align`, `className`.
- `caption`: qué contiene la tabla, solo para el lector de pantalla.
- Pinta su propia caja con borde: no se envuelve en otra tarjeta. El título de la sección (y su descripción) va
  justo encima, fuera de cualquier tarjeta.
- Los totales no van dentro de la tabla (en el celular quedarían fuera de la vista al desplazar): van en una tarjeta
  debajo, a la derecha, como en `Quotations/Show`.

---

## EmptyCell y EmptyState (sin dato, sin filas)

Las comparten `DataListing` y `DetailTable`; una pantalla no las necesita si usa esas dos.

**Ubicación:** `resources/js/components/empty-cell.tsx` y `resources/js/components/empty-state.tsx`.

- `<EmptyCell />`: «—» atenuado, que el lector de pantalla lee «Sin dato». Sale cuando `cell` devuelve un valor vacío
  (`isEmptyCell` en `lib/data-listing.ts`: `null`, `undefined`, `false` o `''`; el `0` es un dato).
- `<EmptyState>mensaje</EmptyState>`: el mensaje centrado en una caja punteada, cuando la tabla no tiene filas.

---

## Combobox (Buscador Seleccionable)

Campo de selección con búsqueda para catálogos grandes (cientos de materias primas o productos). Es un botón con el
aspecto del `Select` que abre una lista en un `Popover` de Radix con un buscador arriba (`cmdk`, `ui/command.tsx`). La
lista se pinta en un Portal, así que no la recorta su contenedor.

**Ubicación:** `resources/js/components/ui/combobox.tsx`

### Props

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `options` | `ComboboxOptionType[]` | - | Array de `{ id, label, description?, disabled? }` |
| `value` | `string \| number \| null` | - | Valor seleccionado (ID) |
| `onChange` | `(value: string \| number) => void` | - | Se llama al elegir una opción **distinta** de la actual (elegir la misma no avisa, para no borrar lo que depende de ella) |
| `placeholder` | `string` | `'Seleccionar...'` | Texto cuando no hay selección |
| `emptyText` | `string` | `'Sin resultados.'` | Texto cuando no hay coincidencias |
| `className` | `string` | - | Clases adicionales **del botón** (el campo), no de la lista |
| `disabled` | `boolean` | `false` | Deshabilitar el componente |
| `id` | `string` | - | `id` del botón: lo señala la etiqueta (`htmlFor`) y recibe el foco ante un 422 (`focus-first-error`) |
| `aria-describedby`, `aria-invalid`, `aria-required` | - | - | La ayuda, el error y si es obligatorio; los pone `<FormField>` |

### Comportamiento

- **Búsqueda** sin tildes, sin mayúsculas, sin espacios y sin signos, en `label` y `description` (`lib/search-text.ts`):
  «epoxica» encuentra «EPÓXICA» y «aba3» encuentra «ABA-3 QC». Conserva el orden de `options`.
- **Teclado:** con el campo enfocado, teclear una letra o un número abre la lista con ese texto en el buscador; flecha
  abajo, Enter o espacio la abren. Escape cierra y el foco vuelve al campo. **Tab** sale con una sola pulsación: si se
  escribió algo, elige la opción resaltada; sin texto, solo cierra.
- **Lista:** ~10 opciones a la vista, nunca más alta que el espacio disponible; un nombre largo baja a una segunda línea.
  En el campo el valor se corta con «…» y el nombre completo queda en `title`.

### Uso recomendado

```tsx
const options = rawMaterials.map(rm => ({
    id: rm.id,
    label: rm.code,
}));

<Combobox
    id="raw_material_id"
    options={options}
    value={data.raw_material_id}
    onChange={(val) => setData('raw_material_id', String(val))}
    placeholder="Busca una materia prima..."
/>
```

### ¿Cuándo usar Combobox vs Select?

La regla vive en `DESIGN.md` §8: **`Select`** para listas fijas (enums, opciones `{value, label}` de
`EnumOptions::for()`) o que casi siempre tienen menos de ~8 opciones (tipo de ítem, validez, forma de pago, bodega);
**`Combobox`** para datos del negocio que crecen y donde buscar ayuda (clientes, productos, materias primas, lotes, OP).
Los casos dudosos, como la presentación de un producto, se deciden en el rediseño de cada módulo. Los lotes van
siempre en `Combobox`, aunque sean pocos: el número de lote es la etiqueta y lo que queda, la entrada y el vencimiento
van en la descripción; donde el movimiento puede crear un lote, «Registrar como nuevo lote» es la primera opción.

### Dentro de un `Sheet` o `Dialog`

Los cajones de movimientos de MP y PT usan `modal={false}`, y ahí el Combobox está probado (mouse, rueda y teclado).
Dentro de un `Sheet` o `Dialog` modal todavía no se ha usado: si se hace, comprobar que la rueda del mouse desplace la
lista.

---

## Piezas de formulario

Las reglas (contenedor de cada formulario, columnas, pie, 422, `noValidate`) están en `DESIGN.md` §8, que es la fuente.
Ejemplos completos: los cajones de movimientos de MP y PT (`components/inventory/`, `components/finished-inventory/`) y
Clientes (`components/clients/ClientForm.tsx`, `pages/Clients/{Create,Edit}.tsx`).

### FormPage

**Ubicación:** `resources/js/components/form-page.tsx`. Marco de una página de formulario: centrado, con relleno y uno
de dos anchos. `width="narrow"` (`max-w-3xl`) sin líneas ni matrices; `width="wide"` (`max-w-5xl`) con líneas o una
matriz. Las migas van en el `layout` de la página; no hay botón «←».

```tsx
<FormPage width="narrow">
    <PageHeader title="Nuevo cliente" />
    <ClientForm … />
</FormPage>
```

### FormField

**Ubicación:** `resources/js/components/form-field.tsx`. Etiqueta, campo, ayuda y error con el mismo espacio. Pone en el
campo el `id` (el de la llave del error, para que `focus-first-error` lo encuentre), `aria-describedby` (ayuda y error),
`aria-invalid` y, con `required`, `aria-required` (el asterisco va oculto al lector de pantalla). Ocupa su columna (`min-w-0`); `className="md:col-span-2"` para la fila completa.

| Prop | Descripción |
|------|-------------|
| `id` | `id` del campo y `htmlFor` de la etiqueta. Igual a la llave del error del servidor (`quantity`, `items.2.quantity`) |
| `label`, `help`, `error` | Etiqueta, ayuda (`text-xs`) y mensaje del servidor (`InputError`) |
| `required` | Asterisco rojo en la etiqueta |
| `children` | Un elemento (`Input`, `Textarea`, `DecimalInput`, `IntegerInput`, `Combobox`, `PlantDateInput`) o, para un `Select`, una función que recibe los props para el `SelectTrigger` |

```tsx
<FormField id="quantity" label="Cantidad" error={form.errors.quantity}>
    <DecimalInput value={form.data.quantity} onValueChange={(v) => form.setData('quantity', v)} placeholder="0,00" />
</FormField>

<FormField id="reason" label="Razón" error={form.errors.reason}>
    {(control) => (
        <Select value={form.data.reason} onValueChange={(v) => form.setData('reason', v)}>
            <SelectTrigger {...control}><SelectValue placeholder="Selecciona razón" /></SelectTrigger>
            <SelectContent>{/* … */}</SelectContent>
        </Select>
    )}
</FormField>
```

### FormActions

**Ubicación:** `resources/js/components/form-actions.tsx`. «Cancelar» a la izquierda y el botón principal (`submit`,
primario) a la derecha, deshabilitado y con rueda mientras `processing`. `cancelHref` (página: vuelve al listado) u
`onCancel` (cajón o ventana: la cierra); sin ninguno, solo el botón principal.

### Cajón y ventana (`ui/sheet.tsx`, `ui/dialog.tsx`)

Un formulario en cajón o ventana pone los campos en `SheetBody`/`DialogBody` (se desplazan por dentro) y el pie en
`SheetFooter`/`DialogFooter` (fijo abajo). El `<form>` envuelve los dos con `className="flex min-h-0 flex-1 flex-col"`
y `noValidate`. Al abrir, el foco va al primer campo; si las opciones todavía cargan (esqueleto), espera a que aparezca.
El cajón de la derecha flota con margen desde `sm` y ocupa la pantalla en el celular; la ventana nunca pasa del alto de
la pantalla.

```tsx
<form noValidate onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
    <SheetBody>{/* campos */}</SheetBody>
    <SheetFooter>
        <FormActions submitLabel="Registrar entrada" processing={form.processing} onCancel={onCancel} />
    </SheetFooter>
</form>
```

### ConfirmDialog y useConfirm

**Ubicación:** `resources/js/components/confirm-dialog.tsx` y `resources/js/hooks/use-confirm.ts`. Reemplaza al
`confirm()` del navegador. `ConfirmDialogProvider` (montado en `app.tsx`) pinta un único diálogo; `useConfirm()`
devuelve una función que abre el diálogo y espera la respuesta (`true` al confirmar, `false` al cancelar o cerrar).
`destructive` pone el botón en rojo. Si empieza una navegación de Inertia con la pregunta abierta, se da por
cancelada. `<ConfirmDialog>` sirve cuando el diálogo se controla con estado propio. `confirm()` y los campos
`type="date"`/`"datetime-local"` los prohíbe `design-contract.test.ts` (única excepción: la fecha con hora del
certificado de calidad, en `production/control-card.tsx`), que también exige `noValidate` en todo `<form>` y `<Form>`.

```tsx
const confirm = useConfirm();

if (await confirm({ title: `¿Eliminar a ${client.business_name}?`, confirmLabel: 'Eliminar', destructive: true })) {
    router.delete(clientsDestroy(client.id).url);
}
```

### PlantDateInput

**Ubicación:** `resources/js/components/plant-date-input.tsx`; la lógica pura en `resources/js/lib/plant-date.ts` (con
sus pruebas). Fecha de planta (`YYYY-MM-DD`) con el calendario de la app (selector de mes y año), nunca el nativo. El
valor por defecto lo pone el formulario (normalmente `plantToday`).

| Prop | Descripción |
|------|-------------|
| `value`, `onChange` | `YYYY-MM-DD` o `''` |
| `notFuture` / `notPast` | No después / no antes de hoy en la planta |
| `min`, `max` | Límites fijos; se combinan con los anteriores y gana el más estricto |
| `saved` | La fecha guardada: se puede conservar aunque quede fuera de los límites (como `PlantDate::allowing()`) |
| `clearable` | Muestra «Quitar fecha» (campos opcionales) |
| `id`, `placeholder`, `disabled` | Como en los demás campos |
| `aria-describedby`, `aria-invalid`, `aria-required` | Los pone `<FormField>` |

```tsx
<PlantDateInput notFuture min={oldestEntryDate} value={form.data.movement_date} onChange={(v) => form.setData('movement_date', v)} />
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
