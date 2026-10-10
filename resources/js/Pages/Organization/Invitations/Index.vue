<script setup>
import { ref, computed } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
    invitations: Array,
    historyInvitations: Array,
    availableRoles: Array,
    organization: Object,
    showHistory: Boolean,
})

// Tab state
const activeTab = ref(props.showHistory ? 'history' : 'pending')

// Search/filter state
const searchQuery = ref('')

// Filtered invitations
const filteredInvitations = computed(() => {
    if (!searchQuery.value) return props.invitations
    const query = searchQuery.value.toLowerCase()
    return props.invitations.filter(inv =>
        inv.email.toLowerCase().includes(query) ||
        inv.invited_by?.name?.toLowerCase().includes(query)
    )
})

const filteredHistory = computed(() => {
    if (!searchQuery.value) return props.historyInvitations
    const query = searchQuery.value.toLowerCase()
    return props.historyInvitations.filter(inv =>
        inv.email.toLowerCase().includes(query) ||
        inv.invited_by?.name?.toLowerCase().includes(query) ||
        inv.accepted_user?.name?.toLowerCase().includes(query)
    )
})

// New invitation form
const showInviteModal = ref(false)
const inviteForm = useForm({
    email: '',
    roles: [],
})

const sendInvitation = () => {
    inviteForm.post(route('organization.invitations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showInviteModal.value = false
            inviteForm.reset()
        },
    })
}

// Bulk invite form
const showBulkInviteModal = ref(false)
const bulkInviteForm = useForm({
    emailsText: '',
    roles: [],
})
const parsedEmails = ref([])
const showBulkPreview = ref(false)

const parseEmails = () => {
    const text = bulkInviteForm.emailsText
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g
    const matches = text.match(emailRegex) || []
    parsedEmails.value = [...new Set(matches.map(e => e.toLowerCase()))]
    showBulkPreview.value = true
}

const removeParsedEmail = (email) => {
    parsedEmails.value = parsedEmails.value.filter(e => e !== email)
}

const sendBulkInvitations = () => {
    router.post(route('organization.invitations.bulk'), {
        emails: parsedEmails.value,
        roles: bulkInviteForm.roles,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showBulkInviteModal.value = false
            bulkInviteForm.reset()
            parsedEmails.value = []
            showBulkPreview.value = false
        },
    })
}

const closeBulkModal = () => {
    showBulkInviteModal.value = false
    bulkInviteForm.reset()
    parsedEmails.value = []
    showBulkPreview.value = false
}

// Delete invitation
const showDeleteModal = ref(false)
const invitationToDelete = ref(null)

const confirmDelete = (invitation) => {
    invitationToDelete.value = invitation
    showDeleteModal.value = true
}

const deleteInvitation = () => {
    router.delete(route('organization.invitations.destroy', invitationToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            invitationToDelete.value = null
        },
    })
}

// Resend invitation
const resendInvitation = (invitation) => {
    router.post(route('organization.invitations.resend', invitation.id), {}, {
        preserveScroll: true,
    })
}

// Role toggle
const toggleRole = (form, roleId) => {
    const index = form.roles.indexOf(roleId)
    if (index > -1) {
        form.roles.splice(index, 1)
    } else {
        form.roles.push(roleId)
    }
}

// Page actions for the toolbar
const pageActions = computed(() => [
    {
        label: 'Bulk Invite',
        icon: 'fas fa-users',
        onClick: () => showBulkInviteModal.value = true,
        variant: 'secondary'
    },
    {
        label: 'Invite User',
        icon: 'fas fa-plus',
        onClick: () => showInviteModal.value = true,
        variant: 'primary'
    }
])
</script>

<template>
    <AppLayout
        title="Organization Invitations"
        page-title="Invitations"
        :page-description="`Manage pending invitations to ${organization.name}`"
        :page-actions="pageActions"
    >

        <div class="space-y-6">
            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8">
                    <button
                        @click="activeTab = 'pending'"
                        :class="[
                            'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'pending'
                                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                        ]"
                    >
                        Pending
                        <span
                            v-if="invitations.length > 0"
                            class="ml-2 px-2 py-0.5 text-xs rounded-full"
                            :class="activeTab === 'pending' ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                        >
                            {{ invitations.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'history'"
                        :class="[
                            'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'history'
                                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                        ]"
                    >
                        History
                        <span
                            v-if="historyInvitations.length > 0"
                            class="ml-2 px-2 py-0.5 text-xs rounded-full"
                            :class="activeTab === 'history' ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                        >
                            {{ historyInvitations.length }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Search -->
            <div class="flex items-center space-x-4">
                <div class="relative flex-1 max-w-md">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by email..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>
            </div>

            <!-- Pending Invitations Tab -->
            <div v-show="activeTab === 'pending'" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" v-if="filteredInvitations.length > 0">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Email
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Roles
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Invited By
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Expires
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="invitation in filteredInvitations" :key="invitation.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ invitation.email }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Sent {{ invitation.sent_ago }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="invitation.role_names && invitation.role_names.length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in invitation.role_names"
                                        :key="role.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                                    >
                                        {{ role.name }}
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">No roles assigned</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ invitation.invited_by?.name || 'Unknown' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm" :class="invitation.is_expired ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'">
                                    {{ invitation.expires_in }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ invitation.expires_at }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs font-medium rounded-full',
                                        invitation.is_expired
                                            ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                            : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
                                    ]"
                                >
                                    {{ invitation.is_expired ? 'Expired' : 'Pending' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <button
                                        @click="resendInvitation(invitation)"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        title="Resend invitation"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="confirmDelete(invitation)"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                        title="Cancel invitation"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No pending invitations</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Get started by inviting a new user to your organization.
                    </p>
                    <div class="mt-6">
                        <button
                            @click="showInviteModal = true"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Invite User
                        </button>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div v-show="activeTab === 'history'" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" v-if="filteredHistory.length > 0">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Email
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Roles
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Invited By
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Accepted
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="invitation in filteredHistory" :key="invitation.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ invitation.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Link
                                    v-if="invitation.accepted_user"
                                    :href="route('users.show', invitation.accepted_user.id)"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    {{ invitation.accepted_user.name }}
                                </Link>
                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="invitation.role_names && invitation.role_names.length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in invitation.role_names"
                                        :key="role.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                                    >
                                        {{ role.name }}
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">No roles assigned</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ invitation.invited_by?.name || 'Unknown' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ invitation.accepted_ago }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ invitation.accepted_at }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invitation history</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Accepted invitations will appear here.
                    </p>
                </div>
            </div>
        </div>

        <!-- Invite User Modal -->
        <Modal :show="showInviteModal" @close="showInviteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Invite New User
                </h2>

                <form @submit.prevent="sendInvitation">
                    <div>
                        <InputLabel for="email" value="Email Address" />
                        <TextInput
                            id="email"
                            v-model="inviteForm.email"
                            type="email"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            placeholder="user@example.com"
                        />
                        <InputError class="mt-2" :message="inviteForm.errors.email" />
                    </div>

                    <div class="mt-4" v-if="availableRoles.length > 0">
                        <InputLabel value="Assign Roles (optional)" />
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="role in availableRoles"
                                :key="role.id"
                                class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    :checked="inviteForm.roles.includes(role.id)"
                                    @change="toggleRole(inviteForm, role.id)"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ role.name }}
                                    </span>
                                    <p v-if="role.description" class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ role.description }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="inviteForm.errors.roles" />
                    </div>

                    <InputError class="mt-2" :message="inviteForm.errors.error" />

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="showInviteModal = false">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': inviteForm.processing }" :disabled="inviteForm.processing">
                            Send Invitation
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Bulk Invite Modal -->
        <Modal :show="showBulkInviteModal" @close="closeBulkModal" max-width="2xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Bulk Invite Users
                </h2>

                <!-- Step 1: Enter emails -->
                <div v-if="!showBulkPreview">
                    <div>
                        <InputLabel value="Email Addresses" />
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                            Enter one email per line, or paste a list of emails (comma or space separated).
                        </p>
                        <textarea
                            v-model="bulkInviteForm.emailsText"
                            rows="6"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="user1@example.com&#10;user2@example.com&#10;user3@example.com"
                        ></textarea>
                    </div>

                    <div class="mt-4" v-if="availableRoles.length > 0">
                        <InputLabel value="Assign Roles (optional)" />
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label
                                v-for="role in availableRoles"
                                :key="role.id"
                                class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    :checked="bulkInviteForm.roles.includes(role.id)"
                                    @change="toggleRole(bulkInviteForm, role.id)"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ role.name }}
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeBulkModal">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton @click="parseEmails" :disabled="!bulkInviteForm.emailsText.trim()">
                            Preview Emails
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Step 2: Preview and confirm -->
                <div v-else>
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Found {{ parsedEmails.length }} email{{ parsedEmails.length !== 1 ? 's' : '' }}
                            </span>
                            <button
                                @click="showBulkPreview = false"
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                            >
                                Edit List
                            </button>
                        </div>

                        <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                            <div
                                v-for="email in parsedEmails"
                                :key="email"
                                class="flex items-center justify-between px-4 py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                            >
                                <span class="text-sm text-gray-900 dark:text-white">{{ email }}</span>
                                <button
                                    @click="removeParsedEmail(email)"
                                    class="text-gray-400 hover:text-red-500 dark:hover:text-red-400"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="bulkInviteForm.roles.length > 0" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Roles to assign:</strong>
                            {{ availableRoles.filter(r => bulkInviteForm.roles.includes(r.id)).map(r => r.name).join(', ') }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeBulkModal">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            @click="sendBulkInvitations"
                            :disabled="parsedEmails.length === 0"
                        >
                            Send {{ parsedEmails.length }} Invitation{{ parsedEmails.length !== 1 ? 's' : '' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Cancel Invitation
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to cancel the invitation to <strong>{{ invitationToDelete?.email }}</strong>? This action cannot be undone.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Keep Invitation
                    </SecondaryButton>
                    <DangerButton @click="deleteInvitation">
                        Cancel Invitation
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
