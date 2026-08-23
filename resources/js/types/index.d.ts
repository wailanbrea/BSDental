export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at?: string | null;
    role?: string;
}

export interface Tenant {
    id: string;
    name: string;
    slug: string;
    domain?: string;
    status: 'active' | 'suspended' | 'provisioning' | 'archived';
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth?: {
        user?: User | null;
    };
    tenant?: Tenant | null;
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };
    errors?: Record<string, string>;
};
