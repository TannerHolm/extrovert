<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Filter, Loader2, Search as SearchIcon, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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

const minFollowers = ref<number | undefined>();
const maxFollowers = ref<number | undefined>();
const minEngagement = ref<number | undefined>();
const maxEngagement = ref<number | undefined>();

const hasActiveFilters = computed(
    () =>
        minFollowers.value !== undefined ||
        maxFollowers.value !== undefined ||
        minEngagement.value !== undefined ||
        maxEngagement.value !== undefined,
);

const filteredResults = computed(() => {
    if (!hasActiveFilters.value) return results.value;

    return results.value.filter((r) => {
        if (minFollowers.value !== undefined && (r.follower_count ?? 0) < minFollowers.value) return false;
        if (maxFollowers.value !== undefined && (r.follower_count ?? 0) > maxFollowers.value) return false;
        if (minEngagement.value !== undefined && (r.engagement_rate ?? 0) < minEngagement.value) return false;
        if (maxEngagement.value !== undefined && (r.engagement_rate ?? 0) > maxEngagement.value) return false;
        return true;
    });
});

const currentPage = ref(1);
const perPage = 9; // 3x3 grid

const totalPages = computed(() => Math.ceil(filteredResults.value.length / perPage));
const paginatedResults = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredResults.value.slice(start, start + perPage);
});

// Reset to page 1 when filters change
watch([minFollowers, maxFollowers, minEngagement, maxEngagement], () => {
    currentPage.value = 1;
});

function clearFilters() {
    minFollowers.value = undefined;
    maxFollowers.value = undefined;
    minEngagement.value = undefined;
    maxEngagement.value = undefined;
}

async function performSearch() {
    if (!query.value.trim()) return;

    loading.value = true;
    searched.value = true;
    error.value = '';
    results.value = [];
    currentPage.value = 1;

    try {
        const url = resultsRoute.url(page.props.currentTeam!.slug, {
            query: {
                query: query.value,
                platform: selectedPlatform.value,
                max_results: 25,
            },
        });

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || `Search failed: ${response.statusText}`);
        }

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

        <!-- Filters -->
        <div v-if="results.length > 0" class="flex flex-wrap items-end gap-3 rounded-lg border p-3">
            <Filter class="h-4 w-4 shrink-0 text-muted-foreground" />

            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Min Followers</label>
                <Input
                    v-model.number="minFollowers"
                    type="number"
                    placeholder="0"
                    min="0"
                    class="h-8 w-32"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Max Followers</label>
                <Input
                    v-model.number="maxFollowers"
                    type="number"
                    placeholder="Any"
                    min="0"
                    class="h-8 w-32"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Min Engagement %</label>
                <Input
                    v-model.number="minEngagement"
                    type="number"
                    placeholder="0"
                    min="0"
                    max="100"
                    step="0.1"
                    class="h-8 w-32"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Max Engagement %</label>
                <Input
                    v-model.number="maxEngagement"
                    type="number"
                    placeholder="Any"
                    min="0"
                    max="100"
                    step="0.1"
                    class="h-8 w-32"
                />
            </div>

            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="clearFilters"
                class="h-8"
            >
                <X class="mr-1 h-3 w-3" />
                Clear
            </Button>

            <span v-if="hasActiveFilters" class="ml-auto text-xs text-muted-foreground">
                Showing {{ filteredResults.length }} of {{ results.length }} results
            </span>
        </div>

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
            v-else-if="filteredResults.length > 0"
            class="space-y-4"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <InfluencerCard
                    v-for="influencer in paginatedResults"
                    :key="`${influencer.platform}-${influencer.platform_id}`"
                    :influencer="influencer"
                    :lists="lists"
                />
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between">
                <span class="text-sm text-muted-foreground">
                    Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filteredResults.length) }} of {{ filteredResults.length }}
                </span>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage <= 1"
                        @click="currentPage--"
                    >
                        <ChevronLeft class="mr-1 h-4 w-4" />
                        Previous
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        {{ currentPage }} / {{ totalPages }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage++"
                    >
                        Next
                        <ChevronRight class="ml-1 h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Filtered Empty State -->
        <div
            v-else-if="results.length > 0 && filteredResults.length === 0"
            class="flex flex-col items-center justify-center py-12"
        >
            <Filter class="h-12 w-12 text-muted-foreground/50" />
            <p class="mt-3 text-sm text-muted-foreground">
                No results match your filters.
            </p>
            <Button variant="ghost" size="sm" class="mt-2" @click="clearFilters">
                Clear filters
            </Button>
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
