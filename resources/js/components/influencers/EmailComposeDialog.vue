<script setup lang="ts">
import { Mail } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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

type Props = {
    influencerName: string;
    influencerHandle: string;
    influencerEmail: string | null;
    platform: string;
};

const props = defineProps<Props>();
const open = ref(false);

const subject = ref('');
const body = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        subject.value = `Partnership Opportunity \u2014 ${props.influencerName}`;
        body.value =
            `Hi ${props.influencerName},\n\n` +
            `I came across your ${props.platform} profile (@${props.influencerHandle}) and love your content. ` +
            `I'd like to discuss a potential partnership opportunity.\n\n` +
            `Would you be open to a quick chat?\n\n` +
            `Best regards`;
    }
});

function openMailClient() {
    const params = new URLSearchParams();
    params.set('subject', subject.value);
    params.set('body', body.value);
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
                    Compose your outreach email. This will open in your email client.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label>To</Label>
                    <Input :model-value="influencerEmail ?? ''" disabled />
                </div>
                <div class="space-y-2">
                    <Label>Subject</Label>
                    <Input v-model="subject" />
                </div>
                <div class="space-y-2">
                    <Label>Message</Label>
                    <textarea
                        v-model="body"
                        rows="8"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button @click="openMailClient">
                    <Mail class="mr-2 h-4 w-4" />
                    Open in Email Client
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
