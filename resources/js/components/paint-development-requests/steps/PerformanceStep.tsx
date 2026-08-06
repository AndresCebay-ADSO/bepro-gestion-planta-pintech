import CheckboxGroup from '@/components/paint-development-requests/fields/CheckboxGroup';
import NumberField from '@/components/paint-development-requests/fields/NumberField';
import SelectField from '@/components/paint-development-requests/fields/SelectField';
import TextAreaField from '@/components/paint-development-requests/fields/TextAreaField';
import TextField from '@/components/paint-development-requests/fields/TextField';
import {
    funcionOptions,
    vidaUtilOptions,
    prioridadOptions,
    pruebasOptions,
    brilloOptions,
    texturaOptions,
    cubricionOptions,
    retencionOptions,
} from '@/components/paint-development-requests/options';
import type { StepProps } from '@/components/paint-development-requests/types';

function perfValue(
    payload: Record<string, unknown>,
    key: string,
    fallback: string = '',
): string {
    const v = payload[key];

    return typeof v === 'string' ? v : fallback;
}

function perfArray(payload: Record<string, unknown>, key: string): string[] {
    const v = payload[key];

    return Array.isArray(v)
        ? v.filter((i): i is string => typeof i === 'string')
        : [];
}

export default function PerformanceStep({
    data,
    setPayload,
    errors,
}: StepProps) {
    const perf = data.performance_payload;

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Función y desempeño esperado
                </h2>
                <div className="grid gap-4">
                    <CheckboxGroup
                        label="Función principal del producto"
                        values={perfArray(perf, 'funcion')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'funcion', v)
                        }
                        options={funcionOptions}
                        required
                        error={errors['performance_payload.funcion']}
                    />
                    <SelectField
                        label="Vida útil esperada del sistema"
                        value={perfValue(perf, 'vida_util')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'vida_util', v)
                        }
                        options={vidaUtilOptions}
                        required
                        error={errors['performance_payload.vida_util']}
                    />
                    <SelectField
                        label="Prioridad técnica principal"
                        value={perfValue(perf, 'prioridad')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'prioridad', v)
                        }
                        options={prioridadOptions}
                        required
                        error={errors['performance_payload.prioridad']}
                    />
                    <CheckboxGroup
                        label="Pruebas o evidencias requeridas"
                        values={perfArray(perf, 'pruebas')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'pruebas', v)
                        }
                        options={pruebasOptions}
                    />
                    <div className="grid gap-4 md:grid-cols-2">
                        <NumberField
                            label="Adherencia objetivo"
                            value={perfValue(perf, 'adherencia_obj')}
                            onChange={(v) =>
                                setPayload(
                                    'performance_payload',
                                    'adherencia_obj',
                                    v,
                                )
                            }
                            placeholder="Ej. 1200"
                            suffix="psi"
                            error={errors['performance_payload.adherencia_obj']}
                        />
                        <NumberField
                            label="Cámara salina objetivo"
                            value={perfValue(perf, 'camara_obj')}
                            onChange={(v) =>
                                setPayload(
                                    'performance_payload',
                                    'camara_obj',
                                    v,
                                )
                            }
                            placeholder="Ej. 1000"
                            suffix="h"
                            error={errors['performance_payload.camara_obj']}
                        />
                    </div>
                    <TextAreaField
                        label="Otros criterios cuantificables de aceptación"
                        value={perfValue(perf, 'criterios_tecnicos')}
                        onChange={(v) =>
                            setPayload(
                                'performance_payload',
                                'criterios_tecnicos',
                                v,
                            )
                        }
                        placeholder="Dureza, flexibilidad, impacto, resistencia química, temperatura..."
                    />
                    <TextAreaField
                        label="Características indispensables"
                        value={perfValue(perf, 'indispensable')}
                        onChange={(v) =>
                            setPayload(
                                'performance_payload',
                                'indispensable',
                                v,
                            )
                        }
                        placeholder="Lo que necesariamente debe cumplir"
                        required
                        error={errors['performance_payload.indispensable']}
                    />
                    <TextAreaField
                        label="Características negociables"
                        value={perfValue(perf, 'negociable')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'negociable', v)
                        }
                        placeholder="Lo que puede ajustarse para balancear costo y desempeño"
                    />
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Acabado requerido
                </h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Color o referencia"
                        value={perfValue(perf, 'color')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'color', v)
                        }
                        placeholder="Ej. RAL 7040 o muestra física"
                        required
                        error={errors['performance_payload.color']}
                    />
                    <SelectField
                        label="Nivel de brillo"
                        value={perfValue(perf, 'brillo')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'brillo', v)
                        }
                        options={brilloOptions}
                        required
                        error={errors['performance_payload.brillo']}
                    />
                    <SelectField
                        label="Textura"
                        value={perfValue(perf, 'textura')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'textura', v)
                        }
                        options={texturaOptions}
                        required
                        error={errors['performance_payload.textura']}
                    />
                    <SelectField
                        label="Cubrición esperada"
                        value={perfValue(perf, 'cubricion')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'cubricion', v)
                        }
                        options={cubricionOptions}
                        required
                        error={errors['performance_payload.cubricion']}
                    />
                    <NumberField
                        label="Tolerancia máxima de color ΔE"
                        value={perfValue(perf, 'delta_e')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'delta_e', v)
                        }
                        placeholder="Ej. 1.0"
                        step="0.1"
                        error={errors['performance_payload.delta_e']}
                    />
                    <SelectField
                        label="Importancia de retención de color y brillo"
                        value={perfValue(perf, 'retencion')}
                        onChange={(v) =>
                            setPayload('performance_payload', 'retencion', v)
                        }
                        options={retencionOptions}
                        required
                        error={errors['performance_payload.retencion']}
                    />
                    <div className="md:col-span-2">
                        <TextAreaField
                            label="Referencia visual o acabado competidor"
                            value={perfValue(perf, 'acabado_ref')}
                            onChange={(v) =>
                                setPayload(
                                    'performance_payload',
                                    'acabado_ref',
                                    v,
                                )
                            }
                            placeholder="Describe la apariencia o indica si habrá muestra física"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
