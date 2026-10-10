<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 border border-transparent rounded-lg font-medium text-sm text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:bg-primary-800 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
