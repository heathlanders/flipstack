<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    integrations: Array,
    availablePlatforms: Object,
    ebayConfig: Object,
    ebayAccounts: { type: Array, default: () => [] },
    shopifyConfig: Object,
    shopifyAccounts: { type: Array, default: () => [] },
    hasExtensionToken: Boolean,
    productCount: { type: Number, default: 0 },
    showEbayImportPrompt: { type: Boolean, default: false },
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const editingCredential = ref(null);

const addForm = useForm({
    platform: 'barcode_spider',
    environment: 'production',
    name: '',
    client_id: '',
    client_secret: '',
});

const editForm = useForm({
    name: '',
    client_id: '',
    client_secret: '',
    is_active: true,
});

const openAddModal = () => {
    addForm.reset();
    addForm.platform = 'barcode_spider';
    addForm.environment = 'production';
    showAddModal.value = true;
};

const addIntegration = () => {
    addForm.post(route('integrations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const openEditModal = (credential) => {
    editingCredential.value = credential;
    editForm.name = credential.name;
    editForm.is_active = credential.is_active;
    showEditModal.value = true;
};

const updateIntegration = () => {
    editForm.put(route('integrations.update', editingCredential.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            editingCredential.value = null;
        },
    });
};

const deleteIntegration = (credential) => {
    if (confirm('Are you sure you want to delete this integration? All associated tokens will be deactivated.')) {
        useForm({}).delete(route('integrations.destroy', credential.id), {
            preserveScroll: true,
        });
    }
};

const verifyCredentials = (credential) => {
    useForm({}).post(route('integrations.verify', credential.id), {
        preserveScroll: true,
    });
};

const getStatusBadge = (credential) => {
    if (!credential.is_complete) {
        return { text: 'Incomplete', class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' };
    }
    if (credential.has_active_token) {
        return { text: 'Connected', class: 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' };
    }
    return { text: 'Not Connected', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' };
};

// Filter out eBay and Shopify from the list since they have their own sections
const nonEbayIntegrations = () => {
    return props.integrations.filter(i => i.platform !== 'ebay' && i.platform !== 'shopify');
};

// Connect another eBay account
const connectingEbay = ref(false);
const ebayAccountName = ref('');

const connectEbay = (credentialId = null) => {
    connectingEbay.value = true;
    const data = {};
    if (credentialId) {
        data.credential_id = credentialId;
    }
    if (ebayAccountName.value) {
        data.name = ebayAccountName.value;
    }
    axios.post(route('integrations.connect-ebay'), data)
        .then(response => {
            if (response.data.redirect_url) {
                window.location.href = response.data.redirect_url;
            }
        })
        .catch(error => {
            alert(error.response?.data?.error || 'Failed to connect eBay account');
        })
        .finally(() => {
            connectingEbay.value = false;
            ebayAccountName.value = '';
        });
};

const togglingAutoSync = ref(null);
const toggleEbayAutoSync = (account) => {
    togglingAutoSync.value = account.id;
    useForm({}).post(route('marketplaces.ebay.credentials.auto-sync.toggle', account.id), {
        preserveScroll: true,
        onFinish: () => { togglingAutoSync.value = null; },
    });
};

const registeringWebhook = ref(null);
const registerEbayWebhook = (account) => {
    if (!confirm(`Register the eBay webhook for "${account.name}"? This calls the eBay Notification API.`)) return;
    registeringWebhook.value = account.id;
    useForm({}).post(route('marketplaces.ebay.credentials.register-notifications', account.id), {
        preserveScroll: true,
        onFinish: () => { registeringWebhook.value = null; },
    });
};

const formatRelative = (iso) => {
    if (!iso) return 'never';
    const diffSec = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    if (diffSec < 60) return `${diffSec}s ago`;
    if (diffSec < 3600) return `${Math.round(diffSec / 60)}m ago`;
    if (diffSec < 86400) return `${Math.round(diffSec / 3600)}h ago`;
    return `${Math.round(diffSec / 86400)}d ago`;
};

const lastSyncClass = (iso) => {
    if (!iso) return 'text-red-600 dark:text-red-400';
    const diffMin = (Date.now() - new Date(iso).getTime()) / 60000;
    if (diffMin > 30) return 'text-red-600 dark:text-red-400';
    if (diffMin > 10) return 'text-amber-600 dark:text-amber-400';
    return 'text-green-600 dark:text-green-400';
};

// Connect Shopify store
const showShopifyConnectModal = ref(false);
const shopifyDomain = ref('');
const connectingShopify = ref(false);

const connectShopifyStore = () => {
    connectingShopify.value = true;
    useForm({ shop_domain: shopifyDomain.value }).post(route('shopify.install'), {
        onFinish: () => {
            connectingShopify.value = false;
            showShopifyConnectModal.value = false;
            shopifyDomain.value = '';
        },
    });
};

// Chrome Extension token management
const extensionTokenConfigured = ref(props.hasExtensionToken);
const extensionToken = ref('');
const extensionGenerating = ref(false);
const extensionRevoking = ref(false);
const extensionTokenCopied = ref(false);

const generateExtensionToken = () => {
    extensionGenerating.value = true;
    extensionToken.value = '';
    axios.post(route('organization.extension-token.generate'))
        .then(response => {
            extensionToken.value = response.data.token;
            extensionTokenConfigured.value = true;
        })
        .catch(() => {
            alert('Failed to generate token.');
        })
        .finally(() => {
            extensionGenerating.value = false;
        });
};

const revokeExtensionToken = () => {
    if (!confirm('Revoke the extension token? The extension will lose access until you generate a new one.')) return;
    extensionRevoking.value = true;
    axios.delete(route('organization.extension-token.revoke'))
        .then(() => {
            extensionTokenConfigured.value = false;
            extensionToken.value = '';
        })
        .catch(() => {
            alert('Failed to revoke token.');
        })
        .finally(() => {
            extensionRevoking.value = false;
        });
};

const copyExtensionToken = () => {
    navigator.clipboard.writeText(extensionToken.value).then(() => {
        extensionTokenCopied.value = true;
        setTimeout(() => { extensionTokenCopied.value = false; }, 2000);
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Platform Integrations
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Connect your e-commerce platforms to manage inventory and orders
                </p>
            </div>
            <PrimaryButton @click="openAddModal">
                Add Integration
            </PrimaryButton>
        </div>

        <!-- eBay Accounts Section -->
        <div v-if="ebayConfig?.configured" class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">eBay Accounts</h4>
                    <span
                        :class="[
                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                            ebayConfig.is_sandbox
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100'
                                : 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100'
                        ]"
                    >
                        {{ ebayConfig.is_sandbox ? 'Sandbox' : 'Production' }}
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <TextInput
                        v-model="ebayAccountName"
                        type="text"
                        class="text-sm h-9 w-48"
                        placeholder="Account name (optional)"
                    />
                    <PrimaryButton
                        @click="connectEbay()"
                        :disabled="connectingEbay"
                        class="text-sm whitespace-nowrap"
                    >
                        {{ connectingEbay ? 'Connecting...' : '+ Connect eBay Account' }}
                    </PrimaryButton>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                    v-for="account in ebayAccounts"
                    :key="account.id"
                    class="border-2 border-indigo-200 dark:border-indigo-700 rounded-lg p-4 bg-indigo-50 dark:bg-indigo-900/20"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ account.name }}
                                </h5>
                                <span
                                    v-if="account.has_active_token"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100"
                                >
                                    Connected
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100"
                                >
                                    Not Connected
                                </span>
                            </div>
                            <p v-if="account.seller_username" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Seller: {{ account.seller_username }}
                            </p>
                            <div class="mt-2 space-y-1 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Auto-sync orders</span>
                                    <button
                                        type="button"
                                        @click="toggleEbayAutoSync(account)"
                                        :disabled="togglingAutoSync === account.id"
                                        :class="[
                                            'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                                            account.auto_sync_enabled ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600',
                                            togglingAutoSync === account.id ? 'opacity-50' : ''
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-3 w-3 transform rounded-full bg-white transition',
                                                account.auto_sync_enabled ? 'translate-x-5' : 'translate-x-1'
                                            ]"
                                        />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Last poll</span>
                                    <span :class="lastSyncClass(account.last_full_sync_at)">{{ formatRelative(account.last_full_sync_at) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Webhook (real-time)</span>
                                    <span v-if="account.webhook_registered" class="text-green-600 dark:text-green-400">Registered</span>
                                    <button
                                        v-else
                                        type="button"
                                        @click="registerEbayWebhook(account)"
                                        :disabled="registeringWebhook === account.id"
                                        class="text-indigo-600 dark:text-indigo-400 underline disabled:opacity-50"
                                    >
                                        {{ registeringWebhook === account.id ? 'Registering...' : 'Register' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-1 ml-2 flex-shrink-0">
                            <Link
                                :href="route('marketplaces.ebay.setup')"
                                class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-md transition-colors"
                            >
                                Manage
                            </Link>
                            <SecondaryButton
                                v-if="!account.has_active_token"
                                @click="connectEbay(account.id)"
                                :disabled="connectingEbay"
                                class="text-xs"
                            >
                                Reconnect
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="ebayAccounts.length === 0" class="border border-dashed border-indigo-300 dark:border-indigo-600 rounded-lg p-6 text-center bg-indigo-50/50 dark:bg-indigo-900/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No eBay accounts connected. Click "Connect eBay Account" to get started.
                </p>
            </div>
        </div>

        <!-- Shopify Stores Section -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">Shopify Stores</h4>
                <div class="flex items-center space-x-2">
                    <Link
                        :href="route('marketplaces.shopify.index')"
                        class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md transition-colors"
                    >
                        {{ shopifyAccounts.length > 0 ? 'Manage Stores' : '+ Connect Shopify Store' }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                    v-for="store in shopifyAccounts"
                    :key="store.id"
                    class="border-2 border-green-200 dark:border-green-700 rounded-lg p-4 bg-green-50 dark:bg-green-900/20"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ store.name }}
                                </h5>
                                <span
                                    v-if="store.has_active_token"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100"
                                >
                                    Connected
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100"
                                >
                                    Not Connected
                                </span>
                            </div>
                            <p v-if="store.shop_domain" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ store.shop_domain }}
                            </p>
                            <p v-if="store.auto_sync_enabled" class="mt-0.5 text-xs text-green-600 dark:text-green-400">
                                Auto-sync enabled
                            </p>
                        </div>
                        <Link
                            :href="route('marketplaces.shopify.index')"
                            class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md transition-colors ml-2 flex-shrink-0"
                        >
                            Manage
                        </Link>
                    </div>
                </div>
            </div>

            <div v-if="shopifyAccounts.length === 0" class="border border-dashed border-green-300 dark:border-green-600 rounded-lg p-6 text-center bg-green-50/50 dark:bg-green-900/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No Shopify stores connected.
                    <span v-if="!shopifyConfig?.configured" class="text-amber-600 dark:text-amber-400">
                        Shopify API credentials must be configured in the environment first.
                    </span>
                </p>
            </div>
        </div>

        <!-- Coming Soon Marketplaces -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Amazon Card (Coming Soon) -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 bg-gray-50 dark:bg-gray-800/50 opacity-75">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Amazon
                            </h4>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Coming Soon
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Connect to Amazon Seller Central
                        </p>
                    </div>
                </div>
            </div>

            <!-- Newegg Card (Coming Soon) -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 bg-gray-50 dark:bg-gray-800/50 opacity-75">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Newegg
                            </h4>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Coming Soon
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Connect to Newegg Marketplace
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chrome Extension Section -->
        <div class="border-2 border-amber-200 dark:border-amber-700 rounded-lg p-6 bg-amber-50 dark:bg-amber-900/20">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            eBay Research Chrome Extension
                        </h4>
                        <span
                            v-if="extensionTokenConfigured"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100"
                        >
                            Token Configured
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                        >
                            Not Configured
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Extract eBay sold listing data directly from search results and send it to FlipStack for price verification.
                    </p>
                </div>
            </div>

            <!-- Installation Steps -->
            <div class="mt-4 bg-white dark:bg-gray-800 rounded-md p-4 border border-amber-100 dark:border-amber-800">
                <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Installation</h5>
                <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li>
                        <a :href="route('organization.extension-download')" class="text-indigo-600 dark:text-indigo-400 underline font-medium">Download the extension</a>
                        and unzip the folder
                    </li>
                    <li>Open <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs">chrome://extensions</code> in Chrome and enable <strong>Developer Mode</strong> (top right)</li>
                    <li>Click <strong>Load unpacked</strong> and select the unzipped folder</li>
                </ol>
            </div>

            <!-- API Token Section -->
            <div class="mt-4 bg-white dark:bg-gray-800 rounded-md p-4 border border-amber-100 dark:border-amber-800">
                <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">API Token</h5>

                <!-- Generated token display -->
                <div v-if="extensionToken" class="mb-3">
                    <p class="text-xs text-amber-700 dark:text-amber-400 mb-2 font-medium">
                        Copy this token now -- it will not be shown again.
                    </p>
                    <div class="flex items-center space-x-2">
                        <code class="flex-1 block px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded text-xs break-all select-all text-gray-800 dark:text-gray-200">{{ extensionToken }}</code>
                        <SecondaryButton @click="copyExtensionToken" class="text-sm whitespace-nowrap">
                            {{ extensionTokenCopied ? 'Copied' : 'Copy' }}
                        </SecondaryButton>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <PrimaryButton
                        v-if="!extensionTokenConfigured"
                        @click="generateExtensionToken"
                        :disabled="extensionGenerating"
                        :class="{ 'opacity-50': extensionGenerating }"
                    >
                        {{ extensionGenerating ? 'Generating...' : 'Generate Token' }}
                    </PrimaryButton>

                    <template v-if="extensionTokenConfigured">
                        <SecondaryButton
                            @click="generateExtensionToken"
                            :disabled="extensionGenerating"
                            :class="{ 'opacity-50': extensionGenerating }"
                        >
                            {{ extensionGenerating ? 'Regenerating...' : 'Regenerate Token' }}
                        </SecondaryButton>
                        <DangerButton
                            @click="revokeExtensionToken"
                            :disabled="extensionRevoking"
                            :class="{ 'opacity-50': extensionRevoking }"
                            class="text-sm"
                        >
                            Revoke
                        </DangerButton>
                    </template>
                </div>

                <p v-if="extensionTokenConfigured && !extensionToken" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Open the extension popup, go to the Settings tab, and paste your FlipStack URL and API token.
                </p>
            </div>
        </div>

        <!-- API Key Integrations -->
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">
                API Integrations
            </h4>
            <div class="grid grid-cols-1 gap-4">
                <div
                    v-for="integration in nonEbayIntegrations()"
                    :key="integration.id"
                    class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ integration.name }}
                                </h4>
                                <span
                                    :class="[
                                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                        getStatusBadge(integration).class
                                    ]"
                                >
                                    {{ getStatusBadge(integration).text }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ availablePlatforms[integration.platform]?.description }}
                            </p>
                            <p v-if="integration.last_verified_at" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Last verified: {{ new Date(integration.last_verified_at).toLocaleString() }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <SecondaryButton
                                v-if="integration.platform !== 'gemini'"
                                @click="verifyCredentials(integration)"
                                class="text-sm"
                            >
                                Verify
                            </SecondaryButton>

                            <SecondaryButton
                                @click="openEditModal(integration)"
                                class="text-sm"
                            >
                                Edit
                            </SecondaryButton>

                            <DangerButton
                                @click="deleteIntegration(integration)"
                                class="text-sm"
                            >
                                Delete
                            </DangerButton>
                        </div>
                    </div>
                </div>

                <div v-if="nonEbayIntegrations().length === 0" class="text-center py-8 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        No API integrations configured. Click "Add Integration" to connect Barcode Spider, Gemini, or EasyPost.
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Integration Modal (for API-key integrations) -->
        <DialogModal :show="showAddModal" @close="showAddModal = false">
            <template #title>
                Add Integration
            </template>

            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="platform" value="Platform" />
                        <select
                            id="platform"
                            v-model="addForm.platform"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        >
                            <option value="barcode_spider">Barcode Spider</option>
                            <option value="gemini">Gemini AI Research</option>
                            <option value="easypost">EasyPost Shipping</option>
                        </select>
                        <InputError :message="addForm.errors.platform" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="name" value="Name (Optional)" />
                        <TextInput
                            id="name"
                            v-model="addForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="addForm.platform === 'barcode_spider' ? 'e.g., Barcode Spider UPC Lookup' : 'e.g., Gemini AI Research'"
                        />
                        <InputError :message="addForm.errors.name" class="mt-2" />
                    </div>

                    <div v-if="addForm.platform === 'barcode_spider'">
                        <InputLabel for="client_id" value="API Key" />
                        <TextInput
                            id="client_id"
                            v-model="addForm.client_id"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="addForm.errors.client_id" class="mt-2" />
                    </div>

                    <div v-if="addForm.platform === 'gemini'">
                        <InputLabel for="gemini_api_key" value="Gemini API Key" />
                        <TextInput
                            id="gemini_api_key"
                            v-model="addForm.client_secret"
                            type="password"
                            class="mt-1 block w-full"
                            placeholder="AIza..."
                            required
                        />
                        <InputError :message="addForm.errors.client_secret" class="mt-2" />
                    </div>

                    <div v-if="addForm.platform === 'easypost'">
                        <InputLabel for="easypost_api_key" value="EasyPost API Key" />
                        <TextInput
                            id="easypost_api_key"
                            v-model="addForm.client_secret"
                            type="password"
                            class="mt-1 block w-full"
                            placeholder="EZ..."
                            required
                        />
                        <InputError :message="addForm.errors.client_secret" class="mt-2" />
                    </div>

                    <div v-if="addForm.platform === 'barcode_spider'" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Note:</strong> Get your free API key (100 lookups/day) from
                            <a href="https://www.barcodespider.com/" target="_blank" class="underline">
                                Barcode Spider
                            </a>
                        </p>
                    </div>

                    <div v-if="addForm.platform === 'gemini'" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    Free Tier: 1,500 requests/day
                                </h3>
                                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                    <p class="mb-2">Get your free API key from <a href="https://aistudio.google.com/apikey" target="_blank" class="underline font-medium">Google AI Studio</a></p>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>AI-powered product research for NCR IDs, part numbers, SKUs</li>
                                        <li>eBay sold listings analysis (last 90 days)</li>
                                        <li>SEO-optimized titles and keywords</li>
                                        <li>Pricing recommendations with market intelligence</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="addForm.platform === 'easypost'" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    Multi-Carrier Shipping Platform
                                </h3>
                                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                    <p class="mb-2">Get your API key from <a href="https://www.easypost.com/account/keys" target="_blank" class="underline font-medium">EasyPost Dashboard</a></p>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Rate shopping across USPS, UPS, FedEx, DHL, and more</li>
                                        <li>Discounted shipping rates (up to 89% off retail)</li>
                                        <li>Automated tracking and delivery notifications</li>
                                        <li>Label purchase and address verification</li>
                                    </ul>
                                    <p class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                                        Use a test key (starts with "EZTK") for sandbox mode, or a production key for live shipping.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showAddModal = false">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': addForm.processing }"
                    :disabled="addForm.processing"
                    @click="addIntegration"
                >
                    Add Integration
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Edit Integration Modal -->
        <DialogModal :show="showEditModal" @close="showEditModal = false">
            <template #title>
                Edit Integration
            </template>

            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="edit_name" value="Name" />
                        <TextInput
                            id="edit_name"
                            v-model="editForm.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="editForm.errors.name" class="mt-2" />
                    </div>

                    <div v-if="editingCredential?.platform !== 'gemini'">
                        <InputLabel for="edit_client_id" value="Client ID / App ID (Optional - leave blank to keep current)" />
                        <TextInput
                            id="edit_client_id"
                            v-model="editForm.client_id"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Leave blank to keep current"
                        />
                        <InputError :message="editForm.errors.client_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_client_secret"
                            :value="editingCredential?.platform === 'gemini' ? 'API Key (Optional - leave blank to keep current)' : 'Client Secret / Cert ID (Optional - leave blank to keep current)'"
                        />
                        <TextInput
                            id="edit_client_secret"
                            v-model="editForm.client_secret"
                            type="password"
                            class="mt-1 block w-full"
                            placeholder="Leave blank to keep current"
                        />
                        <InputError :message="editForm.errors.client_secret" class="mt-2" />
                    </div>

                    <div class="flex items-center">
                        <input
                            id="edit_is_active"
                            v-model="editForm.is_active"
                            type="checkbox"
                            class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                        />
                        <InputLabel for="edit_is_active" value="Active" class="ml-2" />
                    </div>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showEditModal = false">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': editForm.processing }"
                    :disabled="editForm.processing"
                    @click="updateIntegration"
                >
                    Update Integration
                </PrimaryButton>
            </template>
        </DialogModal>
    </div>
</template>
