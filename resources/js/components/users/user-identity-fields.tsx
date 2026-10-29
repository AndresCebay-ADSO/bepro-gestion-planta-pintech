import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { SignatureUploadField } from '@/components/users/signature-upload-field';

export interface UserIdentityData {
    name: string;
    email: string;
    job_title?: string | null;
    phone?: string | null;
    signature?: File | null;
    remove_signature?: boolean;
}

export interface UserIdentityFieldsProps<
    T extends UserIdentityData = UserIdentityData,
> {
    data: T;
    setData: {
        <K extends keyof T>(field: K, value: T[K]): void;
        (data: Partial<T>): void;
        (updater: (previousData: T) => T): void;
    };
    errors: Partial<Record<keyof T | string, string | undefined>>;
    currentSignatureUrl?: string | null;
    disabled?: boolean;
    className?: string;
    showSignature?: boolean;
    /** El correo no se cambia aquí (el perfil, D-USR1): un administrador lo cambia desde Usuarios. */
    emailReadOnly?: boolean;
}

export const UserIdentityFields = <
    T extends UserIdentityData = UserIdentityData,
>({
    data,
    setData,
    errors,
    currentSignatureUrl,
    disabled = false,
    className = 'grid gap-6 md:grid-cols-2',
    showSignature = true,
    emailReadOnly = false,
}: UserIdentityFieldsProps<T>) => {
    return (
        <div className={className}>
            <FormField
                id="name"
                label="Nombre completo"
                required
                error={errors.name}
            >
                <Input
                    name="name"
                    value={data.name}
                    onChange={(e) =>
                        setData('name', e.target.value as T['name'])
                    }
                    maxLength={255}
                    autoComplete="name"
                    placeholder="Ej: Juan Pérez"
                    disabled={disabled}
                />
            </FormField>

            <FormField id="job_title" label="Cargo" error={errors.job_title}>
                <Input
                    name="job_title"
                    value={data.job_title ?? ''}
                    onChange={(e) =>
                        setData('job_title', e.target.value as T['job_title'])
                    }
                    maxLength={255}
                    placeholder="Ej: Gerente de Producción"
                    disabled={disabled}
                />
            </FormField>

            <FormField
                id="email"
                label="Correo electrónico"
                required
                help={
                    emailReadOnly
                        ? 'Para cambiar tu correo, pídelo a un administrador.'
                        : undefined
                }
                error={errors.email}
            >
                <Input
                    name="email"
                    type="email"
                    value={data.email}
                    onChange={(e) =>
                        setData(
                            'email',
                            e.target.value.toLowerCase() as T['email'],
                        )
                    }
                    maxLength={255}
                    readOnly={emailReadOnly}
                    className={
                        emailReadOnly ? 'cursor-not-allowed bg-muted/50' : ''
                    }
                    autoComplete="username"
                    placeholder="juan@pintech.com"
                    disabled={disabled}
                />
            </FormField>

            <FormField id="phone" label="Teléfono" error={errors.phone}>
                <Input
                    name="phone"
                    type="tel"
                    value={data.phone ?? ''}
                    onChange={(e) =>
                        setData('phone', e.target.value as T['phone'])
                    }
                    maxLength={20}
                    placeholder="Ej: 3001234567"
                    disabled={disabled}
                />
            </FormField>

            {showSignature && (
                <SignatureUploadField
                    className="md:col-span-2"
                    currentSignatureUrl={
                        data.remove_signature ? null : currentSignatureUrl
                    }
                    signatureFile={data.signature}
                    onSignatureChange={(file) => {
                        setData((prev) => ({
                            ...prev,
                            signature: file as T['signature'],
                            remove_signature: false as T['remove_signature'],
                        }));
                    }}
                    onRemoveSignature={() => {
                        setData((prev) => ({
                            ...prev,
                            signature: null as T['signature'],
                            remove_signature: true as T['remove_signature'],
                        }));
                    }}
                    error={errors.signature}
                    disabled={disabled}
                />
            )}
        </div>
    );
};

export default UserIdentityFields;
