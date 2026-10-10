import { ref, computed, watchEffect, onUnmounted } from 'vue'

const STORAGE_KEY = 'theme'

const theme = ref(localStorage.getItem(STORAGE_KEY) || 'system')

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')

function applyTheme() {
    const isDark = theme.value === 'dark' ||
        (theme.value === 'system' && mediaQuery.matches)

    document.documentElement.classList.toggle('dark', isDark)
}

function onSystemChange() {
    if (theme.value === 'system') {
        applyTheme()
    }
}

mediaQuery.addEventListener('change', onSystemChange)

// Apply on load
applyTheme()

const resolvedTheme = computed(() => {
    if (theme.value === 'system') {
        return mediaQuery.matches ? 'dark' : 'light'
    }
    return theme.value
})

function setTheme(value) {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
    applyTheme()
}

export function useTheme() {
    return {
        theme,
        resolvedTheme,
        setTheme,
    }
}
