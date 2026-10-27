# DESIGN.md

UX contract for Pintech OS. **Every UI change follows it** (people and agents alike). It complements `CLAUDE.md` and
replaces the former `docs/SISTEMA_TEMAS_UI.md`. Rules marked _(pending)_ describe a decision whose code does not exist yet:
follow the current pattern until it is implemented, and do not invent a third one.

Scope of the bans below: `resources/js/{pages,components,layouts}`. `components/ui/` (shadcn) is the only place allowed
to use raw Tailwind palette or arbitrary values. `resources/js/design-contract.test.ts` enforces part of this contract.

## 1. Principles

- **Minimal and neutral.** Grey surfaces from the tokens; color communicates (status, direction, alert), it never
  decorates. If everything has color, nothing stands out.
- **Desktop and phone at once.** Every screen works at 390 px wide: the admin does everything from the phone.
- **Consistency over creativity.** Reuse the existing component; if none fits, create it once in `components/` and use
  it everywhere. Never a per-screen variant.
- **No decorative controls.** A control that does nothing (a search box that does not search, a fake notification)
  is removed until it works.
- Code in English. Everything the user can see or hear is in Spanish, as it appears on screen: visible text,
  `sr-only`, `aria-label`, `placeholder` and `title`.
- UI text uses sentence case: capital only on the first word and proper nouns; keep acronyms (PT, MP, OP, QR, NIT).
  «Materias primas», «Nuevo usuario», «Órdenes de producción». The page title, the browser tab (`<Head title>`), the
  breadcrumbs and the sidebar say the same thing.

## 2. Light and dark theme

- Theme is a class on `<html>` (`.dark`). `hooks/use-appearance.tsx` (`initializeTheme`) reads `appearance`
  (`light | dark | system`) from `localStorage`, mirrors it to a cookie for SSR and sets `data-theme` and
  `color-scheme`. `resources/views/app.blade.php` applies it before hydration (inline script + `html` background), so
  there is no flash. The background color in that inline `<style>` must match `--background`.
- **All color comes from tokens** in `resources/css/app.css` (`:root` = light, `.dark` = dark), exposed through `@theme`
  as `--color-*`. To change a color, change the token, never each component.
- Never use pure white/black for surfaces. Hierarchy: `bg-background` (app) → `bg-card border shadow-sm` (work
  surface) → `bg-sidebar` (navigation). Sticky header: `bg-background/95 border-b border-border/80`.

Rules:

| Use | Banned |
| :-- | :-- |
| `text-foreground`, `text-muted-foreground` | `text-black`, `text-white`, `text-gray-*`, `text-slate-*` |
| `bg-background`, `bg-card`, `bg-muted`, `border-border` | `bg-white`, `dark:bg-white`, `bg-gray-*` on containers |
| Inputs: `bg-background border-input placeholder:text-muted-foreground`, focus with `ring` | per-field hardcoded colors |
| Fix contrast in the token | `dark:` overrides on one component to patch a token |
| shadcn components, varied through tokens | ad-hoc overrides of a shadcn component in a page |

## 3. Colors

| Token (`bg-*`, `text-*`, `border-*`) | Meaning |
| :-- | :-- |
| `background` / `foreground` | app background / main text |
| `card`, `popover` (+ `-foreground`) | surfaces |
| `muted` / `muted-foreground` | subtle surface / secondary text; the **neutral** tone |
| `border`, `input`, `ring` | lines, field borders, focus |
| `primary` / `primary-foreground` | **only** the main action of a screen, links and the selected menu item |
| `destructive`, `warning`, `success`, `info` (+ `-foreground`) | solid fill / text on that fill |
| `destructive-text`, `warning-text`, `success-text`, `info-text` | colored text on the background or on a 15 % tint |

- **Primary is never a status.** Status uses the tones (§4).
- **Banned:** raw Tailwind palette (`emerald-*`, `green-*`, `amber-*`, `red-*`, `blue-*`, `slate-*`, `gray-*`…),
  hex/`rgb()`/`hsl()` in `className` or `style`. Existing uses (login, forgot-password, `Admin/Users/Index`…) are
  legacy and get removed; do not add new ones.
- **Exception:** `pages/Public/QrLanding/` is a brand page, redesigned separately. Only there are the BePro palette
  (`bepro-*`) and hex colors allowed. Never inside the ERP.
- Contrast (WCAG): every `-text` token is ≥ 4.5:1 against `background`, `card` and its own 15 % tint, in both modes.
  Every `-foreground` is ≥ 4.5:1 on its fill. Re-check when a token changes.

Status tokens:

| Token | Light | Dark |
| :-- | :-- | :-- |
| `--success` / `-foreground` | `hsl(142 71% 30%)` / `hsl(210 40% 98%)` | `hsl(142 60% 45%)` / `hsl(222 30% 12%)` |
| `--info` / `-foreground` | `hsl(205 85% 39%)` / `hsl(210 40% 98%)` | `hsl(205 85% 55%)` / `hsl(222 30% 12%)` |
| `--success-text` | `hsl(142 72% 26%)` | `hsl(142 70% 65%)` |
| `--info-text` | `hsl(210 80% 35%)` | `hsl(205 90% 70%)` |
| `--warning-text` | `hsl(32 95% 30%)` | `hsl(45 93% 60%)` |
| `--destructive-text` | `hsl(0 72% 42%)` | `hsl(0 90% 72%)` |

## 4. Tones (status color)

Five tones, no others (no purple, no primary):

| Tone | Classes | Means |
| :-- | :-- | :-- |
| `neutral` | `bg-muted text-muted-foreground` | draft, inactive, consumed, types and categories |
| `info` | `bg-info/15 text-info-text` | in progress, sent, transfer |
| `warning` | `bg-warning/15 text-warning-text` | pending, in review, partial, exit (outgoing movement) |
| `success` | `bg-success/15 text-success-text` | ready, delivered, completed, approved, accepted, available, active, entry |
| `danger` | `bg-destructive/15 text-destructive-text` | rejected, cancelled, high alert, high priority |

- Pill shape, one only: `rounded-full px-2 py-0.5 text-xs font-medium` + the tone classes.
  Example: `<span className="rounded-full bg-success/15 px-2 py-0.5 text-xs font-medium text-success-text">Activo</span>`.
- The tone comes from the enum (`tone()` in PHP) and is rendered by `<StatusBadge>`, `<ActiveBadge>` and
  `<PriorityBadge>`. **No color maps in pages** (`statusColors`, `priorityColors`…). Screens not migrated yet still
  have their own map: replace it with the component when you touch it.

How it works:

- PHP is the only source. Each enum that is shown as a pill implements `App\Enums\Contracts\HasTone`
  (`label()` + `tone(): App\Enums\Tone`, a `match` with no `default`) and is listed in `App\Support\ToneEnums::ALL`.
  `ToneEnumsTest` fails if an enum with `tone()` is missing from that list or a case has no tone.
- `HandleInertiaRequests` shares `enums` (`{ SalesOrderStatus: { pending: { label, tone } } }`) with
  `Inertia::once`: it travels on the first visit only, and only for signed-in users. `EnumOptions::for()` also adds
  `tone` to the options of those enums.
- `resources/js/lib/tones.ts` is the only tone → classes map (and the pill shape). Pages never import it directly;
  they use a component:

| Component | Use |
| :-- | :-- |
| `<StatusBadge enum="SalesOrderStatus" value={order.status} />` | Any enum of `ToneEnums::ALL`; label and tone from the enum |
| `<ActiveBadge isActive={client.is_active} />` | Active flag: «Activo» success / «Inactivo» neutral |
| `<PriorityBadge value={order.priority} />` | Sales order priority: bars icon + label |
| `useEnumCase('SalesOrderStatus', value)` | The label (and tone) without a pill: plain text, selects |

- A new enum with color: implement `HasTone`, add it to `ToneEnums::ALL` and its short name to `ToneEnumName` in
  `resources/js/types/enums.ts`.

| Enum | Case → tone |
| :-- | :-- |
| `SalesOrderStatus` | pending warning · in_progress info · ready success · delivered success · cancelled danger |
| `SalesOrderPriority` | low neutral · medium neutral · high danger |
| `QuotationStatus` | draft neutral · sent info · accepted success · rejected danger |
| `ProductionOrderStatus` | pending warning · in_progress info · pending_review warning · completed success · cancelled danger |
| `RemnantStatus` | available success · partially_consumed warning · consumed neutral |
| `PaintDevelopmentRequestStatus` | draft neutral · submitted info · in_review warning · approved success · rejected danger |
| `AlertSeverity` | baja neutral · media warning · alta danger |
| `AlertType`, `WarehouseType` | all neutral (told apart by icon or label) |
| `InventoryMovementType` (raw material and finished goods) | entry success · exit warning |
| `FinishedInventoryMovementReason` | transfer info · the rest neutral |
| Active flag (`is_active`) | active success · inactive neutral |

- **Movements:** never color alone. The pill (`<MovementDirectionBadge>`) shows icon + label: entry `ArrowDown`, exit
  `ArrowUp`, transfer `ArrowLeftRight`. The quantity carries the sign (`+25`, `−25`), once per row. The tone goes on the
  pill and the quantity; **never on the row background**.
- **Priority:** only `high` has color (`danger`), with a bars icon (3 bars high, 2 medium, 1 low; medium and low grey).

## 5. Typography

- **One family: Instrument Sans**, self-hosted (`@fontsource/instrument-sans`, weights 400/500/600). Codes, lots and
  order numbers use the system mono: `font-mono`. **Banned:** any other font, Google Fonts or bunny `<link>`/`@import`.
- **Weights:** `font-normal`, `font-medium`, `font-semibold`. **Banned:** `font-bold`, `font-extrabold`, `font-black`
  (700+ is not loaded; the browser fakes it).
- **Sizes:** only the Tailwind scale. **Banned:** arbitrary values such as `text-[11px]`, `text-[0.75rem]`,
  `leading-[20px]`.
- `text-sm` (14 px) for tables, forms, buttons and body. `text-xs` (12 px) only for field help, metadata and the
  label-value label. Dense tables reduce **padding**, never the font size.

| Role | Classes |
| :-- | :-- |
| Page title (h1) | `text-2xl font-semibold tracking-tight` |
| Section title (h2, `CardTitle`) | `text-lg font-semibold` |
| Subsection (h3) | `text-base font-semibold` |
| Body, table cell, button, label | `text-sm` (`font-medium` on labels and buttons) |
| Field help, metadata | `text-xs text-muted-foreground` |
| Page or section description | `text-sm text-muted-foreground` |
| Label-value label, table header | `text-xs font-medium uppercase tracking-wide text-muted-foreground` |
| KPI figure | `text-2xl font-semibold tabular-nums` |

The page title is always `<PageHeader>` (`components/page-header.tsx`), the only `h1` of a screen. No icon next to
it (the sidebar already shows it) and no outer margin: the page container sets the spacing. Only `title` is required:
`description` goes under the title, `badge` next to it, `actions` on the right on desktop and below the title on the
phone, and `children` are extra lines under the description.

```tsx
<PageHeader
    title="Materias primas"
    description="Inventario base de materias primas de planta."
    badge={<ActiveBadge isActive={rawMaterial.is_active} feminine />}
    actions={<Button>Nueva materia prima</Button>}
>
    {/* Líneas extra bajo la descripción, por ejemplo «Enviada por…» en una OP. */}
</PageHeader>
```

- Details use `<DetailPageHeader>`, which renders the breadcrumbs bar and a `<PageHeader>`.
- `components/heading.tsx` is a **section** title, always `h2` (`variant="small"` only makes it smaller), never the page
  title. Headings never skip a level (`h1` → `h3`).
- **Settings screens:** the layout's `<PageHeader>` is the `h1` («Configuración», as in the sidebar); each screen's own
  name («Perfil», «Unidades de medida») is its first `h2` and its browser tab.

## 6. Icons

- Only `lucide-react`. `size-4` in buttons, tables and fields; `size-5` next to page or card titles. Default stroke.
- An icon-only button has `sr-only` text **and** a tooltip, both in Spanish:
  `<Button size="icon" variant="outline"><Pencil className="size-4" /><span className="sr-only">Editar</span></Button>`.
  Exception: the «⋯» button of `<RowActions>` has only the `sr-only` text. It opens a menu, and when the menu closes
  the focus returns to the button and would leave the tooltip open.
- One icon per concept: do not reuse a menu icon for a different entry.
- A button with text carries an icon only when the icon adds what the text does not: movement direction (entry,
  exit, transfer) and file format (PDF, Excel). Create, edit, save and send buttons carry none («Nuevo producto»).
  Detail screens that still have one lose it when they are touched.

## 7. Numbers

- Decimal comma and thousands dot (`1.250,5`), trailing zeros trimmed (`250`, not `250.0000`).
- **No number is printed raw.** Never `{order.quantity}`; always `<FormattedNumber>` or the helpers in
  `lib/formatters.ts` (`formatNumber`, `formatQuantity`, `formatCurrency`, `formatPercent`). Use `tabular-nums` in
  numeric columns.
- Inputs: `DecimalInput` / `IntegerInput`, never `<Input type="number">`. Thousands dot while typing _(pending)_.

## 8. Forms

Three containers, chosen by size:

| Container | When |
| :-- | :-- |
| Page | Long: item lines, several sections or more than ~10 fields (OP, formula, product, quotation, order, role) |
| `Dialog` | Short: up to ~6 fields, one section, no lines; create and edit in the same dialog (client, warehouse, catalogs) |
| `Sheet` | Frequent entries from a listing where the list behind matters (raw material and finished goods movements) |

Same in all three:
- One field piece (label, help, error). One column on the phone, two on desktop for short fields; each field fills its
  column.
- Footer: «Cancelar» on the left, main action (primary) on the right, disabled while saving. Fixed at the bottom on the
  phone.
- **A 422 never closes the form**: data and errors stay. Focus goes to the first invalid field (`lib/focus-first-error.ts`).
- `noValidate` on the `<form>`: the server validates. Empty numeric fields start empty with a placeholder, never a typed
  `0`: «0,00» for decimals, «0» for integers.
- Confirmations use the app dialog (`AlertDialog`, destructive action in red). **Banned:** `window.confirm()`,
  `alert()`. _(pending)_
- Dates with the app calendar, not the native picker _(pending)_.
- **`Select` or `Combobox`:** `Select` (`ui/select.tsx`) for fixed lists (enums, options `{value, label}` from
  `EnumOptions::for()`) or lists that almost always have fewer than ~8 options. `Combobox` (`ui/combobox.tsx`) for
  business data that grows and where searching helps: clients, products, raw materials, batches, production orders.
  Unclear cases (such as a product's presentation) are decided in each module's redesign.

## 9. Tables and listings

- Listing pattern of `CLAUDE.md`: `Index{Domain}Request` + `{Domain}Filter` + `useFilters` + `<DataTableFilters />`;
  server-side pagination (`ui/pagination.tsx`: on the phone only previous, «Página X de Y» and next).
- Every listing is a `<DataListing>` (`components/data-listing.tsx`). Each screen describes its columns **once**; the
  component renders the `<Table>` (`components/ui/table.tsx`) from `md` up and one card per row below `md`. A table
  wider than its box scrolls inside the box, never the page. No sorting by column (the server orders).
- **The identifier opens the detail, not the whole row.** With `rowHref` the `title` cell is a `<Link>` (keyboard,
  middle click, new tab); the rest of the row stays selectable text, and the row only highlights on hover. Pass
  `rowHref` only when the user can do the main action (view the detail or, when there is no detail, edit); return
  `undefined` for a row without permission and the identifier is plain text.
- Exception: an indicator that asks for action, such as the alerts pill, may link to its own screen even though it is
  not the identifier. Nothing else in the row is a link.
- The other actions (edit, deactivate, reactivate, delete) go in `<RowActions>` (`components/row-actions.tsx`), the
  «⋯» menu at the end of the row or in the card corner. **The menu never repeats the identifier's action**: no «Ver»
  when the identifier opens the detail, no «Editar» when it opens the edit (screens without a detail, such as
  Clients). No color, except «Eliminar» in red, last, after a separator. With no visible action there is no button
  (and no column when no row has one).
- A blocked action shows its reason under the label (`disabledReason`; a tooltip does not work on the phone). It is
  not Radix `disabled`: it keeps focus, is announced with `aria-disabled` and does nothing.
- Empty listing: the `empty` message in a dashed box, centered. Detail tables (lines of an OP, a formula or a
  quotation) are not listings: they scroll horizontally with the first column fixed _(pending)_.

Role of each column on the phone card (`mobile`, required; on desktop every column shows):

| `mobile` | What goes there | Card |
| :-- | :-- | :-- |
| `title` | The identifier: code, number, name. Exactly one | Top left; the link when there is `rowHref` |
| `subtitle` | What describes it: category, client, product. At most one | Under the title, muted |
| `status` | The status or active pill. At most one | Next to the title |
| `meta` | Quantities, dates, totals, alerts, short data such as contact or priority. **At most 4** | Label-value pairs, 2 columns |
| `hidden` | Notes and what only serves to compare rows on desktop | Not shown |

What asks for action (alerts, pending items) **never** goes `hidden`: the phone user must see it too. If it does not
fit in the 4 `meta`, the least important `meta` goes `hidden` instead.

`align: 'right'` for figures (adds `tabular-nums`), `'center'` for pills and alerts. `className` styles the desktop
cell only (`max-w-xs truncate` would cut the value on the card); `cardClassName` styles the value on the card (for
example `font-mono` on a code, which then goes in both). The header is the table-header role
of §5 (12 px); cells are 14 px. In development a console warning flags a definition that breaks these limits
(`lib/data-listing.ts`). Table and cards are both in the DOM (toggled with `hidden md:block`): a text search in tests
finds each value twice.

```tsx
<DataListing
    rows={rawMaterials.data}
    rowKey={(item) => item.id}
    rowHref={(item) => (item.can.view ? withReturnTo(rawMaterialsShow({ raw_material: item.id }).url) : undefined)}
    rowLabel={(item) => item.code} // «Acciones de MP-001» for screen readers
    columns={[
        { key: 'code', header: 'Código', cell: (item) => item.code, mobile: 'title', className: 'font-mono', cardClassName: 'font-mono' },
        { key: 'category', header: 'Categoría', cell: (item) => item.category?.name ?? '-', mobile: 'subtitle' },
        { key: 'stock', header: 'Stock disponible', cell: (item) => <FormattedNumber value={item.available_stock} />, mobile: 'meta', align: 'right' },
        { key: 'alerts', header: 'Alertas', cell: (item) => <FormattedNumber value={item.active_alerts_count} />, mobile: 'meta', align: 'center' },
        { key: 'status', header: 'Estado', cell: (item) => <ActiveBadge isActive={item.is_active} feminine />, mobile: 'status' },
    ]}
    actions={(item) => [
        { label: 'Editar', icon: Pencil, href: rawMaterialsEdit({ raw_material: item.id }).url, hidden: !item.can.update },
        { label: 'Desactivar', icon: PowerOff, onSelect: () => setDeleteTarget(item), disabledReason: item.has_available_stock ? 'No disponible: tiene stock en bodega' : undefined },
        { label: 'Eliminar', icon: Trash2, destructive: true, onSelect: () => setDeleteTarget(item), hidden: !item.can.delete },
    ]}
    empty="No se encontraron materias primas."
/>
```

A `RowAction` has either `href` (navigates) or `onSelect` (runs a handler), never both; `hidden` removes it (permission).

## 10. Navigation

- Breadcrumbs appear only below the first level (detail, create, edit), starting at the listing. A first-level
  screen (a listing or a Settings screen) has none: one crumb would repeat the sidebar.
- **Breadcrumbs are the only way back.** No loose «← Volver» button in the content; on the phone the breadcrumb
  collapses to the previous level, e.g. «← Productos». _(pending)_
- The sidebar toggle uses the panel icon (`PanelLeftClose`/`PanelLeftOpen`), never arrows; on the phone, `Menu` (☰).
- Navigation links use Wayfinder (`@/routes`, `@/actions`), never hardcoded URLs.

## 11. Libraries

- Components: shadcn/ui on Radix, added with the shadcn CLI into `components/ui/`. When you touch a component that
  imports `@radix-ui/react-*`, switch it to the unified `radix-ui` package in the same change.
- Combobox (`components/ui/combobox.tsx`): Radix `Popover` + `cmdk` (`ui/command.tsx`). Its search ignores accents,
  case, spaces and punctuation, in the label and the description (`lib/search-text.ts`). Headless UI was removed: do not add it back.
- Icons: `lucide-react` only. Charts: not chosen yet; do not add a chart library.
- Fonts: `@fontsource/*` only, imported in `resources/css/app.css`.

## 12. How a UX PR works

1. Component + 2–3 example screens in a first PR, reviewed in the browser.
2. The remaining screens in a second, mechanical PR that repeats the approved pattern.
3. Every PR that touches screens: check light, dark and **390 px**, with before/after screenshots in
   `~/proyecto/capturas/<branch>/`.

PR checklist:
- [ ] Only tokens; no raw palette, hex or arbitrary sizes in `pages/`, `components/`, `layouts/`.
- [ ] Weights 400/500/600; sizes from the scale in §5.
- [ ] Status through the tones in §4; primary only for the main action.
- [ ] Numbers formatted (§7); icons from `lucide-react` with `sr-only` when alone.
- [ ] Text readable in both themes; visible focus ring.
- [ ] Works at 390 px without horizontal page scroll.
- [ ] `composer ci:check` green (includes `design-contract.test.ts`).
