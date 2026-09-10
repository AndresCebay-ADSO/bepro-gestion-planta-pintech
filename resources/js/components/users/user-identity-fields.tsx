import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SignatureUploadField } from '@/components/users/signature-upload-field';

export interface UserIdentityData {
    name: string;
    email: string;
    job_title?: string | null;
    phone?: string | null;
    signature?: File | null;
    remove_signature?: boolean;
}

export interface UserIdentityFieldsProps<T extends UserIdentityData = UserIdentityData> {
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
}

export const UserIdentityFields = <T extends UserIdentityData = UserIdentityData>({
    data,
    setData,
    errors,
    currentSignatureUrl,
    disabled = false,
    className = 'space-y-6',
    showSignature = true,
}: UserIdentityFieldsProps<T>) => {
    return (
        <div className={className}>
            {/* Nombre Completo */}
            <div className="grid gap-2">
                <Label htmlFor="name">
                    Nombre Completo <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="name"
                    name="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value as T['name'])}
                    required
                    autoComplete="name"
                    placeholder="Ej: Juan Pérez"
                    disabled={disabled}
                />
                <InputError message={errors.name} />
            </div>

            {/* Correo Electrónico */}
            <div className="grid gap-2">
                <Label htmlFor="email">
                    Correo Electrónico <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value.toLowerCase() as T['email'])}
                    required
                    autoComplete="username"
                    placeholder="juan@pintech.com"
                    disabled={disabled}
                />
                <InputError message={errors.email} />
            </div>

            {/* Cargo */}
            <div className="grid gap-2">
                <Label htmlFor="job_title">Cargo</Label>
                <Input
                    id="job_title"
                    name="job_title"
                    value={data.job_title ?? ''}
                    onChange={(e) => setData('job_title', (e.target.value || null) as T['job_title'])}
                    placeholder="Ej: Gerente de Producción"
                    disabled={disabled}
                />
                <InputError message={errors.job_title} />
            </div>

            {/* Teléfono */}
            <div className="grid gap-2">
                <Label htmlFor="phone">Teléfono</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    value={data.phone ?? ''}
                    onChange={(e) => setData('phone', (e.target.value || null) as T['phone'])}
                    placeholder="Ej: 3001234567"
                    disabled={disabled}
                />
                <InputError message={errors.phone} />
            </div>

            {/* Firma Digital */}
            {showSignature && (
                <SignatureUploadField
                    currentSignatureUrl={data.remove_signature ? null : currentSignatureUrl}
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
