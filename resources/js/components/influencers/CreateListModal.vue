<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, useSlots } from 'vue';
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
import { store } from '@/routes/influencers/lists';

type Props = {
    preserveOnSuccess?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    preserveOnSuccess: false,
});

const emit = defineEmits<{
    created: [];
}>();

const open = defineModel<boolean>('open', { default: false });
const page = usePage();
const slots = useSlots();

const form = useForm({
    name: '',
    description: '',
});

function submit() {
    form.post(store(page.props.currentTeam!.slug).url, {
        preserveScroll: props.preserveOnSuccess,
        preserveState: props.preserveOnSuccess,
        onSuccess: () => {
            open.value = false;
            form.reset();
            emit('created');
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="slots.default" as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Create Influencer List</DialogTitle>
                <DialogDescription>
                    Create a new list to organize influencers for campaigns and outreach.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="list-name">Name</Label>
                    <Input
                        id="list-name"
                        v-model="form.name"
                        placeholder="e.g., Q2 Campaign, Summer Launch"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="list-description">Description (optional)</Label>
                    <Input
                        id="list-description"
                        v-model="form.description"
                        placeholder="Brief description of this list's purpose"
                    />
                    <p v-if="form.errors.description" class="text-sm text-destructive">
                        {{ form.errors.description }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        Create List
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
