<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Array,
    credentials: Array,
});

const form = useForm({
    product_id: '',
    marketplace: '',
    integration_credential_id: '',
    scheduled_at: '',
    listing_data: '{}',
});

function submit() {
    form.post(route('marketplaces.scheduled-listings.store'));
}
</script>

<template>
    <AppLayout title="Schedule New Listing">
        <Head title="Schedule New Listing" />

        <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Schedule New Listing</h1>
                <Link
                    :href="route('marketplaces.scheduled-listings.index')"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                >
                    Back to Scheduled Listings
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Product -->
                    <div>
                        <label for="product_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product</label>
                        <select
                            id="product_id"
                            v-model="form.product_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Select a product</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} ({{ product.sku }})
                            </option>
                        </select>
                        <p v-if="form.errors.product_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.product_id }}</p>
                    </div>

                    <!-- Marketplace -->
                    <div>
                        <label for="marketplace" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Marketplace</label>
                        <select
                            id="marketplace"
                            v-model="form.marketplace"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Select a marketplace</option>
                            <option value="ebay">eBay</option>
                            <option value="shopify">Shopify</option>
                            <option value="amazon">Amazon</option>
                        </select>
                        <p v-if="form.errors.marketplace" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.marketplace }}</p>
                    </div>

                    <!-- Integration Credential -->
                    <div>
                        <label for="integration_credential_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Integration Credential</label>
                        <select
                            id="integration_credential_id"
                            v-model="form.integration_credential_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Select a credential</option>
                            <option v-for="cred in credentials" :key="cred.id" :value="cred.id">
                                {{ cred.label ?? cred.platform }} (ID: {{ cred.id }})
                            </option>
                        </select>
                        <p v-if="form.errors.integration_credential_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.integration_credential_id }}</p>
                    </div>

                    <!-- Scheduled At -->
                    <div>
                        <label for="scheduled_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Scheduled At</label>
                        <input
                            id="scheduled_at"
                            type="datetime-local"
                            v-model="form.scheduled_at"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                        <p v-if="form.errors.scheduled_at" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.scheduled_at }}</p>
                    </div>

                    <!-- Listing Data (JSON) -->
                    <div>
                        <label for="listing_data" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Listing Data (JSON)</label>
                        <textarea
                            id="listing_data"
                            v-model="form.listing_data"
                            rows="8"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                            placeholder='{"price": "29.99", "quantity": 5}'
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter listing configuration as valid JSON.</p>
                        <p v-if="form.errors.listing_data" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.listing_data }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <Link
                            :href="route('marketplaces.scheduled-listings.index')"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition disabled:opacity-50"
                        >
                            Schedule Listing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
