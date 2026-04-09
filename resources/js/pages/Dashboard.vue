<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Mail, TrendingUp, UserCheck, Users } from 'lucide-vue-next';
import OutreachStatusBadge from '@/components/influencers/OutreachStatusBadge.vue';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { OutreachStatus, Platform, Team } from '@/types';

type StatusCount = {
    value: OutreachStatus;
    label: string;
    color: string;
    count: number;
};

type RecentEntry = {
    id: number;
    outreach_status: OutreachStatus;
    outreach_status_label: string;
    outreach_status_color: string;
    created_at: string;
    display_name: string | null;
    handle: string;
    avatar_url: string | null;
    platform: Platform;
    list_name: string;
    added_by_name: string | null;
};

type Props = {
    metrics: {
        total_influencers: number;
        active_outreach: number;
        confirmed_partners: number;
    };
    statusCounts: StatusCount[];
    recentEntries: RecentEntry[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam
                    ? dashboard(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const totalPipeline = props.statusCounts.reduce((sum, s) => sum + s.count, 0);

const barColors: Record<string, string> = {
    gray: 'bg-gray-400',
    blue: 'bg-blue-400',
    yellow: 'bg-yellow-400',
    orange: 'bg-orange-400',
    green: 'bg-green-400',
    red: 'bg-red-400',
};

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString();
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            title="Dashboard"
            description="Overview of your influencer pipeline"
        />

        <!-- Metric Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Influencers</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ metrics.total_influencers }}</div>
                    <p class="text-xs text-muted-foreground">Across all lists</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Active Outreach</CardTitle>
                    <Mail class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ metrics.active_outreach }}</div>
                    <p class="text-xs text-muted-foreground">Contacted, replied, or negotiating</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Confirmed Partners</CardTitle>
                    <UserCheck class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ metrics.confirmed_partners }}</div>
                    <p class="text-xs text-muted-foreground">Partnerships secured</p>
                </CardContent>
            </Card>
        </div>

        <!-- Pipeline Bar -->
        <Card v-if="totalPipeline > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-sm font-medium">
                    <TrendingUp class="h-4 w-4" />
                    Pipeline Overview
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Stacked bar -->
                <div class="mb-3 flex h-4 overflow-hidden rounded-full">
                    <div
                        v-for="status in statusCounts"
                        :key="status.value"
                        v-show="status.count > 0"
                        :class="barColors[status.color] || 'bg-gray-400'"
                        :style="{ width: `${(status.count / totalPipeline) * 100}%` }"
                        :title="`${status.label}: ${status.count}`"
                    />
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap gap-4 text-sm">
                    <div
                        v-for="status in statusCounts"
                        :key="status.value"
                        class="flex items-center gap-1.5"
                    >
                        <div class="h-2.5 w-2.5 rounded-full" :class="barColors[status.color]" />
                        <span class="text-muted-foreground">{{ status.label }}</span>
                        <span class="font-medium">{{ status.count }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Recent Activity -->
        <Card>
            <CardHeader>
                <CardTitle class="text-sm font-medium">Recent Activity</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="recentEntries.length > 0" class="space-y-3">
                    <div
                        v-for="entry in recentEntries"
                        :key="entry.id"
                        class="flex items-center gap-3"
                    >
                        <Avatar class="h-8 w-8 shrink-0">
                            <AvatarImage v-if="entry.avatar_url" :src="entry.avatar_url" />
                            <AvatarFallback class="text-xs">
                                {{ (entry.display_name || entry.handle).charAt(0).toUpperCase() }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium">
                                    {{ entry.display_name || entry.handle }}
                                </span>
                                <PlatformIcon :platform="entry.platform" class="h-3.5 w-3.5 shrink-0" />
                                <OutreachStatusBadge
                                    :status="entry.outreach_status"
                                    :label="entry.outreach_status_label"
                                    :color="entry.outreach_status_color"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.list_name }}
                                <span v-if="entry.added_by_name"> &middot; {{ entry.added_by_name }}</span>
                                &middot; {{ formatDate(entry.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No activity yet. Start by discovering and saving influencers.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
