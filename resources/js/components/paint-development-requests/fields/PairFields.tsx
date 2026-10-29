import { DecimalInput } from '@/components/decimal-input';
import { FormField } from '@/components/form-field';
import type { FormFieldControlProps } from '@/components/form-field';
import { TECHNICAL_NUMBER_LIMITS } from '@/lib/number-types';

type FieldDef = {
    name: string;
    value: string;
    onChange: (v: string) => void;
    placeholder: string;
    suffix: string;
    /** Admite valores bajo cero (temperaturas). */
    allowNegative?: boolean;
};

type Props = {
    /**
     * El grupo de datos de los dos campos (`context_payload`): cada uno toma como `id` su propia llave
     * (`context_payload.temp_min`), la misma de su error, para que el foco llegue al campo exacto. La etiqueta señala el
     * primero.
     */
    payload: string;
    label: string;
    a: FieldDef;
    b: FieldDef;
    required?: boolean;
    error?: string;
};

function PairInput({
    field,
    control,
}: {
    field: FieldDef;
    control: Partial<FormFieldControlProps> & { 'aria-label'?: string };
}) {
    return (
        <div className="relative min-w-0">
            <DecimalInput
                {...control}
                {...TECHNICAL_NUMBER_LIMITS}
                allowNegative={field.allowNegative}
                value={field.value}
                onValueChange={field.onChange}
                placeholder={field.placeholder}
            />
            <span className="pointer-events-none absolute top-2.5 right-3 text-xs text-muted-foreground">
                {field.suffix}
            </span>
        </div>
    );
}

/** Dos números bajo una sola etiqueta (mínimo y máximo): el lector de pantalla nombra el segundo con su ayuda. */
export default function PairFields({
    payload,
    label,
    a,
    b,
    required,
    error,
}: Props) {
    return (
        <FormField
            id={`${payload}.${a.name}`}
            label={label}
            required={required}
            error={error}
        >
            {(control) => (
                <div className="grid grid-cols-2 gap-3">
                    <PairInput field={a} control={control} />
                    <PairInput
                        field={b}
                        control={{
                            ...control,
                            id: `${payload}.${b.name}`,
                            'aria-label': `${label}, ${b.placeholder}`,
                        }}
                    />
                </div>
            )}
        </FormField>
    );
}
