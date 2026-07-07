<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, KanbanSquare, ListChecks, Search, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const features = [
    {
        icon: Search,
        title: 'Discover creators',
        description:
            'Search YouTube by keyword, then filter by follower count and engagement rate.',
    },
    {
        icon: ListChecks,
        title: 'Build targeted lists',
        description:
            'Save promising creators into shared, team-scoped lists in a single click — no duplicates.',
    },
    {
        icon: KanbanSquare,
        title: 'Track every outreach',
        description:
            'Move creators through your pipeline from contacted to confirmed on a drag-and-drop kanban board.',
    },
    {
        icon: Users,
        title: 'Collaborate as a team',
        description:
            'Invite teammates with roles and work the same pipeline together, all in one workspace.',
    },
];
</script>

<template>
    <Head title="Influencer discovery + CRM" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col px-6">
            <!-- Nav -->
            <header class="flex items-center justify-between py-6">
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </div>
                    <span class="text-lg font-semibold tracking-tight">Extrovert</span>
                </div>

                <nav class="flex items-center gap-2">
                    <template v-if="page.props.auth.user">
                        <Link :href="dashboardUrl">
                            <Button size="sm">Dashboard</Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">
                            <Button variant="ghost" size="sm">Log in</Button>
                        </Link>
                        <Link v-if="canRegister" :href="register()">
                            <Button size="sm">Get started</Button>
                        </Link>
                    </template>
                </nav>
            </header>

            <!-- Hero -->
            <section class="flex flex-col items-center py-16 text-center lg:py-24">
                <span
                    class="mb-5 inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    Influencer discovery + CRM
                </span>
                <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    Find the right creators. Close the deal.
                </h1>
                <p class="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
                    Extrovert helps your team discover creators on YouTube, save them to lists, and
                    track outreach through a pipeline — all in one place.
                </p>
                <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row">
                    <template v-if="page.props.auth.user">
                        <Link :href="dashboardUrl">
                            <Button size="lg" class="gap-2">
                                Go to dashboard
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link v-if="canRegister" :href="register()">
                            <Button size="lg" class="gap-2">
                                Get started free
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="login()">
                            <Button variant="outline" size="lg">Log in</Button>
                        </Link>
                    </template>
                </div>
            </section>

            <!-- Features -->
            <section class="grid gap-4 pb-20 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border p-5"
                >
                    <div
                        class="mb-4 flex size-9 items-center justify-center rounded-lg bg-muted text-foreground"
                    >
                        <component :is="feature.icon" class="h-5 w-5" />
                    </div>
                    <h3 class="mb-1.5 font-medium">{{ feature.title }}</h3>
                    <p class="text-sm text-muted-foreground">{{ feature.description }}</p>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-5xl flex-col items-center justify-between gap-2 px-6 py-6 text-sm text-muted-foreground sm:flex-row"
            >
                <span>&copy; {{ new Date().getFullYear() }} Extrovert</span>
                <span>Discover · Save · Track</span>
            </div>
        </footer>
    </div>
</template>
