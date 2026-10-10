<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
        <div class="p-4">
            <!-- Header with status badge -->
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center space-x-3">
                    <div
                        class="w-10 h-10 rounded-lg flex items-center justify-center"
                        :class="typeIconBgClass"
                    >
                        <i :class="[typeIcon, 'text-white']"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white text-lg">
                            {{ location.name }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ location.code }}
                        </p>
                    </div>
                </div>
                <span
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="statusClass"
                >
                    {{ location.is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            <!-- Location Details -->
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-tag w-5 mr-2"></i>
                    <span class="capitalize">{{ formatType(location.type) }}</span>
                </div>

                <div v-if="fullAddress" class="flex items-start text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-map-marker-alt w-5 mr-2 mt-0.5"></i>
                    <span>{{ fullAddress }}</span>
                </div>

                <div v-if="location.contact_person" class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-user w-5 mr-2"></i>
                    <span>{{ location.contact_person }}</span>
                </div>

                <div v-if="location.phone" class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-phone w-5 mr-2"></i>
                    <span>{{ location.phone }}</span>
                </div>

                <div v-if="location.is_default" class="flex items-center text-sm text-blue-600 dark:text-blue-400">
                    <i class="fas fa-star w-5 mr-2"></i>
                    <span>Default Location</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                <button
                    type="button"
                    @click="$emit('view', location)"
                    class="flex-1 px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                >
                    <i class="fas fa-eye mr-1"></i>
                    View
                </button>
                <button
                    type="button"
                    @click="$emit('edit', location)"
                    class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                >
                    <i class="fas fa-edit mr-1"></i>
                    Edit
                </button>
                <button
                    type="button"
                    @click="$emit('delete', location)"
                    class="px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-md hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors"
                >
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    location: {
        type: Object,
        required: true
    }
})

defineEmits(['view', 'edit', 'delete'])

const typeIconMap = {
    warehouse: 'fas fa-warehouse',
    store: 'fas fa-store',
    supplier: 'fas fa-truck',
    customer: 'fas fa-user',
    virtual: 'fas fa-cloud'
}

const typeBgColorMap = {
    warehouse: 'bg-blue-500',
    store: 'bg-green-500',
    supplier: 'bg-purple-500',
    customer: 'bg-orange-500',
    virtual: 'bg-gray-500'
}

const typeIcon = computed(() => {
    return typeIconMap[props.location.type] || 'fas fa-map-marker-alt'
})

const typeIconBgClass = computed(() => {
    return typeBgColorMap[props.location.type] || 'bg-gray-500'
})

const statusClass = computed(() => {
    return props.location.is_active
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
})

const fullAddress = computed(() => {
    const parts = [
        props.location.address_line1,
        props.location.city,
        props.location.state,
        props.location.postal_code
    ].filter(Boolean)
    return parts.join(', ')
})

const formatType = (type) => {
    const typeNames = {
        warehouse: 'Warehouse',
        store: 'Store',
        supplier: 'Supplier Location',
        customer: 'Customer Location',
        virtual: 'Virtual Location'
    }
    return typeNames[type] || type
}
</script>
