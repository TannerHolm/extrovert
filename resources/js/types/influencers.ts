export type Platform = 'youtube' | 'instagram' | 'tiktok';

export type OutreachStatus = 'none' | 'contacted' | 'replied' | 'negotiating' | 'confirmed' | 'declined';

export type PlatformOption = {
    value: Platform;
    label: string;
};

export type OutreachStatusOption = {
    value: OutreachStatus;
    label: string;
    color: string;
};

export type InfluencerSearchResult = {
    platform: Platform;
    platform_id: string;
    handle: string;
    profile_url: string;
    display_name: string | null;
    avatar_url: string | null;
    follower_count: number | null;
    engagement_rate: number | null;
    contact_email: string | null;
    latest_activity_at: string | null;
};

export type InfluencerListSummary = {
    id: number;
    name: string;
    entries_count: number;
};

export type InfluencerList = {
    id: number;
    name: string;
    description: string | null;
    entries_count: number;
    created_at: string;
    updated_at: string;
};

export type SavedInfluencer = {
    id: number;
    platform: Platform;
    platform_label: string;
    handle: string;
    profile_url: string;
    display_name: string | null;
    avatar_url: string | null;
    follower_count: number | null;
    engagement_rate: number | null;
    contact_email: string | null;
    latest_activity_at: string | null;
};

export type OutreachMessage = {
    id: number;
    direction: 'outbound' | 'inbound';
    subject: string;
    body: string;
    from_email: string;
    to_email: string;
    sent_by: string | null;
    sent_at: string | null;
};

export type DealStatus = 'draft' | 'agreed' | 'live' | 'completed' | 'cancelled';

export type CompensationType = 'gifted' | 'flat_fee' | 'commission' | 'hybrid';

export type DealStatusOption = {
    value: DealStatus;
    label: string;
    color: string;
};

export type CompensationTypeOption = {
    value: CompensationType;
    label: string;
};

export type Deliverable = {
    type: 'post' | 'reel' | 'video' | 'story';
    platform: Platform;
    due_date: string | null;
    posted_url: string | null;
    posted_at: string | null;
};

export type DealAttribution = {
    revenue_cents: number;
    orders: number;
    new_customers: number;
};

export type AgreementStatus = 'draft' | 'sent' | 'viewed' | 'signed' | 'declined' | 'voided';

export type Agreement = {
    id: number;
    status: AgreementStatus;
    status_label: string;
    status_color: string;
    body_markdown: string;
    signer_name: string | null;
    signer_email: string | null;
    sent_at: string | null;
    signed_at: string | null;
    expires_at: string | null;
    sign_url: string;
};

export type Deal = {
    id: number;
    status: DealStatus;
    status_label: string;
    status_color: string;
    compensation_type: CompensationType;
    compensation_type_label: string;
    flat_fee_cents: number | null;
    commission_rate: number | null;
    product_value_cents: number | null;
    deliverables: Deliverable[];
    overdue_deliverables_count: number;
    usage_rights: string | null;
    starts_at: string | null;
    ends_at: string | null;
    notes: string | null;
    discount_code: string | null;
    affiliate_url: string | null;
    attribution: DealAttribution;
    agreements: Agreement[];
    created_at: string;
};

export type InfluencerListEntry = {
    id: number;
    outreach_status: OutreachStatus;
    outreach_status_label: string;
    outreach_status_color: string;
    notes: string | null;
    added_by: { id: number; name: string } | null;
    created_at: string;
    influencer: SavedInfluencer;
    messages: OutreachMessage[];
    deals: Deal[];
};

export type Paginator<T> = {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};
