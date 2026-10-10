<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    mustChange: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.change.update'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Change Password" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div v-if="mustChange" class="mb-4 text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-md p-3">
            Your password must be changed before you can continue. Please choose a new password.
        </div>

        <div v-else class="mb-4 text-sm text-gray-600">
            Update your password to keep your account secure.
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="current_password" value="Current Password" />
                <TextInput
                    id="current_password"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.current_password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="New Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm New Password" />
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

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Change Password
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
