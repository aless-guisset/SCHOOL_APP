export type User = {
    id: number;
    firstname: string;
    lastname: string;
    email: string;
    phone_number?: string | null;
    avatar?: string;
    is_active?: boolean;
    default_school_id?: number | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
