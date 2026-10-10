<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StockMovementForm from '@/Components/Inventory/StockMovementForm.vue';

const props = defineProps({
    locations: Array,
    reasonCodes: Array,
    movementTypes: Array,
    preselectedProduct: Object,
    permissions: Object,
});

const showSuccess = ref(false);

const handleSuccess = () => {
    showSuccess.value = true;
    setTimeout(() => {
        router.visit(route('inventory.movements.index'));
    }, 1500);
};

const handleCancel = () => {
    router.visit(route('inventory.movements.index'));
};
</script>

<template>
    <AppLayout title="Create Stock Movement">
        <template #header>
            <div class="flex items-center gap-4">
                <button
                    @click="handleCancel"
                    class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </button>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Create Stock Movement
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Record a new inventory movement
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Success Message -->
                <div
                    v-if="showSuccess"
                    class="mb-6 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl flex items-center gap-3"
                >
                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-green-700 dark:text-green-300 font-medium">
                        Movement recorded successfully! Redirecting...
                    </span>
                </div>

                <!-- Form -->
                <StockMovementForm
                    :locations="locations"
                    :reason-codes="reasonCodes"
                    :movement-types="movementTypes"
                    :preselected-product="preselectedProduct"
                    :permissions="permissions"
                    @success="handleSuccess"
                    @cancel="handleCancel"
                />

                <!-- Tips -->
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
                    <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-2">
                        Movement Type Guide
                    </h4>
                    <ul class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
                        <li><strong>Purchase:</strong> Stock received from supplier</li>
                        <li><strong>Sale:</strong> Stock sold to customer</li>
                        <li><strong>Adjustment:</strong> Manual correction (damaged, found, miscounts)</li>
                        <li><strong>Transfer In/Out:</strong> Move between locations</li>
                        <li><strong>Return:</strong> Customer returned items</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
