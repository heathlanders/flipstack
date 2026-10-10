<template>
    <AppLayout title="Add Product">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Add Product to Intake Queue
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Smart Lookup Results (NEW) -->
                <div v-if="lookupResults" class="mb-6 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                Product Found in System
                            </h3>
                            <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                <p><strong>{{ lookupResults.name }}</strong> by {{ lookupResults.brand }}</p>
                                <p class="mt-1">Existing conditions: 
                                    <span v-for="condition in lookupResults.existing_conditions" :key="condition" 
                                          class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">
                                        {{ condition }}
                                    </span>
                                </p>
                                <p v-if="lookupResults.has_new_condition" class="mt-2 text-amber-600 dark:text-amber-400">
                                    ⚠️ NEW condition already exists. Please specify the actual condition of your item.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">
                                
                                <!-- Supplier Selection (NEW) -->
                                <div>
                                    <InputLabel for="supplier_id" value="Supplier/Consigner *" />
                                    <select
                                        id="supplier_id"
                                        v-model="form.supplier_id"
                                        required
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    >
                                        <option value="">Select Supplier/Consigner</option>
                                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                            {{ supplier.name }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.supplier_id" class="mt-2" />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Who owns this product?</p>
                                </div>

                                <!-- NEW: Product Classification Section -->
                                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-900 border border-indigo-200 dark:border-indigo-700 rounded-lg p-4">
                                    <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-300 mb-4 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Product Classification (Helps AI Research)
                                    </h3>

                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Product Category -->
                                        <div>
                                            <InputLabel for="product_category" value="Product Category *" />
                                            <select
                                                id="product_category"
                                                v-model="form.product_category"
                                                required
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            >
                                                <option value="">Select Category</option>
                                                <option value="pos_equipment">POS Equipment</option>
                                                <option value="network_equipment">Network Equipment</option>
                                                <option value="computer_hardware">Computer Hardware</option>
                                                <option value="server_equipment">Server Equipment</option>
                                                <option value="security_equipment">Security Equipment</option>
                                                <option value="peripherals">Peripherals & Accessories</option>
                                                <option value="telecom_equipment">Telecom Equipment</option>
                                                <option value="industrial_equipment">Industrial Equipment</option>
                                                <option value="consumer_electronics">Consumer Electronics</option>
                                                <option value="medical_equipment">Medical Equipment</option>
                                                <option value="automotive_parts">Automotive Parts</option>
                                                <option value="other">Other/Unknown</option>
                                            </select>
                                            <InputError :message="form.errors.product_category" class="mt-2" />
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Helps AI use specialized prompting</p>
                                        </div>

                                        <!-- Product Type -->
                                        <div>
                                            <InputLabel for="product_type" value="Product Type *" />
                                            <select
                                                id="product_type"
                                                v-model="form.product_type"
                                                required
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            >
                                                <option value="">Select Type</option>
                                                <option value="complete_unit">Complete Unit/System</option>
                                                <option value="replacement_part">Replacement Part</option>
                                                <option value="component">Internal Component</option>
                                                <option value="accessory">Accessory/Add-on</option>
                                                <option value="consumable">Consumable Item</option>
                                                <option value="upgrade_module">Upgrade/Expansion Module</option>
                                                <option value="cable_connector">Cable/Connector</option>
                                                <option value="software_media">Software/Media</option>
                                                <option value="documentation">Documentation/Manual</option>
                                                <option value="packaging_only">Packaging Only</option>
                                            </select>
                                            <InputError :message="form.errors.product_type" class="mt-2" />
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Complete unit vs. part/component</p>
                                        </div>
                                    </div>

                                    <!-- Category-specific hints -->
                                    <div v-if="form.product_category" class="mt-3 p-3 bg-white dark:bg-gray-800 rounded border border-indigo-200 dark:border-indigo-600">
                                        <div class="text-xs text-indigo-700 dark:text-indigo-300">
                                            <strong>{{ getCategoryHint().title }}:</strong> {{ getCategoryHint().description }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Photo Upload (Enhanced) -->
                                <div>
                                    <InputLabel value="Product Photos *" />
                                    <div class="mt-2">
                                        <input
                                            type="file"
                                            @change="handlePhotoUpload"
                                            accept="image/*"
                                            multiple
                                            required
                                            class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-200"
                                        />
                                    </div>
                                    <InputError :message="form.errors.photos" class="mt-2" />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Take clear photos from multiple angles</p>
                                    
                                    <!-- Photo Preview (existing) -->
                                    <div v-if="form.photos.length > 0" class="mt-4 grid grid-cols-4 gap-4">
                                        <div v-for="(photo, index) in form.photos" :key="index" class="relative">
                                            <img :src="photo.preview" class="w-full h-32 object-cover rounded-lg" />
                                            <button type="button" @click="removePhoto(index)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Smart Identifier Lookup (Enhanced) -->
                                <div>
                                    <InputLabel for="primary_identifier" value="Barcode / UPC / EAN / Part Number" />
                                    <div class="flex gap-2">
                                        <TextInput
                                            id="primary_identifier"
                                            v-model="form.primary_identifier"
                                            type="text"
                                            class="mt-1 block w-full"
                                            placeholder="Scan or enter any identifier"
                                            @blur="performProductLookup"
                                        />
                                        <button 
                                            type="button" 
                                            @click="performProductLookup"
                                            :disabled="!form.primary_identifier || lookupLoading"
                                            class="mt-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            <svg v-if="lookupLoading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span v-else>Look Up</span>
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.primary_identifier" class="mt-2" />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Enter any identifier to check for existing products</p>
                                </div>

                                <!-- Additional Identifiers (Collapsible) -->
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <button 
                                        type="button"
                                        @click="showAdditionalIds = !showAdditionalIds"
                                        class="flex items-center justify-between w-full text-left"
                                    >
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Additional Identifiers</span>
                                        <svg :class="{ 'rotate-180': showAdditionalIds }" class="h-4 w-4 transform transition-transform text-gray-400">
                                            <path stroke="currentColor" stroke-width="2" d="M6 9l6 6 6-6" />
                                        </svg>
                                    </button>
                                    
                                    <div v-show="showAdditionalIds" class="mt-4 space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="w-full">
                                                <InputLabel for="manufacturer_part_number" value="Manufacturer Part Number" />
                                                <TextInput 
                                                    id="manufacturer_part_number" 
                                                    v-model="form.manufacturer_part_number" 
                                                    type="text" 
                                                    class="mt-1 block w-full"
                                                    placeholder="e.g., 7703-4515-8801"
                                                />
                                            </div>
                                            <div class="w-full">
                                                <InputLabel for="model_number" value="Model Number" />
                                                <TextInput 
                                                    id="model_number" 
                                                    v-model="form.model_number" 
                                                    type="text" 
                                                    class="mt-1 block w-full" 
                                                    placeholder="Product model" 
                                                />
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="w-full">
                                                <InputLabel for="brand" value="Brand" />
                                                <TextInput 
                                                    id="brand" 
                                                    v-model="form.brand" 
                                                    type="text" 
                                                    class="mt-1 block w-full" 
                                                    placeholder="e.g., NCR, Dell, HP" 
                                                />
                                            </div>
                                            <div class="w-full">
                                                <InputLabel for="series" value="Product Series" />
                                                <TextInput 
                                                    id="series" 
                                                    v-model="form.series" 
                                                    type="text" 
                                                    class="mt-1 block w-full" 
                                                    placeholder="e.g., RealPOS" 
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Condition Assessment (NEW) -->
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <InputLabel for="condition" value="Item Condition *" />
                                    <select
                                        id="condition"
                                        v-model="form.condition"
                                        required
                                        class="mt-2 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    >
                                        <option value="NEW">New - Never used, original packaging</option>
                                        <option value="NEW_OTHER">New (Other) - New but not in original packaging</option>
                                        <option value="NEW_WITH_DEFECTS">New with Defects</option>
                                        <option value="MANUFACTURER_REFURBISHED">Manufacturer Refurbished</option>
                                        <option value="CERTIFIED_REFURBISHED">Certified Refurbished</option>
                                        <option value="USED_EXCELLENT">Used - Excellent</option>
                                        <option value="USED_VERY_GOOD">Used - Very Good</option>
                                        <option value="USED_GOOD">Used - Good</option>
                                        <option value="USED_ACCEPTABLE">Used - Acceptable</option>
                                        <option value="FOR_PARTS_OR_NOT_WORKING">For Parts or Not Working</option>
                                    </select>
                                    
                                    <div class="mt-3">
                                        <InputLabel for="condition_description" value="Condition Details" />
                                        <textarea
                                            id="condition_description"
                                            v-model="form.condition_description"
                                            rows="3"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            placeholder="Describe any defects, missing parts, or notable features..."
                                        ></textarea>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Be specific about cosmetic issues, functionality, missing accessories</p>
                                    </div>
                                </div>

                                <!-- Physical Measurements (NEW) -->
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <InputLabel value="Physical Measurements" />
                                        <button 
                                            type="button"
                                            @click="showMeasurements = !showMeasurements"
                                            class="text-sm text-indigo-600 hover:text-indigo-500"
                                        >
                                            {{ showMeasurements ? 'Hide' : 'Add Measurements' }}
                                        </button>
                                    </div>
                                    
                                    <div v-show="showMeasurements" class="grid grid-cols-4 gap-4">
                                        <div>
                                            <InputLabel for="weight_lbs" value="Weight (lbs)" />
                                            <TextInput id="weight_lbs" v-model.number="form.weight_lbs" type="number" step="0.1" class="mt-1 block w-full" placeholder="0.0" />
                                        </div>
                                        <div>
                                            <InputLabel for="length_inches" value="Length (in)" />
                                            <TextInput id="length_inches" v-model.number="form.length_inches" type="number" step="0.1" class="mt-1 block w-full" placeholder="0.0" />
                                        </div>
                                        <div>
                                            <InputLabel for="width_inches" value="Width (in)" />
                                            <TextInput id="width_inches" v-model.number="form.width_inches" type="number" step="0.1" class="mt-1 block w-full" placeholder="0.0" />
                                        </div>
                                        <div>
                                            <InputLabel for="height_inches" value="Height (in)" />
                                            <TextInput id="height_inches" v-model.number="form.height_inches" type="number" step="0.1" class="mt-1 block w-full" placeholder="0.0" />
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Used for shipping calculations and storage optimization</p>
                                </div>

                                <!-- Completeness Checker (NEW) - Enhanced for product type -->
                                <div v-if="completenessTemplate" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <InputLabel :value="`Completeness Check - ${getCompletenessTitle()}`" />
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Check all items included with this {{ lookupResults?.name || form.product_type.replace('_', ' ') }}:</p>
                                    
                                    <div class="space-y-2">
                                        <div v-for="item in completenessTemplate" :key="item.key" class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                            <div class="flex items-center space-x-3">
                                                <input
                                                    :id="`completeness_${item.key}`"
                                                    v-model="form.completeness_data[item.key]"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-indigo-600 focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                                <label :for="`completeness_${item.key}`" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ item.label }}
                                                </label>
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded" :class="item.critical ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600'">
                                                {{ item.critical ? 'Critical' : 'Optional' }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <InputLabel for="missing_items_notes" value="Missing Items Notes" />
                                        <textarea
                                            id="missing_items_notes"
                                            v-model="form.missing_items_notes"
                                            rows="2"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            placeholder="List any missing items or additional notes..."
                                        ></textarea>
                                    </div>
                                </div>

                                <!-- Quick Add Fields (Enhanced) -->
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <InputLabel for="quick_quantity" value="Quantity *" />
                                        <TextInput
                                            id="quick_quantity"
                                            v-model.number="form.quick_quantity"
                                            type="number"
                                            min="1"
                                            required
                                            class="mt-1 block w-full"
                                        />
                                        <InputError :message="form.errors.quick_quantity" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="quick_location_id" value="Storage Location" />
                                        <select
                                            id="quick_location_id"
                                            v-model="form.quick_location_id"
                                            @change="form.quick_bin_id = null"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        >
                                            <option :value="null">-- Select location --</option>
                                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                                {{ loc.name }}<span v-if="loc.code"> ({{ loc.code }})</span>
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.quick_location_id" class="mt-2" />
                                    </div>
                                    <div v-if="selectedLocationBins.length > 0">
                                        <InputLabel for="quick_bin_id" value="Bin (optional)" />
                                        <select
                                            id="quick_bin_id"
                                            v-model="form.quick_bin_id"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        >
                                            <option :value="null">-- No specific bin --</option>
                                            <option v-for="bin in selectedLocationBins" :key="bin.id" :value="bin.id">
                                                {{ bin.zone_code }}-{{ bin.code }}<span v-if="bin.label"> &mdash; {{ bin.label }}</span>
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.quick_bin_id" class="mt-2" />
                                    </div>
                                </div>

                                <!-- User Notes -->
                                <div>
                                    <InputLabel for="user_notes" value="Additional Notes" />
                                    <textarea
                                        id="user_notes"
                                        v-model="form.user_notes"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        placeholder="Any additional notes about this product..."
                                    ></textarea>
                                    <InputError :message="form.errors.user_notes" class="mt-2" />
                                </div>

                                <!-- Submit Button -->
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                            </svg>
                                            This will be sent to the research queue for processing
                                        </span>
                                    </div>
                                    <PrimaryButton
                                        :class="{ 'opacity-25': form.processing }"
                                        :disabled="form.processing"
                                    >
                                        Add to Research Queue
                                    </PrimaryButton>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

// Props from controller
const props = defineProps({
    suppliers: {
        type: Array,
        required: true
    },
    locations: {
        type: Array,
        default: () => []
    }
});

const selectedLocationBins = computed(() => {
    if (!form.quick_location_id) return [];
    const loc = props.locations.find(l => l.id === form.quick_location_id);
    return loc?.bins ?? [];
});

// Reactive state
const showAdditionalIds = ref(true);
const showMeasurements = ref(true);
const lookupLoading = ref(false);
const lookupResults = ref(null);
const photoFiles = ref([]);

// Category hints for better AI research
const categoryHints = {
    pos_equipment: {
        title: 'POS Equipment',
        description: 'Point-of-sale terminals, cash registers, payment processors. AI will search for retail/restaurant industry specs.'
    },
    network_equipment: {
        title: 'Network Equipment',
        description: 'Switches, routers, firewalls, access points. AI will focus on technical specifications and enterprise documentation.'
    },
    computer_hardware: {
        title: 'Computer Hardware',
        description: 'Desktop computers, laptops, workstations. AI will search for consumer and business computer specifications.'
    },
    server_equipment: {
        title: 'Server Equipment',
        description: 'Rack servers, blade servers, storage arrays. AI will prioritize enterprise documentation and technical specifications.'
    },
    security_equipment: {
        title: 'Security Equipment',
        description: 'Surveillance cameras, access control, security software. AI will search for security industry databases.'
    },
    peripherals: {
        title: 'Peripherals & Accessories',
        description: 'Monitors, keyboards, mice, cables. AI will focus on compatibility and consumer electronics databases.'
    },
    telecom_equipment: {
        title: 'Telecom Equipment',
        description: 'Phone systems, modems, telecom hardware. AI will search telecommunications industry specifications.'
    },
    industrial_equipment: {
        title: 'Industrial Equipment',
        description: 'Manufacturing equipment, industrial computers. AI will prioritize industrial and automation documentation.'
    },
    consumer_electronics: {
        title: 'Consumer Electronics',
        description: 'TVs, audio equipment, gaming devices. AI will search consumer electronics databases and reviews.'
    },
    medical_equipment: {
        title: 'Medical Equipment',
        description: 'Medical devices and healthcare equipment. AI will search medical device databases and regulatory information.'
    },
    automotive_parts: {
        title: 'Automotive Parts',
        description: 'Vehicle parts and automotive equipment. AI will search automotive parts databases and compatibility info.'
    },
    other: {
        title: 'Other/Unknown',
        description: 'General product research. AI will use broad search strategies to identify the product category.'
    }
};

// Enhanced completeness templates based on category and type
const completenessTemplates = {
    pos_equipment_complete_unit: [
        { key: 'main_unit', label: 'Main POS Terminal', critical: true },
        { key: 'power_cable', label: 'Power Cable', critical: true },
        { key: 'stand', label: 'Stand/Base', critical: false },
        { key: 'cash_drawer', label: 'Cash Drawer', critical: false },
        { key: 'cash_drawer_keys', label: 'Cash Drawer Keys', critical: true },
        { key: 'receipt_printer', label: 'Receipt Printer', critical: false },
        { key: 'printer_ribbon', label: 'Printer Ribbon/Paper', critical: false },
        { key: 'network_cables', label: 'Network Cables', critical: false },
        { key: 'user_manual', label: 'User Manual', critical: false },
        { key: 'software_media', label: 'Software/Driver Media', critical: false },
        { key: 'original_packaging', label: 'Original Packaging', critical: false }
    ],
    pos_equipment_replacement_part: [
        { key: 'main_part', label: 'Main Replacement Part', critical: true },
        { key: 'mounting_hardware', label: 'Mounting Hardware', critical: false },
        { key: 'connection_cables', label: 'Connection Cables', critical: false },
        { key: 'installation_guide', label: 'Installation Guide', critical: false },
        { key: 'compatibility_info', label: 'Compatibility Documentation', critical: false }
    ],
    network_equipment_complete_unit: [
        { key: 'main_device', label: 'Main Network Device', critical: true },
        { key: 'power_supply', label: 'Power Supply/Adapter', critical: true },
        { key: 'rack_mounting_kit', label: 'Rack Mounting Kit', critical: false },
        { key: 'network_cables', label: 'Network Cables', critical: false },
        { key: 'console_cable', label: 'Console Cable', critical: false },
        { key: 'documentation', label: 'Configuration Documentation', critical: false },
        { key: 'license_info', label: 'License Information', critical: false }
    ],
    network_equipment_replacement_part: [
        { key: 'main_component', label: 'Network Component', critical: true },
        { key: 'compatibility_chart', label: 'Compatibility Chart', critical: false },
        { key: 'installation_tools', label: 'Installation Tools', critical: false },
        { key: 'firmware_info', label: 'Firmware Information', critical: false }
    ],
    default_complete_unit: [
        { key: 'main_item', label: 'Main Item', critical: true },
        { key: 'power_adapter', label: 'Power Adapter/Cable', critical: true },
        { key: 'cables', label: 'Connection Cables', critical: false },
        { key: 'accessories', label: 'Accessories', critical: false },
        { key: 'documentation', label: 'Documentation/Manual', critical: false },
        { key: 'original_packaging', label: 'Original Packaging', critical: false }
    ],
    default_replacement_part: [
        { key: 'main_part', label: 'Replacement Part', critical: true },
        { key: 'installation_hardware', label: 'Installation Hardware', critical: false },
        { key: 'installation_guide', label: 'Installation Guide', critical: false },
        { key: 'warranty_info', label: 'Warranty Information', critical: false }
    ]
};

const completenessTemplate = computed(() => {
    if (!form.product_category || !form.product_type) return null;
    
    // Build template key from category and type
    const templateKey = `${form.product_category}_${form.product_type}`;
    
    // Try specific template first
    if (completenessTemplates[templateKey]) {
        return completenessTemplates[templateKey];
    }
    
    // Fallback to default templates based on type
    if (form.product_type === 'complete_unit') {
        return completenessTemplates.default_complete_unit;
    } else if (form.product_type === 'replacement_part' || form.product_type === 'component') {
        return completenessTemplates.default_replacement_part;
    }
    
    return null;
});

// Methods for category hints
const getCategoryHint = () => {
    return categoryHints[form.product_category] || categoryHints.other;
};

const getCompletenessTitle = () => {
    if (!form.product_type) return 'Product';
    return form.product_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

// Form setup with new fields
const form = useForm({
    // Supplier/Business
    supplier_id: '',
    
    // NEW: Product Classification
    product_category: '',
    product_type: '',
    
    // Photos
    photos: [],
    
    // Identifiers
    primary_identifier: '',
    manufacturer_part_number: '', 
    model_number: '',
    brand: '',
    series: '',
    keywords_text: '',
    
    // Condition
    condition: 'NEW',
    condition_description: '',
    
    // Physical measurements
    weight_lbs: null,
    length_inches: null,
    width_inches: null,
    height_inches: null,
    
    // Completeness
    completeness_data: {},
    missing_items_notes: '',
    
    // Quick data
    quick_quantity: 1,
    quick_location: '',
    quick_location_id: null,
    quick_bin_id: null,
    estimated_value: null,
    user_notes: '',
    
    // System fields
    lookup_attempted: false,
    existing_product_data: null,
    capture_method: 'web_form'
});

// Watch for condition changes to show/hide description field
watch(() => form.condition, (newCondition) => {
    if (newCondition === 'NEW') {
        form.condition_description = '';
    }
});

// Photo handling
const handlePhotoUpload = (event) => {
    const files = Array.from(event.target.files);
    
    files.forEach(file => {
        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert(`Photo ${file.name} is too large. Please use photos smaller than 5MB.`);
            return;
        }
        
        photoFiles.value.push(file);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            form.photos.push({
                file: file,
                preview: e.target.result,
                name: file.name,
                size: file.size
            });
        };
        reader.readAsDataURL(file);
    });
};

const removePhoto = (index) => {
    form.photos.splice(index, 1);
    photoFiles.value.splice(index, 1);
};

const performProductLookup = async () => {
    if (!form.primary_identifier?.trim()) return;
    
    lookupLoading.value = true;
    
    try {
        const response = await axios.post(route('intake-queue.products-lookup'), {
            identifier: form.primary_identifier.trim()
        });
        const data = response.data;
        if (data?.found_existing) {
            lookupResults.value = data;
            if (data.suggested_category) {
                form.product_category = data.suggested_category;
            }
            if (data.suggested_type) {
                form.product_type = data.suggested_type;
            }
        }
        form.lookup_attempted = true;
    } catch (error) {
        console.error('Product lookup failed:', error);
        form.lookup_attempted = true;
    } finally {
        lookupLoading.value = false;
    }
};

// Form submission
const submit = () => {
    // Prepare FormData for file uploads
    const formData = new FormData();
    
    // Add photos
    photoFiles.value.forEach((file, index) => {
        formData.append(`photos[${index}]`, file);
    });
    
    // Add all form fields including new classification fields
    const formFields = {
        supplier_id: form.supplier_id,
        product_category: form.product_category, // NEW
        product_type: form.product_type, // NEW
        primary_identifier: form.primary_identifier,
        manufacturer_part_number: form.manufacturer_part_number,
        model_number: form.model_number,
        brand: form.brand,
        series: form.series,
        keywords_text: form.keywords_text,
        condition: form.condition,
        condition_description: form.condition_description,
        weight_lbs: form.weight_lbs,
        length_inches: form.length_inches,
        width_inches: form.width_inches,
        height_inches: form.height_inches,
        completeness_data: JSON.stringify(form.completeness_data),
        missing_items_notes: form.missing_items_notes,
        quick_quantity: form.quick_quantity,
        quick_location: form.quick_location,
        quick_location_id: form.quick_location_id,
        quick_bin_id: form.quick_bin_id,
        estimated_value: form.estimated_value,
        user_notes: form.user_notes,
        lookup_attempted: form.lookup_attempted,
        existing_product_data: form.existing_product_data ? JSON.stringify(form.existing_product_data) : null,
        capture_method: form.capture_method
    };
    
    // Append each field to FormData
    Object.entries(formFields).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            formData.append(key, value);
        }
    });
    
    // Submit via Inertia
    form.processing = true;
    router.post(route('intake-queue.store'), formData, {
        preserveScroll: true,
        onSuccess: () => {
            form.processing = false;
            // Reset form
            form.reset();
            photoFiles.value = [];
            lookupResults.value = null;
            showAdditionalIds.value = false;
            showMeasurements.value = false;
        },
        onError: (errors) => {
            form.processing = false;
            form.clearErrors();
            Object.keys(errors).forEach(key => {
                form.setError(key, errors[key]);
            });
        },
        onFinish: () => {
            form.processing = false;
        },
    });
};
</script>