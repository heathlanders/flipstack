<script setup>
import { computed } from 'vue'
const props = defineProps({
    type: { type: String, default: 'info' },
    dismissible: { type: Boolean, default: false }
})
const emit = defineEmits(['dismiss'])
const typeClasses = {
    info: 'bg-blue-50 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    success: 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    warning: 'bg-yellow-50 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    error: 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-300',
}
</script>

<template>
    <div class="rounded-lg p-4" :class="typeClasses[type]">
        <div class="flex">
            <div class="flex-1"><slot /></div>
            <button v-if="dismissible" @click="emit('dismiss')" class="ml-4 opacity-70 hover:opacity-100">&times;</button>
        </div>
    </div>
</template>
