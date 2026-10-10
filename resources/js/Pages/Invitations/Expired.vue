<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

const props = defineProps({
    message: String,
    organization: Object,
    reason: {
        type: String,
        default: 'invalid', // 'invalid', 'expired', 'accepted'
    },
});

const stateConfig = computed(() => {
    switch (props.reason) {
        case 'expired':
            return {
                icon: 'clock',
                iconBgColor: 'bg-amber-100 dark:bg-amber-900/30',
                iconColor: 'text-amber-500',
                title: 'Invitation Expired',
                description: 'This invitation link has expired.',
                guidance: 'Request a new invitation from your organization administrator to gain access.',
            };
        case 'accepted':
            return {
                icon: 'check',
                iconBgColor: 'bg-green-100 dark:bg-green-900/30',
                iconColor: 'text-green-500',
                title: 'Already Accepted',
                description: 'This invitation has already been accepted.',
                guidance: 'You can sign in to access your account, or contact the administrator if you need assistance.',
            };
        case 'invalid':
        default:
            return {
                icon: 'x',
                iconBgColor: 'bg-red-100 dark:bg-red-900/30',
                iconColor: 'text-red-500',
                title: 'Invalid Invitation',
                description: 'This invitation link is not valid.',
                guidance: 'The link may have been copied incorrectly or the invitation may have been cancelled.',
            };
    }
});
</script>

<template>
    <Head title="Invitation Unavailable" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="text-center">
            <!-- Dynamic Icon based on reason -->
            <div class="mb-6">
                <div :class="[stateConfig.iconBgColor, 'mx-auto h-16 w-16 rounded-full flex items-center justify-center']">
                    <!-- Clock icon for expired -->
                    <svg v-if="stateConfig.icon === 'clock'" :class="[stateConfig.iconColor, 'h-8 w-8']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- Check icon for accepted -->
                    <svg v-else-if="stateConfig.icon === 'check'" :class="[stateConfig.iconColor, 'h-8 w-8']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- X icon for invalid -->
                    <svg v-else :class="[stateConfig.iconColor, 'h-8 w-8']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                {{ stateConfig.title }}
            </h2>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                {{ message || stateConfig.description }}
            </p>

            <!-- Guidance Box -->
            <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-left">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ stateConfig.guidance }}
                    </p>
                </div>
            </div>

            <!-- Organization-specific message -->
            <div v-if="organization" class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <template v-if="reason === 'expired'">
                        Contact the administrator at <strong>{{ organization.name }}</strong> to request a new invitation.
                    </template>
                    <template v-else-if="reason === 'accepted'">
                        You're already a member of <strong>{{ organization.name }}</strong>. Sign in to continue.
                    </template>
                    <template v-else>
                        If you were expecting an invitation to <strong>{{ organization.name }}</strong>, please contact the organization administrator.
                    </template>
                </p>
            </div>

            <div class="space-y-3">
                <Link
                    :href="route('login')"
                    class="block w-full px-4 py-2 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white transition ease-in-out duration-150 text-center"
                >
                    Go to Login
                </Link>

                <Link
                    v-if="reason !== 'accepted'"
                    :href="route('register')"
                    class="block w-full px-4 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150 text-center"
                >
                    Create New Account
                </Link>
            </div>
        </div>
    </AuthenticationCard>
</template>
