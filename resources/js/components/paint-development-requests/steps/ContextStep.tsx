import CheckboxGroup from '@/components/paint-development-requests/fields/CheckboxGroup';
import PairFields from '@/components/paint-development-requests/fields/PairFields';
import RadioGroup from '@/components/paint-development-requests/fields/RadioGroup';
import SelectField from '@/components/paint-development-requests/fields/SelectField';
import TextAreaField from '@/components/paint-development-requests/fields/TextAreaField';
import TextField from '@/components/paint-development-requests/fields/TextField';
import {
    sustratoOptions,
    estadoSuperficieOptions,
    pinturaExistenteOptions,
    preparacionOptions,
    exposicionOptions,
    aguaHumedadOptions,
    corrosividadOptions,
    ciclosTermicosOptions,
} from '@/components/paint-development-requests/options';
import type { StepProps } from '@/components/paint-development-requests/types';

function ctxValue(
    payload: Record<string, unknown>,
    key: string,
    fallback: string = '',
): string {
    const v = payload[key];

    return typeof v === 'string' ? v : fallback;
}

function ctxArray(payload: Record<string, unknown>, key: string): string[] {
    const v = payload[key];

    return Array.isArray(v)
        ? v.filter((i): i is string => typeof i === 'string')
        : [];
}

export default function ContextStep({
    data,
    setPayload,
    setData,
    errors,
}: StepProps) {
    const ctx = data.context_payload;

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Identificación del requerimiento
                </h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <TextField
                            label="Nombre del cliente"
                            value={data.client_name}
                            onChange={(v) => setData('client_name', v)}
                            placeholder="Ej. Empresa Industrial S.A.S."
                            required
                            error={errors.client_name}
                        />
                    </div>
                    <TextField
                        label="Nombre del proyecto"
                        value={data.project_name}
                        onChange={(v) => setData('project_name', v)}
                        placeholder="Ej. Línea de estructuras metálicas"
                        required
                        error={errors.project_name}
                    />
                    <TextField
                        label="Responsable"
                        value={data.responsible}
                        onChange={(v) => setData('responsible', v)}
                        placeholder="Nombre y cargo"
                        required
                        error={errors.responsible}
                    />
                    <TextField
                        label="Ciudad / región"
                        value={data.city}
                        onChange={(v) => setData('city', v)}
                        placeholder="Ej. Bogotá, Cali, Cartagena"
                        required
                        error={errors.city}
                    />
                    <TextField
                        label="Fecha requerida para la muestra"
                        value={data.sample_due_date}
                        onChange={(v) => setData('sample_due_date', v)}
                        required
                        type="date"
                        error={errors.sample_due_date}
                    />
                    <div className="md:col-span-2">
                        <TextField
                            label="Producto usado actualmente"
                            value={data.current_product}
                            onChange={(v) => setData('current_product', v)}
                            placeholder="Marca y referencia, si existe"
                        />
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Sustrato y estado de la superficie
                </h2>
                <div className="grid gap-4">
                    <SelectField
                        label="Sustrato principal"
                        value={ctxValue(ctx, 'sustrato')}
                        onChange={(v) => {
                            setPayload('context_payload', 'sustrato', v);

                            if (v !== 'Otro') {
                                setPayload(
                                    'context_payload',
                                    'otro_sustrato',
                                    '',
                                );
                            }
                        }}
                        options={sustratoOptions}
                        required
                        error={errors['context_payload.sustrato']}
                    />
                    {ctxValue(ctx, 'sustrato') === 'Otro' && (
                        <TextField
                            label="Especifica el sustrato"
                            value={ctxValue(ctx, 'otro_sustrato')}
                            onChange={(v) =>
                                setPayload(
                                    'context_payload',
                                    'otro_sustrato',
                                    v,
                                )
                            }
                            required
                            error={errors['context_payload.otro_sustrato']}
                        />
                    )}
                    <SelectField
                        label="Estado actual de la superficie"
                        value={ctxValue(ctx, 'estado_superficie')}
                        onChange={(v) =>
                            setPayload(
                                'context_payload',
                                'estado_superficie',
                                v,
                            )
                        }
                        options={estadoSuperficieOptions}
                        required
                        error={errors['context_payload.estado_superficie']}
                    />
                    <RadioGroup
                        label="¿La superficie tiene pintura existente?"
                        value={ctxValue(ctx, 'pintura_existente')}
                        onChange={(v) => {
                            setPayload(
                                'context_payload',
                                'pintura_existente',
                                v,
                            );

                            if (v !== 'Sí') {
                                setPayload(
                                    'context_payload',
                                    'sistema_existente',
                                    '',
                                );
                            }
                        }}
                        options={pinturaExistenteOptions}
                        required
                        error={errors['context_payload.pintura_existente']}
                    />
                    {ctxValue(ctx, 'pintura_existente') === 'Sí' && (
                        <TextAreaField
                            label="Sistema de pintura actual"
                            value={ctxValue(ctx, 'sistema_existente')}
                            onChange={(v) =>
                                setPayload(
                                    'context_payload',
                                    'sistema_existente',
                                    v,
                                )
                            }
                            placeholder="Tecnología, marca, capas, antigüedad y condición conocida"
                            required
                            error={errors['context_payload.sistema_existente']}
                        />
                    )}
                    <CheckboxGroup
                        label="Preparación superficial disponible"
                        values={ctxArray(ctx, 'preparacion')}
                        onChange={(v) =>
                            setPayload('context_payload', 'preparacion', v)
                        }
                        options={preparacionOptions}
                        required
                        error={errors['context_payload.preparacion']}
                    />
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Ambiente de servicio
                </h2>
                <div className="grid gap-4">
                    <CheckboxGroup
                        label="Tipo de exposición"
                        values={ctxArray(ctx, 'exposicion')}
                        onChange={(v) =>
                            setPayload('context_payload', 'exposicion', v)
                        }
                        options={exposicionOptions}
                        required
                        error={errors['context_payload.exposicion']}
                    />
                    <SelectField
                        label="Contacto con agua o humedad"
                        value={ctxValue(ctx, 'agua_humedad')}
                        onChange={(v) =>
                            setPayload('context_payload', 'agua_humedad', v)
                        }
                        options={aguaHumedadOptions}
                        required
                        error={errors['context_payload.agua_humedad']}
                    />
                    <SelectField
                        label="Categoría de corrosividad"
                        value={ctxValue(ctx, 'corrosividad')}
                        onChange={(v) =>
                            setPayload('context_payload', 'corrosividad', v)
                        }
                        options={corrosividadOptions}
                    />
                    <PairFields
                        label="Temperatura de servicio"
                        a={{
                            name: 'temp_min',
                            value: ctxValue(ctx, 'temp_min'),
                            onChange: (v) =>
                                setPayload('context_payload', 'temp_min', v),
                            placeholder: 'Mín.',
                            suffix: '°C',
                        }}
                        b={{
                            name: 'temp_max',
                            value: ctxValue(ctx, 'temp_max'),
                            onChange: (v) =>
                                setPayload('context_payload', 'temp_max', v),
                            placeholder: 'Máx.',
                            suffix: '°C',
                        }}
                        error={
                            errors['context_payload.temp_max'] ||
                            errors['context_payload.temp_min']
                        }
                    />
                    <SelectField
                        label="¿Habrá ciclos de calentamiento y enfriamiento?"
                        value={ctxValue(ctx, 'ciclos_termicos')}
                        onChange={(v) =>
                            setPayload('context_payload', 'ciclos_termicos', v)
                        }
                        options={ciclosTermicosOptions}
                        required
                        error={errors['context_payload.ciclos_termicos']}
                    />
                    <TextAreaField
                        label="Químicos, combustibles o contaminantes presentes"
                        value={ctxValue(ctx, 'quimicos')}
                        onChange={(v) =>
                            setPayload('context_payload', 'quimicos', v)
                        }
                        placeholder="Sustancia, concentración, temperatura y frecuencia de contacto"
                    />
                </div>
            </div>
        </div>
    );
}
