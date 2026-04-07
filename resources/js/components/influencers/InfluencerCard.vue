<script setup lang="ts">
import { ExternalLink, Mail, TrendingUp, Users } from 'lucide-vue-next';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
import SaveToListDropdown from '@/components/influencers/SaveToListDropdown.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import type { InfluencerListSummary, InfluencerSearchResult } from '@/types';

defineProps<{
    influencer: InfluencerSearchResult;
    lists: InfluencerListSummary[];
}>();

function formatFollowers(count: number | null): string {
    if (count === null) return 'N/A';
    if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`;
    if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`;
    return count.toString();
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString();
}

function platformLabel(platform: string): string {
    const labels: Record<string, string> = {
        youtube: 'YouTube',
        instagram: 'Instagram',
        tiktok: 'TikTok',
    };
    return labels[platform] ?? platform;
}
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader class="flex-row items-start gap-3 space-y-0 pb-3">
            <Avatar class="h-12 w-12 shrink-0">
                <AvatarImage
                    v-if="influencer.avatar_url"
                    :src="influencer.avatar_url"
                    :alt="influencer.display_name || influencer.handle"
                />
                <AvatarFallback>
                    {{ (influencer.display_name || influencer.handle).charAt(0).toUpperCase() }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h3 class="truncate text-sm font-semibold">
                        {{ influencer.display_name || influencer.handle }}
                    </h3>
                    <PlatformIcon :platform="influencer.platform" class="h-4 w-4 shrink-0" />
                </div>
                <a
                    :href="influencer.profile_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    {{ influencer.handle }}
                    <ExternalLink class="h-3 w-3" />
                </a>
            </div>
        </CardHeader>

        <CardContent class="flex-1 space-y-3 pb-3">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-1.5">
                    <Users class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium">{{ formatFollowers(influencer.follower_count) }}</span>
                    <span class="text-muted-foreground">followers</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium">
                        {{ influencer.engagement_rate !== null ? `${influencer.engagement_rate}%` : 'N/A' }}
                    </span>
                    <span class="text-muted-foreground">engagement</span>
                </div>
            </div>

            <div v-if="influencer.contact_email" class="flex items-center gap-1.5 text-sm">
                <Mail class="h-4 w-4 text-muted-foreground" />
                <a
                    :href="`mailto:${influencer.contact_email}`"
                    class="truncate text-muted-foreground hover:text-foreground"
                >
                    {{ influencer.contact_email }}
                </a>
            </div>

            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <Badge variant="outline" class="text-xs">
                    {{ platformLabel(influencer.platform) }}
                </Badge>
                <span v-if="influencer.latest_activity_at">
                    Last active: {{ formatDate(influencer.latest_activity_at) }}
                </span>
            </div>
        </CardContent>

        <CardFooter class="pt-0">
            <SaveToListDropdown :lists="lists" :influencer="influencer" />
        </CardFooter>
    </Card>
</template>
