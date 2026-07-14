<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import BacklogApiKeyInput from '@/Components/BacklogApiKeyInput.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import AppFooter from '@/Components/AppFooter.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="flex min-h-screen flex-col bg-gray-100 dark:bg-gray-900">
            <nav class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('notifications.index')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto text-gray-800 dark:text-gray-100"
                                    />
                                </Link>
                            </div>

                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('daily-hours.index')"
                                    :active="
                                        route().current('daily-hours.*')
                                    "
                                >
                                    Daily Tracker
                                </NavLink>
                                <NavLink
                                    :href="route('notifications.index')"
                                    :active="
                                        route().current('notifications.*')
                                    "
                                >
                                    Notifications
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden items-center gap-3 sm:ms-6 sm:flex">
                            <BacklogApiKeyInput />
                            <ThemeToggle />

                            <Dropdown
                                align="right"
                                width="48"
                                content-classes="py-1 bg-white dark:bg-gray-800"
                            >
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-gray-100"
                                        :class="{
                                            'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-300':
                                                route().current('project-settings.*'),
                                        }"
                                        aria-label="Settings menu"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.04a1 1 0 01-.205 1.212l-1.27 1.27a6.993 6.993 0 010 2.778l1.27 1.27a1 1 0 01.205 1.212l-1.18 2.04a1 1 0 01-1.186.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.331 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.04a1 1 0 01.205-1.212l1.27-1.27a6.993 6.993 0 010-2.778l-1.27-1.27a1 1 0 01-.205-1.212l1.18-2.04a1 1 0 011.186-.447l1.598.54A6.993 6.993 0 017.51 3.456l.331-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <span class="hidden md:inline">Settings</span>
                                    </button>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('project-settings.index')">
                                        Project settings
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <div class="-me-2 flex items-center gap-2 sm:hidden">
                            <ThemeToggle />
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:hover:bg-gray-700 dark:hover:text-gray-300 dark:focus:bg-gray-700 dark:focus:text-gray-300"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('daily-hours.index')"
                            :active="
                                route().current('daily-hours.*')
                            "
                        >
                            Daily Tracker
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('notifications.index')"
                            :active="
                                route().current('notifications.*')
                            "
                        >
                            Notifications
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 px-4 pb-2 pt-3 dark:border-gray-700">
                        <p class="mb-1 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Settings
                        </p>
                        <ResponsiveNavLink
                            :href="route('project-settings.index')"
                            :active="
                                route().current('project-settings.*')
                            "
                        >
                            Project settings
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 px-4 pb-4 pt-4 dark:border-gray-700">
                        <BacklogApiKeyInput />
                    </div>
                </div>
            </nav>

            <header
                class="bg-white shadow dark:bg-gray-800 dark:shadow-none dark:ring-1 dark:ring-white/10"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1">
                <slot />
            </main>

            <AppFooter />
        </div>
    </div>
</template>
