<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    status: Number,
    message: String,
})
</script>

<template>
    <AppLayout title="Access Denied">
        <div class="py-16">
            <div class="max-w-lg mx-auto text-center">
                <div class="mb-8">
                    <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 dark:bg-red-900/30">
                        <i class="fas fa-lock text-red-600 dark:text-red-400 text-3xl"></i>
                    </span>
                </div>
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    403
                </h1>
                <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-4">
                    Access Denied
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-8">
                    {{ message || 'You do not have permission to access this resource. Contact your administrator if you believe this is an error.' }}
                </p>
                <div class="flex items-center justify-center gap-4">
                    <a
                        :href="route('dashboard')"
                        class="inline-flex items-center px-6 py-3 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-lg transition-colors"
                    >
                        <i class="fas fa-home mr-2"></i>
                        Go to Dashboard
                    </a>
                    <button
                        @click="$inertia.visit(window.history.back())"
                        class="inline-flex items-center px-6 py-3 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg transition-colors"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Go Back
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
