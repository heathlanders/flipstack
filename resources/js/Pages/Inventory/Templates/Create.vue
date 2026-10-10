<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    name: '',
    description: '',
    platform: 'ebay',
    description_template: '',
    pricing_strategy: '',
    markup_percentage: '',
    fixed_markup_amount: '',
    shipping_template: null,
    return_policy_template: null,
    payment_template: null,
    default_item_specifics: null,
    default_categories: null,
});

const submit = () => {
    form.post(route('inventory.templates.store'));
};
</script>

<template>
    <AppLayout title="Create Template">
        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="flex items-center mb-6">
                    <button @click="$inertia.visit(route('inventory.templates.index'))" class="mr-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Template</h1>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Info -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Basic Information</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Template Name</label>
                                <TextInput v-model="form.name" type="text" class="mt-1 w-full" required placeholder="e.g., Electronics - New Items" />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" placeholder="What is this template for?"></textarea>
                                <InputError :message="form.errors.description" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Platform</label>
                                <select v-model="form.platform" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    <option value="ebay">eBay</option>
                                    <option value="amazon">Amazon</option>
                                    <option value="all">All Platforms</option>
                                </select>
                                <InputError :message="form.errors.platform" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Rules -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Pricing Rules</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pricing Strategy</label>
                                <select v-model="form.pricing_strategy" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    <option value="">None</option>
                                    <option value="percentage">Percentage Markup</option>
                                    <option value="fixed_markup">Fixed Markup</option>
                                    <option value="competitive">Competitive</option>
                                </select>
                                <InputError :message="form.errors.pricing_strategy" class="mt-2" />
                            </div>
                            <div v-if="form.pricing_strategy === 'percentage'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Markup Percentage (%)</label>
                                <TextInput v-model="form.markup_percentage" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="e.g., 30" />
                                <InputError :message="form.errors.markup_percentage" class="mt-2" />
                            </div>
                            <div v-if="form.pricing_strategy === 'fixed_markup'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fixed Markup Amount ($)</label>
                                <TextInput v-model="form.fixed_markup_amount" type="number" step="0.01" min="0" class="mt-1 w-full" placeholder="e.g., 10.00" />
                                <InputError :message="form.errors.fixed_markup_amount" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Description Template -->
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Description Template</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">HTML Description Template</label>
                            <textarea v-model="form.description_template" rows="6" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 font-mono text-sm" placeholder="Use {{product_name}}, {{description}}, {{condition}} as placeholders"></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Available placeholders: {{product_name}}, {{description}}, {{condition}}, {{brand}}, {{sku}}
                            </p>
                            <InputError :message="form.errors.description_template" class="mt-2" />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3">
                        <SecondaryButton type="button" @click="$inertia.visit(route('inventory.templates.index'))">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create Template</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
