<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    carriers: Array,
    packagePresets: Array,
    settings: Object,
    webhookUrl: String,
    shipstationWebhookUrl: String,
    shippingEasyWebhookUrl: String,
    easypostConnected: Boolean,
    shipstationConnected: Boolean,
    shippingEasyConnected: Boolean,
    shippingProvider: String,
})

const activeTab = ref('carriers')

const tabs = [
    { id: 'carriers', name: 'Carriers', icon: 'fas fa-truck' },
    { id: 'shipping', name: 'Shipping Rules', icon: 'fas fa-shipping-fast' },
    { id: 'labels', name: 'Labels & Documents', icon: 'fas fa-file-alt' },
    { id: 'workflow', name: 'Workflow', icon: 'fas fa-project-diagram' },
    { id: 'presets', name: 'Package Presets', icon: 'fas fa-box' },
    { id: 'returns', name: 'Returns', icon: 'fas fa-undo' },
    { id: 'notifications', name: 'Webhooks & Notifications', icon: 'fas fa-bell' },
]

// --- Shipping Form ---
const shippingForm = useForm({
    default_carrier: props.settings.shipping?.default_carrier || 'USPS',
    default_service: props.settings.shipping?.default_service || 'Priority',
    auto_insurance_enabled: props.settings.shipping?.auto_insurance_enabled || false,
    auto_insurance_threshold: props.settings.shipping?.auto_insurance_threshold || 100,
    signature_confirmation: props.settings.shipping?.signature_confirmation || 'none',
    signature_threshold: props.settings.shipping?.signature_threshold || 250,
    saturday_delivery: props.settings.shipping?.saturday_delivery || false,
    always_use_cheapest: props.settings.shipping?.always_use_cheapest || false,
    preferred_carriers: props.settings.shipping?.preferred_carriers || ['USPS', 'UPS'],
    max_shipping_cost: props.settings.shipping?.max_shipping_cost || null,
    additional_item_cost_mode: props.settings.shipping?.additional_item_cost_mode || 'percentage',
    additional_item_cost_percent: props.settings.shipping?.additional_item_cost_percent ?? 50,
    additional_item_cost_fixed: props.settings.shipping?.additional_item_cost_fixed ?? 3.00,
})

const saveShipping = () => {
    shippingForm.put(route('fulfillment.settings.shipping'))
}

// --- Labels Form ---
const labelsForm = useForm({
    label_format: props.settings.labels?.label_format || 'PDF',
    label_size: props.settings.labels?.label_size || '4x6',
    include_packing_slip: props.settings.labels?.include_packing_slip ?? true,
    packing_slip_notes: props.settings.labels?.packing_slip_notes || '',
    include_return_label: props.settings.labels?.include_return_label || false,
    include_logo_on_slip: props.settings.labels?.include_logo_on_slip ?? true,
    custom_footer_text: props.settings.labels?.custom_footer_text || '',
})

const saveLabels = () => {
    labelsForm.put(route('fulfillment.settings.labels'))
}

// --- Workflow Form ---
const workflowForm = useForm({
    auto_assign_enabled: props.settings.workflow?.auto_assign_enabled || false,
    auto_assign_by: props.settings.workflow?.auto_assign_by || 'location',
    auto_upload_tracking: props.settings.workflow?.auto_upload_tracking ?? true,
    order_sync_interval_minutes: props.settings.workflow?.order_sync_interval_minutes || 5,
    dropship_sla_hours: props.settings.workflow?.dropship_sla_hours || 48,
    priority_escalation_hours: props.settings.workflow?.priority_escalation_hours || 24,
    auto_mark_shipped_on_label: props.settings.workflow?.auto_mark_shipped_on_label || false,
    require_weight_on_pack: props.settings.workflow?.require_weight_on_pack ?? true,
    allow_partial_shipments: props.settings.workflow?.allow_partial_shipments ?? true,
})

const saveWorkflow = () => {
    workflowForm.put(route('fulfillment.settings.workflow'))
}

// --- Returns Form ---
const returnsForm = useForm({
    return_window_days: props.settings.returns?.return_window_days || 30,
    restocking_fee_percent: props.settings.returns?.restocking_fee_percent || 0,
    auto_approve_enabled: props.settings.returns?.auto_approve_enabled || false,
    auto_approve_under: props.settings.returns?.auto_approve_under || 25,
    require_photos: props.settings.returns?.require_photos || false,
    return_reasons: props.settings.returns?.return_reasons || ['Defective', 'Wrong item', 'Not as described', 'Changed mind', 'Other'],
    default_refund_method: props.settings.returns?.default_refund_method || 'original_payment',
    auto_restock_on_receive: props.settings.returns?.auto_restock_on_receive || false,
})

const newReturnReason = ref('')

const addReturnReason = () => {
    if (newReturnReason.value.trim() && !returnsForm.return_reasons.includes(newReturnReason.value.trim())) {
        returnsForm.return_reasons.push(newReturnReason.value.trim())
        newReturnReason.value = ''
    }
}

const removeReturnReason = (index) => {
    returnsForm.return_reasons.splice(index, 1)
}

const saveReturns = () => {
    returnsForm.put(route('fulfillment.settings.returns'))
}

// --- Notifications Form ---
const notificationsForm = useForm({
    notify_on_order_import: props.settings.notifications?.notify_on_order_import ?? true,
    notify_on_ship: props.settings.notifications?.notify_on_ship ?? true,
    notify_on_delivery: props.settings.notifications?.notify_on_delivery || false,
    notify_on_return_request: props.settings.notifications?.notify_on_return_request ?? true,
    stale_shipment_alert_hours: props.settings.notifications?.stale_shipment_alert_hours || 72,
    sla_warning_hours: props.settings.notifications?.sla_warning_hours || 4,
    daily_summary_enabled: props.settings.notifications?.daily_summary_enabled || false,
    notification_channel: props.settings.notifications?.notification_channel || 'in_app',
})

const saveNotifications = () => {
    notificationsForm.put(route('fulfillment.settings.notifications'))
}

// --- Carriers ---
const syncingCarriers = ref(false)

const syncCarriers = () => {
    syncingCarriers.value = true
    router.post(route('fulfillment.settings.carriers.sync'), {}, {
        onFinish: () => { syncingCarriers.value = false },
    })
}

const toggleCarrier = (carrier, field) => {
    router.put(route('fulfillment.settings.carriers.update', carrier.id), {
        [field]: !carrier[field],
    }, { preserveScroll: true })
}

// --- Package Presets ---
const showPresetModal = ref(false)
const editingPreset = ref(null)

const presetForm = useForm({
    name: '',
    type: 'box',
    length: '',
    width: '',
    height: '',
    weight_oz: '',
    is_default: false,
})

const openPresetModal = (preset = null) => {
    editingPreset.value = preset
    if (preset) {
        presetForm.name = preset.name
        presetForm.type = preset.type
        presetForm.length = preset.length
        presetForm.width = preset.width
        presetForm.height = preset.height
        presetForm.weight_oz = preset.weight_oz || ''
        presetForm.is_default = preset.is_default
    } else {
        presetForm.reset()
    }
    showPresetModal.value = true
}

const closePresetModal = () => {
    showPresetModal.value = false
    editingPreset.value = null
    presetForm.reset()
}

const savePreset = () => {
    if (editingPreset.value) {
        presetForm.put(route('fulfillment.settings.presets.update', editingPreset.value.id), {
            onSuccess: () => closePresetModal(),
        })
    } else {
        presetForm.post(route('fulfillment.settings.presets.store'), {
            onSuccess: () => closePresetModal(),
        })
    }
}

const deletePreset = (preset) => {
    if (confirm('Are you sure you want to delete this package preset?')) {
        router.delete(route('fulfillment.settings.presets.destroy', preset.id))
    }
}

// --- Webhook URL Copy ---
const webhookCopied = ref(false)

const copyWebhookUrl = () => {
    navigator.clipboard.writeText(props.webhookUrl)
    webhookCopied.value = true
    setTimeout(() => { webhookCopied.value = false }, 2000)
}

// --- Carrier Preference Toggle ---
const togglePreferredCarrier = (carrier) => {
    const idx = shippingForm.preferred_carriers.indexOf(carrier)
    if (idx === -1) {
        shippingForm.preferred_carriers.push(carrier)
    } else {
        shippingForm.preferred_carriers.splice(idx, 1)
    }
}

const carrierOptions = ['USPS', 'UPS', 'FedEx', 'DHL']

// --- Shipping Provider ---
const providerForm = useForm({
    provider: props.shippingProvider || 'easypost',
})

const saveProvider = () => {
    providerForm.put(route('fulfillment.settings.provider'))
}

// --- ShipStation Credentials ---
const shipstationForm = useForm({
    api_key: '',
    api_secret: '',
})

const testingShipStation = ref(false)

const saveShipStation = () => {
    shipstationForm.post(route('fulfillment.settings.shipstation'), {
        onFinish: () => { testingShipStation.value = false },
    })
}

// --- ShipStation Webhook URL Copy ---
const shipstationWebhookCopied = ref(false)

const copyShipStationWebhookUrl = () => {
    navigator.clipboard.writeText(props.shipstationWebhookUrl)
    shipstationWebhookCopied.value = true
    setTimeout(() => { shipstationWebhookCopied.value = false }, 2000)
}

// --- ShippingEasy Credentials ---
const shippingEasyForm = useForm({
    api_key: '',
    api_secret: '',
    store_api_key: '',
})

const saveShippingEasy = () => {
    shippingEasyForm.post(route('fulfillment.settings.shippingeasy'))
}

// --- ShippingEasy Webhook URL Copy ---
const shippingEasyWebhookCopied = ref(false)

const copyShippingEasyWebhookUrl = () => {
    navigator.clipboard.writeText(props.shippingEasyWebhookUrl)
    shippingEasyWebhookCopied.value = true
    setTimeout(() => { shippingEasyWebhookCopied.value = false }, 2000)
}
</script>

<template>
    <AppLayout title="Fulfillment Settings" pageTitle="Fulfillment Settings" pageDescription="Configure shipping, labels, workflow, and automation">
        <div class="flex gap-6">
            <!-- Left Sidebar - Tab Navigation -->
            <div class="w-56 flex-shrink-0">
                <nav class="space-y-1 sticky top-24">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            activeTab === tab.id
                                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-l-2 border-blue-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-200',
                            'group flex items-center px-3 py-2 text-sm font-medium rounded-r-lg w-full text-left transition-colors'
                        ]"
                    >
                        <i :class="[tab.icon, 'mr-3 text-sm']"></i>
                        {{ tab.name }}
                    </button>
                </nav>
            </div>

            <!-- Right Content Area -->
            <div class="flex-1 min-w-0">
                <!-- Carriers Tab -->
                <div v-show="activeTab === 'carriers'" class="space-y-6">
                    <!-- Shipping Provider Selection -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Shipping Provider</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Select your shipping provider for rate shopping and label purchasing.</p>

                        <form @submit.prevent="saveProvider" class="space-y-4">
                            <div class="grid grid-cols-3 gap-4">
                                <label :class="[
                                    'flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition-colors',
                                    providerForm.provider === 'easypost'
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-400'
                                        : 'border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'
                                ]">
                                    <input type="radio" v-model="providerForm.provider" value="easypost" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">EasyPost</span>
                                        <span v-if="easypostConnected" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Connected</span>
                                        <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Not connected</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Multi-carrier API with USPS, UPS, FedEx, and more.</p>
                                    </div>
                                </label>
                                <label :class="[
                                    'flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition-colors',
                                    providerForm.provider === 'shipstation'
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-400'
                                        : 'border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'
                                ]">
                                    <input type="radio" v-model="providerForm.provider" value="shipstation" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">ShipStation</span>
                                        <span v-if="shipstationConnected" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Connected</span>
                                        <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Not connected</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Shipping platform with carrier discounts and automation.</p>
                                    </div>
                                </label>
                                <label :class="[
                                    'flex items-center gap-3 p-4 border rounded-lg cursor-pointer transition-colors',
                                    providerForm.provider === 'shippingeasy'
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-400'
                                        : 'border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'
                                ]">
                                    <input type="radio" v-model="providerForm.provider" value="shippingeasy" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">ShippingEasy</span>
                                        <span v-if="shippingEasyConnected" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Connected</span>
                                        <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Not connected</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Shipping solution with discounted rates and order management.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="providerForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    {{ providerForm.processing ? 'Saving...' : 'Save Provider' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ShipStation Credentials -->
                    <div v-if="providerForm.provider === 'shipstation'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">ShipStation Credentials</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter your ShipStation API Key and API Secret. Find these under Settings > API Keys in your ShipStation dashboard.</p>

                        <form @submit.prevent="saveShipStation" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Key</label>
                                    <input v-model="shipstationForm.api_key" type="text" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Enter API Key">
                                    <p v-if="shipstationForm.errors.api_key" class="text-red-500 text-xs mt-1">{{ shipstationForm.errors.api_key }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Secret</label>
                                    <input v-model="shipstationForm.api_secret" type="password" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Enter API Secret">
                                    <p v-if="shipstationForm.errors.api_secret" class="text-red-500 text-xs mt-1">{{ shipstationForm.errors.api_secret }}</p>
                                </div>
                            </div>
                            <p v-if="shipstationForm.errors.shipstation" class="text-red-500 text-sm">{{ shipstationForm.errors.shipstation }}</p>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="shipstationForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    {{ shipstationForm.processing ? 'Testing & Saving...' : 'Test Connection & Save' }}
                                </button>
                            </div>
                        </form>

                        <!-- ShipStation Webhook URL -->
                        <div v-if="shipstationConnected" class="mt-6 bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ShipStation Webhook URL</label>
                            <div class="flex items-center gap-2">
                                <input :value="shipstationWebhookUrl" type="text" readonly class="flex-1 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-400 text-sm bg-white font-mono">
                                <button type="button" @click="copyShipStationWebhookUrl" class="px-3 py-2 bg-white dark:bg-slate-600 border border-gray-300 dark:border-slate-500 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-slate-500 transition-colors">
                                    <i :class="shipstationWebhookCopied ? 'fas fa-check text-green-600' : 'fas fa-copy'"></i>
                                    {{ shipstationWebhookCopied ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Add this URL in your ShipStation dashboard under Settings > Integrations > Webhook to receive shipment notifications.</p>
                        </div>
                    </div>

                    <!-- ShippingEasy Credentials -->
                    <div v-if="providerForm.provider === 'shippingeasy'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">ShippingEasy Credentials</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter your ShippingEasy API Key and API Secret. Find these under Settings > API Credentials in your ShippingEasy dashboard.</p>

                        <form @submit.prevent="saveShippingEasy" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Key</label>
                                    <input v-model="shippingEasyForm.api_key" type="text" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Enter API Key">
                                    <p v-if="shippingEasyForm.errors.api_key" class="text-red-500 text-xs mt-1">{{ shippingEasyForm.errors.api_key }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Secret</label>
                                    <input v-model="shippingEasyForm.api_secret" type="password" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Enter API Secret">
                                    <p v-if="shippingEasyForm.errors.api_secret" class="text-red-500 text-xs mt-1">{{ shippingEasyForm.errors.api_secret }}</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store API Key</label>
                                <input v-model="shippingEasyForm.store_api_key" type="text" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Enter Store API Key">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Find this under Settings > Stores in your ShippingEasy dashboard. Required for creating orders and purchasing labels.</p>
                                <p v-if="shippingEasyForm.errors.store_api_key" class="text-red-500 text-xs mt-1">{{ shippingEasyForm.errors.store_api_key }}</p>
                            </div>
                            <p v-if="shippingEasyForm.errors.shippingeasy" class="text-red-500 text-sm">{{ shippingEasyForm.errors.shippingeasy }}</p>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="shippingEasyForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    {{ shippingEasyForm.processing ? 'Testing & Saving...' : 'Test Connection & Save' }}
                                </button>
                            </div>
                        </form>

                        <!-- ShippingEasy Webhook URL -->
                        <div v-if="shippingEasyConnected" class="mt-6 bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ShippingEasy Webhook URL</label>
                            <div class="flex items-center gap-2">
                                <input :value="shippingEasyWebhookUrl" type="text" readonly class="flex-1 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-400 text-sm bg-white font-mono">
                                <button type="button" @click="copyShippingEasyWebhookUrl" class="px-3 py-2 bg-white dark:bg-slate-600 border border-gray-300 dark:border-slate-500 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-slate-500 transition-colors">
                                    <i :class="shippingEasyWebhookCopied ? 'fas fa-check text-green-600' : 'fas fa-copy'"></i>
                                    {{ shippingEasyWebhookCopied ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Add this URL in your ShippingEasy dashboard under Settings > Webhooks to receive shipment notifications.</p>
                        </div>
                    </div>

                    <!-- Carrier Accounts -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Carrier Accounts</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage shipping carriers synced from your shipping provider.</p>
                        </div>
                        <button
                            @click="syncCarriers"
                            :disabled="syncingCarriers || !easypostConnected"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <i :class="syncingCarriers ? 'fas fa-spinner fa-spin' : 'fas fa-sync'" class="text-sm"></i>
                            Sync Carriers
                        </button>
                    </div>

                    <div v-if="!easypostConnected" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 mb-6">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400"></i>
                            <p class="text-sm text-yellow-800 dark:text-yellow-300">EasyPost is not connected. Add your EasyPost API key in Organization Integrations to enable carrier sync.</p>
                        </div>
                    </div>

                    <div v-if="carriers.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-truck text-4xl mb-3 opacity-30"></i>
                        <p class="text-sm">No carriers synced yet. Click "Sync Carriers" to pull accounts from EasyPost.</p>
                    </div>

                    <div v-else class="overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                            <thead class="bg-gray-50 dark:bg-slate-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Carrier</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">EasyPost ID</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Default</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                <tr v-for="carrier in carriers" :key="carrier.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ carrier.carrier }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ carrier.account_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 font-mono text-xs">{{ carrier.easypost_carrier_id || '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button @click="toggleCarrier(carrier, 'is_active')" :class="carrier.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors">
                                            <span :class="carrier.is_active ? 'translate-x-5' : 'translate-x-1'" class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"></span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button @click="toggleCarrier(carrier, 'is_default')" :class="carrier.is_default ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600'" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors">
                                            <span :class="carrier.is_default ? 'translate-x-5' : 'translate-x-1'" class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"></span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>

                <!-- Shipping Rules Tab -->
                <div v-show="activeTab === 'shipping'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Shipping Rules</h3>

                    <form @submit.prevent="saveShipping" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Carrier</label>
                                <select v-model="shippingForm.default_carrier" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                    <option v-for="c in carrierOptions" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Service</label>
                                <input v-model="shippingForm.default_service" type="text" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Priority Mail, Ground, etc.">
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Insurance</h4>
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="shippingForm.auto_insurance_enabled" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Auto-add insurance</span>
                                </label>
                                <div v-if="shippingForm.auto_insurance_enabled" class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">for orders over $</span>
                                    <input v-model.number="shippingForm.auto_insurance_threshold" type="number" step="0.01" min="0" class="w-24 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Signature Confirmation</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="shippingForm.signature_confirmation" value="none" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Never require signature</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="shippingForm.signature_confirmation" value="threshold" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Require for orders over $</span>
                                    </label>
                                    <input v-if="shippingForm.signature_confirmation === 'threshold'" v-model.number="shippingForm.signature_threshold" type="number" step="0.01" min="0" class="w-24 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="shippingForm.signature_confirmation" value="always" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Always require signature</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Preferences</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="shippingForm.saturday_delivery" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Include Saturday delivery options</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="shippingForm.always_use_cheapest" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Always auto-select cheapest rate</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Preferred Carriers</h4>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="c in carrierOptions" :key="c" class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" :checked="shippingForm.preferred_carriers.includes(c)" @change="togglePreferredCarrier(c)" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ c }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maximum Shipping Cost ($)</label>
                            <input v-model.number="shippingForm.max_shipping_cost" type="number" step="0.01" min="0" class="w-40 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="No limit">
                            <p class="text-xs text-gray-500 mt-1">Leave empty for no limit. Rates above this will be excluded.</p>
                        </div>

                        <!-- Combined Shipping (Additional Item Cost) -->
                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-1">Combined Shipping</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Set the additional item shipping cost for multi-item purchases on eBay.</p>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Calculation Mode</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" v-model="shippingForm.additional_item_cost_mode" value="percentage" class="text-blue-600 border-gray-300 dark:border-slate-600">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Percentage of shipping</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" v-model="shippingForm.additional_item_cost_mode" value="fixed" class="text-blue-600 border-gray-300 dark:border-slate-600">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Fixed amount</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="shippingForm.additional_item_cost_mode === 'percentage'">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Percentage of First Item Rate (%)</label>
                                    <input v-model.number="shippingForm.additional_item_cost_percent" type="number" step="1" min="0" max="100" class="w-32 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                    <p class="text-xs text-gray-500 mt-1">e.g. 50% of a $10.00 rate = $5.00 per additional item</p>
                                </div>

                                <div v-else>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fixed Additional Item Cost ($)</label>
                                    <input v-model.number="shippingForm.additional_item_cost_fixed" type="number" step="0.01" min="0" class="w-32 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Flat amount charged per additional item in a combined shipment</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="shippingForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {{ shippingForm.processing ? 'Saving...' : 'Save Shipping Rules' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Labels & Documents Tab -->
                <div v-show="activeTab === 'labels'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Labels & Documents</h3>

                    <form @submit.prevent="saveLabels" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label Format</label>
                                <div class="flex gap-4 mt-2">
                                    <label v-for="fmt in ['PDF', 'PNG', 'ZPL']" :key="fmt" class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="labelsForm.label_format" :value="fmt" class="border-gray-300 dark:border-slate-600 text-blue-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ fmt }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label Size</label>
                                <select v-model="labelsForm.label_size" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                    <option value="4x6">4" x 6" (Thermal)</option>
                                    <option value="8.5x11">8.5" x 11" (Letter)</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Packing Slip</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="labelsForm.include_packing_slip" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Include packing slip with shipments</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="labelsForm.include_logo_on_slip" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Include organization logo on packing slip</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="labelsForm.include_return_label" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Include prepaid return label</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Packing Slip Notes</label>
                            <textarea v-model="labelsForm.packing_slip_notes" rows="3" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Notes printed on every packing slip..."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Footer Text</label>
                            <input v-model="labelsForm.custom_footer_text" type="text" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Thank you for your purchase!">
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="labelsForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {{ labelsForm.processing ? 'Saving...' : 'Save Label Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Workflow Tab -->
                <div v-show="activeTab === 'workflow'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Workflow Automation</h3>

                    <form @submit.prevent="saveWorkflow" class="space-y-6">
                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Order Assignment</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="workflowForm.auto_assign_enabled" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Auto-assign orders to locations</span>
                                </label>
                                <div v-if="workflowForm.auto_assign_enabled" class="ml-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assignment Strategy</label>
                                    <select v-model="workflowForm.auto_assign_by" class="w-48 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                        <option value="location">By inventory location</option>
                                        <option value="round_robin">Round robin</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Tracking & Shipping</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="workflowForm.auto_upload_tracking" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Auto-upload tracking to marketplace</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="workflowForm.auto_mark_shipped_on_label" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Auto-mark shipped when label purchased</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="workflowForm.allow_partial_shipments" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Allow partial shipments (split orders)</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Packing Requirements</h4>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="workflowForm.require_weight_on_pack" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Require weight entry before purchasing label</span>
                            </label>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Timing</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Order Sync Interval (min)</label>
                                    <input v-model.number="workflowForm.order_sync_interval_minutes" type="number" min="1" max="60" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dropship SLA (hours)</label>
                                    <input v-model.number="workflowForm.dropship_sla_hours" type="number" min="1" max="168" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Priority Escalation (hours)</label>
                                    <input v-model.number="workflowForm.priority_escalation_hours" type="number" min="1" max="72" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="workflowForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {{ workflowForm.processing ? 'Saving...' : 'Save Workflow Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Package Presets Tab -->
                <div v-show="activeTab === 'presets'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Package Presets</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Save common package sizes for quick selection during packing.</p>
                        </div>
                        <button @click="openPresetModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus text-sm"></i>
                            Add Preset
                        </button>
                    </div>

                    <div v-if="packagePresets.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-box text-4xl mb-3 opacity-30"></i>
                        <p class="text-sm">No package presets configured. Add common package sizes for faster packing.</p>
                    </div>

                    <div v-else class="overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                            <thead class="bg-gray-50 dark:bg-slate-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Dimensions (L x W x H)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tare Weight</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Default</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                <tr v-for="preset in packagePresets" :key="preset.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ preset.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 capitalize">{{ preset.type.replace('_', ' ') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ preset.length }}" x {{ preset.width }}" x {{ preset.height }}"</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ preset.weight_oz ? preset.weight_oz + ' oz' : '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="preset.is_default" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">Default</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="openPresetModal(preset)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 text-sm mr-3">Edit</button>
                                        <button @click="deletePreset(preset)" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Returns Tab -->
                <div v-show="activeTab === 'returns'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Return Policy</h3>

                    <form @submit.prevent="saveReturns" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Return Window (days)</label>
                                <input v-model.number="returnsForm.return_window_days" type="number" min="0" max="365" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                <p class="text-xs text-gray-500 mt-1">Days after delivery that returns are accepted.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Restocking Fee (%)</label>
                                <input v-model.number="returnsForm.restocking_fee_percent" type="number" min="0" max="100" step="0.1" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Auto-Approve</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="returnsForm.auto_approve_enabled" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Auto-approve returns</span>
                                </label>
                                <div v-if="returnsForm.auto_approve_enabled" class="ml-6 flex items-center gap-2">
                                    <span class="text-sm text-gray-500">for items under $</span>
                                    <input v-model.number="returnsForm.auto_approve_under" type="number" step="0.01" min="0" class="w-24 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Options</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="returnsForm.require_photos" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Require photos with return request</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="returnsForm.auto_restock_on_receive" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Auto-restock items when received</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Refund Method</label>
                            <select v-model="returnsForm.default_refund_method" class="w-64 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                <option value="original_payment">Original Payment Method</option>
                                <option value="store_credit">Store Credit</option>
                            </select>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Return Reasons</h4>
                            <div class="space-y-2 mb-3">
                                <div v-for="(reason, index) in returnsForm.return_reasons" :key="index" class="flex items-center gap-2">
                                    <span class="flex-1 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-slate-700 px-3 py-1.5 rounded-lg">{{ reason }}</span>
                                    <button @click="removeReturnReason(index)" class="text-red-500 hover:text-red-700 text-sm p-1">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="newReturnReason" @keyup.enter="addReturnReason" type="text" class="flex-1 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Add a reason...">
                                <button type="button" @click="addReturnReason" class="px-3 py-2 bg-gray-100 dark:bg-slate-600 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-200 dark:hover:bg-slate-500 transition-colors">Add</button>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="returnsForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {{ returnsForm.processing ? 'Saving...' : 'Save Return Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Webhooks & Notifications Tab -->
                <div v-show="activeTab === 'notifications'" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Webhooks & Notifications</h3>

                    <form @submit.prevent="saveNotifications" class="space-y-6">
                        <!-- Webhook URL -->
                        <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">EasyPost Webhook URL</label>
                            <div class="flex items-center gap-2">
                                <input :value="webhookUrl" type="text" readonly class="flex-1 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-400 text-sm bg-white font-mono">
                                <button type="button" @click="copyWebhookUrl" class="px-3 py-2 bg-white dark:bg-slate-600 border border-gray-300 dark:border-slate-500 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-slate-500 transition-colors">
                                    <i :class="webhookCopied ? 'fas fa-check text-green-600' : 'fas fa-copy'"></i>
                                    {{ webhookCopied ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Add this URL in your EasyPost dashboard under Webhooks to receive tracking updates.</p>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Event Notifications</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationsForm.notify_on_order_import" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Notify on new order import</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationsForm.notify_on_ship" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Notify when order ships</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationsForm.notify_on_delivery" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Notify on delivery confirmation</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationsForm.notify_on_return_request" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Notify on return request</span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Alert Thresholds</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Stale Shipment Alert (hours)</label>
                                    <input v-model.number="notificationsForm.stale_shipment_alert_hours" type="number" min="1" max="168" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Alert if no tracking updates after this many hours.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">SLA Warning (hours before deadline)</label>
                                    <input v-model.number="notificationsForm.sla_warning_hours" type="number" min="1" max="48" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Warn before dropship SLA deadline.</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Delivery</h4>
                            <div class="space-y-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="notificationsForm.daily_summary_enabled" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Send daily fulfillment summary</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notification Channel</label>
                                    <select v-model="notificationsForm.notification_channel" class="w-48 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                                        <option value="in_app">In-App Only</option>
                                        <option value="email">Email Only</option>
                                        <option value="both">Both</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" :disabled="notificationsForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                {{ notificationsForm.processing ? 'Saving...' : 'Save Notification Settings' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Package Preset Modal -->
        <div v-if="showPresetModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="closePresetModal"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ editingPreset ? 'Edit Package Preset' : 'New Package Preset' }}
                </h3>

                <form @submit.prevent="savePreset" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <input v-model="presetForm.name" type="text" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Small Flat Rate Box">
                        <p v-if="presetForm.errors.name" class="text-red-500 text-xs mt-1">{{ presetForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                        <select v-model="presetForm.type" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            <option value="box">Box</option>
                            <option value="envelope">Envelope</option>
                            <option value="soft_pack">Soft Pack</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Length (in)</label>
                            <input v-model.number="presetForm.length" type="number" step="0.01" min="0.1" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Width (in)</label>
                            <input v-model.number="presetForm.width" type="number" step="0.01" min="0.1" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Height (in)</label>
                            <input v-model.number="presetForm.height" type="number" step="0.01" min="0.1" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tare Weight (oz)</label>
                        <input v-model.number="presetForm.weight_oz" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" placeholder="Weight of empty packaging">
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="presetForm.is_default" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Set as default package</span>
                    </label>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="closePresetModal" class="px-4 py-2 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="presetForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                            {{ presetForm.processing ? 'Saving...' : (editingPreset ? 'Update' : 'Create') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
