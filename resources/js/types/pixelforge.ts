export type ImageSize = '1024x1024' | '1536x1024' | '1024x1536';

export type ImageQuality = 'low' | 'medium' | 'high';

export type GenerationStatus =
    | 'submitting'
    | 'queued_locally'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export interface TokenRates {
    input_text_tokens: string;
    input_image_tokens: string;
    output_tokens: string;
    reasoning_tokens: string;
}

export interface TokenUsage {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
    total_tokens: number;
}

export interface PricingConfig {
    rates: TokenRates;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: string | number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
}

export interface CostEstimate {
    unicodeCharCount: number;
    minTextTokens: number;
    expectedTextTokens: number;
    maxTextTokens: number;
    imageTokens: number;
    outputTokens: number;
    minReasoningTokens: number;
    expectedReasoningTokens: number;
    maxReasoningTokens: number;
    minProviderCostPicodollars: bigint;
    expectedProviderCostPicodollars: bigint;
    maxProviderCostPicodollars: bigint;
    minUserCostPicodollars: bigint;
    expectedUserCostPicodollars: bigint;
    maxUserCostPicodollars: bigint;
    minUserCostUsd: string;
    expectedUserCostUsd: string;
    maxUserCostUsd: string;
    expectedProviderCostUsd: string;
    expectedMarkupUsd: string;
}

export interface SelectOption<T extends string = string> {
    value: T;
    label: string;
}

export interface GenerationItem {
    id: string;
    pixelforge_id: string | null;
    status: GenerationStatus;
    is_in_flight: boolean;
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    reference_images: string[];
    usage: TokenUsage | null;
    rates_snapshot: TokenRates;
    estimated_min_user_cost_usd: string;
    estimated_expected_user_cost_usd: string;
    estimated_max_user_cost_usd: string;
    actual_provider_cost_usd: string | null;
    actual_user_cost_usd: string | null;
    actual_user_cost_picodollars: number | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    is_image_expired: boolean;
    error_code: string | null;
    error_message: string | null;
    created_at: string | null;
    pixelforge_started_at: string | null;
    pixelforge_completed_at: string | null;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface HistoryPayload {
    items: GenerationItem[];
    has_in_flight: boolean;
    pagination: PaginationMeta;
}

export interface CreditTransactionItem {
    id: number;
    generation_id: string | null;
    type: 'top_up' | 'reserve' | 'release' | 'charge' | 'adjustment';
    amount_picodollars: number;
    amount_usd: string;
    balance_after_usd: string;
    reserved_after_usd: string;
    description: string;
    created_at: string | null;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    credit_balance_picodollars: number;
    reserved_balance_picodollars: number;
    available_balance_picodollars: number;
    credit_balance_usd: string;
    reserved_balance_usd: string;
    available_balance_usd: string;
}

export interface DailyReconciliationRow {
    date: string;
    local_generations: number;
    remote_generations: number | null;
    local_provider_cost_usd: string;
    remote_provider_cost_usd: string | null;
    diff_usd: string | null;
    is_reconciled: boolean;
    credit_spend_usd: string;
    profit_usd: string;
}

export interface UserSpendRow {
    user_id: number;
    name: string;
    email: string;
    available_balance_usd: string;
    succeeded_count: number;
    failed_count: number;
    credit_spend_usd: string;
    provider_cost_usd: string;
    profit_usd: string;
}

export interface AdminReportData {
    filters: {
        start_date: string;
        end_date: string;
    };
    summary: {
        succeeded_generations: number;
        failed_generations: number;
        credit_spend_usd: string;
        provider_cost_usd: string;
        profit_usd: string;
        remote_provider_cost_usd: string | null;
        remote_generations: number | null;
        reconciliation_diff_usd: string | null;
        is_fully_reconciled: boolean;
        remote_error: string | null;
    };
    daily: DailyReconciliationRow[];
    by_user: UserSpendRow[];
}
