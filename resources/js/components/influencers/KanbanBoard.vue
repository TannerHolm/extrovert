<script setup lang="ts">
import { ExternalLink, GripVertical, Mail } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmailComposeDialog from '@/components/influencers/EmailComposeDialog.vue';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { formatFollowers } from '@/lib/format';
import type { InfluencerListEntry, OutreachStatusOption } from '@/types';

const props = defineProps<{
    entries: InfluencerListEntry[];
    outreachStatuses: OutreachStatusOption[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    (e: 'update-status', entryId: number, status: string): void;
}>();

// Kanban drag state
const dragEntryId = ref<number | null>(null);
const dragOverColumn = ref<string | null>(null);

// Kanban columns: pipeline statuses for the board (declined is shown separately)
const kanbanStatuses = computed(() =>
    props.outreachStatuses.filter((s) => s.value !== 'declined'),
);

const declinedStatus = computed(() =>
    props.outreachStatuses.find((s) => s.value === 'declined'),
);

function entriesForStatus(status: string): InfluencerListEntry[] {
    return props.entries.filter((e) => e.outreach_status === status);
}

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
        const entry = props.entries.find((en) => en.id === dragEntryId.value);
        if (entry && entry.outreach_status !== status) {
            emit('update-status', dragEntryId.value, status);
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
    <div class="flex gap-4 overflow-x-auto pb-4">
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
</template>
