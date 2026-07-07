import { Clock, FlaskConical, User as UserIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import type {
    ProductionOrderErrors,
    ProductionOrderFormData,
    ProductionOrderSetData,
    QualitySignerOption,
} from '@/types/production-orders';

type ControlCardProps = {
    data: ProductionOrderFormData;
    setData: ProductionOrderSetData;
    errors: ProductionOrderErrors;
    isReadOnly: boolean;
    processing: boolean;
    hasOrderData: boolean;
    showSubmit: boolean;
    submitLabel: string;
    processingLabel: string;
    showReject?: boolean;
    onReject?: () => void;
    qualitySigners: QualitySignerOption[];
    showQualitySigner?: boolean;
};

export function ControlCard({
    data,
    setData,
    errors,
    isReadOnly,
    processing,
    hasOrderData,
    showSubmit,
    submitLabel,
    processingLabel,
    showReject = false,
    onReject,
    qualitySigners,
    showQualitySigner = false,
}: ControlCardProps) {
    const selectedSigner = qualitySigners.find(
        (s) => s.id === data.quality_responsible_user_id,
    );
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Clock className="h-4 w-4 text-primary" />
                    Tiempos y Control
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label
                        htmlFor="responsible"
                        className="flex items-center gap-1"
                    >
                        <UserIcon className="h-3 w-3" /> Responsable
                    </Label>
                    <Input
                        id="responsible"
                        placeholder="Nombre del operario"
                        value={data.responsible_name}
                        onChange={(event) =>
                            setData('responsible_name', event.target.value)
                        }
                        disabled={isReadOnly}
                    />
                </div>

                {(showQualitySigner || (isReadOnly && data.quality_responsible_user_id)) && (
                    <div className="space-y-2">
                        <Label
                            htmlFor="quality-responsible"
                            className="flex items-center gap-1"
                        >
                            <UserIcon className="h-3 w-3 text-primary" />{' '}
                            Responsable de Calidad (Firma Certificado)
                        </Label>
                        {!isReadOnly ? (
                            <>
                                <Select
                                    value={
                                        data.quality_responsible_user_id
                                            ? String(
                                                  data.quality_responsible_user_id,
                                              )
                                            : ''
                                    }
                                    onValueChange={(value) =>
                                        setData(
                                            'quality_responsible_user_id',
                                            Number(value),
                                        )
                                    }
                                    disabled={isReadOnly}
                                >
                                    <SelectTrigger id="quality-responsible">
                                        <SelectValue placeholder="Selecciona quien firma el certificado..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {qualitySigners.map((signer) => (
                                            <SelectItem
                                                key={signer.id}
                                                value={String(signer.id)}
                                            >
                                                {signer.name} —{' '}
                                                {signer.job_title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedSigner && (
                                    <div className="flex items-center gap-3 rounded-md border bg-muted/30 p-2">
                                        {selectedSigner.signature_url && (
                                            <img
                                                src={
                                                    selectedSigner.signature_url
                                                }
                                                alt="Firma"
                                                className="h-8 max-w-[120px] object-contain"
                                            />
                                        )}
                                        <div className="text-xs">
                                            <p className="font-medium">
                                                {selectedSigner.name}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {selectedSigner.job_title}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : selectedSigner ? (
                            <div className="flex items-center gap-3 rounded-md border bg-muted/30 p-2">
                                {selectedSigner.signature_url && (
                                    <img
                                        src={selectedSigner.signature_url}
                                        alt="Firma"
                                        className="h-8 max-w-[120px] object-contain"
                                    />
                                )}
                                <div className="text-xs">
                                    <p className="font-medium">
                                        {selectedSigner.name}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {selectedSigner.job_title}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No asignado
                            </p>
                        )}
                        {errors.quality_responsible_user_id && (
                            <p className="text-xs text-destructive">
                                {errors.quality_responsible_user_id}
                            </p>
                        )}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="density">
                            Densidad (kg/gal){' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="density"
                            type="number"
                            step="0.0001"
                            value={data.density_kg_per_gallon}
                            onChange={(event) =>
                                setData(
                                    'density_kg_per_gallon',
                                    event.target.value,
                                )
                            }
                            disabled={isReadOnly}
                        />
                        {errors.density_kg_per_gallon && (
                            <p className="text-xs text-destructive">
                                {errors.density_kg_per_gallon}
                            </p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="spillage">Derrame (gal)</Label>
                        <Input
                            id="spillage"
                            type="number"
                            step="0.01"
                            value={data.spillage_quantity}
                            onChange={(event) =>
                                setData('spillage_quantity', event.target.value)
                            }
                            disabled={isReadOnly}
                        />
                    </div>
                </div>
                <Separator />
                <div className="space-y-4 rounded-md border bg-muted/30 p-4">
                    <div className="space-y-1">
                        <p className="flex items-center gap-1.5 text-sm font-semibold">
                            <FlaskConical className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            Sobrante de Producto Terminado
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Si sobraron galones sin envasar, regístralos aquí
                            para reutilizarlos en futuras órdenes.
                        </p>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="remnant">Galones Sobrantes</Label>
                            <Input
                                id="remnant"
                                type="number"
                                step="0.0001"
                                placeholder="0.0"
                                value={data.remnant_quantity_gallons}
                                onChange={(event) =>
                                    setData(
                                        'remnant_quantity_gallons',
                                        event.target.value,
                                    )
                                }
                                disabled={isReadOnly}
                            />
                            {data.remnant_quantity_gallons &&
                                data.density_kg_per_gallon && (
                                    <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        ≈{' '}
                                        {(
                                            Number(
                                                data.remnant_quantity_gallons,
                                            ) *
                                            Number(data.density_kg_per_gallon)
                                        ).toFixed(4)}{' '}
                                        kg
                                    </p>
                                )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="remnant_notes">
                                Nota del Sobrante
                            </Label>
                            <Input
                                id="remnant_notes"
                                placeholder="Ej: Tarro azul al fondo..."
                                value={data.remnant_notes}
                                onChange={(event) =>
                                    setData('remnant_notes', event.target.value)
                                }
                                disabled={isReadOnly}
                            />
                        </div>
                    </div>
                </div>
                <Separator />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label className="text-xs">Inicio Agitación</Label>
                        <Input
                            type="datetime-local"
                            className="h-9 text-xs"
                            value={data.agitation_start_time}
                            onChange={(event) =>
                                setData(
                                    'agitation_start_time',
                                    event.target.value,
                                )
                            }
                            disabled={isReadOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label className="text-xs">Fin Agitación</Label>
                        <Input
                            type="datetime-local"
                            className="h-9 text-xs"
                            value={data.agitation_end_time}
                            onChange={(event) =>
                                setData(
                                    'agitation_end_time',
                                    event.target.value,
                                )
                            }
                            disabled={isReadOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label className="text-xs">Inicio Empaque</Label>
                        <Input
                            type="datetime-local"
                            className="h-9 text-xs"
                            value={data.packaging_start_time}
                            onChange={(event) =>
                                setData(
                                    'packaging_start_time',
                                    event.target.value,
                                )
                            }
                            disabled={isReadOnly}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label className="text-xs">Fin Empaque</Label>
                        <Input
                            type="datetime-local"
                            className="h-9 text-xs"
                            value={data.packaging_end_time}
                            onChange={(event) =>
                                setData(
                                    'packaging_end_time',
                                    event.target.value,
                                )
                            }
                            disabled={isReadOnly}
                        />
                    </div>
                </div>
                <Separator />
                <div className="space-y-2">
                    <Label htmlFor="notes">Observaciones</Label>
                    <Textarea
                        id="notes"
                        placeholder="Notas sobre el lote..."
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        disabled={isReadOnly}
                    />
                </div>

                {!isReadOnly && (
                    <>
                        {errors.packaging && (
                            <p className="text-xs text-destructive">
                                {errors.packaging}
                            </p>
                        )}
                        {errors.ingredients && (
                            <p className="text-xs text-destructive">
                                {errors.ingredients}
                            </p>
                        )}
                    </>
                )}

                {showSubmit && (
                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        size="lg"
                        disabled={processing || !hasOrderData}
                    >
                        {processing ? processingLabel : submitLabel}
                    </Button>
                )}

                {showReject && onReject && (
                    <Button
                        type="button"
                        variant="outline"
                        className="mt-2 w-full"
                        onClick={onReject}
                        disabled={processing}
                    >
                        Devolver a planta
                    </Button>
                )}

                {!isReadOnly && !hasOrderData && (
                    <p className="text-xs text-destructive">
                        La orden no tiene detalle de insumos ni plan de empaque.
                        Revise la fórmula y vuelva a crearla.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
