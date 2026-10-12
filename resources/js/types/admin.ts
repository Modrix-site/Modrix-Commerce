export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** Shape of a paginated Laravel API Resource collection. */
export type ResourcePaginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

export type RoleSummary = {
    id: number;
    name: string;
    display_name: string;
};

export type StaffUser = {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    disabled: boolean;
    two_factor_enabled: boolean;
    roles: RoleSummary[];
    created_at: string | null;
};

export type AssignableRole = RoleSummary & {
    description: string | null;
    grantable: boolean;
};

export type AuditLogEntry = {
    id: number;
    action: string;
    actor: string | null;
    subject_type: string | null;
    subject_id: number | null;
    ip_address: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

export type RequirementCheck = {
    key: string;
    label: string;
    passed: boolean;
    detail: string;
};

export type CurrencyOption = {
    code: string;
    name: string;
};

export type MoneyValue = {
    amount: string;
    currency: string;
    formatted: string;
};

export type Option = {
    value: string;
    label: string;
};

export type CategoryOption = {
    id: number;
    name: string;
    depth: number;
};

export type ExtensionSummary = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    author: string | null;
    state: string;
    state_label: string;
    version: string | null;
    available_version: string | null;
    update_available: boolean;
    failure: string | null;
    errors: string[];
    requires: Record<string, string>;
    capabilities: { key: string; label: string }[];
};

export type ExtensionSettingField = {
    key: string;
    type: 'boolean' | 'string' | 'text' | 'integer' | 'select' | 'email';
    label: string;
    help?: string;
    default?: unknown;
    required?: boolean;
    min?: number;
    max?: number;
    max_length?: number;
    options?: { value: string | number; label: string }[];
};

export type ThemeSummary = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    author: string | null;
    state: 'active' | 'available' | 'invalid' | 'failed';
    version: string | null;
    available_version: string | null;
    update_available: boolean;
    changelog: { version: string; date: string; notes: string }[];
    errors: string[];
    failure: string | null;
    swatches: string[];
    fonts: [string, string] | null;
    screenshot: string | null;
    capabilities: string[];
    has_demo: boolean;
    customized: boolean;
};

export type ThemeField = {
    key: string;
    type:
        | 'color'
        | 'select'
        | 'boolean'
        | 'integer'
        | 'string'
        | 'text'
        | 'link'
        | 'image'
        | 'font';
    label: string;
    group?: string;
    options?: string[];
    min?: number;
    max?: number;
    required?: boolean;
    default?: string | number | boolean;
};

export type ThemeSectionValue = {
    type: string;
    settings: Record<string, string | number | boolean | null>;
};

export type ThemeImageOption = { value: string; label: string; url: string };
