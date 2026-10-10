<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    supplier: { type: Object, default: null },
    support_email: { type: String, default: null },
});
</script>

<template>
    <Head title="Account Inactive" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4">
        <div class="max-w-lg w-full bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/40 mb-4">
                <InformationCircleIcon class="h-7 w-7 text-yellow-600 dark:text-yellow-400" />
            </div>

            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Account Inactive</h1>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Your supplier account is currently inactive. Contact support to reactivate it.
            </p>

            <div v-if="supplier?.status_reason" class="mt-4 p-3 bg-gray-50 dark:bg-gray-900/40 rounded text-left text-sm text-gray-700 dark:text-gray-300">
                <strong class="block text-gray-900 dark:text-white mb-1">Reason</strong>
                {{ supplier.status_reason }}
            </div>

            <a
                v-if="support_email"
                :href="`mailto:${support_email}`"
                class="mt-6 inline-block text-blue-600 dark:text-blue-400 hover:underline text-sm"
            >
                {{ support_email }}
            </a>

            <div class="mt-6">
                <Link href="/logout" method="post" as="button" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                    Sign out
                </Link>
            </div>
        </div>
    </div>
</template>
