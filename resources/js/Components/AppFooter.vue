<script setup>
const year = new Date().getFullYear();
</script>

<template>
    <footer
        class="border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-1 px-4 py-4 text-center text-xs text-gray-500 sm:flex-row sm:text-start dark:text-gray-400 sm:px-6 lg:px-8"
        >
            <p>© {{ year }} All rights reserved, Michael Sabado.</p>
            <p>Built by Michael Sabado</p>
        </div>
    </footer>
</template>
