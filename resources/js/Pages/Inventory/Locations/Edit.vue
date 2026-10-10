<template>
    <AppLayout :title="`Edit ${location.name}`">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('inventory.locations.show', location.id)"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Edit Location
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <form @submit.prevent="submit" class="p-6">
                        <div class="space-y-6">
                            <!-- Basic Information -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            :class="{ 'border-red-500': form.errors.name }"
                                            required
                                        />
                                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Code <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.code"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            :class="{ 'border-red-500': form.errors.code }"
                                            maxlength="50"
                                            required
                                        />
                                        <p v-if="form.errors.code" class="mt-1 text-sm text-red-500">{{ form.errors.code }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Type <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="form.type"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        >
                                            <option value="">Select Type</option>
                                            <option v-for="(label, value) in types" :key="value" :value="value">
                                                {{ label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-500">{{ form.errors.type }}</p>
                                    </div>

                                    <div class="flex items-center space-x-6 pt-6">
                                        <label class="flex items-center">
                                            <input
                                                v-model="form.is_active"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                                        </label>

                                        <label class="flex items-center">
                                            <input
                                                v-model="form.is_default"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Default Location</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Address Section -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Address</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Address Line 1
                                        </label>
                                        <input
                                            v-model="form.address_line1"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Address Line 2
                                        </label>
                                        <input
                                            v-model="form.address_line2"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                City
                                            </label>
                                            <input
                                                v-model="form.city"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                State
                                            </label>
                                            <input
                                                v-model="form.state"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Postal Code
                                            </label>
                                            <input
                                                v-model="form.postal_code"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Country
                                            </label>
                                            <input
                                                v-model="form.country"
                                                type="text"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Section -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Contact Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Contact Person
                                        </label>
                                        <input
                                            v-model="form.contact_person"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Email
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-500">{{ form.errors.email }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Phone
                                        </label>
                                        <input
                                            v-model="form.phone"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- Shipping Configuration -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Shipping Configuration</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    Configure this location as a ship-from address for fulfillment.
                                </p>

                                <div class="space-y-4">
                                    <!-- Ship From Toggle -->
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Use as Ship-From Address
                                            </label>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Enable to use this location as the origin for shipping labels
                                            </p>
                                        </div>
                                        <input
                                            v-model="form.is_ship_from"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 h-5 w-5"
                                        />
                                    </div>

                                    <!-- Address Verification Status -->
                                    <div v-if="form.is_ship_from" class="flex items-center justify-between p-3 rounded-lg" :class="location.ship_from_verified ? 'bg-green-50 dark:bg-green-900/20' : 'bg-amber-50 dark:bg-amber-900/20'">
                                        <div class="flex items-center gap-2 text-sm" :class="location.ship_from_verified ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'">
                                            <i :class="location.ship_from_verified ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'"></i>
                                            <span v-if="location.ship_from_verified">Address verified by EasyPost</span>
                                            <span v-else>Address not verified - verify to use for shipping labels</span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="verifyAddress"
                                            :disabled="verifying || !form.address_line1 || !form.city || !form.state || !form.postal_code"
                                            class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                                            :class="verifying ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700 text-white'"
                                        >
                                            <span v-if="verifying">
                                                <i class="fas fa-spinner fa-spin mr-1"></i> Verifying...
                                            </span>
                                            <span v-else-if="location.ship_from_verified">
                                                Re-verify
                                            </span>
                                            <span v-else>
                                                Verify Address
                                            </span>
                                        </button>
                                    </div>
                                    <p v-if="$page.props.errors?.address" class="text-sm text-red-500">{{ $page.props.errors.address }}</p>

                                    <!-- Default Package Dimensions -->
                                    <div v-if="form.is_ship_from">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                            Default Package Dimensions
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            Pre-fill dimensions when packing orders from this location.
                                        </p>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                    Weight (oz)
                                                </label>
                                                <input
                                                    v-model="form.default_weight_oz"
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    placeholder="16"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                    Length (in)
                                                </label>
                                                <input
                                                    v-model="form.default_length"
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    placeholder="12"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                    Width (in)
                                                </label>
                                                <input
                                                    v-model="form.default_width"
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    placeholder="10"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                    Height (in)
                                                </label>
                                                <input
                                                    v-model="form.default_height"
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    placeholder="6"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Printer Assignments -->
                                    <div v-if="form.is_ship_from">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                            Printer Assignments
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            Assign printers for automated document printing at this location.
                                        </p>

                                        <div class="space-y-3">
                                            <!-- Shipping Label Printer -->
                                            <div class="flex items-center gap-4">
                                                <div class="flex-1">
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                        Shipping Label Printer
                                                    </label>
                                                    <select
                                                        v-model="form.shipping_label_printer_id"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    >
                                                        <option value="">None (download only)</option>
                                                        <option v-for="printer in printers" :key="printer.id" :value="printer.id">
                                                            {{ printer.name }} ({{ printer.driver }})
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="pt-4">
                                                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                        <input
                                                            v-model="form.auto_print_shipping_label"
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                            :disabled="!form.shipping_label_printer_id"
                                                        />
                                                        Auto-print
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Packing Slip Printer -->
                                            <div class="flex items-center gap-4">
                                                <div class="flex-1">
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                        Packing Slip Printer
                                                    </label>
                                                    <select
                                                        v-model="form.packing_slip_printer_id"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    >
                                                        <option value="">None (download only)</option>
                                                        <option v-for="printer in printers" :key="printer.id" :value="printer.id">
                                                            {{ printer.name }} ({{ printer.driver }})
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="pt-4">
                                                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                        <input
                                                            v-model="form.auto_print_packing_slip"
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                            :disabled="!form.packing_slip_printer_id"
                                                        />
                                                        Auto-print
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Pick List Printer -->
                                            <div class="flex items-center gap-4">
                                                <div class="flex-1">
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                        Pick List Printer
                                                    </label>
                                                    <select
                                                        v-model="form.pick_list_printer_id"
                                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    >
                                                        <option value="">None (download only)</option>
                                                        <option v-for="printer in printers" :key="printer.id" :value="printer.id">
                                                            {{ printer.name }} ({{ printer.driver }})
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="pt-4">
                                                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                        <input
                                                            v-model="form.auto_print_pick_list"
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                            :disabled="!form.pick_list_printer_id"
                                                        />
                                                        Auto-print
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <p v-if="printers.length === 0" class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                                            No printers configured. Add printers in Organization Settings to enable auto-printing.
                                        </p>
                                    </div>
                                </div>
                            </div>

                        <!-- Form Actions -->
                        <div class="mt-6 flex justify-end space-x-3 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <Link
                                :href="route('inventory.locations.show', location.id)"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Saving...
                                </span>
                                <span v-else>
                                    Save Changes
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    location: {
        type: Object,
        required: true
    },
    types: {
        type: Object,
        default: () => ({})
    },
    printers: {
        type: Array,
        default: () => []
    }
})

const form = useForm({
    name: props.location.name || '',
    code: props.location.code || '',
    type: props.location.type || 'warehouse',
    address_line1: props.location.address_line1 || '',
    address_line2: props.location.address_line2 || '',
    city: props.location.city || '',
    state: props.location.state || '',
    postal_code: props.location.postal_code || '',
    country: props.location.country || '',
    contact_person: props.location.contact_person || '',
    email: props.location.email || '',
    phone: props.location.phone || '',
    is_active: props.location.is_active !== undefined ? props.location.is_active : true,
    is_default: props.location.is_default || false,
    // Shipping configuration
    is_ship_from: props.location.is_ship_from || false,
    default_weight_oz: props.location.default_weight_oz || '',
    default_length: props.location.default_length || '',
    default_width: props.location.default_width || '',
    default_height: props.location.default_height || '',
    // Printer assignments
    shipping_label_printer_id: props.location.shipping_label_printer_id || '',
    packing_slip_printer_id: props.location.packing_slip_printer_id || '',
    pick_list_printer_id: props.location.pick_list_printer_id || '',
    auto_print_shipping_label: props.location.auto_print_shipping_label || false,
    auto_print_packing_slip: props.location.auto_print_packing_slip || false,
    auto_print_pick_list: props.location.auto_print_pick_list || false,
})

const submit = () => {
    form.put(route('inventory.locations.update', props.location.id))
}

const verifying = ref(false)

const verifyAddress = () => {
    verifying.value = true
    router.post(route('fulfillment.locations.verify-address', props.location.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            verifying.value = false
        },
    })
}
</script>
