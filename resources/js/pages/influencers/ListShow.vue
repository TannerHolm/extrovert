<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Kanban, List, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import KanbanBoard from '@/components/influencers/KanbanBoard.vue';
import ListView from '@/components/influencers/ListView.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/influencers/lists';
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

const paginatedEntries = computed(() => {
    if (Array.isArray(props.entries)) return null;
    return props.entries;
});

const allEntries = computed<InfluencerListEntry[]>(() => {
    if (Array.isArray(props.entries)) return props.entries;
    return props.entries.data;
});

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

function updateStatus(entryId: number, status: string) {
    const teamSlug = page.props.currentTeam!.slug;
    router.patch(
        updateEntry({ current_team: teamSlug, influencerList: props.list.id, entry: entryId }).url,
        { outreach_status: status },
        { preserveScroll: true },
    );
}

function saveNotes(entryId: number, notes: string) {
    const teamSlug = page.props.currentTeam!.slug;
    router.patch(
        updateEntry({ current_team: teamSlug, influencerList: props.list.id, entry: entryId }).url,
        { notes },
        { preserveScroll: true },
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

        <!-- Kanban view -->
        <KanbanBoard
            v-if="viewMode === 'kanban' && allEntries.length > 0"
            :entries="allEntries"
            :outreach-statuses="outreachStatuses"
            :can-manage="canManage"
            @update-status="updateStatus"
        />

        <!-- List view -->
        <ListView
            v-else-if="viewMode === 'list' && allEntries.length > 0"
            :entries="allEntries"
            :outreach-statuses="outreachStatuses"
            :can-manage="canManage"
            @update-status="updateStatus"
            @remove="removeEntry"
            @save-notes="saveNotes"
        />

        <!-- Pagination (list view only) -->
        <div v-if="viewMode === 'list' && paginatedEntries && paginatedEntries.meta.last_page > 1" class="flex items-center justify-between">
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
