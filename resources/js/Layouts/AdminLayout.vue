<script setup>
import AppLayout from './AppLayout.vue'

defineProps({
    title: String,
})
</script>

<template>
    <AppLayout :title="title">
        <template #header>
            <slot name="header" />
        </template>
        <slot />
    </AppLayout>
</template>
