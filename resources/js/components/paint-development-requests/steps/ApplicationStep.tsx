import CheckboxGroup from '@/components/paint-development-requests/fields/CheckboxGroup';
import NumberField from '@/components/paint-development-requests/fields/NumberField';
import PairFields from '@/components/paint-development-requests/fields/PairFields';
import SelectField from '@/components/paint-development-requests/fields/SelectField';
import TextAreaField from '@/components/paint-development-requests/fields/TextAreaField';
import TextField from '@/components/paint-development-requests/fields/TextField';
import {
    metodoOptions,
    geometriaOptions,
    antidescuelgueOptions,
    vehiculoOptions,
    tecnologiaOptions,
    componentesOptions,
    relacionMezclaOptions,
    ajustadorOptions,
    restriccionesOptions,
} from '@/components/paint-development-requests/options';
import type { StepProps } from '@/components/paint-development-requests/types';

function appValue(
    payload: Record<string, unknown>,
    key: string,
    fallback: string = '',
): string {
    const v = payload[key];

    return typeof v === 'string' ? v : fallback;
}

function appArray(payload: Record<string, unknown>, key: string): string[] {
    const v = payload[key];

    return Array.isArray(v)
        ? v.filter((i): i is string => typeof i === 'string')
        : [];
}

export default function ApplicationStep({
    data,
    setPayload,
    errors,
}: StepProps) {
    const app = data.application_payload;

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Aplicación en campo
                </h2>
                <div className="grid gap-4">
                    <CheckboxGroup
                        id="application_payload.metodo"
                        label="Método de aplicación"
                        values={appArray(app, 'metodo')}
                        onChange={(v) =>
                            setPayload('application_payload', 'metodo', v)
                        }
                        options={metodoOptions}
                        required
                        error={errors['application_payload.metodo']}
                    />
                    <PairFields
                        payload="application_payload"
                        label="Condiciones durante la aplicación"
                        a={{
                            name: 'temp_aplicacion',
                            value: appValue(app, 'temp_aplicacion'),
                            onChange: (v) =>
                                setPayload(
                                    'application_payload',
                                    'temp_aplicacion',
                                    v,
                                ),
                            placeholder: 'Temperatura',
                            suffix: '°C',
                            allowNegative: true,
                        }}
                        b={{
                            name: 'humedad_aplicacion',
                            value: appValue(app, 'humedad_aplicacion'),
                            onChange: (v) =>
                                setPayload(
                                    'application_payload',
                                    'humedad_aplicacion',
                                    v,
                                ),
                            placeholder: 'Humedad',
                            suffix: '% HR',
                        }}
                        error={
                            errors['application_payload.temp_aplicacion'] ||
                            errors['application_payload.humedad_aplicacion']
                        }
                    />
                    <SelectField
                        id="application_payload.geometria"
                        label="Geometría o tipo de pieza"
                        value={appValue(app, 'geometria')}
                        onChange={(v) =>
                            setPayload('application_payload', 'geometria', v)
                        }
                        options={geometriaOptions}
                        required
                        error={errors['application_payload.geometria']}
                    />
                    <PairFields
                        payload="application_payload"
                        label="Espesor seco requerido por mano"
                        a={{
                            name: 'dft_min',
                            value: appValue(app, 'dft_min'),
                            onChange: (v) =>
                                setPayload('application_payload', 'dft_min', v),
                            placeholder: 'Mín.',
                            suffix: 'mils',
                        }}
                        b={{
                            name: 'dft_max',
                            value: appValue(app, 'dft_max'),
                            onChange: (v) =>
                                setPayload('application_payload', 'dft_max', v),
                            placeholder: 'Máx.',
                            suffix: 'mils',
                        }}
                        error={
                            errors['application_payload.dft_max'] ||
                            errors['application_payload.dft_min']
                        }
                    />
                    <NumberField
                        id="application_payload.manos"
                        label="Número previsto de manos"
                        value={appValue(app, 'manos')}
                        onChange={(v) =>
                            setPayload('application_payload', 'manos', v)
                        }
                        placeholder="Ej. 2"
                        integer
                        required
                        error={errors['application_payload.manos']}
                    />
                    <TextField
                        id="application_payload.repinte"
                        label="Tiempo disponible entre manos"
                        value={appValue(app, 'repinte')}
                        onChange={(v) =>
                            setPayload('application_payload', 'repinte', v)
                        }
                        placeholder="Ej. 4–24 horas"
                        required
                        error={errors['application_payload.repinte']}
                    />
                    <TextField
                        id="application_payload.servicio"
                        label="Tiempo máximo para puesta en servicio"
                        value={appValue(app, 'servicio')}
                        onChange={(v) =>
                            setPayload('application_payload', 'servicio', v)
                        }
                        placeholder="Ej. 72 horas"
                        required
                        error={errors['application_payload.servicio']}
                    />
                    <SelectField
                        id="application_payload.antidescuelgue"
                        label="Exigencia de resistencia al descuelgue"
                        value={appValue(app, 'antidescuelgue')}
                        onChange={(v) =>
                            setPayload(
                                'application_payload',
                                'antidescuelgue',
                                v,
                            )
                        }
                        options={antidescuelgueOptions}
                        required
                        error={errors['application_payload.antidescuelgue']}
                    />
                    <TextAreaField
                        id="application_payload.equipo_detalle"
                        label="Equipo, boquilla, presión o restricciones"
                        value={appValue(app, 'equipo_detalle')}
                        onChange={(v) =>
                            setPayload(
                                'application_payload',
                                'equipo_detalle',
                                v,
                            )
                        }
                        placeholder="Incluye experiencia del aplicador y datos del equipo"
                    />
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-semibold">
                    Tecnología y sistema de pintura
                </h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <SelectField
                        id="application_payload.vehiculo"
                        label="Base requerida"
                        value={appValue(app, 'vehiculo')}
                        onChange={(v) =>
                            setPayload('application_payload', 'vehiculo', v)
                        }
                        options={vehiculoOptions}
                        required
                        error={errors['application_payload.vehiculo']}
                    />
                    <SelectField
                        id="application_payload.tecnologia"
                        label="Tecnología preferida"
                        value={appValue(app, 'tecnologia')}
                        onChange={(v) => {
                            setPayload('application_payload', 'tecnologia', v);

                            if (v !== 'Otra') {
                                setPayload(
                                    'application_payload',
                                    'otra_tecnologia',
                                    '',
                                );
                            }
                        }}
                        options={tecnologiaOptions}
                        required
                        error={errors['application_payload.tecnologia']}
                    />
                    {appValue(app, 'tecnologia') === 'Otra' && (
                        <div className="md:col-span-2">
                            <TextField
                                id="application_payload.otra_tecnologia"
                                label="Especifica la tecnología"
                                value={appValue(app, 'otra_tecnologia')}
                                onChange={(v) =>
                                    setPayload(
                                        'application_payload',
                                        'otra_tecnologia',
                                        v,
                                    )
                                }
                                required
                                error={
                                    errors[
                                        'application_payload.otra_tecnologia'
                                    ]
                                }
                            />
                        </div>
                    )}
                    <SelectField
                        id="application_payload.componentes"
                        label="Número de componentes"
                        value={appValue(app, 'componentes')}
                        onChange={(v) =>
                            setPayload('application_payload', 'componentes', v)
                        }
                        options={componentesOptions}
                        required
                        error={errors['application_payload.componentes']}
                    />
                    <SelectField
                        id="application_payload.relacion_mezcla"
                        label="Relación de mezcla preferida"
                        value={appValue(app, 'relacion_mezcla')}
                        onChange={(v) =>
                            setPayload(
                                'application_payload',
                                'relacion_mezcla',
                                v,
                            )
                        }
                        options={relacionMezclaOptions}
                    />
                    <NumberField
                        id="application_payload.pot_life"
                        label="Pot life mínimo requerido"
                        value={appValue(app, 'pot_life')}
                        onChange={(v) =>
                            setPayload('application_payload', 'pot_life', v)
                        }
                        suffix="min"
                        integer
                        error={errors['application_payload.pot_life']}
                    />
                    <NumberField
                        id="application_payload.solidos_vol"
                        label="Sólidos por volumen objetivo"
                        value={appValue(app, 'solidos_vol')}
                        onChange={(v) =>
                            setPayload('application_payload', 'solidos_vol', v)
                        }
                        suffix="%"
                        error={errors['application_payload.solidos_vol']}
                    />
                    <SelectField
                        id="application_payload.ajustador"
                        label="¿Se permite ajustador?"
                        value={appValue(app, 'ajustador')}
                        onChange={(v) =>
                            setPayload('application_payload', 'ajustador', v)
                        }
                        options={ajustadorOptions}
                        required
                        error={errors['application_payload.ajustador']}
                    />
                    <NumberField
                        id="application_payload.voc"
                        label="VOC máximo permitido"
                        value={appValue(app, 'voc')}
                        onChange={(v) =>
                            setPayload('application_payload', 'voc', v)
                        }
                        suffix="g/L"
                        error={errors['application_payload.voc']}
                    />
                    <div className="md:col-span-2">
                        <CheckboxGroup
                            id="application_payload.restricciones"
                            label="Restricciones especiales"
                            values={appArray(app, 'restricciones')}
                            onChange={(v) =>
                                setPayload(
                                    'application_payload',
                                    'restricciones',
                                    v,
                                )
                            }
                            options={restriccionesOptions}
                        />
                    </div>
                    <div className="md:col-span-2">
                        <TextAreaField
                            id="application_payload.sistema_capas"
                            label="Sistema completo previsto"
                            value={appValue(app, 'sistema_capas')}
                            onChange={(v) =>
                                setPayload(
                                    'application_payload',
                                    'sistema_capas',
                                    v,
                                )
                            }
                            placeholder="Qué imprimante, intermedia y acabado irán debajo o encima"
                            required
                            error={errors['application_payload.sistema_capas']}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
