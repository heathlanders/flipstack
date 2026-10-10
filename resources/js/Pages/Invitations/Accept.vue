<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    invitation: Object,
    existingUser: Boolean,
});

const isOrgOwnerInvitation = computed(() => props.invitation.is_org_owner_invitation);

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
    terms: false,
    organization_name: '',
    organization_subdomain: '',
    organization_email: props.invitation.email || '',
});

// Auto-suggest subdomain from organization name
watch(() => form.organization_name, (name) => {
    if (name) {
        form.organization_subdomain = name
            .toLowerCase()
            .replace(/[^a-z0-9-]/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '')
            .substring(0, 63);
    }
});

const submit = () => {
    form.post(route('invitations.accept.store', { token: props.invitation.token }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// Password strength calculation
const passwordStrength = computed(() => {
    const password = form.password;
    if (!password) return { score: 0, label: '', color: '' };

    let score = 0;

    // Length checks
    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;

    // Character variety checks
    if (/[a-z]/.test(password)) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^a-zA-Z0-9]/.test(password)) score += 1;

    if (score <= 2) return { score: 1, label: 'Weak', color: 'bg-red-500' };
    if (score <= 4) return { score: 2, label: 'Fair', color: 'bg-yellow-500' };
    if (score <= 5) return { score: 3, label: 'Good', color: 'bg-blue-500' };
    return { score: 4, label: 'Strong', color: 'bg-green-500' };
});
</script>

<template>
    <Head title="Accept Invitation" />

    <AuthenticationCard>
        <template #logo>
            <!-- Show organization logo if available, otherwise default logo -->
            <template v-if="invitation.organization?.logo_url">
                <img
                    :src="invitation.organization.logo_url"
                    :alt="invitation.organization.name"
                    class="h-16 w-auto mx-auto"
                />
            </template>
            <template v-else>
                <AuthenticationCardLogo />
            </template>
        </template>

        <div class="mb-6 text-center">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                You've been invited!
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                <template v-if="isOrgOwnerInvitation">
                    <span v-if="invitation.invited_by">{{ invitation.invited_by.name }} has invited you to create your own organization</span>
                    <span v-else>You've been invited to create your own organization</span>
                </template>
                <template v-else>
                    <span v-if="invitation.invited_by">{{ invitation.invited_by.name }} has invited you to join </span>
                    <span v-else>You've been invited to join </span>
                    <strong>{{ invitation.organization?.name || 'the organization' }}</strong>
                </template>
            </p>
        </div>

        <!-- Invitation Details Card -->
        <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                <p><strong>Email:</strong> {{ invitation.email }}</p>
                <p>
                    <strong>Expires:</strong>
                    <span class="text-amber-600 dark:text-amber-400">{{ invitation.expires_in }}</span>
                    <span class="text-xs text-gray-500 ml-1">({{ invitation.expires_at }})</span>
                </p>
            </div>
        </div>

        <!-- Assigned Roles Section -->
        <div v-if="invitation.roles && invitation.roles.length > 0" class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
            <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                Your Role{{ invitation.roles.length > 1 ? 's' : '' }}
            </h3>
            <div class="space-y-2">
                <div
                    v-for="role in invitation.roles"
                    :key="role.id"
                    class="flex items-start"
                >
                    <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ role.name }}</span>
                        <p v-if="role.description" class="text-xs text-gray-600 dark:text-gray-400">{{ role.description }}</p>
                    </div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit">
            <!-- Existing User - Just Password -->
            <template v-if="existingUser">
                <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-100 dark:border-green-800">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <template v-if="isOrgOwnerInvitation">
                                You already have an account. Enter your password and organization details to create your new organization.
                            </template>
                            <template v-else>
                                You already have an account. Enter your password to join this organization.
                            </template>
                        </p>
                    </div>
                </div>

                <div>
                    <InputLabel for="password" value="Password" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="current-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
            </template>

            <!-- New User - Full Registration -->
            <template v-else>
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="Password" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <!-- Password Strength Indicator -->
                    <div v-if="form.password" class="mt-2">
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div
                                    :class="[passwordStrength.color, 'h-full transition-all duration-300']"
                                    :style="{ width: `${passwordStrength.score * 25}%` }"
                                ></div>
                            </div>
                            <span class="text-xs font-medium" :class="{
                                'text-red-600 dark:text-red-400': passwordStrength.score === 1,
                                'text-yellow-600 dark:text-yellow-400': passwordStrength.score === 2,
                                'text-blue-600 dark:text-blue-400': passwordStrength.score === 3,
                                'text-green-600 dark:text-green-400': passwordStrength.score === 4,
                            }">{{ passwordStrength.label }}</span>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password_confirmation" value="Confirm Password" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                    <InputLabel for="terms">
                        <div class="flex items-center">
                            <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                            <div class="ms-2">
                                I agree to the <a target="_blank" :href="route('terms.show')" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">Terms of Service</a> and <a target="_blank" :href="route('policy.show')" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">Privacy Policy</a>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.terms" />
                    </InputLabel>
                </div>
            </template>

            <!-- Organization Details (for org owner invitations) -->
            <div v-if="isOrgOwnerInvitation" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Organization Details</h3>

                <div>
                    <InputLabel for="organization_name" value="Organization Name" />
                    <TextInput
                        id="organization_name"
                        v-model="form.organization_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        placeholder="My Company"
                    />
                    <InputError class="mt-2" :message="form.errors.organization_name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="organization_subdomain" value="Subdomain" />
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <TextInput
                            id="organization_subdomain"
                            v-model="form.organization_subdomain"
                            type="text"
                            class="block w-full rounded-none rounded-l-md"
                            required
                            placeholder="my-company"
                        />
                        <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 text-sm text-gray-500 dark:text-gray-400">
                            .flipstack.app
                        </span>
                    </div>
                    <InputError class="mt-2" :message="form.errors.organization_subdomain" />
                </div>

                <div class="mt-4">
                    <InputLabel for="organization_email" value="Organization Email" />
                    <TextInput
                        id="organization_email"
                        v-model="form.organization_email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        placeholder="contact@mycompany.com"
                    />
                    <InputError class="mt-2" :message="form.errors.organization_email" />
                </div>
            </div>

            <InputError class="mt-2" :message="form.errors.error" />

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    <template v-if="form.processing">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </template>
                    <template v-else>
                        <template v-if="isOrgOwnerInvitation">
                            {{ existingUser ? 'Create Organization' : 'Create Account & Organization' }}
                        </template>
                        <template v-else>
                            {{ existingUser ? 'Join Organization' : 'Create Account & Join' }}
                        </template>
                    </template>
                </PrimaryButton>
            </div>
        </form>

        <!-- What to expect section -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">What happens next?</h3>
            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                <template v-if="isOrgOwnerInvitation">
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full text-xs font-medium text-blue-600 dark:text-blue-400 mr-2">1</span>
                        <span>Your new organization will be created with your chosen name and subdomain</span>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full text-xs font-medium text-blue-600 dark:text-blue-400 mr-2">2</span>
                        <span>Default categories, brands, and supplier tiers will be set up for you</span>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full text-xs font-medium text-blue-600 dark:text-blue-400 mr-2">3</span>
                        <span>Go through onboarding to configure your organization and invite your team</span>
                    </div>
                </template>
                <template v-else>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full text-xs font-medium text-blue-600 dark:text-blue-400 mr-2">1</span>
                        <span>Complete your account setup</span>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full text-xs font-medium text-blue-600 dark:text-blue-400 mr-2">2</span>
                        <span>Go through a quick onboarding to get familiar with the platform</span>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-full text-xs font-medium text-blue-600 dark:text-blue-400 mr-2">3</span>
                        <span>Start collaborating with your team</span>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticationCard>
</template>
