<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Mail, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import OutreachStatusBadge from '@/components/influencers/OutreachStatusBadge.vue';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/influencers/lists';
import { show } from '@/routes/influencers/lists';
import {
    destroy as destroyEntry,
    update as updateEntry,
} from '@/routes/influencers/entries';
import type { InfluencerListEntry, OutreachStatusOption } from '@/types';

type Props = {
    list: { id: number; name: string; description: string | null };
    entries: InfluencerListEntry[];
    outreachStatuses: OutreachStatusOption[];
    canManage: boolean;
};

const props = defineProps<Props>();
const page = usePage();

defineOptions({
    layout: (layoutProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Influencer Lists',
                href: layoutProps.currentTeam
                    ? index(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const filterStatus = ref('all');
const editingNotes = ref<number | null>(null);
const notesDraft = ref('');

function filteredEntries() {
    if (filterStatus.value === 'all') return props.entries;
    return props.entries.filter((e) => e.outreach_status === filterStatus.value);
}

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

function updateStatus(entryId: number, status: string) {
    const teamSlug = page.props.currentTeam!.slug;
    router.patch(
        updateEntry({ current_team: teamSlug, influencerList: props.list.id, entry: entryId }).url,
        { outreach_status: status },
        { preserveScroll: true },
    );
}

function startEditNotes(entry: InfluencerListEntry) {
    editingNotes.value = entry.id;
    notesDraft.value = entry.notes || '';
}

function saveNotes(entryId: number) {
    const teamSlug = page.props.currentTeam!.slug;
    router.patch(
        updateEntry({ current_team: teamSlug, influencerList: props.list.id, entry: entryId }).url,
        { notes: notesDraft.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingNotes.value = null;
            },
        },
    );
}

function removeEntry(entryId: number) {
    if (!confirm('Remove this influencer from the list?')) return;
    const teamSlug = page.props.currentTeam!.slug;
    router.delete(
        destroyEntry({ current_team: teamSlug, influencerList: props.list.id, entry: entryId }).url,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="list.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center gap-3">
            <Link
                :href="index(page.props.currentTeam!.slug).url"
                class="rounded-md p-1 hover:bg-muted"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <Heading
                variant="small"
                :title="list.name"
                :description="list.description || undefined"
            />
        </div>

        <!-- Filter -->
        <div class="flex items-center gap-3">
            <span class="text-sm text-muted-foreground">Filter:</span>
            <Select v-model="filterStatus">
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem
                        v-for="status in outreachStatuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <span class="text-sm text-muted-foreground">
                {{ filteredEntries().length }} influencer{{ filteredEntries().length !== 1 ? 's' : '' }}
            </span>
        </div>

        <!-- Entries Table -->
        <div v-if="filteredEntries().length > 0" class="space-y-3">
            <div
                v-for="entry in filteredEntries()"
                :key="entry.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <!-- Influencer Info -->
                    <div class="flex items-start gap-3">
                        <Avatar class="h-10 w-10 shrink-0">
                            <AvatarImage
                                v-if="entry.influencer.avatar_url"
                                :src="entry.influencer.avatar_url"
                                :alt="entry.influencer.display_name || entry.influencer.handle"
                            />
                            <AvatarFallback>
                                {{ (entry.influencer.display_name || entry.influencer.handle).charAt(0).toUpperCase() }}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">
                                    {{ entry.influencer.display_name || entry.influencer.handle }}
                                </span>
                                <PlatformIcon :platform="entry.influencer.platform" class="h-4 w-4" />
                            </div>
                            <a
                                :href="entry.influencer.profile_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            >
                                {{ entry.influencer.handle }}
                                <ExternalLink class="h-3 w-3" />
                            </a>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <div v-if="canManage">
                            <Select
                                :model-value="entry.outreach_status"
                                @update:model-value="(v) => updateStatus(entry.id, String(v))"
                            >
                                <SelectTrigger class="w-[150px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="status in outreachStatuses"
                                        :key="status.value"
                                        :value="status.value"
                                    >
                                        {{ status.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <OutreachStatusBadge
                            v-else
                            :status="entry.outreach_status"
                            :label="entry.outreach_status_label"
                            :color="entry.outreach_status_color"
                        />
                        <Button
                            v-if="canManage"
                            variant="ghost"
                            size="sm"
                            @click="removeEntry(entry.id)"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>

                <!-- Metrics Row -->
                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <div class="flex items-center gap-1">
                        <Users class="h-3.5 w-3.5" />
                        {{ formatFollowers(entry.influencer.follower_count) }} followers
                    </div>
                    <div v-if="entry.influencer.engagement_rate !== null">
                        {{ entry.influencer.engagement_rate }}% engagement
                    </div>
                    <div v-if="entry.influencer.contact_email" class="flex items-center gap-1">
                        <Mail class="h-3.5 w-3.5" />
                        <a
                            :href="`mailto:${entry.influencer.contact_email}`"
                            class="hover:text-foreground"
                        >
                            {{ entry.influencer.contact_email }}
                        </a>
                    </div>
                    <div v-if="entry.influencer.latest_activity_at">
                        Last active: {{ formatDate(entry.influencer.latest_activity_at) }}
                    </div>
                    <div v-if="entry.added_by">
                        Added by {{ entry.added_by.name }}
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-3">
                    <div v-if="editingNotes === entry.id" class="flex gap-2">
                        <Input
                            v-model="notesDraft"
                            placeholder="Add notes about this influencer..."
                            class="flex-1"
                            @keyup.enter="saveNotes(entry.id)"
                        />
                        <Button size="sm" @click="saveNotes(entry.id)">Save</Button>
                        <Button size="sm" variant="ghost" @click="editingNotes = null">Cancel</Button>
                    </div>
                    <div v-else>
                        <button
                            v-if="canManage"
                            @click="startEditNotes(entry)"
                            class="text-sm text-muted-foreground hover:text-foreground"
                        >
                            {{ entry.notes || 'Add notes...' }}
                        </button>
                        <p v-else-if="entry.notes" class="text-sm text-muted-foreground">
                            {{ entry.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="flex flex-col items-center justify-center py-12"
        >
            <Users class="h-12 w-12 text-muted-foreground/30" />
            <p class="mt-3 text-sm text-muted-foreground">
                {{ filterStatus === 'all'
                    ? 'No influencers in this list yet. Use Discover to find and save influencers.'
                    : 'No influencers match the selected filter.'
                }}
            </p>
        </div>
    </div>
</template>
