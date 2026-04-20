export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type BadgeInfo = {
    count: number;
    filterHref: string | null;
};

export type Auth = {
    user: User;
    permissions: string[];
    roles?: string[];
    badges: Record<string, BadgeInfo>;
    dg_puede_subir?: boolean;
    es_aprobador_costos?: boolean;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
