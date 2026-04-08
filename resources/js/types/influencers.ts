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

export type InfluencerListEntry = {
    id: number;
    outreach_status: OutreachStatus;
    outreach_status_label: string;
    outreach_status_color: string;
    notes: string | null;
    added_by: { id: number; name: string } | null;
    created_at: string;
    influencer: SavedInfluencer;
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
