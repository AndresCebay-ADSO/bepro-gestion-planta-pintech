import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Save, Send } from 'lucide-react';
import { useState } from 'react';

import ApplicationStep from '@/components/paint-development-requests/steps/ApplicationStep';
import ContextStep from '@/components/paint-development-requests/steps/ContextStep';
import PerformanceStep from '@/components/paint-development-requests/steps/PerformanceStep';
import SpecificationsStep from '@/components/paint-development-requests/steps/SpecificationsStep';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type RawPaintDevFormData = {
    client_name: string | null;
    project_name: string | null;
    responsible: string | null;
    city: string | null;
    sample_due_date: string | null;
    current_product: string | null;
    context_payload: Record<string, unknown>;
    performance_payload: Record<string, unknown>;
    application_payload: Record<string, unknown>;
    specifications_payload: Record<string, unknown>;
};

export type PaintDevFormData = {
    client_name: string;
    project_name: string;
    responsible: string;
    city: string;
    sample_due_date: string;
    current_product: string;
    context_payload: {
        sustrato: string;
        estado_superficie: string;
        otro_sustrato: string;
        pintura_existente: string;
        sistema_existente: string;
        preparacion: string[];
        exposicion: string[];
        agua_humedad: string;
        corrosividad: string;
        temp_min: string;
        temp_max: string;
        ciclos_termicos: string;
        quimicos: string;
    };
    performance_payload: {
        funcion: string[];
        vida_util: string;
        prioridad: string;
        pruebas: string[];
        adherencia_obj: string;
        camara_obj: string;
        criterios_tecnicos: string;
        indispensable: string;
        negociable: string;
        color: string;
        brillo: string;
        textura: string;
        cubricion: string;
        delta_e: string;
        retencion: string;
        acabado_ref: string;
    };
    application_payload: {
        metodo: string[];
        temp_aplicacion: string;
        humedad_aplicacion: string;
        geometria: string;
        dft_min: string;
        dft_max: string;
        manos: string;
        repinte: string;
        servicio: string;
        antidescuelgue: string;
        equipo_detalle: string;
        vehiculo: string;
        tecnologia: string;
        otra_tecnologia: string;
        componentes: string;
        relacion_mezcla: string;
        pot_life: string;
        solidos_vol: string;
        ajustador: string;
        voc: string;
        restricciones: string[];
        sistema_capas: string;
    };
    specifications_payload: {
        viscosidad_metodo: string;
        densidad: string;
        solidos_peso: string;
        finura: string;
        secados: string;
        estabilidad: string;
        presentacion: string[];
        consumo: string;
        frecuencia: string;
        almacenamiento: string;
        kit_ab: string;
        competidor: string;
        meta_competidor: string;
        costo_objetivo: string;
        cantidad_prueba: string;
        aprobador: string;
        forma_aprobacion: string;
        criterios_aprobacion: string;
        observaciones: string;
    };
};

function emptyFormData(): PaintDevFormData {
    return {
        client_name: '',
        project_name: '',
        responsible: '',
        city: '',
        sample_due_date: '',
        current_product: '',
        context_payload: {
            sustrato: '',
            estado_superficie: '',
            otro_sustrato: '',
            pintura_existente: '',
            sistema_existente: '',
            preparacion: [],
            exposicion: [],
            agua_humedad: '',
            corrosividad: '',
            temp_min: '',
            temp_max: '',
            ciclos_termicos: '',
            quimicos: '',
        },
        performance_payload: {
            funcion: [],
            vida_util: '',
            prioridad: '',
            pruebas: [],
            adherencia_obj: '',
            camara_obj: '',
            criterios_tecnicos: '',
            indispensable: '',
            negociable: '',
            color: '',
            brillo: '',
            textura: '',
            cubricion: '',
            delta_e: '',
            retencion: '',
            acabado_ref: '',
        },
        application_payload: {
            metodo: [],
            temp_aplicacion: '',
            humedad_aplicacion: '',
            geometria: '',
            dft_min: '',
            dft_max: '',
            manos: '',
            repinte: '',
            servicio: '',
            antidescuelgue: '',
            equipo_detalle: '',
            vehiculo: '',
            tecnologia: '',
            otra_tecnologia: '',
            componentes: '',
            relacion_mezcla: '',
            pot_life: '',
            solidos_vol: '',
            ajustador: '',
            voc: '',
            restricciones: [],
            sistema_capas: '',
        },
        specifications_payload: {
            viscosidad_metodo: '',
            densidad: '',
            solidos_peso: '',
            finura: '',
            secados: '',
            estabilidad: '',
            presentacion: [],
            consumo: '',
            frecuencia: '',
            almacenamiento: '',
            kit_ab: '',
            competidor: '',
            meta_competidor: '',
            costo_objetivo: '',
            cantidad_prueba: '',
            aprobador: '',
            forma_aprobacion: '',
            criterios_aprobacion: '',
            observaciones: '',
        },
    };
}

export function toFormData(
    raw: Partial<RawPaintDevFormData>,
): PaintDevFormData {
    const empty = emptyFormData();

    return {
        client_name: raw.client_name ?? '',
        project_name: raw.project_name ?? '',
        responsible: raw.responsible ?? '',
        city: raw.city ?? '',
        sample_due_date: raw.sample_due_date ?? '',
        current_product: raw.current_product ?? '',
        context_payload: {
            ...empty.context_payload,
            ...(raw.context_payload ?? {}),
        },
        performance_payload: {
            ...empty.performance_payload,
            ...(raw.performance_payload ?? {}),
        },
        application_payload: {
            ...empty.application_payload,
            ...(raw.application_payload ?? {}),
        },
        specifications_payload: {
            ...empty.specifications_payload,
            ...(raw.specifications_payload ?? {}),
        },
    };
}

type Props = {
    initialData: Partial<RawPaintDevFormData>;
    submitUrl: string;
    method?: 'post' | 'put';
    title: string;
    backUrl: string;
    saveLabel: string;
    submitLabel: string;
};

const STEPS = [
    { id: 1, title: 'Contexto', subtitle: 'Proyecto, sustrato y exposición' },
    { id: 2, title: 'Desempeño', subtitle: 'Función, resistencia y acabado' },
    { id: 3, title: 'Aplicación', subtitle: 'Método, espesores y tecnología' },
    {
        id: 4,
        title: 'Especificaciones',
        subtitle: 'Control, suministro y aprobación',
    },
];

export default function PaintDevelopmentRequestForm({
    initialData,
    submitUrl,
    method = 'post',
    title,
    backUrl,
    saveLabel,
    submitLabel,
}: Props) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<PaintDevFormData>(toFormData(initialData));

    const [step, setStep] = useState(1);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [localErrors, setLocalErrors] = useState<Record<string, string>>({});

    const combinedErrors: Record<string, string> = {
        ...errors,
        ...localErrors,
    };

    const handleSubmit = (asDraft: boolean) => {
        for (let i = 1; i <= 4; i++) {
            const stepErrors = validateStep(i);

            if (Object.keys(stepErrors).length > 0) {
                setLocalErrors((prev) => ({ ...prev, ...stepErrors }));
                setStep(i);
                window.scrollTo({ top: 0, behavior: 'smooth' });

                return;
            }
        }

        transform((formData) => ({
            ...formData,
            _draft: asDraft,
        }));

        if (method === 'put') {
            put(submitUrl);
        } else {
            post(submitUrl);
        }
    };

    const numericPayloadFields = new Set([
        'context_payload.temp_min',
        'context_payload.temp_max',
        'application_payload.temp_aplicacion',
        'application_payload.humedad_aplicacion',
        'application_payload.dft_min',
        'application_payload.dft_max',
        'application_payload.manos',
        'application_payload.pot_life',
        'application_payload.solidos_vol',
        'application_payload.voc',
        'performance_payload.adherencia_obj',
        'performance_payload.camara_obj',
        'performance_payload.delta_e',
    ]);

    const clearLocalError = (key: string) => {
        setLocalErrors((prev) => {
            if (!(key in prev)) {
                return prev;
            }

            const next = { ...prev };
            delete next[key];

            return next;
        });
    };

    const handleSetData = (field: string, value: unknown) => {
        clearLocalError(field);
        setData(
            field as keyof PaintDevFormData,
            value as PaintDevFormData[keyof PaintDevFormData],
        );
    };

    const handleSetPayload = (
        payloadKey: string,
        field: string,
        value: unknown,
    ) => {
        const errorKey = `${payloadKey}.${field}`;
        clearLocalError(errorKey);

        const normalizedValue =
            value === '' && numericPayloadFields.has(errorKey) ? null : value;

        setData((prev) => ({
            ...prev,
            [payloadKey]: {
                ...(prev[payloadKey as keyof PaintDevFormData] as Record<
                    string,
                    unknown
                >),
                [field]: normalizedValue,
            },
        }));
    };

    const ctx = data.context_payload;
    const perf = data.performance_payload;
    const app = data.application_payload;
    const spec = data.specifications_payload;

    const validateStep = (n: number): Record<string, string> => {
        const errs: Record<string, string> = {};

        if (n === 1) {
            if (!data.client_name) {
                errs.client_name = 'El nombre del cliente es obligatorio.';
            }

            if (!data.project_name) {
                errs.project_name = 'El nombre del proyecto es obligatorio.';
            }

            if (!data.responsible) {
                errs.responsible = 'El responsable es obligatorio.';
            }

            if (!data.city) {
                errs.city = 'La ciudad es obligatoria.';
            }

            if (!data.sample_due_date) {
                errs.sample_due_date = 'La fecha requerida es obligatoria.';
            }

            if (!ctx.sustrato) {
                errs['context_payload.sustrato'] = 'Selecciona un sustrato.';
            }

            if (!ctx.estado_superficie) {
                errs['context_payload.estado_superficie'] =
                    'Selecciona el estado de la superficie.';
            }

            if (!ctx.pintura_existente) {
                errs['context_payload.pintura_existente'] =
                    'Indica si hay pintura existente.';
            }

            if ((ctx.preparacion as string[]).length === 0) {
                errs['context_payload.preparacion'] =
                    'Selecciona al menos una preparación.';
            }

            if ((ctx.exposicion as string[]).length === 0) {
                errs['context_payload.exposicion'] =
                    'Selecciona al menos un tipo de exposición.';
            }

            if (!ctx.agua_humedad) {
                errs['context_payload.agua_humedad'] =
                    'Selecciona el contacto con agua/humedad.';
            }

            if (!ctx.ciclos_termicos) {
                errs['context_payload.ciclos_termicos'] =
                    'Indica si habrá ciclos térmicos.';
            }

            if (ctx.sustrato === 'Otro' && !ctx.otro_sustrato) {
                errs['context_payload.otro_sustrato'] =
                    'Especifica el sustrato.';
            }

            if (ctx.pintura_existente === 'Sí' && !ctx.sistema_existente) {
                errs['context_payload.sistema_existente'] =
                    'Describe el sistema de pintura actual.';
            }

            const tempMin = ctx.temp_min ? parseFloat(ctx.temp_min) : null;
            const tempMax = ctx.temp_max ? parseFloat(ctx.temp_max) : null;

            if (tempMin !== null && tempMax !== null && tempMin > tempMax) {
                errs['context_payload.temp_max'] =
                    'La temperatura mínima no puede ser mayor que la máxima.';
            }

            return errs;
        }

        if (n === 2) {
            if ((perf.funcion as string[]).length === 0) {
                errs['performance_payload.funcion'] =
                    'Selecciona al menos una función.';
            }

            if (!perf.vida_util) {
                errs['performance_payload.vida_util'] =
                    'Selecciona la vida útil esperada.';
            }

            if (!perf.prioridad) {
                errs['performance_payload.prioridad'] =
                    'Selecciona la prioridad técnica.';
            }

            if (!perf.indispensable) {
                errs['performance_payload.indispensable'] =
                    'Describe las características indispensables.';
            }

            if (!perf.color) {
                errs['performance_payload.color'] =
                    'Indica el color o referencia.';
            }

            if (!perf.brillo) {
                errs['performance_payload.brillo'] =
                    'Selecciona el nivel de brillo.';
            }

            if (!perf.textura) {
                errs['performance_payload.textura'] = 'Selecciona la textura.';
            }

            if (!perf.cubricion) {
                errs['performance_payload.cubricion'] =
                    'Selecciona la cubrición.';
            }

            if (!perf.retencion) {
                errs['performance_payload.retencion'] =
                    'Selecciona la retención de color/brillo.';
            }

            return errs;
        }

        if (n === 3) {
            if ((app.metodo as string[]).length === 0) {
                errs['application_payload.metodo'] =
                    'Selecciona al menos un método de aplicación.';
            }

            if (!app.geometria) {
                errs['application_payload.geometria'] =
                    'Selecciona la geometría.';
            }

            if (!app.manos) {
                errs['application_payload.manos'] =
                    'Indica el número de manos.';
            }

            if (!app.repinte) {
                errs['application_payload.repinte'] =
                    'Indica el tiempo de repinte.';
            }

            if (!app.servicio) {
                errs['application_payload.servicio'] =
                    'Indica el tiempo para puesta en servicio.';
            }

            if (!app.antidescuelgue) {
                errs['application_payload.antidescuelgue'] =
                    'Selecciona la exigencia de descuelgue.';
            }

            if (!app.vehiculo) {
                errs['application_payload.vehiculo'] =
                    'Selecciona la base requerida.';
            }

            if (!app.tecnologia) {
                errs['application_payload.tecnologia'] =
                    'Selecciona la tecnología preferida.';
            }

            if (!app.componentes) {
                errs['application_payload.componentes'] =
                    'Selecciona el número de componentes.';
            }

            if (!app.ajustador) {
                errs['application_payload.ajustador'] =
                    'Indica si se permite ajustador.';
            }

            if (!app.sistema_capas) {
                errs['application_payload.sistema_capas'] =
                    'Describe el sistema completo previsto.';
            }

            if (app.tecnologia === 'Otra' && !app.otra_tecnologia) {
                errs['application_payload.otra_tecnologia'] =
                    'Especifica la tecnología.';
            }

            if (app.manos) {
                const manosVal = parseFloat(app.manos);

                if (!Number.isInteger(manosVal) || manosVal < 1) {
                    errs['application_payload.manos'] =
                        'El número de manos debe ser un entero mayor o igual a 1.';
                }
            }

            if (app.pot_life) {
                const potLifeVal = parseFloat(app.pot_life);

                if (!Number.isInteger(potLifeVal) || potLifeVal < 1) {
                    errs['application_payload.pot_life'] =
                        'El pot life debe ser un entero mayor o igual a 1.';
                }
            }

            if (app.solidos_vol) {
                const solidosVal = parseFloat(app.solidos_vol);

                if (
                    Number.isNaN(solidosVal) ||
                    solidosVal < 0 ||
                    solidosVal > 100
                ) {
                    errs['application_payload.solidos_vol'] =
                        'Los sólidos en volumen deben estar entre 0 y 100.';
                }
            }

            if (app.voc) {
                const vocVal = parseFloat(app.voc);

                if (Number.isNaN(vocVal) || vocVal < 0) {
                    errs['application_payload.voc'] =
                        'El VOC debe ser un número mayor o igual a 0.';
                }
            }

            const dftMin = app.dft_min ? parseFloat(app.dft_min) : null;
            const dftMax = app.dft_max ? parseFloat(app.dft_max) : null;

            if (dftMin !== null && dftMax !== null && dftMin > dftMax) {
                errs['application_payload.dft_max'] =
                    'El espesor mínimo no puede ser mayor que el máximo.';
            }

            return errs;
        }

        if (n === 4) {
            if (!spec.secados) {
                errs['specifications_payload.secados'] =
                    'Indica los tiempos de secado.';
            }

            if (!spec.estabilidad) {
                errs['specifications_payload.estabilidad'] =
                    'Indica la estabilidad y vida en almacenamiento.';
            }

            if ((spec.presentacion as string[]).length === 0) {
                errs['specifications_payload.presentacion'] =
                    'Selecciona al menos una presentación.';
            }

            if (!spec.consumo) {
                errs['specifications_payload.consumo'] =
                    'Indica el consumo estimado.';
            }

            if (!spec.frecuencia) {
                errs['specifications_payload.frecuencia'] =
                    'Selecciona la frecuencia de compra.';
            }

            if (!spec.meta_competidor) {
                errs['specifications_payload.meta_competidor'] =
                    'Selecciona el objetivo frente a la referencia.';
            }

            if (!spec.costo_objetivo) {
                errs['specifications_payload.costo_objetivo'] =
                    'Indica el costo o precio objetivo.';
            }

            if (!spec.cantidad_prueba) {
                errs['specifications_payload.cantidad_prueba'] =
                    'Indica la cantidad de muestra o prueba.';
            }

            if (!spec.aprobador) {
                errs['specifications_payload.aprobador'] =
                    'Indica quién aprueba el desarrollo.';
            }

            if (!spec.forma_aprobacion) {
                errs['specifications_payload.forma_aprobacion'] =
                    'Selecciona la forma de aprobación.';
            }

            if (!spec.criterios_aprobacion) {
                errs['specifications_payload.criterios_aprobacion'] =
                    'Describe los criterios exactos de aprobación.';
            }

            return errs;
        }

        return errs;
    };

    const goStep = (target: number) => {
        if (target < 1 || target > 4) {
            return;
        }

        if (target > step) {
            for (let i = 1; i <= target; i++) {
                const stepErrors = validateStep(i);

                if (Object.keys(stepErrors).length > 0) {
                    setLocalErrors((prev) => ({ ...prev, ...stepErrors }));
                    setStep(i);
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    return;
                }
            }
        }

        setStep(target);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <>
            <div className="space-y-4 p-6">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={backUrl}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {title}
                        </h1>
                    </div>
                </div>

                {/* Stepper */}
                <div className="flex items-center justify-between">
                    {STEPS.map((s, idx) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => goStep(s.id)}
                            className={`flex flex-1 items-center gap-2 px-2 py-2 text-sm font-medium transition-colors ${
                                step === s.id
                                    ? 'text-primary'
                                    : step > s.id
                                      ? 'text-muted-foreground'
                                      : 'text-muted-foreground/50'
                            }`}
                        >
                            <span
                                className={`flex h-8 w-8 items-center justify-center rounded-full border text-xs ${
                                    step === s.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : step > s.id
                                          ? 'border-primary/30 bg-primary/10 text-primary'
                                          : 'border-input bg-background'
                                }`}
                            >
                                {step > s.id ? '✓' : s.id}
                            </span>
                            <span className="hidden sm:inline">{s.title}</span>
                            {idx < STEPS.length - 1 && (
                                <ChevronRight className="ml-auto h-4 w-4 text-muted-foreground" />
                            )}
                        </button>
                    ))}
                </div>

                {/* Progress bar */}
                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full bg-primary transition-all"
                        style={{ width: `${step * 25}%` }}
                    />
                </div>

                <form className="space-y-6">
                    {step === 1 && (
                        <ContextStep
                            data={data}
                            setPayload={handleSetPayload}
                            setData={handleSetData}
                            errors={combinedErrors}
                        />
                    )}
                    {step === 2 && (
                        <PerformanceStep
                            data={data}
                            setPayload={handleSetPayload}
                            setData={handleSetData}
                            errors={combinedErrors}
                        />
                    )}
                    {step === 3 && (
                        <ApplicationStep
                            data={data}
                            setPayload={handleSetPayload}
                            setData={handleSetData}
                            errors={combinedErrors}
                        />
                    )}
                    {step === 4 && (
                        <SpecificationsStep
                            data={data}
                            setPayload={handleSetPayload}
                            setData={handleSetData}
                            errors={combinedErrors}
                        />
                    )}

                    {/* Server errors summary */}
                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                            <p className="font-medium">
                                Hay errores en el formulario:
                            </p>
                            <ul className="mt-1 list-disc pl-4">
                                {Object.entries(errors).map(
                                    ([key, message]) => (
                                        <li key={key}>{message}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}

                    {/* Footer actions */}
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4 shadow-sm">
                        <div className="text-xs text-muted-foreground">
                            Los campos con{' '}
                            <span className="text-destructive">*</span> son
                            obligatorios
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {step > 1 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => goStep(step - 1)}
                                >
                                    ← Anterior
                                </Button>
                            )}
                            {step < 4 && (
                                <Button
                                    type="button"
                                    variant="default"
                                    onClick={() => goStep(step + 1)}
                                >
                                    Continuar →
                                </Button>
                            )}
                            {step === 4 && (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => handleSubmit(true)}
                                    >
                                        <Save className="mr-2 h-4 w-4" />
                                        {saveLabel}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="default"
                                        disabled={processing}
                                        onClick={() => setConfirmOpen(true)}
                                    >
                                        <Send className="mr-2 h-4 w-4" />
                                        {submitLabel}
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </form>
            </div>

            {/* Confirm submit dialog */}
            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Enviar solicitud</DialogTitle>
                        <DialogDescription>
                            ¿Guardar y enviar esta solicitud para revisión? No
                            podrás editarla después.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => {
                                setConfirmOpen(false);
                                handleSubmit(false);
                            }}
                        >
                            Enviar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
