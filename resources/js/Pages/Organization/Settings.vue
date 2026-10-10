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
import IntegrationsTab from './Partials/IntegrationsTab.vue'

const props = defineProps({
    organization: Object,
    settings: Object,
    timezones: Array,
    currencies: Array,
    languages: Array,
    integrations: Array,
    ebayConfig: Object,
    shopifyConfig: Object,
    availablePlatforms: Object,
    hasExtensionToken: Boolean,
    productCount: Number,
    showEbayImportPrompt: Boolean,
    permissions: Object
})

// Active tab management
const activeTab = ref('general')

const tabs = [
    { id: 'general', name: 'General', icon: 'fas fa-building' },
    { id: 'preferences', name: 'Preferences', icon: 'fas fa-cog' },
    { id: 'notifications', name: 'Notifications', icon: 'fas fa-bell' },
    { id: 'security', name: 'Security', icon: 'fas fa-shield-alt' },
    { id: 'integrations', name: 'Integrations', icon: 'fas fa-plug' }
]

// Form for organization settings
const form = useForm({
    company_name: props.settings.company_name || '',
    company_description: props.settings.company_description || '',
    company_website: props.settings.company_website || '',
    company_phone: props.settings.company_phone || '',
    company_email: props.settings.company_email || '',
    company_address: props.settings.company_address || {
        street: '',
        city: '',
        state: '',
        zip: '',
        country: 'US'
    },
    preferences: props.settings.preferences || {
        timezone: 'UTC',
        date_format: 'M j, Y',
        time_format: '12',
        currency: 'USD',
        language: 'en'
    },
    notifications: props.settings.notifications || {
        email_notifications: true,
        slack_notifications: false,
        sms_notifications: false,
        notification_frequency: 'real_time'
    },
    security: props.settings.security || {
        require_2fa: false,
        password_expiry_days: 90,
        session_timeout_minutes: 480,
        allowed_domains: []
    },
    integrations: props.settings.integrations || {
        slack_webhook: '',
        email_provider: 'default',
        backup_provider: 'local'
    }
})

const allowedDomainsText = ref((props.settings.security?.allowed_domains || []).join('\n'))

// Update allowed domains from textarea
const updateAllowedDomains = () => {
    form.security.allowed_domains = allowedDomainsText.value
        .split('\n')
        .map(domain => domain.trim())
        .filter(domain => domain.length > 0)
}

const submit = () => {
    updateAllowedDomains()
    
    form.put(route('organization.update-settings'), {
        onSuccess: () => {
            // Handle success
        }
    })
}

const countryOptions = [
    { value: 'US', label: 'United States' },
    { value: 'CA', label: 'Canada' },
    { value: 'UK', label: 'United Kingdom' },
    { value: 'AU', label: 'Australia' },
    { value: 'DE', label: 'Germany' },
    { value: 'FR', label: 'France' },
    { value: 'ES', label: 'Spain' },
    { value: 'IT', label: 'Italy' },
    { value: 'JP', label: 'Japan' },
    { value: 'CN', label: 'China' }
]

const dateFormatOptions = [
    { value: 'M j, Y', label: 'Jan 15, 2024' },
    { value: 'm/d/Y', label: '01/15/2024' },
    { value: 'd/m/Y', label: '15/01/2024' },
    { value: 'Y-m-d', label: '2024-01-15' },
    { value: 'F j, Y', label: 'January 15, 2024' }
]

const notificationFrequencyOptions = [
    { value: 'real_time', label: 'Real-time' },
    { value: 'hourly', label: 'Hourly digest' },
    { value: 'daily', label: 'Daily digest' },
    { value: 'weekly', label: 'Weekly summary' }
]
</script>

<template>
    <AppLayout
        title="Organization Settings"
        page-title="Organization Settings"
        page-description="Manage your organization's configuration and preferences"
    >

        <div class="max-w-6xl mx-auto">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Settings Navigation -->
                <div class="lg:w-1/4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <nav class="space-y-2">
                            <button v-for="tab in tabs" :key="tab.id"
                                    @click="activeTab = tab.id"
                                    :class="[
                                        'w-full flex items-center space-x-3 px-4 py-3 text-left rounded-lg transition-colors',
                                        activeTab === tab.id
                                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                            : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700'
                                    ]">
                                <i :class="tab.icon"></i>
                                <span class="font-medium">{{ tab.name }}</span>
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="lg:w-3/4">
                    <form @submit.prevent="submit" class="space-y-8">
                        <!-- General Settings -->
                        <div v-if="activeTab === 'general'" 
                             class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                            <div class="flex items-center space-x-3 mb-6">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-building text-blue-600 dark:text-blue-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Company Information</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Basic company details and contact information</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Company Name -->
                                <div>
                                    <InputLabel for="company_name" value="Company Name" />
                                    <TextInput
                                        id="company_name"
                                        v-model="form.company_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.company_name" />
                                </div>

                                <!-- Company Website -->
                                <div>
                                    <InputLabel for="company_website" value="Website" />
                                    <TextInput
                                        id="company_website"
                                        v-model="form.company_website"
                                        type="url"
                                        class="mt-1 block w-full"
                                        placeholder="https://example.com"
                                    />
                                    <InputError class="mt-2" :message="form.errors.company_website" />
                                </div>

                                <!-- Company Phone -->
                                <div>
                                    <InputLabel for="company_phone" value="Phone Number" />
                                    <TextInput
                                        id="company_phone"
                                        v-model="form.company_phone"
                                        type="tel"
                                        class="mt-1 block w-full"
                                        placeholder="+1 (555) 123-4567"
                                    />
                                    <InputError class="mt-2" :message="form.errors.company_phone" />
                                </div>

                                <!-- Company Email -->
                                <div>
                                    <InputLabel for="company_email" value="Contact Email" />
                                    <TextInput
                                        id="company_email"
                                        v-model="form.company_email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        placeholder="contact@example.com"
                                    />
                                    <InputError class="mt-2" :message="form.errors.company_email" />
                                </div>

                                <!-- Company Description -->
                                <div class="md:col-span-2">
                                    <InputLabel for="company_description" value="Company Description" />
                                    <TextareaInput
                                        id="company_description"
                                        v-model="form.company_description"
                                        class="mt-1 block w-full"
                                        rows="3"
                                        placeholder="Brief description of your company..."
                                    />
                                    <InputError class="mt-2" :message="form.errors.company_description" />
                                </div>
                            </div>

                            <!-- Address Section -->
                            <div class="mt-8">
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Company Address</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Street Address -->
                                    <div class="md:col-span-2">
                                        <InputLabel for="street" value="Street Address" />
                                        <TextInput
                                            id="street"
                                            v-model="form.company_address.street"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                    </div>

                                    <!-- City -->
                                    <div>
                                        <InputLabel for="city" value="City" />
                                        <TextInput
                                            id="city"
                                            v-model="form.company_address.city"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                    </div>

                                    <!-- State -->
                                    <div>
                                        <InputLabel for="state" value="State / Province" />
                                        <TextInput
                                            id="state"
                                            v-model="form.company_address.state"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                    </div>

                                    <!-- ZIP -->
                                    <div>
                                        <InputLabel for="zip" value="ZIP / Postal Code" />
                                        <TextInput
                                            id="zip"
                                            v-model="form.company_address.zip"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                    </div>

                                    <!-- Country -->
                                    <div>
                                        <InputLabel for="country" value="Country" />
                                        <SelectInput
                                            id="country"
                                            v-model="form.company_address.country"
                                            class="mt-1 block w-full"
                                        >
                                            <option v-for="country in countryOptions" :key="country.value" :value="country.value">
                                                {{ country.label }}
                                            </option>
                                        </SelectInput>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preferences Settings -->
                        <div v-if="activeTab === 'preferences'" 
                             class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                            <div class="flex items-center space-x-3 mb-6">
                                <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-cog text-emerald-600 dark:text-emerald-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">System Preferences</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Configure system-wide defaults and preferences</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Timezone -->
                                <div>
                                    <InputLabel for="timezone" value="Default Timezone" />
                                    <SelectInput
                                        id="timezone"
                                        v-model="form.preferences.timezone"
                                        class="mt-1 block w-full"
                                    >
                                        <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                            {{ tz.label }}
                                        </option>
                                    </SelectInput>
                                </div>

                                <!-- Currency -->
                                <div>
                                    <InputLabel for="currency" value="Default Currency" />
                                    <SelectInput
                                        id="currency"
                                        v-model="form.preferences.currency"
                                        class="mt-1 block w-full"
                                    >
                                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">
                                            {{ currency.label }}
                                        </option>
                                    </SelectInput>
                                </div>

                                <!-- Date Format -->
                                <div>
                                    <InputLabel for="date_format" value="Date Format" />
                                    <SelectInput
                                        id="date_format"
                                        v-model="form.preferences.date_format"
                                        class="mt-1 block w-full"
                                    >
                                        <option v-for="format in dateFormatOptions" :key="format.value" :value="format.value">
                                            {{ format.label }}
                                        </option>
                                    </SelectInput>
                                </div>

                                <!-- Time Format -->
                                <div>
                                    <InputLabel for="time_format" value="Time Format" />
                                    <SelectInput
                                        id="time_format"
                                        v-model="form.preferences.time_format"
                                        class="mt-1 block w-full"
                                    >
                                        <option value="12">12-hour (2:30 PM)</option>
                                        <option value="24">24-hour (14:30)</option>
                                    </SelectInput>
                                </div>

                                <!-- Language -->
                                <div>
                                    <InputLabel for="language" value="Default Language" />
                                    <SelectInput
                                        id="language"
                                        v-model="form.preferences.language"
                                        class="mt-1 block w-full"
                                    >
                                        <option v-for="lang in languages" :key="lang.value" :value="lang.value">
                                            {{ lang.label }}
                                        </option>
                                    </SelectInput>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications Settings -->
                        <div v-if="activeTab === 'notifications'" 
                             class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                            <div class="flex items-center space-x-3 mb-6">
                                <div class="w-10 h-10 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-bell text-yellow-600 dark:text-yellow-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Notification Settings</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Configure how and when notifications are sent</p>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <!-- Notification Channels -->
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Notification Channels</h4>
                                    <div class="space-y-3">
                                        <label class="flex items-center">
                                            <Checkbox v-model:checked="form.notifications.email_notifications" />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                Email notifications
                                            </span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <Checkbox v-model:checked="form.notifications.slack_notifications" />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                Slack notifications
                                            </span>
                                        </label>
                                        
                                        <label class="flex items-center">
                                            <Checkbox v-model:checked="form.notifications.sms_notifications" />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                SMS notifications
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Notification Frequency -->
                                <div>
                                    <InputLabel for="notification_frequency" value="Notification Frequency" />
                                    <SelectInput
                                        id="notification_frequency"
                                        v-model="form.notifications.notification_frequency"
                                        class="mt-1 block w-full max-w-xs"
                                    >
                                        <option v-for="freq in notificationFrequencyOptions" :key="freq.value" :value="freq.value">
                                            {{ freq.label }}
                                        </option>
                                    </SelectInput>
                                </div>
                            </div>
                        </div>

                        <!-- Security Settings -->
                        <div v-if="activeTab === 'security'" 
                             class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                            <div class="flex items-center space-x-3 mb-6">
                                <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-red-600 dark:text-red-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Security Settings</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Configure security policies and requirements</p>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <!-- Two-Factor Authentication -->
                                <div>
                                    <label class="flex items-center">
                                        <Checkbox v-model:checked="form.security.require_2fa" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Require two-factor authentication for all users
                                        </span>
                                    </label>
                                </div>

                                <!-- Password Settings -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="password_expiry_days" value="Password Expiry (Days)" />
                                        <TextInput
                                            id="password_expiry_days"
                                            v-model="form.security.password_expiry_days"
                                            type="number"
                                            class="mt-1 block w-full"
                                            min="0"
                                            max="365"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">Set to 0 to disable password expiry</p>
                                    </div>

                                    <div>
                                        <InputLabel for="session_timeout_minutes" value="Session Timeout (Minutes)" />
                                        <TextInput
                                            id="session_timeout_minutes"
                                            v-model="form.security.session_timeout_minutes"
                                            type="number"
                                            class="mt-1 block w-full"
                                            min="15"
                                            max="1440"
                                        />
                                    </div>
                                </div>

                                <!-- Allowed Domains -->
                                <div>
                                    <InputLabel for="allowed_domains" value="Allowed Email Domains" />
                                    <TextareaInput
                                        id="allowed_domains"
                                        v-model="allowedDomainsText"
                                        class="mt-1 block w-full"
                                        rows="4"
                                        placeholder="example.com&#10;company.org&#10;subdomain.example.com"
                                    />
                                    <p class="text-xs text-gray-500 mt-1">One domain per line. Leave empty to allow all domains.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Integrations Settings -->
                        <div v-if="activeTab === 'integrations'"
                             class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                            <IntegrationsTab
                                :integrations="integrations"
                                :ebayConfig="ebayConfig"
                                :shopifyConfig="shopifyConfig"
                                :availablePlatforms="availablePlatforms"
                                :hasExtensionToken="hasExtensionToken"
                                :productCount="productCount"
                                :showEbayImportPrompt="showEbayImportPrompt"
                            />
                        </div>

                        <!-- Form Actions -->
                        <div v-if="permissions.can_edit" class="flex items-center justify-end space-x-4 pt-6">
                            <SecondaryButton @click="$inertia.visit(route('dashboard'))" type="button">
                                Cancel
                            </SecondaryButton>
                            
                            <PrimaryButton 
                                :class="{ 'opacity-25': form.processing }" 
                                :disabled="form.processing"
                                type="submit"
                            >
                                <i class="fas fa-save mr-2"></i>
                                Save Settings
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>