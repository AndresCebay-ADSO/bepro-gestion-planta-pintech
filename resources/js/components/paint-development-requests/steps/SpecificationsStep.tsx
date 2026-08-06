import CheckboxGroup from '@/components/paint-development-requests/fields/CheckboxGroup';
import SelectField from '@/components/paint-development-requests/fields/SelectField';
import TextAreaField from '@/components/paint-development-requests/fields/TextAreaField';
import TextField from '@/components/paint-development-requests/fields/TextField';
import {
    presentacionOptions,
    frecuenciaOptions,
    metaCompetidorOptions,
    formaAprobacionOptions,
} from '@/components/paint-development-requests/options';
import type { StepProps } from '@/components/paint-development-requests/types';

function specValue(
    payload: Record<string, unknown>,
    key: string,
    fallback: string = '',
): string {
    const v = payload[key];

    return typeof v === 'string' ? v : fallback;
}

function specArray(payload: Record<string, unknown>, key: string): string[] {
    const v = payload[key];

    return Array.isArray(v)
        ? v.filter((i): i is string => typeof i === 'string')
        : [];
}

export default function SpecificationsStep({
    data,
    setPayload,
    errors,
}: StepProps) {
    const spec = data.specifications_payload;

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Especificaciones de control
                </h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Viscosidad y método"
                        value={specValue(spec, 'viscosidad_metodo')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'viscosidad_metodo',
                                v,
                            )
                        }
                        placeholder="Ej. 75–85 KU a 25 °C"
                    />
                    <TextField
                        label="Densidad objetivo"
                        value={specValue(spec, 'densidad')}
                        onChange={(v) =>
                            setPayload('specifications_payload', 'densidad', v)
                        }
                        placeholder="Ej. 1,45–1,55 kg/L a 25 °C"
                    />
                    <TextField
                        label="Sólidos por peso objetivo"
                        value={specValue(spec, 'solidos_peso')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'solidos_peso',
                                v,
                            )
                        }
                        placeholder="Ej. 75 ± 2%"
                    />
                    <TextField
                        label="Finura de molienda"
                        value={specValue(spec, 'finura')}
                        onChange={(v) =>
                            setPayload('specifications_payload', 'finura', v)
                        }
                        placeholder="Ej. mínimo 6 Hegman"
                    />
                    <div className="md:col-span-2">
                        <TextAreaField
                            label="Tiempos de secado objetivo"
                            value={specValue(spec, 'secados')}
                            onChange={(v) =>
                                setPayload(
                                    'specifications_payload',
                                    'secados',
                                    v,
                                )
                            }
                            placeholder="Tacto, manipulación, repinte y curado total"
                            required
                            error={errors['specifications_payload.secados']}
                        />
                    </div>
                    <div className="md:col-span-2">
                        <TextAreaField
                            label="Estabilidad y vida en almacenamiento"
                            value={specValue(spec, 'estabilidad')}
                            onChange={(v) =>
                                setPayload(
                                    'specifications_payload',
                                    'estabilidad',
                                    v,
                                )
                            }
                            placeholder="Ej. 12 meses a 10–30 °C, sin asentamiento duro"
                            required
                            error={errors['specifications_payload.estabilidad']}
                        />
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Suministro y logística
                </h2>
                <div className="grid gap-4">
                    <CheckboxGroup
                        label="Presentaciones requeridas"
                        values={specArray(spec, 'presentacion')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'presentacion',
                                v,
                            )
                        }
                        options={presentacionOptions}
                        required
                        error={errors['specifications_payload.presentacion']}
                    />
                    <TextField
                        label="Consumo estimado"
                        value={specValue(spec, 'consumo')}
                        onChange={(v) =>
                            setPayload('specifications_payload', 'consumo', v)
                        }
                        placeholder="Ej. 300 galones/mes"
                        required
                        error={errors['specifications_payload.consumo']}
                    />
                    <SelectField
                        label="Frecuencia de compra"
                        value={specValue(spec, 'frecuencia')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'frecuencia',
                                v,
                            )
                        }
                        options={frecuenciaOptions}
                        required
                        error={errors['specifications_payload.frecuencia']}
                    />
                    <TextAreaField
                        label="Condiciones de almacenamiento y transporte"
                        value={specValue(spec, 'almacenamiento')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'almacenamiento',
                                v,
                            )
                        }
                    />
                    <TextAreaField
                        label="Configuración del kit A/B"
                        value={specValue(spec, 'kit_ab')}
                        onChange={(v) =>
                            setPayload('specifications_payload', 'kit_ab', v)
                        }
                        placeholder="Tamaños y relación por volumen, si aplica"
                    />
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Objetivo comercial y aprobación
                </h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Producto competidor o referencia"
                        value={specValue(spec, 'competidor')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'competidor',
                                v,
                            )
                        }
                        placeholder="Marca y referencia exacta"
                    />
                    <SelectField
                        label="Objetivo frente a la referencia"
                        value={specValue(spec, 'meta_competidor')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'meta_competidor',
                                v,
                            )
                        }
                        options={metaCompetidorOptions}
                        required
                        error={errors['specifications_payload.meta_competidor']}
                    />
                    <TextField
                        label="Costo o precio objetivo"
                        value={specValue(spec, 'costo_objetivo')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'costo_objetivo',
                                v,
                            )
                        }
                        placeholder="Ej. $28.000 COP/kg o “por definir”"
                        required
                        error={errors['specifications_payload.costo_objetivo']}
                    />
                    <TextField
                        label="Cantidad de muestra o prueba industrial"
                        value={specValue(spec, 'cantidad_prueba')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'cantidad_prueba',
                                v,
                            )
                        }
                        placeholder="Ej. 1 galón o kit de 5 galones"
                        required
                        error={errors['specifications_payload.cantidad_prueba']}
                    />
                    <TextField
                        label="Quién aprueba el desarrollo"
                        value={specValue(spec, 'aprobador')}
                        onChange={(v) =>
                            setPayload('specifications_payload', 'aprobador', v)
                        }
                        placeholder="Nombre, cargo y empresa"
                        required
                        error={errors['specifications_payload.aprobador']}
                    />
                    <SelectField
                        label="Forma de aprobación"
                        value={specValue(spec, 'forma_aprobacion')}
                        onChange={(v) =>
                            setPayload(
                                'specifications_payload',
                                'forma_aprobacion',
                                v,
                            )
                        }
                        options={formaAprobacionOptions}
                        required
                        error={
                            errors['specifications_payload.forma_aprobacion']
                        }
                    />
                    <div className="md:col-span-2">
                        <TextAreaField
                            label="Criterios exactos de aprobación"
                            value={specValue(spec, 'criterios_aprobacion')}
                            onChange={(v) =>
                                setPayload(
                                    'specifications_payload',
                                    'criterios_aprobacion',
                                    v,
                                )
                            }
                            placeholder="Cómo se decidirá objetivamente si la muestra queda aprobada"
                            required
                            error={
                                errors[
                                    'specifications_payload.criterios_aprobacion'
                                ]
                            }
                        />
                    </div>
                    <div className="md:col-span-2">
                        <TextAreaField
                            label="Observaciones adicionales"
                            value={specValue(spec, 'observaciones')}
                            onChange={(v) =>
                                setPayload(
                                    'specifications_payload',
                                    'observaciones',
                                    v,
                                )
                            }
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
