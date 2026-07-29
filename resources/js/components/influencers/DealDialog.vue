<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Handshake, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy as destroyDeal,
    store as storeDeal,
    update as updateDeal,
} from '@/routes/influencers/entries/deals';
import type {
    CompensationType,
    CompensationTypeOption,
    Deal,
    DealStatusOption,
    Deliverable,
} from '@/types';

type Props = {
    open: boolean;
    listId: number;
    entryId: number;
    influencerName: string;
    // Null when creating a new deal.
    deal: Deal | null;
    dealStatuses: DealStatusOption[];
    compensationTypes: CompensationTypeOption[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const page = usePage();

type DeliverableDraft = {
    type: Deliverable['type'];
    platform: Deliverable['platform'];
    due_date: string;
    posted_url: string;
};

const deliverableTypes: Deliverable['type'][] = ['post', 'reel', 'video', 'story'];
const deliverablePlatforms: Deliverable['platform'][] = ['youtube', 'instagram', 'tiktok'];

// Number inputs can emit either strings or numbers depending on the browser
// and component, so money/rate fields are typed to accept both.
const form = useForm({
    status: 'draft',
    compensation_type: 'flat_fee' as CompensationType,
    flat_fee: '' as string | number,
    commission_rate: '' as string | number,
    product_value: '' as string | number,
    deliverables: [] as DeliverableDraft[],
    usage_rights: '',
    starts_at: '',
    ends_at: '',
    notes: '',
});

const confirmingDelete = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        confirmingDelete.value = false;
        form.clearErrors();
        form.status = props.deal?.status ?? 'draft';
        form.compensation_type = props.deal?.compensation_type ?? 'flat_fee';
        form.flat_fee = props.deal?.flat_fee_cents != null ? String(props.deal.flat_fee_cents / 100) : '';
        form.commission_rate = props.deal?.commission_rate != null ? String(props.deal.commission_rate) : '';
        form.product_value = props.deal?.product_value_cents != null ? String(props.deal.product_value_cents / 100) : '';
        form.deliverables = (props.deal?.deliverables ?? []).map((deliverable) => ({
            type: deliverable.type,
            platform: deliverable.platform,
            due_date: deliverable.due_date ?? '',
            posted_url: deliverable.posted_url ?? '',
        }));
        form.usage_rights = props.deal?.usage_rights ?? '';
        form.starts_at = props.deal?.starts_at ?? '';
        form.ends_at = props.deal?.ends_at ?? '';
        form.notes = props.deal?.notes ?? '';
    },
);

function addDeliverable() {
    form.deliverables.push({
        type: 'post',
        platform: 'instagram',
        due_date: '',
        posted_url: '',
    });
}

function removeDeliverable(index: number) {
    form.deliverables.splice(index, 1);
}

function showsFlatFee(type: CompensationType): boolean {
    return type === 'flat_fee' || type === 'hybrid';
}

function showsCommission(type: CompensationType): boolean {
    return type === 'commission' || type === 'hybrid';
}

function showsProductValue(type: CompensationType): boolean {
    return type === 'gifted' || type === 'hybrid';
}

// Server-side field names (e.g. flat_fee_cents) differ from the form's dollar
// inputs, so error keys aren't covered by the form's typed errors object.
function errorFor(field: string): string | undefined {
    return (form.errors as Record<string, string>)[field];
}

function toCents(value: string | number): number | null {
    if (String(value).trim() === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
}

function save() {
    const routeParams = {
        current_team: page.props.currentTeam!.slug,
        influencerList: props.listId,
        entry: props.entryId,
    };

    const transformed = form.transform((data) => ({
        status: data.status,
        compensation_type: data.compensation_type,
        flat_fee_cents: showsFlatFee(data.compensation_type) ? toCents(data.flat_fee) : null,
        commission_rate: showsCommission(data.compensation_type) && String(data.commission_rate).trim() !== ''
            ? Number(data.commission_rate)
            : null,
        product_value_cents: showsProductValue(data.compensation_type) ? toCents(data.product_value) : null,
        deliverables: data.deliverables.map((deliverable) => ({
            type: deliverable.type,
            platform: deliverable.platform,
            due_date: deliverable.due_date || null,
            posted_url: deliverable.posted_url || null,
        })),
        usage_rights: data.usage_rights || null,
        starts_at: data.starts_at || null,
        ends_at: data.ends_at || null,
        notes: data.notes || null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.deal) {
        transformed.patch(updateDeal({ ...routeParams, deal: props.deal.id }).url, options);
    } else {
        transformed.post(storeDeal(routeParams).url, options);
    }
}

function removeDeal() {
    if (!props.deal) {
        return;
    }

    form.delete(
        destroyDeal({
            current_team: page.props.currentTeam!.slug,
            influencerList: props.listId,
            entry: props.entryId,
            deal: props.deal.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Handshake class="h-5 w-5" />
                    {{ deal ? 'Edit deal' : 'New deal' }} — {{ influencerName }}
                </DialogTitle>
                <DialogDescription>
                    Record what was agreed: compensation, deliverables, and dates.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-if="deal" class="space-y-2">
                        <Label>Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in dealStatuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.status" class="text-sm text-destructive">{{ form.errors.status }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Compensation</Label>
                        <Select v-model="form.compensation_type">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in compensationTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.compensation_type" class="text-sm text-destructive">
                            {{ form.errors.compensation_type }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div v-if="showsFlatFee(form.compensation_type)" class="space-y-2">
                        <Label>Flat fee ($)</Label>
                        <Input v-model="form.flat_fee" type="number" min="0" step="0.01" placeholder="500.00" />
                        <p v-if="errorFor('flat_fee_cents')" class="text-sm text-destructive">
                            {{ errorFor('flat_fee_cents') }}
                        </p>
                    </div>
                    <div v-if="showsCommission(form.compensation_type)" class="space-y-2">
                        <Label>Commission (%)</Label>
                        <Input v-model="form.commission_rate" type="number" min="0" max="100" step="0.01" placeholder="15" />
                        <p v-if="form.errors.commission_rate" class="text-sm text-destructive">
                            {{ form.errors.commission_rate }}
                        </p>
                    </div>
                    <div v-if="showsProductValue(form.compensation_type)" class="space-y-2">
                        <Label>Product value ($)</Label>
                        <Input v-model="form.product_value" type="number" min="0" step="0.01" placeholder="120.00" />
                        <p v-if="errorFor('product_value_cents')" class="text-sm text-destructive">
                            {{ errorFor('product_value_cents') }}
                        </p>
                    </div>
                </div>

                <!-- Deliverables -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Deliverables</Label>
                        <Button type="button" variant="outline" size="sm" @click="addDeliverable">
                            <Plus class="mr-1 h-3.5 w-3.5" /> Add
                        </Button>
                    </div>
                    <p v-if="form.deliverables.length === 0" class="text-sm text-muted-foreground">
                        No deliverables yet — add the posts, videos, or stories this deal includes.
                    </p>
                    <div
                        v-for="(deliverable, index) in form.deliverables"
                        :key="index"
                        class="flex flex-wrap items-center gap-2 rounded-md border p-2"
                    >
                        <Select v-model="deliverable.type">
                            <SelectTrigger class="w-[110px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in deliverableTypes" :key="type" :value="type">
                                    {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="deliverable.platform">
                            <SelectTrigger class="w-[130px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="platform in deliverablePlatforms" :key="platform" :value="platform">
                                    {{ platform.charAt(0).toUpperCase() + platform.slice(1) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Input v-model="deliverable.due_date" type="date" class="w-[150px]" title="Due date" />
                        <Input
                            v-model="deliverable.posted_url"
                            type="url"
                            placeholder="Posted URL (once live)"
                            class="min-w-[180px] flex-1"
                        />
                        <Button type="button" variant="ghost" size="sm" @click="removeDeliverable(index)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                    <p v-if="form.errors.deliverables" class="text-sm text-destructive">{{ form.errors.deliverables }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Starts</Label>
                        <Input v-model="form.starts_at" type="date" />
                        <p v-if="form.errors.starts_at" class="text-sm text-destructive">{{ form.errors.starts_at }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label>Ends</Label>
                        <Input v-model="form.ends_at" type="date" />
                        <p v-if="form.errors.ends_at" class="text-sm text-destructive">{{ form.errors.ends_at }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Usage rights</Label>
                    <textarea
                        v-model="form.usage_rights"
                        rows="2"
                        placeholder="e.g. Brand may reuse content in paid ads for 90 days"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    />
                    <p v-if="form.errors.usage_rights" class="text-sm text-destructive">{{ form.errors.usage_rights }}</p>
                </div>

                <div class="space-y-2">
                    <Label>Notes</Label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    />
                    <p v-if="form.errors.notes" class="text-sm text-destructive">{{ form.errors.notes }}</p>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <div v-if="deal">
                    <Button
                        v-if="!confirmingDelete"
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        @click="confirmingDelete = true"
                    >
                        Delete deal
                    </Button>
                    <div v-else class="flex items-center gap-2">
                        <span class="text-sm text-muted-foreground">Delete this deal?</span>
                        <Button type="button" variant="destructive" size="sm" :disabled="form.processing" @click="removeDeal">
                            Delete
                        </Button>
                        <Button type="button" variant="ghost" size="sm" @click="confirmingDelete = false">
                            Keep
                        </Button>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
                    <Button type="button" :disabled="form.processing" @click="save">
                        {{ deal ? 'Save deal' : 'Create deal' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
