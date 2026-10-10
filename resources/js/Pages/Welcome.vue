<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="FlipStack - Reselling Platform" />

    <div class="min-h-screen bg-white dark:bg-gray-900">
        <!-- Navigation -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center gap-2">
                        <svg class="size-9" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#3b82f6" />
                            <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#3b82f6" opacity="0.7" />
                        </svg>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">FlipStack</span>
                    </Link>

                    <!-- Nav links -->
                    <div v-if="canLogin" class="flex items-center gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                            >
                                Sign in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
                            >
                                Get Started
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden">
            <!-- Background decorations -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/10 rounded-full blur-3xl"></div>
                <div class="absolute top-60 -left-40 w-96 h-96 bg-primary-400/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 right-1/4 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                    <!-- Text content -->
                    <div class="text-center lg:text-left">
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 dark:text-white leading-tight">
                            The All-in-One Platform for
                            <span class="text-primary-600 dark:text-primary-400">Reselling Success</span>
                        </h1>
                        <p class="mt-6 text-lg sm:text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto lg:mx-0">
                            Manage inventory, list products across multiple platforms, and scale your reselling business with powerful tools built for teams.
                        </p>
                        <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-white bg-primary-600 rounded-xl hover:bg-primary-700 transition-all hover:scale-105 shadow-lg shadow-primary-500/25"
                            >
                                Start Free Trial
                                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </Link>
                            <Link
                                :href="route('login')"
                                class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-all"
                            >
                                Sign In
                            </Link>
                        </div>
                    </div>

                    <!-- Dashboard mockup -->
                    <div class="mt-16 lg:mt-0">
                        <div class="relative">
                            <!-- Browser chrome -->
                            <div class="bg-gray-900 rounded-t-xl p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                    <div class="flex-1 ml-4">
                                        <div class="bg-gray-700 rounded-md px-3 py-1 text-xs text-gray-400 max-w-xs">
                                            app.flipstack.com/dashboard
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Dashboard content -->
                            <div class="bg-gray-100 dark:bg-gray-800 rounded-b-xl p-4 shadow-2xl">
                                <!-- Stats row -->
                                <div class="grid grid-cols-3 gap-3 mb-4">
                                    <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Active Listings</div>
                                        <div class="text-lg font-bold text-gray-900 dark:text-white">2,847</div>
                                        <div class="text-xs text-emerald-600">+12.5%</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Revenue</div>
                                        <div class="text-lg font-bold text-gray-900 dark:text-white">$48.2K</div>
                                        <div class="text-xs text-emerald-600">+8.3%</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-700 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Orders</div>
                                        <div class="text-lg font-bold text-gray-900 dark:text-white">384</div>
                                        <div class="text-xs text-emerald-600">+24.1%</div>
                                    </div>
                                </div>
                                <!-- Chart placeholder -->
                                <div class="bg-white dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Sales Overview</span>
                                        <span class="text-xs text-gray-500">Last 7 days</span>
                                    </div>
                                    <div class="flex items-end justify-between gap-2 h-24">
                                        <div class="w-full bg-primary-200 dark:bg-primary-900 rounded-t" style="height: 40%"></div>
                                        <div class="w-full bg-primary-200 dark:bg-primary-900 rounded-t" style="height: 60%"></div>
                                        <div class="w-full bg-primary-200 dark:bg-primary-900 rounded-t" style="height: 45%"></div>
                                        <div class="w-full bg-primary-200 dark:bg-primary-900 rounded-t" style="height: 80%"></div>
                                        <div class="w-full bg-primary-200 dark:bg-primary-900 rounded-t" style="height: 65%"></div>
                                        <div class="w-full bg-primary-500 rounded-t" style="height: 90%"></div>
                                        <div class="w-full bg-primary-200 dark:bg-primary-900 rounded-t" style="height: 75%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-gray-50 dark:bg-gray-800/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white">
                        Everything you need to scale
                    </h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                        Powerful features designed specifically for reselling businesses of all sizes.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            Inventory Management
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Track stock levels, manage locations, and sync inventory across all your selling platforms in real-time.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900/30 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            Multi-Platform Listing
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Create listings once and publish to eBay, Amazon, Mercari, and more with optimized descriptions and pricing.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            Order Fulfillment
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Streamline shipping with batch label printing, automated tracking updates, and smart order routing.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white">
                        Get started in minutes
                    </h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">
                        Three simple steps to transform your reselling operation.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 relative">
                    <!-- Connecting line (desktop) -->
                    <div class="hidden md:block absolute top-12 left-1/6 right-1/6 h-0.5 bg-gradient-to-r from-primary-200 via-primary-400 to-primary-200 dark:from-primary-800 dark:via-primary-600 dark:to-primary-800"></div>

                    <!-- Step 1 -->
                    <div class="text-center relative">
                        <div class="w-24 h-24 bg-primary-600 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10">
                            <span class="text-3xl font-bold text-white">1</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            Connect Your Accounts
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Link your eBay, Amazon, and other marketplace accounts securely in just a few clicks.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="text-center relative">
                        <div class="w-24 h-24 bg-primary-600 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10">
                            <span class="text-3xl font-bold text-white">2</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            Import Your Inventory
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Bring in existing products or scan new items with our mobile app for instant cataloging.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="text-center relative">
                        <div class="w-24 h-24 bg-primary-600 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10">
                            <span class="text-3xl font-bold text-white">3</span>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
                            Start Selling Smarter
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            List products, track sales, and manage orders all from one powerful dashboard.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Integrations Section -->
        <section class="py-20 bg-gray-50 dark:bg-gray-800/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    Connect your favorite platforms
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-12 max-w-2xl mx-auto">
                    Seamlessly integrate with the marketplaces where your customers shop.
                </p>

                <div class="flex flex-wrap justify-center gap-6">
                    <!-- eBay -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl px-8 py-6 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-400">e</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white">eBay</span>
                    </div>

                    <!-- Amazon -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl px-8 py-6 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                            <span class="text-lg font-bold text-orange-600 dark:text-orange-400">a</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white">Amazon</span>
                    </div>

                    <!-- Mercari -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl px-8 py-6 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">M</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white">Mercari</span>
                    </div>

                    <!-- Poshmark -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl px-8 py-6 shadow-sm flex items-center gap-3">
                        <div class="w-10 h-10 bg-pink-100 dark:bg-pink-900/30 rounded-lg flex items-center justify-center">
                            <span class="text-lg font-bold text-pink-600 dark:text-pink-400">P</span>
                        </div>
                        <span class="font-semibold text-gray-900 dark:text-white">Poshmark</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 rounded-3xl p-12 lg:p-16 text-center relative overflow-hidden">
                    <!-- Decorative elements -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full blur-3xl"></div>

                    <div class="relative z-10">
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-6">
                            Ready to scale your reselling business?
                        </h2>
                        <p class="text-xl text-primary-100 mb-10 max-w-2xl mx-auto">
                            Join thousands of resellers who are saving time and increasing profits with FlipStack.
                        </p>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex items-center justify-center px-10 py-4 text-lg font-semibold text-primary-600 bg-white rounded-xl hover:bg-gray-100 transition-all hover:scale-105 shadow-lg"
                        >
                            Get Started Free
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 border-t border-gray-200 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <svg class="size-8" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#3b82f6" />
                            <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#3b82f6" opacity="0.7" />
                        </svg>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">FlipStack</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        &copy; {{ new Date().getFullYear() }} FlipStack. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
