<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, FileText, Mail, MessageCircleReply, Sparkles, X } from 'lucide-vue-next';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { approve as approveRoute, dismiss as dismissRoute } from '@/routes/suggestions';
import type { Team } from '@/types';

type Suggestion = {
    id: number;
    type: 'first_touch' | 'follow_up' | 'reply_triage' | 'deal_recap';
    type_label: string;
    sends_email: boolean;
    payload: {
        subject?: string;
        body?: string;
        to?: string;
        recap?: string;
        classification?: string;
        suggested_status?: string;
        summary?: string;
        inbound_excerpt?: string;
        days_since_contact?: number;
        ai?: boolean;
    };
    who: string;
    where: string;
    created_at: string;
};

type Props = {
    suggestions: Suggestion[];
    canManage: boolean;
};

const props = defineProps<Props>();
const page = usePage();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Suggestions',
                href: layoutProps.currentTeam ? dashboard(layoutProps.currentTeam.slug) : '/',
            },
        ],
    }),
});

// Editable copies of each draft, keyed by suggestion id.
const drafts = reactive<Record<number, { subject: string; body: string }>>({});

for (const suggestion of props.suggestions) {
    drafts[suggestion.id] = {
        subject: suggestion.payload.subject ?? '',
        body: suggestion.payload.body ?? '',
    };
}

const processing = reactive<Record<number, boolean>>({});

function teamSlug(): string {
    return page.props.currentTeam!.slug;
}

function approve(suggestion: Suggestion) {
    processing[suggestion.id] = true;
    router.post(
        approveRoute({ current_team: teamSlug(), suggestion: suggestion.id }).url,
        suggestion.sends_email
            ? { subject: drafts[suggestion.id].subject, body: drafts[suggestion.id].body }
            : {},
        {
            preserveScroll: true,
            onFinish: () => (processing[suggestion.id] = false),
        },
    );
}

function dismiss(suggestion: Suggestion) {
    processing[suggestion.id] = true;
    router.post(
        dismissRoute({ current_team: teamSlug(), suggestion: suggestion.id }).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (processing[suggestion.id] = false),
        },
    );
}

const typeIcons = {
    first_touch: Mail,
    follow_up: Mail,
    reply_triage: MessageCircleReply,
    deal_recap: FileText,
};

function approveLabel(suggestion: Suggestion): string {
    switch (suggestion.type) {
        case 'reply_triage':
            return drafts[suggestion.id]?.body ? 'Apply status & send reply' : 'Apply status';
        case 'deal_recap':
            return 'Save to deal notes';
        default:
            return 'Approve & send';
    }
}
</script>

<template>
    <Head title="Suggestions" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            title="Suggestions"
            description="AI-drafted next steps — nothing goes out without your approval"
        />

        <div v-if="suggestions.length > 0" class="space-y-4">
            <Card v-for="suggestion in suggestions" :key="suggestion.id">
                <CardHeader class="pb-3">
                    <CardTitle class="flex flex-wrap items-center gap-2 text-sm font-medium">
                        <component :is="typeIcons[suggestion.type]" class="h-4 w-4" />
                        {{ suggestion.type_label }} — {{ suggestion.who }}
                        <span class="text-xs font-normal text-muted-foreground">{{ suggestion.where }}</span>
                        <Badge v-if="suggestion.payload.ai" variant="outline" class="gap-1 text-[10px]">
                            <Sparkles class="h-3 w-3" />
                            AI draft
                        </Badge>
                        <Badge
                            v-if="suggestion.type === 'reply_triage' && suggestion.payload.classification"
                            variant="outline"
                            class="text-[10px] capitalize"
                        >
                            {{ suggestion.payload.classification }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <!-- Context for triage -->
                    <div
                        v-if="suggestion.payload.inbound_excerpt"
                        class="rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground"
                    >
                        <p class="mb-1 font-medium text-foreground">They wrote:</p>
                        <p class="whitespace-pre-line">{{ suggestion.payload.inbound_excerpt }}</p>
                        <p v-if="suggestion.payload.summary" class="mt-2 italic">{{ suggestion.payload.summary }}</p>
                    </div>

                    <p v-if="suggestion.type === 'reply_triage' && suggestion.payload.suggested_status" class="text-sm">
                        Suggested pipeline status:
                        <Badge variant="outline" class="capitalize">{{ suggestion.payload.suggested_status }}</Badge>
                    </p>

                    <!-- Editable email draft -->
                    <template v-if="suggestion.sends_email">
                        <div class="space-y-2">
                            <Label>To</Label>
                            <Input :model-value="suggestion.payload.to ?? ''" disabled />
                        </div>
                        <div class="space-y-2">
                            <Label>Subject</Label>
                            <Input v-model="drafts[suggestion.id].subject" :disabled="!canManage" />
                        </div>
                        <div class="space-y-2">
                            <Label>Message</Label>
                            <textarea
                                v-model="drafts[suggestion.id].body"
                                rows="7"
                                :disabled="!canManage"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-70"
                            />
                        </div>
                    </template>

                    <!-- Recap text -->
                    <p v-if="suggestion.type === 'deal_recap'" class="rounded-md border bg-muted/30 p-3 text-sm whitespace-pre-line">
                        {{ suggestion.payload.recap }}
                    </p>

                    <div v-if="canManage" class="flex items-center justify-end gap-2 pt-1">
                        <Button variant="ghost" size="sm" :disabled="processing[suggestion.id]" @click="dismiss(suggestion)">
                            <X class="mr-1 h-4 w-4" />
                            Dismiss
                        </Button>
                        <Button size="sm" :disabled="processing[suggestion.id]" @click="approve(suggestion)">
                            <Check class="mr-1 h-4 w-4" />
                            {{ approveLabel(suggestion) }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="flex flex-col items-center justify-center rounded-lg border py-16">
            <Sparkles class="h-12 w-12 text-muted-foreground/30" />
            <p class="mt-3 max-w-md text-center text-sm text-muted-foreground">
                Nothing waiting for review. Suggestions appear when new influencers are saved, contacted creators go
                quiet, replies arrive, or deals complete.
            </p>
        </div>
    </div>
</template>
