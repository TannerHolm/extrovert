<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatCents } from '@/lib/format';

type Point = {
    label: string;
    spend_cents: number;
    revenue_cents: number;
};

const props = defineProps<{ points: Point[] }>();

const W = 640;
const H = 280;
const M = { top: 12, right: 16, bottom: 34, left: 52 };

const innerW = W - M.left - M.right;
const innerH = H - M.top - M.bottom;

// One shared domain for both axes keeps the break-even diagonal honest.
const max = computed(() => {
    const values = props.points.flatMap((p) => [p.spend_cents, p.revenue_cents]);

    return Math.max(...values, 1) * 1.08;
});

function x(cents: number): number {
    return M.left + (cents / max.value) * innerW;
}

function y(cents: number): number {
    return M.top + innerH - (cents / max.value) * innerH;
}

const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => f * max.value));

function short(cents: number): string {
    const dollars = cents / 100;

    if (dollars >= 1_000_000) {
        return `$${(dollars / 1_000_000).toFixed(1)}M`;
    }

    if (dollars >= 1_000) {
        return `$${(dollars / 1_000).toFixed(dollars >= 10_000 ? 0 : 1)}k`;
    }

    return `$${Math.round(dollars)}`;
}

const hovered = ref<Point | null>(null);
const tooltipPos = ref({ x: 0, y: 0 });

function enter(point: Point) {
    hovered.value = point;
    tooltipPos.value = { x: x(point.spend_cents), y: y(point.revenue_cents) };
}
</script>

<template>
    <div class="relative">
        <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" aria-label="Spend versus attributed revenue per deal">
            <!-- Grid (recessive) -->
            <g>
                <line
                    v-for="tick in ticks"
                    :key="`gy-${tick}`"
                    :x1="M.left"
                    :x2="W - M.right"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    class="stroke-border/60"
                    stroke-width="1"
                />
            </g>

            <!-- Break-even diagonal: above it a deal earned more than it cost -->
            <line
                :x1="x(0)"
                :y1="y(0)"
                :x2="x(max)"
                :y2="y(max)"
                class="stroke-muted-foreground/40"
                stroke-width="1"
                stroke-dasharray="4 4"
            />

            <!-- Axis labels -->
            <g class="fill-muted-foreground text-[10px]">
                <text
                    v-for="tick in ticks"
                    :key="`ty-${tick}`"
                    :x="M.left - 6"
                    :y="y(tick) + 3"
                    text-anchor="end"
                >
                    {{ short(tick) }}
                </text>
                <text
                    v-for="tick in ticks"
                    :key="`tx-${tick}`"
                    :x="x(tick)"
                    :y="H - M.bottom + 14"
                    text-anchor="middle"
                >
                    {{ short(tick) }}
                </text>
                <text :x="M.left + innerW / 2" :y="H - 4" text-anchor="middle" class="font-medium">Spend</text>
                <text
                    :x="12"
                    :y="M.top + innerH / 2"
                    text-anchor="middle"
                    class="font-medium"
                    :transform="`rotate(-90, 12, ${M.top + innerH / 2})`"
                >
                    Revenue
                </text>
            </g>

            <!-- Marks (surface ring separates overlapping dots) -->
            <g>
                <circle
                    v-for="(point, i) in points"
                    :key="`dot-${i}`"
                    :cx="x(point.spend_cents)"
                    :cy="y(point.revenue_cents)"
                    r="4.5"
                    class="fill-blue-500 stroke-background"
                    stroke-width="1.5"
                    :opacity="hovered && hovered !== point ? 0.4 : 0.9"
                />
            </g>

            <!-- Oversized hit targets -->
            <g>
                <circle
                    v-for="(point, i) in points"
                    :key="`hit-${i}`"
                    :cx="x(point.spend_cents)"
                    :cy="y(point.revenue_cents)"
                    r="12"
                    fill="transparent"
                    @mouseenter="enter(point)"
                    @mouseleave="hovered = null"
                />
            </g>
        </svg>

        <!-- Tooltip -->
        <div
            v-if="hovered"
            class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-md border bg-popover px-3 py-2 text-xs shadow-md"
            :style="{
                left: `${(tooltipPos.x / W) * 100}%`,
                top: `${(tooltipPos.y / H) * 100}%`,
                transform: 'translate(-50%, -120%)',
            }"
        >
            <p class="font-medium">{{ hovered.label }}</p>
            <p class="text-muted-foreground">Spend: {{ formatCents(hovered.spend_cents) }}</p>
            <p class="text-muted-foreground">Revenue: {{ formatCents(hovered.revenue_cents) }}</p>
        </div>
    </div>
</template>
