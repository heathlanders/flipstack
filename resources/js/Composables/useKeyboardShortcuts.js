import { ref, onMounted, onUnmounted } from 'vue'

/**
 * Composable for managing keyboard shortcuts
 * Handles platform-specific modifiers (Cmd on Mac, Ctrl on Windows/Linux)
 */
export function useKeyboardShortcuts() {
    const registeredShortcuts = ref(new Map())
    const isMac = ref(false)

    // Detect platform on mount
    onMounted(() => {
        isMac.value = navigator.platform.toLowerCase().includes('mac')
        document.addEventListener('keydown', handleKeyDown)
    })

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeyDown)
        registeredShortcuts.value.clear()
    })

    /**
     * Handle keydown events and trigger registered shortcuts
     */
    const handleKeyDown = (event) => {
        // Skip if user is typing in an input, textarea, or contenteditable
        const target = event.target
        if (
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable
        ) {
            // Allow specific shortcuts even in inputs (like Cmd+K)
            const shortcutKey = getShortcutKey(event)
            if (!shortcutKey) return
            const shortcut = registeredShortcuts.value.get(shortcutKey)
            if (!shortcut?.allowInInput) {
                return
            }
        }

        const shortcutKey = getShortcutKey(event)
        if (!shortcutKey) return
        const shortcut = registeredShortcuts.value.get(shortcutKey)

        if (shortcut) {
            event.preventDefault()
            event.stopPropagation()
            shortcut.callback(event)
        }
    }

    /**
     * Generate a unique key for a keyboard event
     */
    const getShortcutKey = (event) => {
        if (!event.key) return null

        const parts = []

        if (event.ctrlKey) parts.push('ctrl')
        if (event.metaKey) parts.push('meta')
        if (event.altKey) parts.push('alt')
        if (event.shiftKey) parts.push('shift')

        // Normalize the key
        let key = event.key.toLowerCase()
        if (key === ' ') key = 'space'

        parts.push(key)
        return parts.join('+')
    }

    /**
     * Register a keyboard shortcut
     * @param {Object} options - Shortcut configuration
     * @param {string} options.key - The key to listen for (e.g., 'k', 'n', 'escape')
     * @param {boolean} options.meta - Require Cmd (Mac) or Ctrl (Windows/Linux)
     * @param {boolean} options.ctrl - Require Ctrl key
     * @param {boolean} options.alt - Require Alt key
     * @param {boolean} options.shift - Require Shift key
     * @param {Function} options.callback - Function to call when shortcut is triggered
     * @param {boolean} options.allowInInput - Allow shortcut even when typing in inputs
     */
    const registerShortcut = (options) => {
        const { key, meta = false, ctrl = false, alt = false, shift = false, callback, allowInInput = false } = options

        const parts = []

        // Handle platform-specific modifiers
        if (meta) {
            // On Mac, use meta (Cmd), on others use ctrl
            if (isMac.value) {
                parts.push('meta')
            } else {
                parts.push('ctrl')
            }
        }
        if (ctrl) parts.push('ctrl')
        if (alt) parts.push('alt')
        if (shift) parts.push('shift')

        parts.push(key.toLowerCase())
        const shortcutKey = parts.join('+')

        registeredShortcuts.value.set(shortcutKey, {
            callback,
            allowInInput
        })

        // Return unregister function
        return () => {
            registeredShortcuts.value.delete(shortcutKey)
        }
    }

    /**
     * Unregister a keyboard shortcut
     */
    const unregisterShortcut = (key, modifiers = {}) => {
        const { meta = false, ctrl = false, alt = false, shift = false } = modifiers

        const parts = []
        if (meta) {
            if (isMac.value) {
                parts.push('meta')
            } else {
                parts.push('ctrl')
            }
        }
        if (ctrl) parts.push('ctrl')
        if (alt) parts.push('alt')
        if (shift) parts.push('shift')
        parts.push(key.toLowerCase())

        const shortcutKey = parts.join('+')
        registeredShortcuts.value.delete(shortcutKey)
    }

    /**
     * Get the display string for a shortcut (e.g., "Cmd+K" or "Ctrl+K")
     */
    const getShortcutDisplay = (key, modifiers = {}) => {
        const { meta = false, ctrl = false, alt = false, shift = false } = modifiers

        const parts = []

        if (meta) {
            parts.push(isMac.value ? 'Cmd' : 'Ctrl')
        }
        if (ctrl) parts.push('Ctrl')
        if (alt) parts.push(isMac.value ? 'Option' : 'Alt')
        if (shift) parts.push('Shift')

        parts.push(key.toUpperCase())

        return parts.join('+')
    }

    return {
        isMac,
        registerShortcut,
        unregisterShortcut,
        getShortcutDisplay
    }
}
