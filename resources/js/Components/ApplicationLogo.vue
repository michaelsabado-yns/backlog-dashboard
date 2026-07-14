<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        class="fill-none"
        aria-hidden="true"
    >
        <!-- Soft square frame (fill-none so fill-current on the parent cannot solid-fill this) -->
        <rect
            class="fill-none"
            x="1.75"
            y="1.75"
            width="36.5"
            height="36.5"
            rx="10"
            stroke="currentColor"
            stroke-width="2.5"
        />
        <!-- Backlog / issue stack -->
        <rect
            class="fill-current opacity-35"
            x="10"
            y="11"
            width="20"
            height="4.25"
            rx="2.125"
        />
        <rect
            class="fill-current opacity-65"
            x="10"
            y="18"
            width="16.5"
            height="4.25"
            rx="2.125"
        />
        <rect
            class="fill-current"
            x="10"
            y="25"
            width="12.5"
            height="4.25"
            rx="2.125"
        />
        <!-- Notification accent -->
        <circle class="fill-current" cx="30.5" cy="9.5" r="4.75" />
    </svg>
</template>
