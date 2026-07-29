<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Check, FileSignature, Link2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { formatDate } from '@/lib/format';
import {
    send as sendAgreement,
    update as updateAgreement,
    voidMethod as voidAgreement,
} from '@/routes/agreements';
import type { Agreement } from '@/types';

type Props = {
    open: boolean;
    agreement: Agreement | null;
    influencerName: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const page = usePage();
const copied = ref(false);

const form = useForm({
    body_markdown: '',
    signer_name: '',
    signer_email: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.agreement) {
            return;
        }

        copied.value = false;
        form.clearErrors();
        form.body_markdown = props.agreement.body_markdown;
        form.signer_name = props.agreement.signer_name ?? '';
        form.signer_email = props.agreement.signer_email ?? '';
    },
);

const isDraft = computed(() => props.agreement?.status === 'draft');
const isPending = computed(() => ['sent', 'viewed'].includes(props.agreement?.status ?? ''));

function teamSlug(): string {
    return page.props.currentTeam!.slug;
}

function saveDraft(options: { onSuccess?: () => void } = {}) {
    if (!props.agreement) {
        return;
    }

    form.patch(updateAgreement({ current_team: teamSlug(), agreement: props.agreement.id }).url, {
        preserveScroll: true,
        ...options,
    });
}

function send() {
    if (!props.agreement) {
        return;
    }

    const sendUrl = sendAgreement({ current_team: teamSlug(), agreement: props.agreement.id }).url;

    const fireSend = () =>
        router.post(sendUrl, {}, {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
        });

    // Persist any edits before the snapshot is taken.
    if (isDraft.value) {
        saveDraft({ onSuccess: fireSend });
    } else {
        fireSend();
    }
}

function voidIt() {
    if (!props.agreement || !confirm('Void this agreement? The signing link stops working immediately.')) {
        return;
    }

    router.post(
        voidAgreement({ current_team: teamSlug(), agreement: props.agreement.id }).url,
        {},
        { preserveScroll: true, onSuccess: () => emit('update:open', false) },
    );
}

async function copySignLink() {
    if (!props.agreement) {
        return;
    }

    await navigator.clipboard.writeText(props.agreement.sign_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileSignature class="h-5 w-5" />
                    Agreement — {{ influencerName }}
                </DialogTitle>
                <DialogDescription>
                    {{ isDraft
                        ? 'Review and edit the drafted agreement, then send it for signature.'
                        : isPending
                            ? 'Sent for signature. The document is locked; you can resend or void it.'
                            : 'This agreement is ' + (agreement?.status_label.toLowerCase() ?? '') + '.' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="agreement" class="space-y-4 py-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Signer name</Label>
                        <Input v-model="form.signer_name" :disabled="!isDraft" />
                        <p v-if="form.errors.signer_name" class="text-sm text-destructive">{{ form.errors.signer_name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label>Signer email</Label>
                        <Input v-model="form.signer_email" type="email" :disabled="!isDraft" />
                        <p v-if="form.errors.signer_email" class="text-sm text-destructive">{{ form.errors.signer_email }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Agreement (markdown)</Label>
                    <textarea
                        v-model="form.body_markdown"
                        rows="16"
                        :disabled="!isDraft"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 font-mono text-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-70"
                    />
                    <p v-if="form.errors.body_markdown" class="text-sm text-destructive">{{ form.errors.body_markdown }}</p>
                </div>

                <div v-if="!isDraft" class="space-y-1 text-sm text-muted-foreground">
                    <p v-if="agreement.sent_at">Sent {{ formatDate(agreement.sent_at) }}<span v-if="agreement.expires_at"> · expires {{ formatDate(agreement.expires_at) }}</span></p>
                    <p v-if="agreement.signed_at" class="text-green-600 dark:text-green-400">
                        Signed by {{ agreement.signer_name }} on {{ formatDate(agreement.signed_at) }}
                    </p>
                    <button
                        v-if="isPending"
                        class="flex items-center gap-1 text-muted-foreground hover:text-foreground"
                        @click="copySignLink"
                    >
                        <Check v-if="copied" class="h-3.5 w-3.5 text-green-500" />
                        <Link2 v-else class="h-3.5 w-3.5" />
                        {{ copied ? 'Copied' : 'Copy signing link' }}
                    </button>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <div>
                    <Button
                        v-if="isPending || isDraft"
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        @click="voidIt"
                    >
                        Void
                    </Button>
                </div>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Close</Button>
                    <Button v-if="isDraft" type="button" variant="outline" :disabled="form.processing" @click="saveDraft()">
                        Save draft
                    </Button>
                    <Button v-if="isDraft || isPending" type="button" :disabled="form.processing" @click="send">
                        {{ isDraft ? 'Send for signature' : 'Resend' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
