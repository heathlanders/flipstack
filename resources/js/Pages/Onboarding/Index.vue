<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import TextareaInput from '@/Components/TextareaInput.vue'
import Checkbox from '@/Components/Checkbox.vue'

const props = defineProps({
    user: Object,
    progress: Object,
    current_step: String,
    steps: Object,
    organization: Object,
    support_email: { type: String, default: null },
})

const currentStepData = ref({})
const isLoading = ref(false)

const stepConfig = computed(() => {
    return props.steps[props.current_step] || {}
})

// Step-specific forms
const profileForm = useForm({
    bio: props.user.bio || '',
    phone: props.user.phone || ''
})

const teamsForm = useForm({
    team_requests: []
})

const skuPrefixForm = useForm({
    sku_prefix: ''
})

const firstActionForm = useForm({
    visited_inventory: false,
    visited_intake_queue: false,
    reviewed_organization: false
})

// SKU Prefix specific refs
const skuPrefixSuggestions = ref([])
const skuPrefixValidation = ref({ message: '', isError: false })
const checkingAvailability = ref(false)

// SKU Prefix methods
const validateSkuPrefix = () => {
    const prefix = skuPrefixForm.sku_prefix.toUpperCase()
    skuPrefixForm.sku_prefix = prefix

    if (!prefix) {
        skuPrefixValidation.value = { message: '', isError: false }
        return
    }

    if (prefix.length < 3) {
        skuPrefixValidation.value = { message: 'Must be at least 3 characters', isError: true }
        return
    }

    if (prefix.length > 10) {
        skuPrefixValidation.value = { message: 'Cannot be longer than 10 characters', isError: true }
        return
    }

    if (!/^[A-Z0-9]+$/.test(prefix)) {
        skuPrefixValidation.value = { message: 'Only uppercase letters and numbers allowed', isError: true }
        return
    }

    skuPrefixValidation.value = { message: 'Valid format ✓', isError: false }
    checkAvailability()
}

const checkAvailability = async () => {
    if (!skuPrefixForm.sku_prefix || skuPrefixValidation.value.isError) return

    checkingAvailability.value = true

    try {
        const response = await window.axios.post(route('onboarding.sku-prefix.check-availability'), {
            prefix: skuPrefixForm.sku_prefix
        })

        skuPrefixValidation.value = {
            message: response.data.message,
            isError: !response.data.available
        }
    } catch (error) {
        console.error('SKU prefix availability check failed:', error)
        const errorMessage = error.response?.status === 419
            ? 'Session expired. Please refresh the page.'
            : 'Error checking availability. Please try again.'
        skuPrefixValidation.value = {
            message: errorMessage,
            isError: true
        }
    } finally {
        checkingAvailability.value = false
    }
}

const selectSuggestion = (suggestion) => {
    skuPrefixForm.sku_prefix = suggestion
    validateSkuPrefix()
}

// Generate suggestions based on organization name
const generateSuggestions = () => {
    const name = props.organization.name.toUpperCase()
    const cleaned = name.replace(/[^A-Z0-9]/g, '')
    
    const suggestions = []
    if (cleaned.length >= 3) {
        suggestions.push(cleaned.substring(0, 4))
        suggestions.push(cleaned.substring(0, 5))
        if (cleaned.length >= 6) {
            suggestions.push(cleaned.substring(0, 6))
        }
    }
    
    return [...new Set(suggestions)] // Remove duplicates
}

// Initialize suggestions when component loads
skuPrefixSuggestions.value = generateSuggestions()

const completeStep = (stepData = {}) => {
    isLoading.value = true

    router.post(route('onboarding.complete-step'), {
        step: props.current_step,
        data: stepData
    }, {
        preserveState: true,
        onSuccess: () => {
            isLoading.value = false
        },
        onError: () => {
            isLoading.value = false
        }
    })
}

const skipOnboarding = () => {
    router.post(route('onboarding.skip'), {}, {
        onSuccess: () => {
            router.visit(route('dashboard'))
        }
    })
}

const completeOnboarding = () => {
    router.post(route('onboarding.complete'), {}, {
        onSuccess: () => {
            router.visit(route('dashboard'))
        }
    })
}

const progressPercentage = computed(() => {
    const totalSteps = Object.keys(props.steps).length
    const completedSteps = (props.progress?.steps_completed || []).length
    return Math.round((completedSteps / totalSteps) * 100)
})

const getStepIcon = (step) => {
    const icons = {
        welcome: 'fas fa-hand-wave',
        profile: 'fas fa-user',
        teams: 'fas fa-users',
        sku_prefix_setup: 'fas fa-tag',
        role_understanding: 'fas fa-badge',
        first_action: 'fas fa-play',
        resources: 'fas fa-book'
    }
    return icons[step] || 'fas fa-circle'
}

const isStepCompleted = (step) => {
    return (props.progress?.steps_completed || []).includes(step)
}
</script>

<template>
    <AppLayout title="Welcome">
        <div class="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-900 dark:to-gray-800">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <img src="/logo.png" alt="Logo" class="h-8 w-auto">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome to {{ organization.name }}!</h1>
                                <p class="text-gray-600 dark:text-gray-400">Let's get you set up and ready to be productive</p>
                            </div>
                        </div>
                        
                        <button @click="skipOnboarding"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm">
                            Skip Setup →
                        </button>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-6">
                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                            <span>Setup Progress</span>
                            <span>{{ progressPercentage }}% Complete</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                 :style="`width: ${progressPercentage}%`"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Step Navigation -->
                    <div class="lg:w-1/3">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Setup Steps</h3>
                            
                            <nav class="space-y-2">
                                <div v-for="(stepInfo, stepKey) in steps" :key="stepKey"
                                     :class="[
                                         'flex items-center space-x-3 p-3 rounded-lg transition-colors',
                                         current_step === stepKey 
                                             ? 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800'
                                             : isStepCompleted(stepKey)
                                                 ? 'text-emerald-700 dark:text-emerald-300'
                                                 : 'text-gray-600 dark:text-gray-400'
                                     ]">
                                    <div :class="[
                                        'w-6 h-6 rounded-full flex items-center justify-center text-xs',
                                        current_step === stepKey
                                            ? 'bg-blue-500 text-white'
                                            : isStepCompleted(stepKey)
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-gray-300 text-gray-600'
                                    ]">
                                        <i v-if="isStepCompleted(stepKey)" class="fas fa-check"></i>
                                        <i v-else :class="getStepIcon(stepKey)"></i>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="font-medium">{{ stepInfo.title }}</div>
                                        <div class="text-sm opacity-75">{{ stepInfo.estimated_time }}</div>
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>

                    <!-- Step Content -->
                    <div class="lg:w-2/3">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                            
                            <!-- Welcome Step -->
                            <div v-if="current_step === 'welcome'">
                                <div class="text-center mb-8">
                                    <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-hand-wave text-blue-600 dark:text-blue-400 text-2xl"></i>
                                    </div>
                                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Welcome aboard, {{ user.name }}!</h2>
                                    <p class="text-gray-600 dark:text-gray-400 text-lg">
                                        We're excited to have you join {{ organization.name }}. Let's get you set up for success.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <h3 class="font-semibold text-blue-900 dark:text-blue-300 mb-2">Your Role</h3>
                                        <p class="text-blue-700 dark:text-blue-400">{{ user.primary_role }}</p>
                                    </div>
                                    
                                    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                                        <h3 class="font-semibold text-emerald-900 dark:text-emerald-300 mb-2">Setup Time</h3>
                                        <p class="text-emerald-700 dark:text-emerald-400">About 5-10 minutes</p>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <PrimaryButton @click="completeStep()" :disabled="isLoading" class="px-8">
                                        Let's Get Started!
                                    </PrimaryButton>
                                </div>
                            </div>

                            <!-- Profile Step -->
                            <div v-else-if="current_step === 'profile'">
                                <div class="mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Complete Your Profile</h2>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Add some personal details to help your teammates get to know you better.
                                    </p>
                                </div>

                                <form @submit.prevent="completeStep(profileForm.data())" class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Bio / About Yourself
                                        </label>
                                        <TextareaInput
                                            v-model="profileForm.bio"
                                            rows="3"
                                            placeholder="Tell your team a bit about yourself, your background, interests..."
                                            class="w-full"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Phone Number (Optional)
                                        </label>
                                        <TextInput
                                            v-model="profileForm.phone"
                                            type="tel"
                                            placeholder="+1 (555) 123-4567"
                                            class="w-full"
                                        />
                                    </div>

                                    <div class="flex space-x-4">
                                        <SecondaryButton @click="completeStep()" type="button">
                                            Skip for Now
                                        </SecondaryButton>
                                        <PrimaryButton type="submit" :disabled="isLoading">
                                            Save & Continue
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            <!-- Teams Step -->
                            <div v-else-if="current_step === 'teams'">
                                <div class="mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Join Your Teams</h2>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Connect with the teams you'll be working with. You can always join more teams later.
                                    </p>
                                </div>

                                <div class="text-center py-8">
                                    <i class="fas fa-users text-gray-300 dark:text-gray-600 text-4xl mb-4"></i>
                                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                                        Your team assignments will be configured by your administrator.
                                    </p>
                                    <p class="text-sm text-gray-400">
                                        You'll receive invitations to join relevant teams soon.
                                    </p>
                                </div>

                                <div class="flex space-x-4">
                                    <PrimaryButton @click="completeStep()" :disabled="isLoading">
                                        Continue
                                    </PrimaryButton>
                                </div>
                            </div>

                            <!-- SKU Prefix Setup Step -->
                            <div v-else-if="current_step === 'sku_prefix_setup'">
                                <div class="mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Set Up Your SKU Prefix</h2>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Choose a unique prefix for your organization's product SKUs. This will be used to generate internal product codes.
                                    </p>
                                </div>

                                <form @submit.prevent="completeStep(skuPrefixForm.data())" class="space-y-6">
                                    <!-- SKU Prefix Input -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            SKU Prefix
                                        </label>
                                        <TextInput
                                            v-model="skuPrefixForm.sku_prefix"
                                            type="text"
                                            placeholder="e.g., ACME, DEMO, ABC123"
                                            maxlength="10"
                                            :class="[
                                                'w-full uppercase',
                                                skuPrefixValidation.isError ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : ''
                                            ]"
                                            @input="validateSkuPrefix"
                                            @blur="checkAvailability"
                                        />
                                        
                                        <!-- Validation Messages -->
                                        <div v-if="skuPrefixValidation.message" class="mt-2">
                                            <p :class="skuPrefixValidation.isError ? 'text-red-600' : 'text-green-600'" class="text-sm flex items-center">
                                                <i v-if="checkingAvailability" class="fas fa-spinner fa-spin mr-2"></i>
                                                {{ skuPrefixValidation.message }}
                                            </p>
                                        </div>

                                        <p class="mt-2 text-sm text-gray-500">
                                            3-10 characters, uppercase letters and numbers only
                                        </p>
                                    </div>

                                    <!-- Preview -->
                                    <div v-if="skuPrefixForm.sku_prefix && !skuPrefixValidation.isError" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                        <div class="text-sm">
                                            <p class="font-medium text-blue-800 dark:text-blue-300 mb-2">Your SKUs will look like:</p>
                                            <div class="font-mono space-y-1">
                                                <p class="text-blue-600 dark:text-blue-400">{{ skuPrefixForm.sku_prefix }}-000001</p>
                                                <p class="text-blue-600 dark:text-blue-400">{{ skuPrefixForm.sku_prefix }}-000002</p>
                                                <p class="text-blue-600 dark:text-blue-400">{{ skuPrefixForm.sku_prefix }}-000003...</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Suggestions -->
                                    <div v-if="skuPrefixSuggestions.length > 0 && !skuPrefixForm.sku_prefix" class="space-y-3">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Suggested prefixes based on "{{ organization.name }}":</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="suggestion in skuPrefixSuggestions"
                                                :key="suggestion"
                                                type="button"
                                                @click="selectSuggestion(suggestion)"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                            >
                                                {{ suggestion }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex space-x-4">
                                        <SecondaryButton @click="completeStep()" type="button">
                                            Skip for Now
                                        </SecondaryButton>
                                        <PrimaryButton
                                            type="submit"
                                            :disabled="isLoading || !skuPrefixForm.sku_prefix || skuPrefixValidation.isError"
                                            :class="{ 'opacity-50': !skuPrefixForm.sku_prefix || skuPrefixValidation.isError }"
                                        >
                                            Save & Continue
                                        </PrimaryButton>
                                    </div>
                                </form>

                                <!-- Help Section -->
                                <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                                    <details class="group">
                                        <summary class="flex items-center font-medium text-gray-900 dark:text-white cursor-pointer">
                                            <span>Why do I need a SKU prefix?</span>
                                            <i class="fas fa-chevron-down ml-auto text-gray-500 group-open:rotate-180 transition-transform"></i>
                                        </summary>
                                        <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                            <p class="mb-2">Your SKU prefix helps you:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-4">
                                                <li>Organize products by your brand</li>
                                                <li>Track inventory across platforms</li>
                                                <li>Avoid conflicts with other sellers</li>
                                                <li>Maintain consistent product identification</li>
                                            </ul>
                                        </div>
                                    </details>
                                </div>
                            </div>

                            <!-- Role Understanding Step -->
                            <div v-else-if="current_step === 'role_understanding'">
                                <div class="mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Understand Your Role</h2>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Learn about your responsibilities and what you can do in the system.
                                    </p>
                                </div>

                                <div class="space-y-6">
                                    <div class="p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <h3 class="font-semibold text-blue-900 dark:text-blue-300 mb-3">
                                            Your Role: {{ user.primary_role }}
                                        </h3>
                                        
                                        <div v-if="user.primary_role === 'buyer'" class="space-y-2">
                                            <p class="text-blue-700 dark:text-blue-400 text-sm">As a Buyer, you can:</p>
                                            <ul class="list-disc pl-5 text-blue-600 dark:text-blue-300 text-sm space-y-1">
                                                <li>Add new products to inventory</li>
                                                <li>Research and source products</li>
                                                <li>Manage supplier relationships</li>
                                                <li>View analytics and reports</li>
                                            </ul>
                                        </div>
                                        
                                        <div v-else-if="user.primary_role === 'lister'" class="space-y-2">
                                            <p class="text-blue-700 dark:text-blue-400 text-sm">As a Lister, you can:</p>
                                            <ul class="list-disc pl-5 text-blue-600 dark:text-blue-300 text-sm space-y-1">
                                                <li>Process items from the intake queue</li>
                                                <li>Create and optimize product listings</li>
                                                <li>Manage product descriptions and images</li>
                                                <li>Track listing performance</li>
                                            </ul>
                                        </div>
                                        
                                        <div v-else class="space-y-2">
                                            <p class="text-blue-700 dark:text-blue-400 text-sm">In your role, you have access to:</p>
                                            <ul class="list-disc pl-5 text-blue-600 dark:text-blue-300 text-sm space-y-1">
                                                <li>Dashboard and analytics</li>
                                                <li>Team collaboration tools</li>
                                                <li>Relevant system features</li>
                                                <li>Reports and insights</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                        <p class="text-yellow-800 dark:text-yellow-300 text-sm">
                                            💡 <strong>Tip:</strong> Your permissions and access may expand as you take on more responsibilities within your teams.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-8">
                                    <PrimaryButton @click="completeStep()" :disabled="isLoading">
                                        Got it, Continue
                                    </PrimaryButton>
                                </div>
                            </div>

                            <!-- First Action Step -->
                            <div v-else-if="current_step === 'first_action'">
                                <div class="mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Take Your First Action</h2>
                                    <p class="text-gray-600 dark:text-gray-400">{{ stepConfig.description }}</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <Link :href="route('dashboard')"
                                          class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas fa-tachometer-alt text-blue-600 dark:text-blue-400"></i>
                                            <div>
                                                <h3 class="font-medium text-gray-900 dark:text-white">Explore Dashboard</h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Check out your personalized dashboard</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link v-if="user.primary_role === 'buyer'"
                                          :href="route('inventory.dashboard')"
                                          class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas fa-cube text-emerald-600 dark:text-emerald-400"></i>
                                            <div>
                                                <h3 class="font-medium text-gray-900 dark:text-white">View Inventory</h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Browse existing products</p>
                                            </div>
                                        </div>
                                    </Link>

                                    <Link v-if="user.primary_role === 'lister'"
                                          :href="route('intake-queue.index')"
                                          class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas fa-inbox text-purple-600 dark:text-purple-400"></i>
                                            <div>
                                                <h3 class="font-medium text-gray-900 dark:text-white">Intake Queue</h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Process incoming items</p>
                                            </div>
                                        </div>
                                    </Link>
                                </div>

                                <div class="mt-8">
                                    <PrimaryButton @click="completeStep(firstActionForm.data())" :disabled="isLoading">
                                        I've Explored, Continue
                                    </PrimaryButton>
                                </div>
                            </div>

                            <!-- Resources Step -->
                            <div v-else-if="current_step === 'resources'">
                                <div class="mb-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">You're all set</h2>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Click below to finish setup and head to your dashboard. If you need help, you can reach us anytime.
                                    </p>
                                </div>

                                <div v-if="support_email" class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg max-w-md">
                                    <h3 class="font-medium text-gray-900 dark:text-white mb-2">Need a hand?</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Email our support team and we'll get back to you quickly.
                                    </p>
                                    <a
                                        :href="`mailto:${support_email}`"
                                        class="text-blue-600 dark:text-blue-400 text-sm hover:underline"
                                    >
                                        {{ support_email }}
                                    </a>
                                </div>

                                <div class="mt-8">
                                    <PrimaryButton @click="completeOnboarding()" :disabled="isLoading" class="px-8">
                                        Finish Setup
                                    </PrimaryButton>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>