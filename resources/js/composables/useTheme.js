import { router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Sync session-backed theme preference to the document root.
 */
export function useTheme() {
    const page = usePage();
    const theme = computed(() =>
        page.props.theme === 'dark' ? 'dark' : 'light',
    );
    const isDark = computed(() => theme.value === 'dark');

    const applyThemeClass = (value) => {
        document.documentElement.classList.toggle('dark', value === 'dark');
        document.documentElement.style.colorScheme =
            value === 'dark' ? 'dark' : 'light';
    };

    watch(
        theme,
        (value) => {
            applyThemeClass(value);
        },
        { immediate: true },
    );

    const setTheme = (next) => {
        if (next !== 'light' && next !== 'dark') {
            return;
        }

        applyThemeClass(next);

        router.post(
            route('theme.update'),
            { theme: next },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const toggleTheme = () => {
        setTheme(isDark.value ? 'light' : 'dark');
    };

    return {
        theme,
        isDark,
        setTheme,
        toggleTheme,
    };
}
