<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    GripVertical,
    Kanban,
    List,
    Mail,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmailComposeDialog from '@/components/influencers/EmailComposeDialog.vue';
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
import type { InfluencerListEntry, OutreachStatusOption, Paginator } from '@/types';

type Props = {
    list: { id: number; name: string; description: string | null };
    entries: Paginator<InfluencerListEntry> | InfluencerListEntry[];
    view: 'list' | 'kanban';
    filters: { status: string };
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

const viewMode = ref<'list' | 'kanban'>(props.view);
const filterStatus = ref(props.filters.status);
const editingNotes = ref<number | null>(null);
const notesDraft = ref('');

// Kanban drag state
const dragEntryId = ref<number | null>(null);
const dragOverColumn = ref<string | null>(null);

const paginatedEntries = computed(() => {
    if (Array.isArray(props.entries)) return null;
    return props.entries;
});

const allEntries = computed<InfluencerListEntry[]>(() => {
    if (Array.isArray(props.entries)) return props.entries;
    return props.entries.data;
});

// Kanban columns: pipeline statuses for the board
const kanbanStatuses = computed(() =>
    props.outreachStatuses.filter((s) => s.value !== 'declined'),
);

const declinedStatus = computed(() =>
    props.outreachStatuses.find((s) => s.value === 'declined'),
);

function entriesForStatus(status: string): InfluencerListEntry[] {
    return allEntries.value.filter((e) => e.outreach_status === status);
}

function switchView(mode: 'list' | 'kanban') {
    viewMode.value = mode;
    const teamSlug = page.props.currentTeam!.slug;
    router.get(
        show({ current_team: teamSlug, influencerList: props.list.id }).url,
        { view: mode, ...(filterStatus.value !== 'all' ? { status: filterStatus.value } : {}) },
        { preserveState: false },
    );
}

function applyFilter(status: string) {
    filterStatus.value = status;
    const teamSlug = page.props.currentTeam!.slug;
    router.get(
        show({ current_team: teamSlug, influencerList: props.list.id }).url,
        { view: viewMode.value, ...(status !== 'all' ? { status } : {}) },
        { preserveState: true, preserveScroll: true, only: ['entries', 'filters'] },
    );
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

// Drag and drop handlers
function onDragStart(e: DragEvent, entryId: number) {
    dragEntryId.value = entryId;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(entryId));
    }
}

function onDragOver(e: DragEvent, status: string) {
    e.preventDefault();
    dragOverColumn.value = status;
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
}

function onDragLeave(status: string) {
    if (dragOverColumn.value === status) {
        dragOverColumn.value = null;
    }
}

function onDrop(e: DragEvent, status: string) {
    e.preventDefault();
    dragOverColumn.value = null;
    if (dragEntryId.value !== null) {
        const entry = allEntries.value.find((en) => en.id === dragEntryId.value);
        if (entry && entry.outreach_status !== status) {
            updateStatus(dragEntryId.value, status);
        }
    }
    dragEntryId.value = null;
}

function onDragEnd() {
    dragEntryId.value = null;
    dragOverColumn.value = null;
}

// Color mapping for kanban column headers
const statusBorderColors: Record<string, string> = {
    gray: 'border-t-gray-400',
    blue: 'border-t-blue-400',
    yellow: 'border-t-yellow-400',
    orange: 'border-t-orange-400',
    green: 'border-t-green-400',
    red: 'border-t-red-400',
};

const statusBgColors: Record<string, string> = {
    gray: 'bg-gray-50 dark:bg-gray-950/30',
    blue: 'bg-blue-50 dark:bg-blue-950/30',
    yellow: 'bg-yellow-50 dark:bg-yellow-950/30',
    orange: 'bg-orange-50 dark:bg-orange-950/30',
    green: 'bg-green-50 dark:bg-green-950/30',
    red: 'bg-red-50 dark:bg-red-950/30',
};
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

        <!-- Toolbar: Filter + View Toggle -->
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted-foreground">Filter:</span>
                <Select :model-value="filterStatus" @update:model-value="(v) => applyFilter(String(v))">
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
                    {{ allEntries.length }} influencer{{ allEntries.length !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- View Toggle -->
            <div class="flex rounded-lg border p-0.5">
                <button
                    @click="switchView('list')"
                    :class="[
                        'rounded-md p-1.5 transition-colors',
                        viewMode === 'list'
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    title="List view"
                >
                    <List class="h-4 w-4" />
                </button>
                <button
                    @click="switchView('kanban')"
                    :class="[
                        'rounded-md p-1.5 transition-colors',
                        viewMode === 'kanban'
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    title="Kanban view"
                >
                    <Kanban class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- ===== KANBAN VIEW ===== -->
        <div v-if="viewMode === 'kanban' && allEntries.length > 0" class="flex gap-4 overflow-x-auto pb-4">
            <!-- Pipeline columns -->
            <div
                v-for="status in kanbanStatuses"
                :key="status.value"
                class="flex w-64 shrink-0 flex-col rounded-lg border border-t-4"
                :class="[
                    statusBorderColors[status.color] || 'border-t-gray-400',
                    dragOverColumn === status.value ? 'ring-2 ring-primary/50' : '',
                ]"
                @dragover="(e) => onDragOver(e, status.value)"
                @dragleave="() => onDragLeave(status.value)"
                @drop="(e) => onDrop(e, status.value)"
            >
                <!-- Column Header -->
                <div class="flex items-center justify-between p-3 pb-2">
                    <h3 class="text-sm font-semibold">{{ status.label }}</h3>
                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {{ entriesForStatus(status.value).length }}
                    </span>
                </div>

                <!-- Column Cards -->
                <div class="flex flex-1 flex-col gap-2 p-2 pt-0" :class="statusBgColors[status.color] || ''">
                    <div
                        v-for="entry in entriesForStatus(status.value)"
                        :key="entry.id"
                        :draggable="canManage"
                        @dragstart="(e) => onDragStart(e, entry.id)"
                        @dragend="onDragEnd"
                        :class="[
                            'cursor-grab rounded-md border bg-background p-3 shadow-sm transition-opacity active:cursor-grabbing',
                            dragEntryId === entry.id ? 'opacity-50' : '',
                        ]"
                    >
                        <div class="flex items-start gap-2">
                            <GripVertical v-if="canManage" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground/50" />
                            <Avatar class="h-7 w-7 shrink-0">
                                <AvatarImage
                                    v-if="entry.influencer.avatar_url"
                                    :src="entry.influencer.avatar_url"
                                    :alt="entry.influencer.display_name || entry.influencer.handle"
                                />
                                <AvatarFallback class="text-xs">
                                    {{ (entry.influencer.display_name || entry.influencer.handle).charAt(0).toUpperCase() }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ entry.influencer.display_name || entry.influencer.handle }}
                                </p>
                                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <PlatformIcon :platform="entry.influencer.platform" class="h-3 w-3" />
                                    <span class="truncate">{{ entry.influencer.handle }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                            <span>{{ formatFollowers(entry.influencer.follower_count) }}</span>
                            <div class="flex gap-1">
                                <EmailComposeDialog
                                    :influencer-name="entry.influencer.display_name || entry.influencer.handle"
                                    :influencer-handle="entry.influencer.handle"
                                    :influencer-email="entry.influencer.contact_email"
                                    :platform="entry.influencer.platform_label"
                                >
                                    <button
                                        :disabled="!entry.influencer.contact_email"
                                        :class="[
                                            'rounded p-1 transition-colors',
                                            entry.influencer.contact_email
                                                ? 'hover:bg-muted hover:text-foreground'
                                                : 'cursor-not-allowed opacity-30',
                                        ]"
                                        :title="entry.influencer.contact_email ? 'Send email' : 'No email available'"
                                    >
                                        <Mail class="h-3.5 w-3.5" />
                                    </button>
                                </EmailComposeDialog>
                                <a
                                    :href="entry.influencer.profile_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="rounded p-1 hover:bg-muted hover:text-foreground"
                                >
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </a>
                            </div>
                        </div>

                        <p v-if="entry.notes" class="mt-1.5 truncate text-xs text-muted-foreground/70 italic">
                            {{ entry.notes }}
                        </p>
                    </div>

                    <!-- Empty column -->
                    <div
                        v-if="entriesForStatus(status.value).length === 0"
                        class="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground"
                    >
                        Drop here
                    </div>
                </div>
            </div>

            <!-- Declined column (dimmed) -->
            <div
                v-if="declinedStatus && entriesForStatus('declined').length > 0"
                class="flex w-64 shrink-0 flex-col rounded-lg border border-t-4 opacity-60"
                :class="statusBorderColors['red']"
                @dragover="(e) => onDragOver(e, 'declined')"
                @dragleave="() => onDragLeave('declined')"
                @drop="(e) => onDrop(e, 'declined')"
            >
                <div class="flex items-center justify-between p-3 pb-2">
                    <h3 class="text-sm font-semibold">Declined</h3>
                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {{ entriesForStatus('declined').length }}
                    </span>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-2 pt-0" :class="statusBgColors['red']">
                    <div
                        v-for="entry in entriesForStatus('declined')"
                        :key="entry.id"
                        class="rounded-md border bg-background p-3 shadow-sm"
                    >
                        <div class="flex items-start gap-2">
                            <Avatar class="h-7 w-7 shrink-0">
                                <AvatarImage
                                    v-if="entry.influencer.avatar_url"
                                    :src="entry.influencer.avatar_url"
                                />
                                <AvatarFallback class="text-xs">
                                    {{ (entry.influencer.display_name || entry.influencer.handle).charAt(0).toUpperCase() }}
                                </AvatarFallback>
                            </Avatar>
                            <p class="truncate text-sm font-medium">
                                {{ entry.influencer.display_name || entry.influencer.handle }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== LIST VIEW ===== -->
        <div v-else-if="viewMode === 'list' && allEntries.length > 0" class="space-y-3">
            <div
                v-for="entry in allEntries"
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
                        <EmailComposeDialog
                            :influencer-name="entry.influencer.display_name || entry.influencer.handle"
                            :influencer-handle="entry.influencer.handle"
                            :influencer-email="entry.influencer.contact_email"
                            :platform="entry.influencer.platform_label"
                        />
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
                        {{ entry.influencer.contact_email }}
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

        <!-- Pagination (list view only) -->
        <div v-if="viewMode === 'list' && paginatedEntries?.meta?.last_page > 1" class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">
                Showing {{ paginatedEntries.meta.from }}–{{ paginatedEntries.meta.to }} of {{ paginatedEntries.meta.total }}
            </span>
            <div class="flex gap-2">
                <Link
                    v-if="paginatedEntries.links.prev"
                    :href="paginatedEntries.links.prev"
                    preserveState
                    preserveScroll
                >
                    <Button variant="outline" size="sm">Previous</Button>
                </Link>
                <Button v-else variant="outline" size="sm" disabled>Previous</Button>
                <Link
                    v-if="paginatedEntries.links.next"
                    :href="paginatedEntries.links.next"
                    preserveState
                    preserveScroll
                >
                    <Button variant="outline" size="sm">Next</Button>
                </Link>
                <Button v-else variant="outline" size="sm" disabled>Next</Button>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="allEntries.length === 0"
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
