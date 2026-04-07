<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { List, Plus } from 'lucide-vue-next';
import CreateListModal from '@/components/influencers/CreateListModal.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/influencers/lists';
import { show } from '@/routes/influencers/lists';
import type { InfluencerList } from '@/types';

type Props = {
    lists: InfluencerList[];
};

defineProps<Props>();
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

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString();
}
</script>

<template>
    <Head title="Influencer Lists" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Influencer Lists"
                description="Organize and track influencers for your campaigns"
            />

            <CreateListModal>
                <Button>
                    <Plus class="mr-1" /> New List
                </Button>
            </CreateListModal>
        </div>

        <div class="space-y-3">
            <Link
                v-for="list in lists"
                :key="list.id"
                :href="show({ current_team: page.props.currentTeam!.slug, influencerList: list.id }).url"
                class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                        <List class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h3 class="font-medium">{{ list.name }}</h3>
                        <p v-if="list.description" class="text-sm text-muted-foreground">
                            {{ list.description }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-sm text-muted-foreground">
                    <span>{{ list.entries_count }} influencer{{ list.entries_count !== 1 ? 's' : '' }}</span>
                    <span>Created {{ formatDate(list.created_at) }}</span>
                </div>
            </Link>

            <div
                v-if="lists.length === 0"
                class="flex flex-col items-center justify-center py-12"
            >
                <List class="h-12 w-12 text-muted-foreground/30" />
                <p class="mt-3 text-sm text-muted-foreground">
                    No lists yet. Create one to start organizing influencers.
                </p>
                <CreateListModal>
                    <Button variant="outline" class="mt-4">
                        <Plus class="mr-1" /> Create your first list
                    </Button>
                </CreateListModal>
            </div>
        </div>
    </div>
</template>
