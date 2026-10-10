<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    name: '',
    sku: '',
    barcode: '',
    cost_price: '',
    description: '',
    images: [],
});

const imagePreviewUrls = ref([]);

const handleImageUpload = (event) => {
    const files = Array.from(event.target.files);
    const maxFiles = 5;
    const currentCount = form.images.length;
    const remaining = maxFiles - currentCount;

    if (remaining <= 0) {
        return;
    }

    const newFiles = files.slice(0, remaining);
    form.images = [...form.images, ...newFiles];

    newFiles.forEach((file) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreviewUrls.value.push(e.target.result);
        };
        reader.readAsDataURL(file);
    });
};

const removeImage = (index) => {
    form.images.splice(index, 1);
    imagePreviewUrls.value.splice(index, 1);
};

const submitForm = () => {
    form.post(route('supplier.products.store'), {
        forceFormData: true,
    });
};
</script>

<template>
    <AppLayout title="Submit Product">
        <Head title="Submit Product" />

        <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Submit Product for Review</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Submit a new product for the organization to review and add to inventory.
                </p>
            </div>

            <form @submit.prevent="submitForm" class="space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-6">
                    <!-- Product Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Product Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                            placeholder="Enter product name"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- SKU -->
                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            SKU
                        </label>
                        <input
                            id="sku"
                            v-model="form.sku"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                            placeholder="Enter SKU (optional)"
                        />
                        <p v-if="form.errors.sku" class="mt-1 text-sm text-red-600">{{ form.errors.sku }}</p>
                    </div>

                    <!-- Barcode -->
                    <div>
                        <label for="barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Barcode (UPC/EAN)
                        </label>
                        <input
                            id="barcode"
                            v-model="form.barcode"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                            placeholder="Enter barcode (optional)"
                        />
                        <p v-if="form.errors.barcode" class="mt-1 text-sm text-red-600">{{ form.errors.barcode }}</p>
                    </div>

                    <!-- Cost Price -->
                    <div>
                        <label for="cost_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Cost Price ($)
                        </label>
                        <input
                            id="cost_price"
                            v-model="form.cost_price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                            placeholder="0.00"
                        />
                        <p v-if="form.errors.cost_price" class="mt-1 text-sm text-red-600">{{ form.errors.cost_price }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                            placeholder="Product description, condition notes, etc."
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <!-- Images -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Product Images (max 5)
                        </label>
                        <div class="mt-2">
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                :disabled="form.images.length >= 5"
                                class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-gray-700 dark:file:text-gray-300"
                                @change="handleImageUpload"
                            />
                        </div>
                        <p v-if="form.errors.images" class="mt-1 text-sm text-red-600">{{ form.errors.images }}</p>

                        <!-- Image previews -->
                        <div v-if="imagePreviewUrls.length > 0" class="mt-4 grid grid-cols-5 gap-3">
                            <div
                                v-for="(url, index) in imagePreviewUrls"
                                :key="index"
                                class="relative"
                            >
                                <img :src="url" class="w-full h-24 object-cover rounded-md border border-gray-200 dark:border-gray-600" />
                                <button
                                    type="button"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600"
                                    @click="removeImage(index)"
                                >
                                    X
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Submitting...</span>
                        <span v-else>Submit Product</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
