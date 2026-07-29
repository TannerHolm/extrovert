<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ArrowUpDown, Download, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import PlatformIcon from '@/components/influencers/PlatformIcon.vue';
import RoiScatter from '@/components/reports/RoiScatter.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCents } from '@/lib/format';
import { dashboard } from '@/routes';
import { commissions } from '@/routes/reports';
import type { Platform, Team } from '@/types';

type RoiRow = {
    deal_id: number;
    partner: string;
    handle: string;
    platform: Platform;
    avatar_url: string | null;
    status: string;
    status_label: string;
    status_color: string;
    spend_cents: number;
    commission_cents: number;
    revenue_cents: number;
    orders: number;
    new_customers: number;
    cost_per_new_customer_cents: number | null;
    posts: number;
    revenue_per_post_cents: number | null;
    roi: number | null;
};

type Props = {
    deals: RoiRow[];
    totals: {
        spend_cents: number;
        revenue_cents: number;
        orders: number;
        new_customers: number;
    };
    month: string;
};

const props = defineProps<Props>();
const page = usePage();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Partner ROI',
                href: layoutProps.currentTeam ? dashboard(layoutProps.currentTeam.slug) : '/',
            },
        ],
    }),
});

type SortKey = keyof Pick<
    RoiRow,
    'partner' | 'spend_cents' | 'revenue_cents' | 'orders' | 'new_customers' | 'cost_per_new_customer_cents' | 'revenue_per_post_cents' | 'roi'
>;

const sortKey = ref<SortKey>('revenue_cents');
const sortDesc = ref(true);

function sortBy(key: SortKey) {
    if (sortKey.value === key) {
        sortDesc.value = !sortDesc.value;
    } else {
        sortKey.value = key;
        sortDesc.value = true;
    }
}

const sorted = computed(() => {
    const rows = [...props.deals];
    rows.sort((a, b) => {
        const av = a[sortKey.value];
        const bv = b[sortKey.value];

        if (av === null && bv === null) {
            return 0;
        }

        if (av === null) {
            return 1;
        }

        if (bv === null) {
            return -1;
        }

        if (typeof av === 'string' && typeof bv === 'string') {
            return sortDesc.value ? bv.localeCompare(av) : av.localeCompare(bv);
        }

        return sortDesc.value ? Number(bv) - Number(av) : Number(av) - Number(bv);
    });

    return rows;
});

const csvUrl = computed(() =>
    commissions({ current_team: page.props.currentTeam!.slug }, { query: { month: props.month } }).url,
);

const overallRoi = computed(() => {
    if (props.totals.spend_cents === 0) {
        return null;
    }

    return (props.totals.revenue_cents / props.totals.spend_cents).toFixed(2);
});

const columns: { key: SortKey; label: string; align?: string }[] = [
    { key: 'partner', label: 'Partner' },
    { key: 'spend_cents', label: 'Spend' },
    { key: 'revenue_cents', label: 'Revenue' },
    { key: 'orders', label: 'Orders' },
    { key: 'new_customers', label: 'New customers' },
    { key: 'cost_per_new_customer_cents', label: 'Cost / new customer' },
    { key: 'revenue_per_post_cents', label: 'Revenue / post' },
    { key: 'roi', label: 'ROI' },
];

const dealBadgeClasses: Record<string, string> = {
    gray: 'border-gray-500/40 text-gray-600 dark:text-gray-400',
    blue: 'border-blue-500/40 text-blue-600 dark:text-blue-400',
    green: 'border-green-500/40 text-green-600 dark:text-green-400',
    purple: 'border-purple-500/40 text-purple-600 dark:text-purple-400',
    red: 'border-red-500/40 text-red-600 dark:text-red-400',
};
</script>

<template>
    <Head title="Partner ROI" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                variant="small"
                title="Partner ROI"
                description="Spend vs attributed revenue per deal — commissions accrue from real orders"
            />
            <a :href="csvUrl">
                <Button variant="outline" size="sm">
                    <Download class="mr-2 h-4 w-4" />
                    Commissions CSV ({{ month }})
                </Button>
            </a>
        </div>

        <!-- Totals -->
        <div class="grid gap-4 md:grid-cols-4">
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Total spend</CardTitle></CardHeader>
                <CardContent><div class="text-2xl font-bold">{{ formatCents(totals.spend_cents) }}</div></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Attributed revenue</CardTitle></CardHeader>
                <CardContent><div class="text-2xl font-bold">{{ formatCents(totals.revenue_cents) }}</div></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Orders</CardTitle></CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ totals.orders }}</div>
                    <p class="text-xs text-muted-foreground">{{ totals.new_customers }} from new customers</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm font-medium">Blended ROI</CardTitle></CardHeader>
                <CardContent><div class="text-2xl font-bold">{{ overallRoi !== null ? `${overallRoi}×` : '—' }}</div></CardContent>
            </Card>
        </div>

        <!-- Scatter -->
        <Card v-if="deals.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-sm font-medium">
                    <TrendingUp class="h-4 w-4" />
                    Spend vs revenue per deal
                </CardTitle>
            </CardHeader>
            <CardContent>
                <RoiScatter :points="deals.map((d) => ({ label: d.partner, spend_cents: d.spend_cents, revenue_cents: d.revenue_cents }))" />
                <p class="mt-1 text-xs text-muted-foreground">Deals above the dashed line earned more than they cost.</p>
            </CardContent>
        </Card>

        <!-- Table -->
        <div v-if="deals.length > 0" class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/40 text-left">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="cursor-pointer px-3 py-2 font-medium whitespace-nowrap select-none hover:text-foreground"
                            :class="sortKey === column.key ? 'text-foreground' : 'text-muted-foreground'"
                            @click="sortBy(column.key)"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ column.label }}
                                <ArrowUpDown class="h-3 w-3" />
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in sorted" :key="row.deal_id" class="border-b last:border-0 hover:bg-muted/30">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <Avatar class="h-7 w-7 shrink-0">
                                    <AvatarImage v-if="row.avatar_url" :src="row.avatar_url" />
                                    <AvatarFallback class="text-xs">{{ row.partner.charAt(0).toUpperCase() }}</AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-medium">{{ row.partner }}</span>
                                        <PlatformIcon :platform="row.platform" class="h-3.5 w-3.5 shrink-0" />
                                    </div>
                                    <Badge
                                        variant="outline"
                                        class="mt-0.5 text-[10px]"
                                        :class="dealBadgeClasses[row.status_color] || dealBadgeClasses.gray"
                                    >
                                        {{ row.status_label }}
                                    </Badge>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ formatCents(row.spend_cents) }}
                            <span v-if="row.commission_cents > 0" class="block text-xs text-muted-foreground">
                                incl. {{ formatCents(row.commission_cents) }} commission
                            </span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap font-medium">{{ formatCents(row.revenue_cents) }}</td>
                        <td class="px-3 py-2">{{ row.orders }}</td>
                        <td class="px-3 py-2">{{ row.new_customers }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ row.cost_per_new_customer_cents !== null ? formatCents(row.cost_per_new_customer_cents) : '—' }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ row.revenue_per_post_cents !== null ? formatCents(row.revenue_per_post_cents) : '—' }}
                        </td>
                        <td class="px-3 py-2 font-medium whitespace-nowrap">
                            <span
                                v-if="row.roi !== null"
                                :class="row.roi >= 1 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ row.roi.toFixed(2) }}×
                            </span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="flex flex-col items-center justify-center rounded-lg border py-16">
            <TrendingUp class="h-12 w-12 text-muted-foreground/30" />
            <p class="mt-3 max-w-md text-center text-sm text-muted-foreground">
                No deals yet. ROI appears here once deals exist and Shopify orders start attributing to partner codes and links.
            </p>
        </div>
    </div>
</template>
