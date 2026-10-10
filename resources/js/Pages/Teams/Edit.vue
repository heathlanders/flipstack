<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import TextareaInput from '@/Components/TextareaInput.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'

const props = defineProps({
    departments: Array,
    roles: Array,
    users: Array
})

const form = useForm({
    name: ''
})

const submit = () => {
    form.post(route('teams.store'))
}
</script>

<template>
    <AppLayout title="Create Team">
        <template #header>
            <div class="flex items-center space-x-4">
                <button @click="$inertia.visit(route('teams.index'))"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-arrow-left text-lg"></i>
                </button>
                
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Create New Team</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Create a new team to collaborate with others on projects
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Team Details -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Team Details</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Create a new team to collaborate with others on projects</p>
                        </div>
                    </div>

                    <!-- Team Owner -->
                    <div class="mb-6">
                        <InputLabel value="Team Owner" />
                        <div class="flex items-center mt-2">
                            <img class="object-cover w-12 h-12 rounded-full" 
                                 :src="$page.props.auth.user.profile_photo_url" 
                                 :alt="$page.props.auth.user.name">
                            <div class="ml-4 leading-tight">
                                <div class="text-gray-900 dark:text-white">{{ $page.props.auth.user.name }}</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Name -->
                    <div>
                        <InputLabel for="name" value="Team Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="block w-full mt-1"
                            autofocus
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4">
                    <SecondaryButton @click="$inertia.visit(route('teams.index'))" type="button">
                        Cancel
                    </SecondaryButton>
                    
                    <PrimaryButton 
                        :class="{ 'opacity-25': form.processing }" 
                        :disabled="form.processing"
                        type="submit"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Create
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>