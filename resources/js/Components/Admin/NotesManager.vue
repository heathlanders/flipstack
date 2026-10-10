<script setup>
import { ref } from 'vue'

const props = defineProps({
    notes: { type: Array, default: () => [] },
    supplierId: { type: [String, Number], required: true }
})

const emit = defineEmits(['add', 'delete'])

const newNote = ref('')

const addNote = () => {
    if (newNote.value.trim()) {
        emit('add', newNote.value.trim())
        newNote.value = ''
    }
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
    })
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex space-x-2">
            <input 
                v-model="newNote"
                type="text"
                placeholder="Add a note..."
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm"
                @keyup.enter="addNote"
            />
            <button 
                @click="addNote"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
            >
                Add
            </button>
        </div>
        
        <div v-if="notes.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            No notes yet
        </div>
        
        <div v-else class="space-y-3">
            <div 
                v-for="note in notes" 
                :key="note.id" 
                class="p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
            >
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-900 dark:text-white">{{ note.content }}</p>
                    <button @click="emit('delete', note.id)" class="text-red-600 hover:text-red-800 text-xs ml-2">
                        Delete
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    {{ note.user?.name || 'System' }} • {{ formatDate(note.created_at) }}
                </p>
            </div>
        </div>
    </div>
</template>
