<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VerificationWizard from '@/Components/IntakeQueue/VerificationWizard.vue';

const props = defineProps({
    queueItem: Object,
    nextItemId: Number,
    locations: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout title="Process Queue Item">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Process Queue Item #{{ queueItem.id }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ queueItem.barcode ? `UPC: ${queueItem.barcode}` : 'No barcode' }}
                        • Captured by {{ queueItem.captured_by }}
                        • {{ new Date(queueItem.captured_at).toLocaleDateString() }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="nextItemId"
                        :href="route('intake-queue.show', nextItemId)"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md text-sm font-medium transition"
                    >
                        Skip to Next →
                    </Link>
                    <Link
                        :href="route('intake-queue.index')"
                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 text-sm font-medium"
                    >
                        ← Back to Queue
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <VerificationWizard :queue-item="queueItem" :locations="locations" />
            </div>
        </div>
    </AppLayout>
</template>
