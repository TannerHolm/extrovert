<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Loader2, Search as SearchIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import InfluencerCard from '@/components/influencers/InfluencerCard.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search as searchRoute } from '@/routes/influencers';
import { results as resultsRoute } from '@/routes/influencers/search';
import type {
    InfluencerListSummary,
    InfluencerSearchResult,
    PlatformOption,
} from '@/types';

type Props = {
    platforms: PlatformOption[];
    lists: InfluencerListSummary[];
};

const props = defineProps<Props>();
const page = usePage();

defineOptions({
    layout: (layoutProps: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Discover',
                href: layoutProps.currentTeam
                    ? searchRoute(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const query = ref('');
const selectedPlatform = ref(props.platforms[0]?.value ?? 'youtube');
const results = ref<InfluencerSearchResult[]>([]);
const loading = ref(false);
const searched = ref(false);
const error = ref('');

async function performSearch() {
    if (!query.value.trim()) return;

    loading.value = true;
    searched.value = true;
    error.value = '';
    results.value = [];

    try {
        const url = resultsRoute.url(page.props.currentTeam!.slug, {
            query: {
                query: query.value,
                platform: selectedPlatform.value,
                max_results: 10,
            },
        });

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Search failed: ${response.statusText}`);
        }

        const data = await response.json();
        results.value = data.results || [];
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Search failed. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="Discover Influencers" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Discover Influencers"
            description="Search for content creators across YouTube, Instagram, and TikTok"
        />

        <form @submit.prevent="performSearch" class="space-y-4">
            <!-- Platform Tabs -->
            <div class="flex gap-1 rounded-lg border p-1">
                <button
                    v-for="platform in platforms"
                    :key="platform.value"
                    type="button"
                    @click="selectedPlatform = platform.value"
                    :class="[
                        'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                        selectedPlatform === platform.value
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                >
                    {{ platform.label }}
                </button>
            </div>

            <!-- Search Input -->
            <div class="flex gap-2">
                <Input
                    v-model="query"
                    type="search"
                    placeholder="Search by keyword, niche, or topic..."
                    class="flex-1"
                    @keyup.enter="performSearch"
                />
                <Button type="submit" :disabled="loading || !query.trim()">
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                    <SearchIcon v-else class="mr-2 h-4 w-4" />
                    Search
                </Button>
            </div>
        </form>

        <!-- Error -->
        <div
            v-if="error"
            class="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive"
        >
            {{ error }}
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-12">
            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            <p class="mt-2 text-sm text-muted-foreground">Searching...</p>
        </div>

        <!-- Results Grid -->
        <div
            v-else-if="results.length > 0"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <InfluencerCard
                v-for="influencer in results"
                :key="`${influencer.platform}-${influencer.platform_id}`"
                :influencer="influencer"
                :lists="lists"
            />
        </div>

        <!-- Empty State -->
        <div
            v-else-if="searched && !loading"
            class="flex flex-col items-center justify-center py-12"
        >
            <SearchIcon class="h-12 w-12 text-muted-foreground/50" />
            <p class="mt-3 text-sm text-muted-foreground">
                No influencers found. Try a different search term or platform.
            </p>
        </div>

        <!-- Initial State -->
        <div
            v-else
            class="flex flex-col items-center justify-center py-12"
        >
            <SearchIcon class="h-12 w-12 text-muted-foreground/30" />
            <p class="mt-3 text-sm text-muted-foreground">
                Enter a keyword to discover influencers
            </p>
        </div>
    </div>
</template>
