<script setup>
import { ref } from 'vue'

defineProps({
    documents: { type: Array, default: () => [] },
    supplierId: { type: [String, Number], required: true }
})

const emit = defineEmits(['upload', 'delete'])

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatFileSize = (bytes) => {
    if (!bytes) return '-'
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(1024))
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i]
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Documents</h4>
            <button 
                @click="emit('upload')"
                class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
                Upload
            </button>
        </div>
        
        <div v-if="documents.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            No documents uploaded
        </div>
        
        <div v-else class="space-y-2">
            <div 
                v-for="doc in documents" 
                :key="doc.id" 
                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
            >
                <div class="flex items-center space-x-3">
                    <div class="text-gray-400">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ doc.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatFileSize(doc.size) }} • {{ formatDate(doc.uploaded_at) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a :href="doc.url" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                    <button @click="emit('delete', doc.id)" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                </div>
            </div>
        </div>
    </div>
</template>
