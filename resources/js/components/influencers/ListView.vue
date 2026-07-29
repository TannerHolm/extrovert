<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Check, ExternalLink, FileSignature, Handshake, Link2, Mail, Tag, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import AgreementDialog from '@/components/influencers/AgreementDialog.vue';
import DealDialog from '@/components/influencers/DealDialog.vue';
import EmailComposeDialog from '@/components/influencers/EmailComposeDialog.vue';
import OutreachStatusBadge from '@/components/influencers/OutreachStatusBadge.vue';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCents, formatDate, formatFollowers } from '@/lib/format';
import { store as storeAgreement } from '@/routes/agreements';
import type {
    Agreement,
    CompensationTypeOption,
    Deal,
    DealStatusOption,
    InfluencerListEntry,
    OutreachStatusOption,
} from '@/types';

defineProps<{
    entries: InfluencerListEntry[];
    outreachStatuses: OutreachStatusOption[];
    dealStatuses: DealStatusOption[];
    compensationTypes: CompensationTypeOption[];
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

const page = usePage();

const dealDialogOpen = ref(false);
const dealDialogEntry = ref<InfluencerListEntry | null>(null);
const dealDialogDeal = ref<Deal | null>(null);

const agreementDialogOpen = ref(false);
const agreementDialogAgreement = ref<Agreement | null>(null);
const agreementDialogName = ref('');

function startEditNotes(entry: InfluencerListEntry) {
    editingNotes.value = entry.id;
    notesDraft.value = entry.notes || '';
}

function saveNotes(entryId: number) {
    emit('save-notes', entryId, notesDraft.value);
    editingNotes.value = null;
}

// Deals become relevant once a conversation has progressed to real terms.
function canHaveDeal(entry: InfluencerListEntry): boolean {
    return ['negotiating', 'confirmed'].includes(entry.outreach_status);
}

function openDealDialog(entry: InfluencerListEntry, deal: Deal | null) {
    dealDialogEntry.value = entry;
    dealDialogDeal.value = deal;
    dealDialogOpen.value = true;
}

function dealSummary(deal: Deal): string {
    const parts: string[] = [];

    if (deal.flat_fee_cents !== null) {
        parts.push(formatCents(deal.flat_fee_cents));
    }

    if (deal.commission_rate !== null) {
        parts.push(`${deal.commission_rate}% commission`);
    }

    if (deal.product_value_cents !== null) {
        parts.push(`${formatCents(deal.product_value_cents)} product`);
    }

    const summary = parts.length > 0 ? parts.join(' + ') : deal.compensation_type_label;
    const deliverables = deal.deliverables.length;

    return deliverables > 0
        ? `${summary} · ${deliverables} deliverable${deliverables !== 1 ? 's' : ''}`
        : summary;
}

const copiedDealId = ref<number | null>(null);

async function copyAffiliateLink(deal: Deal) {
    if (!deal.affiliate_url) {
        return;
    }

    await navigator.clipboard.writeText(deal.affiliate_url);
    copiedDealId.value = deal.id;
    setTimeout(() => {
        if (copiedDealId.value === deal.id) {
            copiedDealId.value = null;
        }
    }, 2000);
}

const dealBadgeClasses: Record<string, string> = {
    gray: 'border-gray-500/40 text-gray-600 dark:text-gray-400',
    blue: 'border-blue-500/40 text-blue-600 dark:text-blue-400',
    yellow: 'border-yellow-500/40 text-yellow-600 dark:text-yellow-400',
    green: 'border-green-500/40 text-green-600 dark:text-green-400',
    purple: 'border-purple-500/40 text-purple-600 dark:text-purple-400',
    red: 'border-red-500/40 text-red-600 dark:text-red-400',
};

// The newest agreement is the live one; older ones are history.
function currentAgreement(deal: Deal): Agreement | null {
    return deal.agreements[0] ?? null;
}

function draftAgreement(deal: Deal) {
    router.post(
        storeAgreement({ current_team: page.props.currentTeam!.slug, deal: deal.id }).url,
        {},
        { preserveScroll: true },
    );
}

function openAgreementDialog(deal: Deal, entry: InfluencerListEntry) {
    const agreement = currentAgreement(deal);

    if (!agreement) {
        return;
    }

    agreementDialogAgreement.value = agreement;
    agreementDialogName.value = entry.influencer.display_name || entry.influencer.handle;
    agreementDialogOpen.value = true;
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

            <!-- Deals -->
            <div
                v-if="entry.deals.length > 0 || (canManage && canHaveDeal(entry))"
                class="mt-3 space-y-2"
            >
                <div
                    v-for="deal in entry.deals"
                    :key="deal.id"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <Handshake class="h-3.5 w-3.5 text-muted-foreground" />
                    <Badge
                        variant="outline"
                        :class="dealBadgeClasses[deal.status_color] || dealBadgeClasses.gray"
                    >
                        {{ deal.status_label }}
                    </Badge>
                    <span class="text-muted-foreground">{{ dealSummary(deal) }}</span>
                    <span
                        v-if="deal.overdue_deliverables_count > 0"
                        class="flex items-center gap-1 text-amber-600 dark:text-amber-400"
                    >
                        <AlertTriangle class="h-3.5 w-3.5" />
                        {{ deal.overdue_deliverables_count }} overdue
                    </span>
                    <span
                        v-if="deal.discount_code"
                        class="flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                        title="Partner discount code"
                    >
                        <Tag class="h-3 w-3" />
                        {{ deal.discount_code }}
                    </span>
                    <button
                        v-if="deal.affiliate_url"
                        class="flex items-center gap-1 text-muted-foreground hover:text-foreground"
                        :title="deal.affiliate_url"
                        @click="copyAffiliateLink(deal)"
                    >
                        <Check v-if="copiedDealId === deal.id" class="h-3.5 w-3.5 text-green-500" />
                        <Link2 v-else class="h-3.5 w-3.5" />
                        {{ copiedDealId === deal.id ? 'Copied' : 'Copy link' }}
                    </button>
                    <span
                        v-if="deal.attribution.orders > 0"
                        class="text-green-600 dark:text-green-400"
                        :title="`${deal.attribution.new_customers} new customer${deal.attribution.new_customers !== 1 ? 's' : ''}`"
                    >
                        {{ formatCents(deal.attribution.revenue_cents) }} · {{ deal.attribution.orders }} order{{ deal.attribution.orders !== 1 ? 's' : '' }}
                    </span>
                    <button
                        v-if="canManage"
                        class="text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                        @click="openDealDialog(entry, deal)"
                    >
                        Edit
                    </button>
                    <template v-if="canManage">
                        <button
                            v-if="currentAgreement(deal)"
                            class="flex items-center gap-1 text-muted-foreground hover:text-foreground"
                            @click="openAgreementDialog(deal, entry)"
                        >
                            <FileSignature class="h-3.5 w-3.5" />
                            Agreement:
                            <Badge
                                variant="outline"
                                :class="dealBadgeClasses[currentAgreement(deal)!.status_color] || dealBadgeClasses.gray"
                            >
                                {{ currentAgreement(deal)!.status_label }}
                            </Badge>
                        </button>
                        <button
                            v-else
                            class="flex items-center gap-1 text-muted-foreground hover:text-foreground"
                            @click="draftAgreement(deal)"
                        >
                            <FileSignature class="h-3.5 w-3.5" />
                            Draft agreement
                        </button>
                    </template>
                </div>
                <Button
                    v-if="canManage && canHaveDeal(entry) && entry.deals.length === 0"
                    variant="outline"
                    size="sm"
                    @click="openDealDialog(entry, null)"
                >
                    <Handshake class="mr-1 h-3.5 w-3.5" />
                    Create deal
                </Button>
                <button
                    v-else-if="canManage && canHaveDeal(entry)"
                    class="text-sm text-muted-foreground hover:text-foreground"
                    @click="openDealDialog(entry, null)"
                >
                    + Add another deal
                </button>
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

        <DealDialog
            v-if="dealDialogEntry"
            v-model:open="dealDialogOpen"
            :list-id="listId"
            :entry-id="dealDialogEntry.id"
            :influencer-name="dealDialogEntry.influencer.display_name || dealDialogEntry.influencer.handle"
            :deal="dealDialogDeal"
            :deal-statuses="dealStatuses"
            :compensation-types="compensationTypes"
        />

        <AgreementDialog
            v-model:open="agreementDialogOpen"
            :agreement="agreementDialogAgreement"
            :influencer-name="agreementDialogName"
        />
    </div>
</template>
