<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import InputLabel from '@/Components/InputLabel.vue'

// Using the EXACT prop names Laravel sends (from debug output)
const props = defineProps({
    team: Object,
    members: Array,                    // NOT team.users
    permissions: Object,               // NOT user_permissions - contains {can_edit, can_delete, can_manage_members, can_invite}
    available_roles: Array,
    pending_invitations: Array
})

const addTeamMemberForm = useForm({
    email: '',
    role: null
})

const updateRoleForm = useForm({
    role_id: null
})

const leaveTeamForm = useForm({})
const removeTeamMemberForm = useForm({})

const currentlyManagingRole = ref(false)
const managingRoleFor = ref(null)
const confirmingLeavingTeam = ref(false)
const teamMemberBeingRemoved = ref(null)

const addTeamMember = () => {
    addTeamMemberForm.post(route('team-members.store', props.team), {
        preserveScroll: true,
        onSuccess: () => addTeamMemberForm.reset()
    })
}

const cancelTeamInvitation = (invitation) => {
    $inertia.delete(route('team-invitations.destroy', invitation), {
        preserveScroll: true
    })
}

const manageRole = (teamMember) => {
    managingRoleFor.value = teamMember
    updateRoleForm.role_id = teamMember.role.id
    currentlyManagingRole.value = true
}

const updateRole = () => {
    updateRoleForm.put(route('teams.update-member-role', [props.team.id, managingRoleFor.value.id]), {
        preserveScroll: true,
        onSuccess: () => currentlyManagingRole.value = false
    })
}

const confirmLeavingTeam = () => {
    confirmingLeavingTeam.value = true
}

const leaveTeam = () => {
    leaveTeamForm.delete(route('team-members.destroy', [props.team, $page.props.auth.user]))
}

const confirmTeamMemberRemoval = (teamMember) => {
    teamMemberBeingRemoved.value = teamMember
}

const removeTeamMember = () => {
    removeTeamMemberForm.delete(route('team-members.destroy', [props.team, teamMemberBeingRemoved.value]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => teamMemberBeingRemoved.value = null
    })
}

const displayableRole = (roleId) => {
    const foundRole = props.available_roles.find(r => r.id === roleId)
    return foundRole ? foundRole.name : 'Unknown'
}
</script>

<template>
    <AppLayout :title="`${team.name} - Team Management`">
        <template #header>
            <div class="flex items-center space-x-4">
                <button @click="$inertia.visit(route('teams.index'))"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-arrow-left text-lg"></i>
                </button>
                
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">{{ team.name }}</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Manage team members and settings
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-8">
            <!-- Add Team Member - using permissions.can_invite -->
            <div v-if="permissions.can_invite" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-plus text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Add Team Member</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Add a new team member to your team, allowing them to collaborate with you</p>
                    </div>
                </div>

                <form @submit.prevent="addTeamMember" class="space-y-6">
                    <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
                        Please provide the email address of the person you would like to add to this team.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Member Email -->
                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput
                                id="email"
                                v-model="addTeamMemberForm.email"
                                type="email"
                                class="mt-1 block w-full"
                            />
                            <div v-if="addTeamMemberForm.errors.email" class="mt-2 text-sm text-red-600">
                                {{ addTeamMemberForm.errors.email }}
                            </div>
                        </div>

                        <!-- Role -->
                        <div v-if="available_roles.length > 0">
                            <InputLabel for="roles" value="Role" />
                            <SelectInput
                                id="roles"
                                v-model="addTeamMemberForm.role"
                                class="mt-1 block w-full"
                            >
                                <option value="">Select Role</option>
                                <option v-for="role in available_roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </SelectInput>
                            <div v-if="addTeamMemberForm.errors.role" class="mt-2 text-sm text-red-600">
                                {{ addTeamMemberForm.errors.role }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <PrimaryButton 
                            :class="{ 'opacity-25': addTeamMemberForm.processing }" 
                            :disabled="addTeamMemberForm.processing"
                        >
                            Add
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Pending Team Member Invitations -->
            <div v-if="team.team_invitations && team.team_invitations.length > 0 && permissions.can_invite" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 dark:text-yellow-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Pending Team Invitations</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">These people have been invited to your team</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-for="invitation in team.team_invitations" :key="invitation.id" 
                         class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ invitation.email }}
                        </div>

                        <div class="flex items-center">
                            <button
                                v-if="permissions.can_manage_members"
                                class="cursor-pointer ml-6 text-sm text-red-500 focus:outline-none"
                                @click="cancelTeamInvitation(invitation)"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Team Members - using members prop, not team.users -->
            <div v-if="members && members.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Team Members</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">All of the people that are part of this team</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-for="user in members" :key="user.id" 
                         class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <img class="w-8 h-8 rounded-full object-cover" 
                                 :src="user.profile_photo_url" 
                                 :alt="user.name">
                            <div class="ml-4 dark:text-white">
                                {{ user.name }}
                            </div>
                        </div>

                        <div class="flex items-center">
                            <!-- Manage Team Member Role -->
                            <button
                                v-if="permissions.can_manage_members && available_roles.length"
                                class="ml-2 text-sm text-gray-400 underline"
                                @click="manageRole(user)"
                            >
                                {{ user.role.name }}
                            </button>

                            <div v-else-if="available_roles.length" class="ml-2 text-sm text-gray-400">
                                {{ user.role.name }}
                            </div>

                            <!-- Leave Team -->
                            <button
                                v-if="$page.props.auth.user.id === user.id"
                                class="cursor-pointer ml-6 text-sm text-red-500"
                                @click="confirmLeavingTeam"
                            >
                                Leave
                            </button>

                            <!-- Remove Team Member -->
                            <button
                                v-else-if="permissions.can_manage_members"
                                class="cursor-pointer ml-6 text-sm text-red-500"
                                @click="confirmTeamMemberRemoval(user)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State for No Members -->
            <div v-else class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Team Members</h3>
                <p class="text-gray-600 mb-4">This team doesn't have any members yet.</p>
                <div v-if="permissions.can_invite" class="text-sm text-gray-500">
                    Use the "Add Team Member" form above to invite people to this team.
                </div>
            </div>
        </div>

        <!-- Role Management Modal -->
        <Modal :show="currentlyManagingRole" @close="currentlyManagingRole = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Manage Role
                </h2>

                <div v-if="managingRoleFor">
                    <div class="space-y-2">
                        <div v-for="role in available_roles" :key="role.id">
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700"
                                   :class="{'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800': updateRoleForm.role_id === role.id}">
                                <input type="radio"
                                       :value="role.id"
                                       v-model="updateRoleForm.role_id"
                                       class="sr-only">
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                                        {{ role.name }}
                                    </div>
                                    <div v-if="role.description" class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                        {{ role.description }}
                                    </div>
                                </div>
                                <div v-if="updateRoleForm.role_id === role.id" class="ml-auto">
                                    <i class="fas fa-check text-blue-600"></i>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <SecondaryButton @click="currentlyManagingRole = false">
                        Cancel
                    </SecondaryButton>

                    <PrimaryButton
                        :class="{ 'opacity-25': updateRoleForm.processing }"
                        :disabled="updateRoleForm.processing"
                        @click="updateRole"
                    >
                        Save
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Leave Team Confirmation Modal -->
        <Modal :show="confirmingLeavingTeam" @close="confirmingLeavingTeam = false">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-orange-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Leave Team</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you would like to leave this team?
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="confirmingLeavingTeam = false">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        :class="{ 'opacity-25': leaveTeamForm.processing }"
                        :disabled="leaveTeamForm.processing"
                        @click="leaveTeam"
                    >
                        Leave
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Remove Team Member Confirmation Modal -->
        <Modal :show="teamMemberBeingRemoved" @close="teamMemberBeingRemoved = null">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Remove Team Member</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you would like to remove this person from the team?
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="teamMemberBeingRemoved = null">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        :class="{ 'opacity-25': removeTeamMemberForm.processing }"
                        :disabled="removeTeamMemberForm.processing"
                        @click="removeTeamMember"
                    >
                        Remove
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>