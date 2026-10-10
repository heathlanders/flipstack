<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2 bg-red-600 hover:bg-red-700 border border-transparent rounded-lg font-medium text-sm text-white shadow-sm active:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
