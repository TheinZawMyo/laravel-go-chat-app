export type AccountStatus = 'active' | 'suspended' | 'deactivated';

export type User = {
    id: string;
    name: string;
    username: string;
    email: string;
    avatar_url: string | null;
    bio: string | null;
    status_text: string | null;
    status: AccountStatus;
    email_verified_at: string | null;
    last_seen_at: string | null;
    created_at: string;
    updated_at: string;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
