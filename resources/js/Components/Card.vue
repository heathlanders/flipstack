<script setup>
defineProps({
    title: String,
    subtitle: String,
    padding: { type: Boolean, default: true }
})
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200/60 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <slot name="header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
            </slot>
        </div>
        <div :class="padding ? 'p-6' : ''"><slot /></div>
        <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800">
            <slot name="footer" />
        </div>
    </div>
</template>
