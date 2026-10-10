<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: Object,
    paymentMethods: Array,
    notificationPreferences: Object,
    tierInfo: Object,
    commissionInfo: Object,
});

const activeTab = ref('profile');

const tabs = [
    { key: 'profile', label: 'Profile', icon: 'fas fa-user' },
    { key: 'payment', label: 'Payment', icon: 'fas fa-credit-card' },
    { key: 'notifications', label: 'Notifications', icon: 'fas fa-bell' },
    { key: 'tier', label: 'Tier & Commission', icon: 'fas fa-layer-group' },
];

const profileForm = useForm({
    name: props.supplier.name || '',
    company_name: props.supplier.company_name || '',
    email: props.supplier.email || '',
    phone: props.supplier.phone || '',
    address_line1: props.supplier.address_line1 || '',
    address_line2: props.supplier.address_line2 || '',
    city: props.supplier.city || '',
    state: props.supplier.state || '',
    postal_code: props.supplier.postal_code || '',
    country: props.supplier.country || '',
});

const paymentForm = useForm({
    payment_method: props.supplier.payment_method || '',
    bank_account_last4: props.supplier.bank_account_last4 || '',
    payment_info: props.supplier.payment_info || {},
});

const notificationForm = useForm({
    notification_preferences: JSON.parse(JSON.stringify(props.notificationPreferences)),
});

const saveProfile = () => {
    profileForm.put(route('supplier.settings.update'), {
        preserveScroll: true,
    });
};

const savePayment = () => {
    paymentForm.put(route('supplier.settings.update'), {
        preserveScroll: true,
    });
};

const saveNotifications = () => {
    notificationForm.put(route('supplier.settings.update'), {
        preserveScroll: true,
    });
};

const notificationCategories = [
    { key: 'settlement', label: 'Settlement', description: 'Settlement created, approved, or paid' },
    { key: 'tier_change', label: 'Tier Changes', description: 'Tier promotions or demotions' },
    { key: 'approval', label: 'Approvals', description: 'Product or account approvals' },
    { key: 'payment', label: 'Payments', description: 'Payment processing and confirmations' },
    { key: 'system', label: 'System', description: 'System announcements and maintenance' },
];

const notificationChannels = ['email', 'sms', 'push'];

const toggleNotification = (category, channel) => {
    if (!notificationForm.notification_preferences.categories[category]) {
        notificationForm.notification_preferences.categories[category] = { email: false, sms: false, push: false };
    }
    notificationForm.notification_preferences.categories[category][channel] =
        !notificationForm.notification_preferences.categories[category][channel];
};

const formatPercent = (value) => {
    return `${parseFloat(value || 0).toFixed(1)}%`;
};
</script>

<template>
    <Head title="Supplier Settings" />

    <AppLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">Settings</h1>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav class="flex space-x-8">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            :class="[
                                activeTab === tab.key
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:border-gray-300',
                                'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2'
                            ]"
                        >
                            <i :class="tab.icon" class="text-xs"></i>
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Profile Tab -->
                <div v-show="activeTab === 'profile'">
                    <form @submit.prevent="saveProfile" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Business Profile</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business Name</label>
                                <input v-model="profileForm.name" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company Name</label>
                                <input v-model="profileForm.company_name" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Email</label>
                                <input v-model="profileForm.email" type="email"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                                <input v-model="profileForm.phone" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address Line 1</label>
                                <input v-model="profileForm.address_line1" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address Line 2</label>
                                <input v-model="profileForm.address_line2" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label>
                                <input v-model="profileForm.city" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">State</label>
                                <input v-model="profileForm.state" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Postal Code</label>
                                <input v-model="profileForm.postal_code" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Country</label>
                                <input v-model="profileForm.country" type="text"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <!-- W-9 and Contract Status (read-only) -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Document Status</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">W-9 on File</span>
                                    <span :class="supplier.w9_on_file ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-sm font-medium">
                                        {{ supplier.w9_on_file ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Contract Signed</span>
                                    <span :class="supplier.contract_signed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-sm font-medium">
                                        {{ supplier.contract_signed ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                                <div v-if="supplier.contract_expires_at" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg md:col-span-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Contract Expires</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.contract_expires_at }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="profileForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 text-sm font-medium">
                                {{ profileForm.processing ? 'Saving...' : 'Save Profile' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Payment Tab -->
                <div v-show="activeTab === 'payment'">
                    <form @submit.prevent="savePayment" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Payment Settings</h2>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
                            <select v-model="paymentForm.payment_method"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select a method</option>
                                <option v-for="method in paymentMethods" :key="method.value" :value="method.value">
                                    {{ method.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="paymentForm.payment_method === 'ach'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bank Account (last 4 digits)</label>
                            <input v-model="paymentForm.bank_account_last4" type="text" maxlength="4"
                                   class="mt-1 block w-48 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="1234" />
                        </div>

                        <!-- Settlement Schedule (read-only) -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Settlement Schedule</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Settlement schedule is managed by the organization admin.</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Schedule</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ supplier.settlement_schedule || 'Not set' }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Settlement Day</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.settlement_day || 'Not set' }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Next Settlement</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.next_settlement_date || 'TBD' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="paymentForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 text-sm font-medium">
                                {{ paymentForm.processing ? 'Saving...' : 'Save Payment Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notifications Tab -->
                <div v-show="activeTab === 'notifications'">
                    <form @submit.prevent="saveNotifications" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Notification Preferences</h2>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-3 px-2 text-sm font-medium text-gray-700 dark:text-gray-300">Category</th>
                                        <th v-for="channel in notificationChannels" :key="channel"
                                            class="text-center py-3 px-2 text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">
                                            {{ channel }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cat in notificationCategories" :key="cat.key"
                                        class="border-b border-gray-100 dark:border-gray-700">
                                        <td class="py-3 px-2">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ cat.label }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ cat.description }}</p>
                                        </td>
                                        <td v-for="channel in notificationChannels" :key="channel" class="text-center py-3 px-2">
                                            <button type="button"
                                                    @click="toggleNotification(cat.key, channel)"
                                                    :class="[
                                                        notificationForm.notification_preferences.categories[cat.key]?.[channel]
                                                            ? 'bg-indigo-600'
                                                            : 'bg-gray-200 dark:bg-gray-600',
                                                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'
                                                    ]">
                                                <span :class="[
                                                    notificationForm.notification_preferences.categories[cat.key]?.[channel]
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0',
                                                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                                                ]" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="notificationForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 text-sm font-medium">
                                {{ notificationForm.processing ? 'Saving...' : 'Save Preferences' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tier & Commission Tab -->
                <div v-show="activeTab === 'tier'">
                    <div class="space-y-6">
                        <!-- Current Tier -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Current Tier</h2>
                            <template v-if="tierInfo">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tier Name</p>
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ tierInfo.name }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tier Code</p>
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ tierInfo.code }}</p>
                                    </div>
                                    <div v-if="tierInfo.description" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg md:col-span-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Description</p>
                                        <p class="text-sm text-gray-900 dark:text-white">{{ tierInfo.description }}</p>
                                    </div>
                                    <div v-if="tierInfo.next_evaluation" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Next Tier Evaluation</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ tierInfo.next_evaluation }}</p>
                                    </div>
                                    <div v-if="tierInfo.commission_adjustment" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tier Commission Adjustment</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ tierInfo.commission_adjustment }}%</p>
                                    </div>
                                </div>
                                <div v-if="tierInfo.benefits && tierInfo.benefits.length > 0" class="mt-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Benefits</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li v-for="(benefit, i) in tierInfo.benefits" :key="i"
                                            class="text-sm text-gray-600 dark:text-gray-400">{{ benefit }}</li>
                                    </ul>
                                </div>
                            </template>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No tier assigned.</p>
                        </div>

                        <!-- Commission Structure -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Commission Structure</h2>
                            <template v-if="commissionInfo">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Structure</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ commissionInfo.name }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Type</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ commissionInfo.type }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Base Rate</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatPercent(commissionInfo.base_rate) }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Effective Rate</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatPercent(commissionInfo.effective_rate) }}</p>
                                    </div>
                                </div>
                            </template>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No commission structure assigned.</p>
                        </div>

                        <!-- Performance Overview -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance Overview</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Overall</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ supplier.cached_performance_score || '--' }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Quality</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ supplier.cached_quality_score || '--' }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Fulfillment</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ supplier.cached_fulfillment_score || '--' }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Volume</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ supplier.cached_volume_score || '--' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
