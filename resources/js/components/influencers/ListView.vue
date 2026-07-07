<script setup lang="ts">
import { ExternalLink, Mail, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import EmailComposeDialog from '@/components/influencers/EmailComposeDialog.vue';
import OutreachStatusBadge from '@/components/influencers/OutreachStatusBadge.vue';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
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
import { formatDate, formatFollowers } from '@/lib/format';
import type { InfluencerListEntry, OutreachStatusOption } from '@/types';

defineProps<{
    entries: InfluencerListEntry[];
    outreachStatuses: OutreachStatusOption[];
    canManage: boolean;
    listId: number;
}>();

const emit = defineEmits<{
    (e: 'update-status', entryId: number, status: string): void;
    (e: 'remove', entryId: number): void;
    (e: 'save-notes', entryId: number, notes: string): void;
}>();

const editingNotes = ref<number | null>(null);
const notesDraft = ref('');

function startEditNotes(entry: InfluencerListEntry) {
    editingNotes.value = entry.id;
    notesDraft.value = entry.notes || '';
}

function saveNotes(entryId: number) {
    emit('save-notes', entryId, notesDraft.value);
    editingNotes.value = null;
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="entry in entries"
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
                        :list-id="listId"
                        :entry-id="entry.id"
                        :messages="entry.messages"
                    />
                    <div v-if="canManage">
                        <Select
                            :model-value="entry.outreach_status"
                            @update:model-value="(v) => emit('update-status', entry.id, String(v))"
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
                        @click="emit('remove', entry.id)"
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
                <div v-if="entry.messages.length > 0" class="flex items-center gap-1">
                    <Mail class="h-3.5 w-3.5" />
                    {{ entry.messages.length }} email{{ entry.messages.length !== 1 ? 's' : '' }}
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
</template>
