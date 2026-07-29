<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, FileSignature, ShieldCheck, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { decline as declineRoute, submit as submitRoute } from '@/routes/sign';

type Props = {
    agreement: {
        token: string;
        brand_name: string;
        signer_name: string | null;
        signer_email: string | null;
        body_html: string;
        status: string;
        signable: boolean;
        expired: boolean;
        signed_at: string | null;
        expires_at: string | null;
        content_hash: string | null;
    };
};

const props = defineProps<Props>();

const form = useForm({
    typed_name: props.agreement.signer_name ?? '',
    consent: false as boolean,
});

const flashSigned = computed(() => props.agreement.status === 'signed');
const declined = computed(() => props.agreement.status === 'declined');
const unavailable = computed(
    () => !props.agreement.signable && !flashSigned.value && !declined.value,
);

function sign() {
    form.post(submitRoute(props.agreement.token).url, { preserveScroll: true });
}

function decline() {
    if (!confirm('Decline this agreement? The brand will be able to see that you declined.')) {
        return;
    }

    form.post(declineRoute(props.agreement.token).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${agreement.brand_name} agreement`" />

    <div class="min-h-screen bg-muted/40 py-10">
        <div class="mx-auto max-w-3xl space-y-6 px-4">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <FileSignature class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold">{{ agreement.brand_name }} — Partnership Agreement</h1>
                    <p class="text-sm text-muted-foreground">
                        Prepared for {{ agreement.signer_name }}
                    </p>
                </div>
            </div>

            <!-- Status banners -->
            <div
                v-if="flashSigned"
                class="flex items-center gap-2 rounded-lg border border-green-500/40 bg-green-500/10 p-4 text-green-700 dark:text-green-400"
            >
                <CheckCircle2 class="h-5 w-5 shrink-0" />
                <p class="text-sm">
                    Signed{{ agreement.signed_at ? ` on ${new Date(agreement.signed_at).toLocaleString()}` : '' }}.
                    A copy of the executed agreement has been emailed to you.
                </p>
            </div>

            <div
                v-else-if="declined"
                class="flex items-center gap-2 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-red-700 dark:text-red-400"
            >
                <XCircle class="h-5 w-5 shrink-0" />
                <p class="text-sm">You declined this agreement. If that was a mistake, contact {{ agreement.brand_name }}.</p>
            </div>

            <div
                v-else-if="unavailable"
                class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400"
            >
                {{ agreement.expired
                    ? 'This signing link has expired. Contact ' + agreement.brand_name + ' to request a new one.'
                    : 'This agreement is no longer available for signing.' }}
            </div>

            <!-- Agreement body -->
            <div class="rounded-lg border bg-background p-8 shadow-sm">
                <div class="agreement-body" v-html="agreement.body_html" />
            </div>

            <!-- Signature form -->
            <div v-if="agreement.signable" class="space-y-4 rounded-lg border bg-background p-6 shadow-sm">
                <h2 class="font-medium">Sign this agreement</h2>

                <div class="space-y-2">
                    <Label for="typed_name">Type your full legal name as your signature</Label>
                    <Input
                        id="typed_name"
                        v-model="form.typed_name"
                        class="font-serif text-lg italic"
                        placeholder="Your full name"
                    />
                    <p v-if="form.errors.typed_name" class="text-sm text-destructive">{{ form.errors.typed_name }}</p>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        :model-value="form.consent"
                        class="mt-0.5"
                        @update:model-value="(v) => (form.consent = v === true)"
                    />
                    <span>
                        I agree to sign this document electronically, and I understand my electronic
                        signature has the same legal effect as a handwritten signature.
                    </span>
                </label>
                <p v-if="form.errors.consent" class="text-sm text-destructive">{{ form.errors.consent }}</p>

                <div class="flex items-center justify-between">
                    <button class="text-sm text-muted-foreground hover:text-destructive" @click="decline">
                        Decline
                    </button>
                    <Button :disabled="form.processing || !form.consent || form.typed_name.trim() === ''" @click="sign">
                        <ShieldCheck class="mr-2 h-4 w-4" />
                        Sign agreement
                    </Button>
                </div>
            </div>

            <p v-if="agreement.content_hash" class="text-center text-xs text-muted-foreground">
                Document integrity SHA-256: {{ agreement.content_hash.slice(0, 16) }}…
            </p>
        </div>
    </div>
</template>

<style scoped>
.agreement-body :deep(h1) {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}
.agreement-body :deep(h2) {
    font-size: 1.05rem;
    font-weight: 600;
    margin: 1.25rem 0 0.5rem;
}
.agreement-body :deep(p) {
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    line-height: 1.65;
}
.agreement-body :deep(ul) {
    list-style: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}
.agreement-body :deep(hr) {
    margin: 1.25rem 0;
    border-color: hsl(var(--border, 0 0% 85%));
}
.agreement-body :deep(strong) {
    font-weight: 600;
}
</style>
