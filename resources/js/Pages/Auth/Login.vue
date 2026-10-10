<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const isDevelopHost = computed(
    () => typeof window !== 'undefined'
        && window.location.hostname === 'develop.flipstack.app',
);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen flex">
        <!-- Left Panel - Branding (hidden on mobile) -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 relative overflow-hidden">
            <!-- Decorative blur shapes -->
            <div class="absolute top-20 -left-20 w-72 h-72 bg-primary-500/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-40 right-10 w-96 h-96 bg-primary-400/15 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-center px-12 xl:px-16">
                <!-- Logo -->
                <div class="mb-12">
                    <div class="flex items-center gap-3">
                        <svg class="size-12 text-white" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="currentColor" />
                            <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="currentColor" opacity="0.7" />
                        </svg>
                        <span class="text-3xl font-bold text-white">FlipStack</span>
                    </div>
                </div>

                <!-- Headline -->
                <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-6">
                    Streamline Your Reselling Business
                </h1>
                <p class="text-xl text-primary-100 mb-12 max-w-md">
                    The all-in-one platform to manage inventory, list products, and grow your reselling empire.
                </p>

                <!-- Feature highlights -->
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Real-Time Inventory Sync</h3>
                            <p class="text-primary-200 text-sm">Keep your stock levels updated across all platforms automatically.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Powerful Analytics</h3>
                            <p class="text-primary-200 text-sm">Track sales, margins, and performance with detailed insights.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">Team Collaboration</h3>
                            <p class="text-primary-200 text-sm">Work together with buyers, listers, and fulfillment staff.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="w-full lg:w-1/2 flex flex-col">
            <!-- Mobile header with gradient -->
            <div class="lg:hidden bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-8">
                <Link href="/" class="flex items-center gap-3">
                    <svg class="size-10 text-white" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="currentColor" />
                        <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="currentColor" opacity="0.7" />
                    </svg>
                    <span class="text-2xl font-bold text-white">FlipStack</span>
                </Link>
            </div>

            <!-- Form container -->
            <div class="flex-1 flex items-center justify-center px-6 py-12 bg-white dark:bg-gray-900">
                <div class="w-full max-w-md">
                    <!-- Desktop logo -->
                    <div class="hidden lg:block mb-10">
                        <Link href="/" class="flex items-center gap-3">
                            <svg class="size-12" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#3b82f6" />
                                <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#3b82f6" opacity="0.7" />
                            </svg>
                            <span class="text-2xl font-bold text-gray-900 dark:text-white">FlipStack</span>
                        </Link>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                        Welcome back
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-8">
                        Sign in to your account to continue
                    </p>

                    <div v-if="status" class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="email" value="Email address" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <Checkbox v-model:checked="form.remember" name="remember" />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 rounded"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <PrimaryButton
                            class="w-full justify-center py-3 text-base transition-transform hover:scale-[1.02]"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Sign in
                        </PrimaryButton>
                    </form>

                    <p class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400">
                        Don't have an account?
                        <Link
                            :href="route('register')"
                            class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                            Create one now
                        </Link>
                    </p>

                    <div
                        v-if="isDevelopHost"
                        class="mt-6 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-3 text-center"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300 mb-1">
                            Develop / QA
                        </p>
                        <a
                            href="/storage/downloads/flipstack-develop.apk"
                            class="text-sm font-medium text-amber-800 dark:text-amber-200 hover:underline"
                        >
                            Download FlipStack Dev APK (Android)
                        </a>
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                            Installs alongside the production app.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
