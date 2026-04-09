<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bookmark, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import CreateListModal from '@/components/influencers/CreateListModal.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { store } from '@/routes/influencers/entries';
import type { InfluencerListSummary, InfluencerSearchResult } from '@/types';

const props = defineProps<{
    lists: InfluencerListSummary[];
    influencer: InfluencerSearchResult;
}>();

const emit = defineEmits<{
    saved: [];
}>();

const page = usePage();
const saving = ref(false);
const showCreateModal = ref(false);

function saveToList(listId: number) {
    saving.value = true;
    router.post(
        store({ current_team: page.props.currentTeam!.slug, influencerList: listId }).url,
        props.influencer as any,
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                emit('saved');
            },
        },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" :disabled="saving">
                <Bookmark class="mr-1 h-4 w-4" />
                Save
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuLabel>Save to list</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="list in lists"
                :key="list.id"
                @click="saveToList(list.id)"
            >
                {{ list.name }}
                <span class="ml-auto text-xs text-muted-foreground">
                    {{ list.entries_count }}
                </span>
            </DropdownMenuItem>
            <template v-if="lists.length === 0">
                <DropdownMenuItem disabled>
                    No lists yet
                </DropdownMenuItem>
            </template>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="showCreateModal = true">
                <Plus class="mr-2 h-4 w-4" />
                New list
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <CreateListModal v-model:open="showCreateModal" preserve-on-success />
</template>
