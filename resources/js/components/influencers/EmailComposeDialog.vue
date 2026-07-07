<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as sendOutreachEmail } from '@/routes/influencers/entries/emails';
import type { OutreachMessage } from '@/types';

type Props = {
    influencerName: string;
    influencerHandle: string;
    influencerEmail: string | null;
    platform: string;
    // When a saved entry is provided, the email is sent + logged through the app.
    // Otherwise the dialog falls back to opening the user's mail client (mailto).
    listId?: number | null;
    entryId?: number | null;
    messages?: OutreachMessage[];
};

const props = withDefaults(defineProps<Props>(), {
    listId: null,
    entryId: null,
    messages: () => [],
});

const page = usePage();
const open = ref(false);

const canSendInApp = computed(
    () => props.listId !== null && props.entryId !== null,
);

const form = useForm({ subject: '', body: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.subject = `Partnership Opportunity — ${props.influencerName}`;
        form.body =
            `Hi ${props.influencerName},\n\n` +
            `I came across your ${props.platform} profile (@${props.influencerHandle}) and love your content. ` +
            `I'd like to discuss a potential partnership opportunity.\n\n` +
            `Would you be open to a quick chat?\n\n` +
            `Best regards`;
    }
});

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString();
}

function send() {
    if (canSendInApp.value) {
        form.post(
            sendOutreachEmail({
                current_team: page.props.currentTeam!.slug,
                influencerList: props.listId!,
                entry: props.entryId!,
            }).url,
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    form.reset();
                    open.value = false;
                },
            },
        );
        return;
    }

    // mailto fallback for contexts without a saved entry (e.g. Discover results).
    const params = new URLSearchParams();
    params.set('subject', form.subject);
    params.set('body', form.body);
    window.open(`mailto:${props.influencerEmail}?${params.toString()}`, '_blank');
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="!influencerEmail"
                    :title="influencerEmail ? `Email ${influencerEmail}` : 'No email available'"
                >
                    <Mail class="h-4 w-4" />
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Email {{ influencerName }}</DialogTitle>
                <DialogDescription>
                    {{ canSendInApp
                        ? 'Compose and send your outreach — it will be logged to this influencer\'s timeline.'
                        : 'Compose your outreach email. This will open in your email client.' }}
                </DialogDescription>
            </DialogHeader>

            <!-- Message history -->
            <div
                v-if="messages.length > 0"
                class="max-h-40 space-y-2 overflow-y-auto rounded-md border bg-muted/30 p-2"
            >
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="rounded-md border bg-background p-2 text-xs"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">
                            {{ message.direction === 'outbound' ? 'Sent' : 'Received' }}: {{ message.subject }}
                        </span>
                        <span class="shrink-0 text-muted-foreground">{{ formatDate(message.sent_at) }}</span>
                    </div>
                    <p class="mt-1 line-clamp-3 whitespace-pre-line text-muted-foreground">{{ message.body }}</p>
                    <p v-if="message.sent_by" class="mt-1 text-muted-foreground/70">by {{ message.sent_by }}</p>
                </div>
            </div>

            <div class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label>To</Label>
                    <Input :model-value="influencerEmail ?? ''" disabled />
                </div>
                <div class="space-y-2">
                    <Label>Subject</Label>
                    <Input v-model="form.subject" :class="{ 'border-destructive': form.errors.subject }" />
                    <p v-if="form.errors.subject" class="text-sm text-destructive">{{ form.errors.subject }}</p>
                </div>
                <div class="space-y-2">
                    <Label>Message</Label>
                    <textarea
                        v-model="form.body"
                        rows="8"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{ 'border-destructive': form.errors.body }"
                    />
                    <p v-if="form.errors.body" class="text-sm text-destructive">{{ form.errors.body }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button :disabled="form.processing || !influencerEmail" @click="send">
                    <Mail class="mr-2 h-4 w-4" />
                    {{ canSendInApp ? 'Send email' : 'Open in Email Client' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
