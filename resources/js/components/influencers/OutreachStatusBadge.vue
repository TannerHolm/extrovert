<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { OutreachStatus } from '@/types';

defineProps<{
    status: OutreachStatus;
    label: string;
    color: string;
}>();

const colorClasses: Record<string, string> = {
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
};
</script>

<template>
    <Badge :class="colorClasses[color] || colorClasses.gray" variant="secondary">
        {{ label }}
    </Badge>
</template>
