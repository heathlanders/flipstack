<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    apkAvailable: Boolean,
    apkUrl: String,
    fileSize: String,
    lastUpdated: String,
    version: String,
});

const isAndroid = ref(false);
const isIOS = ref(false);

onMounted(() => {
    const ua = navigator.userAgent.toLowerCase();
    isAndroid.value = ua.includes('android');
    isIOS.value = /iphone|ipad|ipod/.test(ua);
});
</script>

<template>
    <Head title="Download FlipStack Mobile App" />

    <div class="min-h-screen bg-white dark:bg-gray-900">
        <!-- Navigation -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <Link href="/" class="flex items-center gap-2">
                        <svg class="size-9" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#3b82f6" />
                            <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#3b82f6" opacity="0.7" />
                        </svg>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">FlipStack</span>
                    </Link>
                    <Link href="/login" class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                        Sign in
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Main content -->
        <div class="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
            <div class="max-w-lg mx-auto text-center">
                <!-- App icon -->
                <div class="mx-auto w-24 h-24 bg-primary-100 dark:bg-primary-900/30 rounded-3xl flex items-center justify-center mb-8">
                    <svg class="w-14 h-14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#3b82f6" />
                        <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#3b82f6" opacity="0.7" />
                    </svg>
                </div>

                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    FlipStack Mobile
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                    Scan barcodes, manage inventory, and pick orders from your phone.
                </p>

                <!-- iOS notice -->
                <div v-if="isIOS" class="mb-8 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        The mobile app is currently available for Android only. iOS support is coming soon.
                    </p>
                </div>

                <!-- Download card -->
                <div v-if="apkAvailable" class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-6 mb-8">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <svg class="w-6 h-6 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.523 2.238a.75.75 0 010 1.084l-2.09 2.09A8.226 8.226 0 0120.25 12c0 4.556-3.694 8.25-8.25 8.25S3.75 16.556 3.75 12c0-2.784 1.38-5.25 3.494-6.746l.014.014L5.168 3.178a.75.75 0 011.06-1.06l2.09 2.09A8.194 8.194 0 0112 3.75c1.39 0 2.71.344 3.865.953l2.09-2.09a.75.75 0 011.06 0l.508.625zM12 5.25a6.75 6.75 0 100 13.5 6.75 6.75 0 000-13.5z"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Android</span>
                    </div>

                    <a
                        :href="apkUrl"
                        class="inline-flex items-center justify-center w-full px-6 py-3 text-base font-semibold text-white bg-primary-600 rounded-xl hover:bg-primary-700 transition-colors gap-2"
                        :class="{ 'opacity-50 pointer-events-none': isIOS }"
                    >
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" />
                            <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                        </svg>
                        Download APK
                    </a>

                    <div class="mt-3 flex items-center justify-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="version">v{{ version }}</span>
                        <span v-if="fileSize">{{ fileSize }}</span>
                        <span v-if="lastUpdated">Updated {{ lastUpdated }}</span>
                    </div>
                </div>

                <!-- Not available yet -->
                <div v-else class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-6 mb-8">
                    <p class="text-gray-600 dark:text-gray-400">
                        The app download is not available yet. Check back soon.
                    </p>
                </div>

                <!-- Install instructions -->
                <div v-if="apkAvailable && !isIOS" class="text-left">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                        How to install
                    </h2>
                    <ol class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                            <span>Tap <strong class="text-gray-900 dark:text-white">Download APK</strong> above</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                            <span>When prompted, allow your browser to install apps from unknown sources</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                            <span>Open the downloaded file and tap <strong class="text-gray-900 dark:text-white">Install</strong></span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full flex items-center justify-center text-xs font-bold">4</span>
                            <span>Sign in with your FlipStack credentials</span>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</template>
