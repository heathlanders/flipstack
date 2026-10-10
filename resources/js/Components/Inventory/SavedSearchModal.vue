<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'save']);

const searchName = ref('');
const error = ref('');

watch(() => props.show, (newVal) => {
    if (newVal) {
        searchName.value = '';
        error.value = '';
    }
});

const handleSave = () => {
    if (!searchName.value.trim()) {
        error.value = 'Please enter a name for this search.';
        return;
    }
    
    emit('save', searchName.value.trim());
};

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                    @click="handleClose"
                ></div>

                <!-- Modal -->
                <div class="relative z-10 w-full max-w-md p-6 mx-auto bg-white rounded-lg shadow-xl dark:bg-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Save Search
                        </h3>
                        <button
                            @click="handleClose"
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Save your current filters and search query for quick access later.
                    </p>

                    <div class="mb-4">
                        <label for="search-name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Search Name
                        </label>
                        <input
                            id="search-name"
                            v-model="searchName"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="e.g., Low stock electronics"
                            @keyup.enter="handleSave"
                        />
                        <p v-if="error" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ error }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            @click="handleClose"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleSave"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700"
                        >
                            Save Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
