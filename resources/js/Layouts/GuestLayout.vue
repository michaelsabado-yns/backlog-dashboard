<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppFooter from '@/Components/AppFooter.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center bg-gray-100 pt-6 dark:bg-gray-900 sm:justify-center sm:pt-0"
    >
        <div class="absolute end-4 top-4 sm:end-6 sm:top-6">
            <ThemeToggle />
        </div>

        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 text-gray-500 dark:text-gray-400" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10 sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>

        <div class="mt-auto w-full pt-8">
            <AppFooter />
        </div>
    </div>
</template>
