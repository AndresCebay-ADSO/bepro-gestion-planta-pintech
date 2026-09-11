export type UserRole = 'admin' | 'produccion' | 'comercial' | 'operador';

export type Role = {
    id: number;
    name: string;
    guard_name?: string;
    [key: string]: unknown;
};

export type UserRoleRecord = {
    name: UserRole | string;
    [key: string]: unknown;
};

export type User = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    job_title?: string | null;
    signature_url?: string | null;
    is_active?: boolean;
    avatar?: string;
    role?: UserRole | string;
    roles?: UserRoleRecord[] | string[];
    role_names?: string[];
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
