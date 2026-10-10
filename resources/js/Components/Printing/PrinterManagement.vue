<template>
    <AppLayout title="Label Printing & Printer Management">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Label Printing & Printer Management
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Jobs (30 days)</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ statistics.total_jobs }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Success Rate</div>
                        <div class="text-2xl font-bold text-green-600">{{ Math.round(statistics.success_rate) }}%</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Labels Printed</div>
                        <div class="text-2xl font-bold text-blue-600">{{ statistics.total_labels_printed }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed Jobs</div>
                        <div class="text-2xl font-bold text-red-600">{{ statistics.failed_jobs }}</div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg mb-8">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-8 px-6">
                            <button 
                                @click="activeTab = 'settings'"
                                :class="[
                                    'py-4 px-1 border-b-2 font-medium text-sm',
                                    activeTab === 'settings' 
                                        ? 'border-indigo-500 text-indigo-600' 
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                Auto-Print Settings
                            </button>
                            <button 
                                @click="activeTab = 'printers'"
                                :class="[
                                    'py-4 px-1 border-b-2 font-medium text-sm',
                                    activeTab === 'printers' 
                                        ? 'border-indigo-500 text-indigo-600' 
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                Printers ({{ printers.length }})
                            </button>
                            <button 
                                @click="activeTab = 'templates'"
                                :class="[
                                    'py-4 px-1 border-b-2 font-medium text-sm',
                                    activeTab === 'templates' 
                                        ? 'border-indigo-500 text-indigo-600' 
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                Label Templates ({{ templates.length }})
                            </button>
                            <button 
                                @click="activeTab = 'jobs'"
                                :class="[
                                    'py-4 px-1 border-b-2 font-medium text-sm',
                                    activeTab === 'jobs' 
                                        ? 'border-indigo-500 text-indigo-600' 
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                Recent Jobs
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        
                        <!-- Auto-Print Settings Tab -->
                        <div v-show="activeTab === 'settings'">
                            <form @submit.prevent="updateSettings">
                                <div class="space-y-6">
                                    <div class="flex items-center">
                                        <input 
                                            id="auto_print_on_sku" 
                                            name="auto_print_on_sku" 
                                            type="checkbox" 
                                            v-model="settingsForm.auto_print_on_sku"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        >
                                        <label for="auto_print_on_sku" class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Automatically print labels when products receive SKUs
                                        </label>
                                    </div>

                                    <div class="grid grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Default Copies</label>
                                            <input 
                                                type="number" 
                                                min="1" 
                                                max="10"
                                                v-model="settingsForm.default_copies"
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Auto-Print Template</label>
                                            <select 
                                                v-model="settingsForm.auto_print_template_id"
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                                <option value="">Select template...</option>
                                                <option 
                                                    v-for="template in productTemplates" 
                                                    :key="template.id" 
                                                    :value="template.id"
                                                >
                                                    {{ template.name }} ({{ template.width_mm }}x{{ template.height_mm }}mm)
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="flex items-center">
                                        <input 
                                            id="print_location_labels" 
                                            name="print_location_labels" 
                                            type="checkbox" 
                                            v-model="settingsForm.print_location_labels"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        >
                                        <label for="print_location_labels" class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Also print warehouse location labels
                                        </label>
                                    </div>

                                    <div>
                                        <PrimaryButton type="submit" :disabled="settingsForm.processing">
                                            Update Settings
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Printers Tab -->
                        <div v-show="activeTab === 'printers'">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Printer Configurations</h3>
                                <SecondaryButton @click="showPrinterModal = true">
                                    Add Printer
                                </SecondaryButton>
                            </div>

                            <div class="grid gap-6">
                                <div 
                                    v-for="printer in printers" 
                                    :key="printer.id" 
                                    class="border border-gray-200 dark:border-gray-700 rounded-lg p-6"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                                {{ printer.name }}
                                                <span v-if="printer.is_default" class="ml-2 bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                    Default
                                                </span>
                                                <span v-if="!printer.is_active" class="ml-2 bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                    Inactive
                                                </span>
                                            </h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ printer.printer_type.toUpperCase() }}: {{ printer.connection_string }}
                                            </p>
                                            <p v-if="printer.printer_model" class="text-sm text-gray-500 dark:text-gray-400">
                                                Model: {{ printer.printer_model }}
                                            </p>
                                            <p v-if="printer.last_test_printed_at" class="text-sm text-green-600">
                                                Last tested: {{ formatDate(printer.last_test_printed_at) }}
                                            </p>
                                            <p v-if="printer.last_error" class="text-sm text-red-600">
                                                Error: {{ printer.last_error }}
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <SecondaryButton @click="testPrinter(printer)" :disabled="testingPrinter === printer.id">
                                                {{ testingPrinter === printer.id ? 'Testing...' : 'Test' }}
                                            </SecondaryButton>
                                            <SecondaryButton @click="editPrinter(printer)">
                                                Edit
                                            </SecondaryButton>
                                            <DangerButton @click="deletePrinter(printer)">
                                                Delete
                                            </DangerButton>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Templates Tab -->
                        <div v-show="activeTab === 'templates'">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Label Templates</h3>
                                <SecondaryButton @click="showTemplateModal = true">
                                    Create Template
                                </SecondaryButton>
                            </div>

                            <div class="grid gap-6">
                                <div 
                                    v-for="template in templates" 
                                    :key="template.id" 
                                    class="border border-gray-200 dark:border-gray-700 rounded-lg p-6"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                                {{ template.name }}
                                                <span v-if="template.is_default" class="ml-2 bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                    Default
                                                </span>
                                                <span :class="[
                                                    'ml-2 text-xs font-medium px-2.5 py-0.5 rounded',
                                                    template.template_type === 'product' ? 'bg-blue-100 text-blue-800' :
                                                    template.template_type === 'shelf' ? 'bg-purple-100 text-purple-800' :
                                                    'bg-gray-100 text-gray-800'
                                                ]">
                                                    {{ template.template_type }}
                                                </span>
                                            </h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ template.width_mm }}mm × {{ template.height_mm }}mm @ {{ template.dpi }}DPI
                                            </p>
                                            <p v-if="template.description" class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                                {{ template.description }}
                                            </p>
                                        </div>
                                        <div class="flex items-start space-x-2 ml-4">
                                            <img 
                                                v-if="template.preview_image_url" 
                                                :src="`/storage/${template.preview_image_url}`" 
                                                :alt="template.name + ' preview'"
                                                class="w-20 h-20 border border-gray-200 rounded"
                                            >
                                            <div class="flex flex-col space-y-2">
                                                <SecondaryButton @click="editTemplate(template)">
                                                    Edit
                                                </SecondaryButton>
                                                <DangerButton @click="deleteTemplate(template)">
                                                    Delete
                                                </DangerButton>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Print Jobs Tab -->
                        <div v-show="activeTab === 'jobs'">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Recent Print Jobs</h3>
                            
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Job</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Printer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                            <th class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="job in recentJobs" :key="job.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                #{{ job.id }}
                                                <br>
                                                <span class="text-xs text-gray-500">{{ job.job_type }} / {{ job.trigger_type }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <div v-if="job.product">
                                                    {{ job.product.internal_sku || job.product.sku }}
                                                    <br>
                                                    <span class="text-xs text-gray-500">{{ job.product.name }}</span>
                                                </div>
                                                <span v-else class="text-gray-500">Batch Job</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ job.printer_config.name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span :class="[
                                                    'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                                                    job.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                    job.status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                                                    job.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                    job.status === 'queued' ? 'bg-blue-100 text-blue-800' :
                                                    'bg-gray-100 text-gray-800'
                                                ]">
                                                    {{ job.status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(job.created_at) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button v-if="job.status === 'failed'" 
                                                        @click="retryJob(job)" 
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                    Retry
                                                </button>
                                                <button v-if="job.status === 'queued'" 
                                                        @click="cancelJob(job)" 
                                                        class="text-red-600 hover:text-red-900">
                                                    Cancel
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Printer Modal (simplified - full implementation would be larger) -->
        <Modal :show="showPrinterModal" @close="closePrinterModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    {{ editingPrinter ? 'Edit Printer' : 'Add Printer' }}
                </h3>
                <!-- Printer form fields would go here -->
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="closePrinterModal">Cancel</SecondaryButton>
                    <PrimaryButton @click="savePrinter">Save Printer</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Template Modal (simplified) -->
        <Modal :show="showTemplateModal" @close="closeTemplateModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    {{ editingTemplate ? 'Edit Template' : 'Create Template' }}
                </h3>
                <!-- Template form fields would go here -->
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="closeTemplateModal">Cancel</SecondaryButton>
                    <PrimaryButton @click="saveTemplate">Save Template</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    printers: Array,
    templates: Array,
    recentJobs: Array,
    statistics: Object,
    organization: Object,
})

const activeTab = ref('settings')
const showPrinterModal = ref(false)
const showTemplateModal = ref(false)
const editingPrinter = ref(null)
const editingTemplate = ref(null)
const testingPrinter = ref(null)

// Settings form
const settingsForm = useForm({
    auto_print_on_sku: props.organization.label_settings?.auto_print_on_sku || false,
    default_copies: props.organization.label_settings?.default_copies || 1,
    auto_print_template_id: props.organization.label_settings?.auto_print_template_id || '',
    print_location_labels: props.organization.label_settings?.print_location_labels || false,
})

const productTemplates = computed(() => {
    return props.templates.filter(t => t.template_type === 'product')
})

// Methods
const updateSettings = () => {
    settingsForm.post(route('printer-management.update-settings'))
}

const testPrinter = async (printer) => {
    testingPrinter.value = printer.id
    try {
        const response = await axios.post(route('printer-management.test-printer', printer.id))
        // Handle response
    } catch (error) {
        // Handle error
    } finally {
        testingPrinter.value = null
    }
}

const editPrinter = (printer) => {
    editingPrinter.value = printer
    showPrinterModal.value = true
}

const editTemplate = (template) => {
    editingTemplate.value = template
    showTemplateModal.value = true
}

const deletePrinter = (printer) => {
    if (confirm('Are you sure you want to delete this printer?')) {
        router.delete(route('printer-management.destroy-printer', printer.id))
    }
}

const deleteTemplate = (template) => {
    if (confirm('Are you sure you want to delete this template?')) {
        router.delete(route('printer-management.destroy-template', template.id))
    }
}

const retryJob = (job) => {
    router.post(route('printer-management.retry-job', job.id))
}

const cancelJob = (job) => {
    router.post(route('printer-management.cancel-job', job.id))
}

const closePrinterModal = () => {
    showPrinterModal.value = false
    editingPrinter.value = null
}

const closeTemplateModal = () => {
    showTemplateModal.value = false
    editingTemplate.value = null
}

const savePrinter = () => {
    // Implementation for saving printer
}

const saveTemplate = () => {
    // Implementation for saving template
}

const formatDate = (date) => {
    return new Date(date).toLocaleString()
}
</script>
