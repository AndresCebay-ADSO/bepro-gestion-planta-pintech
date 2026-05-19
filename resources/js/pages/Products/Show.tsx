import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileStack, FileText, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';

import {
    destroy as destroyProductDocument,
    download as downloadProductDocument,
    store as storeProductDocument,
} from '@/actions/App/Http/Controllers/ProductDocumentController';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    create as formulasCreate,
    show as formulasShow,
} from '@/routes/formulas';
import { index as productsIndex } from '@/routes/products';

type FormulaItem = {
    id: number;
    version: number;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    created_by?: { name: string } | null;
};

type Props = {
    product: {
        id: number;
        code: string;
        name: string;
        is_active: boolean;
        category?: { name: string } | null;
        unit_of_measure?: { name: string; symbol: string } | null;
        current_cost?: string | null;
        current_price?: string | null;
        profit_margin?: string | null;
        brand?: string;
        description?: string | null;
        quality_viscosity_lower?: number | string | null;
        quality_viscosity_upper?: number | string | null;
        quality_fineness_lower?: number | string | null;
        quality_fineness_upper?: number | string | null;
        quality_solids_lower?: number | string | null;
        quality_solids_upper?: number | string | null;
        variants?: Array<{
            id: number;
            sku: string;
            presentation_label?: string | null;
            color?: string | null;
            finish?: string | null;
            base_type?: string | null;
            component_system: '1K' | '2K' | 'KIT';
            current_price?: string | null;
            is_active: boolean;
            unit_of_measure?: { name: string; symbol: string } | null;
        }>;
        formulas?: FormulaItem[];
        product_documents?: Array<{
            id: number;
            document_type: 'ficha_tecnica' | 'hoja_seguridad';
            file_name: string;
            file_size: number;
            version: number;
            created_at: string;
        }>;
    };
    can: {
        update: boolean;
        delete: boolean;
    };
    documentTypes: Array<{
        value: 'ficha_tecnica' | 'hoja_seguridad';
        label: string;
    }>;
    units: Array<{
        id: number;
        name: string;
        symbol: string;
    }>;
    rawMaterials?: Array<{
        id: number;
        code: string;
        category?: { id: number; name: string };
    }>;
};

export default function ProductsShow({ product, can, documentTypes, units, rawMaterials }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [isDocumentDialogOpen, setIsDocumentDialogOpen] = useState(false);

    const formatQualityRange = (
        lower: number | string | null | undefined,
        upper: number | string | null | undefined,
    ): string | null => {
        const hasLower = lower !== null && lower !== undefined && lower !== '';
        const hasUpper = upper !== null && upper !== undefined && upper !== '';

        if (!hasLower && !hasUpper) {
            return null;
        }

        if (hasLower && hasUpper) {
            return `${lower} – ${upper}`;
        }

        if (hasLower) {
            return `≥ ${lower}`;
        }

        return `≤ ${upper}`;
    };

    const hasCertificateRanges = Boolean(
        formatQualityRange(product.quality_viscosity_lower, product.quality_viscosity_upper)
            || formatQualityRange(product.quality_fineness_lower, product.quality_fineness_upper)
            || formatQualityRange(product.quality_solids_lower, product.quality_solids_upper),
    );

    const form = useForm({
        sku: '',
        unit_of_measure_id: '',
        presentation_value: '',
        presentation_label: '',
        color: '',
        finish: '',
        base_type: '',
        component_system: '1K',
        current_cost: '',
        current_price: '',
        package_raw_material_id: '',
        is_active: true,
    });
    const documentForm = useForm({
        document_type: documentTypes[0]?.value ?? 'ficha_tecnica',
        document: null as File | null,
    });

    const handleDelete = () => {
        if (!window.confirm(`¿Eliminar el producto ${product.code}?`)) {
            return;
        }

        router.delete(`/products/${product.id}`);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/products/${product.id}/variants`, {
            onSuccess: () => {
                setIsOpen(false);
                form.reset();
            },
        });
    };

    const handleDocumentSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        documentForm.post(storeProductDocument({ product: product.id }).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsDocumentDialogOpen(false);
                documentForm.reset();
            },
        });
    };

    const activeFormula = product.formulas?.find((f) => f.is_active);
    const documents = product.product_documents ?? [];

    return (
        <>
            <Head title={`Producto ${product.code}`} />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Link
                                href={productsIndex().url}
                                className="hover:text-foreground"
                            >
                                Productos
                            </Link>
                            <span>/</span>
                            <span className="font-mono">{product.code}</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold text-foreground">
                                {product.name}
                            </h1>
                            <Badge
                                variant={
                                    product.is_active ? 'default' : 'secondary'
                                }
                            >
                                {product.is_active ? 'Activo' : 'Inactivo'}
                            </Badge>
                        </div>
                        <p className="font-mono text-sm text-muted-foreground">
                            {product.code}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={productsIndex().url}>Volver</Link>
                        </Button>
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={`/products/${product.id}/edit`}>
                                    Editar
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Info del producto */}
                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Marca
                        </p>
                        <p className="text-sm font-medium text-foreground">
                            {product.brand ?? '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Categoría
                        </p>
                        <p className="text-sm text-foreground">
                            {product.category?.name ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Unidad de Medida
                        </p>
                        <p className="text-sm text-foreground">
                            {product.unit_of_measure
                                ? `${product.unit_of_measure.name} (${product.unit_of_measure.symbol})`
                                : '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Precio de Venta
                        </p>
                        <p className="text-sm font-medium text-foreground">
                            {product.current_price
                                ? <FormattedNumber value={product.current_price} currency maxDecimals={2} trimTrailingZeros />
                                : 'No asignado'}
                        </p>
                    </div>
                </div>

                {(product.description || hasCertificateRanges) && (
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        {product.description && (
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Descripción
                                </p>
                                <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-foreground">
                                    {product.description}
                                </p>
                            </div>
                        )}
                        {hasCertificateRanges && (
                            <div className={product.description ? 'border-t border-border pt-4' : ''}>
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Rangos certificado de calidad
                                </p>
                                <dl className="mt-3 grid gap-4 sm:grid-cols-3">
                                    {formatQualityRange(product.quality_viscosity_lower, product.quality_viscosity_upper) && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Viscosidad (KU)</dt>
                                            <dd className="mt-0.5 font-mono text-sm font-medium">
                                                {formatQualityRange(product.quality_viscosity_lower, product.quality_viscosity_upper)}
                                            </dd>
                                        </div>
                                    )}
                                    {formatQualityRange(product.quality_fineness_lower, product.quality_fineness_upper) && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Molienda (HG)</dt>
                                            <dd className="mt-0.5 font-mono text-sm font-medium">
                                                {formatQualityRange(product.quality_fineness_lower, product.quality_fineness_upper)}
                                            </dd>
                                        </div>
                                    )}
                                    {formatQualityRange(product.quality_solids_lower, product.quality_solids_upper) && (
                                        <div>
                                            <dt className="text-xs text-muted-foreground">Sólidos (%)</dt>
                                            <dd className="mt-0.5 font-mono text-sm font-medium">
                                                {formatQualityRange(product.quality_solids_lower, product.quality_solids_upper)}
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        )}
                    </div>
                )}

                <Card className="overflow-hidden border-border/80 shadow-sm">
                    <CardHeader className="border-b border-border bg-muted/30 px-6 py-4">
                        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <CardTitle className="text-lg">Documentación técnica</CardTitle>
                                    <Badge variant="secondary" className="font-normal">
                                        QR por lote
                                    </Badge>
                                </div>
                                <CardDescription className="text-sm leading-relaxed">
                                    PDFs reutilizables vinculados a este producto. Aparecen automáticamente en la landing
                                    pública de cada orden completada, junto al certificado de calidad del lote.
                                </CardDescription>
                            </div>
                            {can.update && (
                                <Dialog open={isDocumentDialogOpen} onOpenChange={setIsDocumentDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm" className="shrink-0">
                                            <Upload className="mr-1.5 h-4 w-4" />
                                            Subir PDF
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>Subir documento técnico</DialogTitle>
                                            <DialogDescription>
                                                Elige el tipo y adjunta un PDF. Si ya existe un documento del mismo tipo,
                                                se archiva la versión anterior y queda vigente la nueva.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <form onSubmit={handleDocumentSubmit} className="space-y-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="document_type">Tipo de documento</Label>
                                                <Select
                                                    value={documentForm.data.document_type}
                                                    onValueChange={(value) =>
                                                        documentForm.setData('document_type', value as 'ficha_tecnica' | 'hoja_seguridad')
                                                    }
                                                >
                                                    <SelectTrigger id="document_type">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {documentTypes.map((type) => (
                                                            <SelectItem key={type.value} value={type.value}>
                                                                {type.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {documentForm.errors.document_type && (
                                                    <p className="text-xs text-destructive">{documentForm.errors.document_type}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="document">Archivo PDF</Label>
                                                <Input
                                                    id="document"
                                                    type="file"
                                                    accept="application/pdf,.pdf"
                                                    className="cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary"
                                                    onChange={(event) => documentForm.setData('document', event.target.files?.[0] ?? null)}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Tamaño máximo 10 MB. Solo se aceptan archivos PDF.
                                                </p>
                                                {documentForm.errors.document && (
                                                    <p className="text-xs text-destructive">{documentForm.errors.document}</p>
                                                )}
                                            </div>
                                            <div className="flex justify-end gap-2 pt-2">
                                                <Button type="button" variant="outline" onClick={() => setIsDocumentDialogOpen(false)}>
                                                    Cancelar
                                                </Button>
                                                <Button type="submit" disabled={documentForm.processing}>
                                                    {documentForm.processing ? 'Subiendo...' : 'Guardar'}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {documents.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                                    <FileStack className="h-7 w-7 text-muted-foreground" />
                                </div>
                                <div className="max-w-sm space-y-1">
                                    <p className="text-sm font-medium text-foreground">Sin documentos técnicos</p>
                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                        Carga la ficha técnica y la hoja de seguridad para que los lotes completados muestren
                                        enlaces de descarga públicos coherentes con tu catálogo.
                                    </p>
                                </div>
                                {can.update && (
                                    <Button size="sm" variant="outline" onClick={() => setIsDocumentDialogOpen(true)}>
                                        <Upload className="mr-1.5 h-4 w-4" />
                                        Subir el primero
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <ul className="divide-y divide-border">
                                {documents.map((document) => {
                                    const typeLabel =
                                        documentTypes.find((type) => type.value === document.document_type)?.label ??
                                        document.document_type;

                                    return (
                                        <li
                                            key={document.id}
                                            className="flex flex-col gap-4 px-6 py-4 transition-colors hover:bg-muted/20 md:flex-row md:items-center md:justify-between"
                                        >
                                            <div className="flex min-w-0 flex-1 items-start gap-4">
                                                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                    <FileText className="h-5 w-5" />
                                                </div>
                                                <div className="min-w-0 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="truncate font-medium text-foreground">{document.file_name}</p>
                                                        <Badge variant="outline" className="shrink-0 text-[10px] uppercase tracking-wide">
                                                            {typeLabel}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatBytes(document.file_size)}
                                                        <span className="mx-1.5 text-border">·</span>
                                                        Versión {document.version}
                                                        <span className="mx-1.5 text-border">·</span>
                                                        <FormattedDate value={document.created_at} format="date" />
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center justify-end gap-1 md:pl-4">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button variant="outline" size="icon" className="h-9 w-9" asChild>
                                                            <a href={downloadProductDocument({ document: document.id }).url}>
                                                                <Download className="h-4 w-4" />
                                                                <span className="sr-only">Descargar PDF</span>
                                                            </a>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Descargar PDF</TooltipContent>
                                                </Tooltip>
                                                {can.update && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-9 w-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                onClick={() => {
                                                                    if (window.confirm('¿Eliminar este documento técnico?')) {
                                                                        router.delete(destroyProductDocument({ document: document.id }).url, {
                                                                            preserveScroll: true,
                                                                        });
                                                                    }
                                                                }}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                                <span className="sr-only">Eliminar documento</span>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Eliminar</TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {/* Variantes */}
                <div className="rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-6 py-4">
                        <div>
                            <h2 className="font-medium text-foreground">
                                Variantes / SKU
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Presentaciones de venta: galón, bidón, tambor, etc. El valor se define en galones.
                            </p>
                        </div>
                        {can.update && (
                            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                                <DialogTrigger asChild>
                                    <Button size="sm">Nueva Variante</Button>
                                </DialogTrigger>
                                <DialogContent className="max-w-lg">
                                    <DialogHeader>
                                        <DialogTitle>Nueva Variante</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="sku">SKU</Label>
                                                <Input
                                                    id="sku"
                                                    value={form.data.sku}
                                                    onChange={(e) => form.setData('sku', e.target.value)}
                                                    placeholder="Ej: ESM-BLA-01-GL"
                                                />
                                                {form.errors.sku && (
                                                    <p className="text-xs text-destructive">{form.errors.sku}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="unit_of_measure_id">Unidad de Medida</Label>
                                                <Select
                                                    value={form.data.unit_of_measure_id}
                                                    onValueChange={(v) => form.setData('unit_of_measure_id', v)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Selecciona..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {units.map((u) => (
                                                            <SelectItem key={u.id} value={String(u.id)}>
                                                                {u.name} ({u.symbol})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {form.errors.unit_of_measure_id && (
                                                    <p className="text-xs text-destructive">{form.errors.unit_of_measure_id}</p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="presentation_value">Valor Presentación (en galones)</Label>
                                                <Input
                                                    id="presentation_value"
                                                    type="number"
                                                    step="0.0001"
                                                    value={form.data.presentation_value}
                                                    onChange={(e) => form.setData('presentation_value', e.target.value)}
                                                    placeholder="Ej: 1, 5, 0.75, 50"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Ejemplos: 1 = Galón, 5 = Bidón 5gal, 0.75 = 3/4 galón, 50 = Tambor
                                                </p>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="presentation_label">Label Presentación</Label>
                                                <Input
                                                    id="presentation_label"
                                                    value={form.data.presentation_label}
                                                    onChange={(e) => form.setData('presentation_label', e.target.value)}
                                                    placeholder="Ej: Galón 3.785L, Bidón 5 Gal"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="color">Color</Label>
                                                <Input
                                                    id="color"
                                                    value={form.data.color}
                                                    onChange={(e) => form.setData('color', e.target.value)}
                                                    placeholder="Ej: Blanco"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="finish">Acabado</Label>
                                                <Input
                                                    id="finish"
                                                    value={form.data.finish}
                                                    onChange={(e) => form.setData('finish', e.target.value)}
                                                    placeholder="Ej: Brillante"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="base_type">Tipo Base</Label>
                                                <Input
                                                    id="base_type"
                                                    value={form.data.base_type}
                                                    onChange={(e) => form.setData('base_type', e.target.value)}
                                                    placeholder="Ej: Agua / Solvente"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="component_system">Sistema</Label>
                                                <Select
                                                    value={form.data.component_system}
                                                    onValueChange={(v) => form.setData('component_system', v as '1K' | '2K' | 'KIT')}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="1K">1K (Un componente)</SelectItem>
                                                        <SelectItem value="2K">2K (Dos componentes)</SelectItem>
                                                        <SelectItem value="KIT">KIT (Kit completo)</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="current_cost">Costo Actual</Label>
                                                <Input
                                                    id="current_cost"
                                                    type="number"
                                                    step="0.0001"
                                                    value={form.data.current_cost}
                                                    onChange={(e) => form.setData('current_cost', e.target.value)}
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="current_price">Precio Venta</Label>
                                                <Input
                                                    id="current_price"
                                                    type="number"
                                                    step="0.0001"
                                                    value={form.data.current_price}
                                                    onChange={(e) => form.setData('current_price', e.target.value)}
                                                    placeholder="0.00"
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="package_raw_material_id">Envase / Contenedor</Label>
                                            <Select
                                                value={form.data.package_raw_material_id}
                                                onValueChange={(v) => form.setData('package_raw_material_id', v)}
                                            >
                                                <SelectTrigger id="package_raw_material_id">
                                                    <SelectValue placeholder="Selecciona el envase (opcional)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {rawMaterials?.map((rm: any) => (
                                                        <SelectItem key={rm.id} value={String(rm.id)}>
                                                            {rm.code}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-xs text-muted-foreground">
                                                Se descontará del inventario al completar la orden de producción
                                            </p>
                                        </div>

                                        <div className="flex justify-end gap-2 pt-4">
                                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                                Cancelar
                                            </Button>
                                            <Button type="submit" disabled={form.processing}>
                                                {form.processing ? 'Guardando...' : 'Guardar Variante'}
                                            </Button>
                                        </div>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-4 text-left font-medium">SKU</th>
                                <th className="p-4 text-left font-medium">Presentación</th>
                                <th className="p-4 text-left font-medium">Color</th>
                                <th className="p-4 text-left font-medium">Acabado</th>
                                <th className="p-4 text-left font-medium">Sistema</th>
                                <th className="p-4 text-left font-medium">Precio</th>
                                <th className="p-4 text-left font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(product.variants ?? []).map((variant) => (
                                <tr
                                    key={variant.id}
                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono font-medium">{variant.sku}</td>
                                    <td className="p-4 text-muted-foreground">
                                        {variant.presentation_label ?? '-'}
                                        {variant.unit_of_measure
                                            ? ` (${variant.unit_of_measure.symbol})`
                                            : ''}
                                    </td>
                                    <td className="p-4 text-muted-foreground">{variant.color ?? '-'}</td>
                                    <td className="p-4 text-muted-foreground">{variant.finish ?? '-'}</td>
                                    <td className="p-4">
                                        <Badge variant="secondary">{variant.component_system}</Badge>
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {variant.current_price ? <FormattedNumber value={variant.current_price} currency maxDecimals={2} /> : '-'}
                                    </td>
                                    <td className="p-4">
                                        <Badge variant={variant.is_active ? 'default' : 'secondary'}>
                                            {variant.is_active ? 'Activa' : 'Inactiva'}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                            {(product.variants ?? []).length === 0 && (
                                <tr>
                                    <td colSpan={7} className="p-8 text-center text-sm text-muted-foreground">
                                        Este producto aún no tiene variantes registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-6 py-4">
                        <div>
                            <h2 className="font-medium text-foreground">
                                Fórmulas
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {activeFormula
                                    ? `Versión activa: v${activeFormula.version}`
                                    : 'Sin fórmula activa'}
                            </p>
                        </div>
                        <Button size="sm" asChild>
                            <Link
                                href={
                                    formulasCreate({ query: { product_id: product.id } })
                                        .url
                                }
                            >
                                Nueva Fórmula
                            </Link>
                        </Button>
                    </div>

                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Versión
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Estado
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Notas
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Creada por
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {(product.formulas ?? []).map((formula) => (
                                <tr
                                    key={formula.id}
                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono font-medium">
                                        v{formula.version}
                                    </td>
                                    <td className="p-4">
                                        <Badge
                                            variant={
                                                formula.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {formula.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {formula.notes ?? '-'}
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {formula.created_by?.name ?? '-'}
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        <FormattedDate value={formula.created_at} format="datetime" />
                                    </td>
                                    <td className="p-4 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    formulasShow({
                                                        formula: formula.id,
                                                    }).url
                                                }
                                            >
                                                Ver
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {(product.formulas ?? []).length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay fórmulas registradas. Crea la
                                        primera usando el botón "Nueva Fórmula".
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function formatBytes(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
