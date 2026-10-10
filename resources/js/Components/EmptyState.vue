<script setup>
defineProps({
    title: { type: String, default: 'No data found' },
    description: { type: String, default: '' },
    icon: { type: String, default: null }
})
</script>

<template>
    <div class="text-center py-12">
        <div v-if="icon" class="mx-auto h-12 w-12 text-gray-400 mb-4">
            <component :is="icon" class="h-12 w-12" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ title }}</h3>
        <p v-if="description" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
        <div class="mt-6"><slot /></div>
    </div>
</template>
