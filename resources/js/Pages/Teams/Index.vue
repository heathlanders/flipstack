<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DashboardCard from '@/Components/Cards/DashboardCard.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PaginationLinks from '@/Components/Pagination/PaginationLinks.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
    teams: Object,
    stats: Object,
    filters: Object,
    departments: Array,
    can_create_team: Boolean
})

// Reactive filters
const search = ref(props.filters.search || '')
const selectedDepartment = ref(props.filters.department || '')
const selectedStatus = ref(props.filters.status || '')

// Modal states
const showLeaveModal = ref(false)
const teamToLeave = ref(null)
const isJoining = ref(false)

// Clear all filters
const clearFilters = () => {
    search.value = ''
    selectedDepartment.value = ''
    selectedStatus.value = ''
}

// Team actions
const confirmLeaveTeam = (team) => {
    teamToLeave.value = team
    showLeaveModal.value = true
}

const leaveTeam = () => {
    if (teamToLeave.value) {
        router.delete(route('teams.leave', teamToLeave.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showLeaveModal.value = false
                teamToLeave.value = null
            }
        })
    }
}

const joinTeam = (team) => {
    if (isJoining.value) return // Prevent double clicks
    
    isJoining.value = true
    router.post(route('teams.join', team.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // Refresh the page to update team membership status
            router.reload({
                only: ['teams'],
                preserveScroll: true
            })
        },
        onError: (errors) => {
            console.error('Join team error:', errors)
        },
        onFinish: () => {
            isJoining.value = false
        }
    })
}

const viewDetails = (team) => {
    router.visit(route('teams.show', team.id))
}

const editTeam = (team) => {
    router.visit(route('teams.edit', team.id))
}

// Get team color and styling
const getDepartmentClasses = (department) => {
    const colorMap = {
        sourcing: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-600 dark:text-blue-400'
        },
        listing: {
            bg: 'bg-emerald-100 dark:bg-emerald-900/30',
            text: 'text-emerald-600 dark:text-emerald-400'
        },
        fulfillment: {
            bg: 'bg-orange-100 dark:bg-orange-900/30',
            text: 'text-orange-600 dark:text-orange-400'
        },
        management: {
            bg: 'bg-purple-100 dark:bg-purple-900/30',
            text: 'text-purple-600 dark:text-purple-400'
        },
        support: {
            bg: 'bg-pink-100 dark:bg-pink-900/30',
            text: 'text-pink-600 dark:text-pink-400'
        }
    }
    return colorMap[department] || {
        bg: 'bg-gray-100 dark:bg-gray-900/30',
        text: 'text-gray-600 dark:text-gray-400'
    }
}

const getDepartmentIcon = (department) => {
    const icons = {
        sourcing: 'fas fa-shopping-cart',
        listing: 'fas fa-list-alt',
        fulfillment: 'fas fa-truck',
        management: 'fas fa-users-cog',
        support: 'fas fa-headset'
    }
    return icons[department] || 'fas fa-users'
}

// Safe score classes (in case productivity_score doesn't exist)
const getScoreClasses = (score) => {
    if (!score && score !== 0) return { text: 'text-gray-500 dark:text-gray-400' }
    
    if (score >= 80) {
        return { text: 'text-emerald-600 dark:text-emerald-400' }
    }
    if (score >= 60) {
        return { text: 'text-yellow-600 dark:text-yellow-400' }
    }
    return { text: 'text-red-600 dark:text-red-400' }
}

// Page actions for the toolbar
const pageActions = computed(() => {
    const actions = []

    if (props.can_create_team) {
        actions.push({
            label: 'Create Team',
            icon: 'fas fa-plus',
            href: route('teams.create'),
            variant: 'primary'
        })
    }

    return actions
})

// Update URL when filters change - using manual debouncing like users/index.vue
let debounceTimer = null
watch([search, selectedDepartment, selectedStatus], () => {
    if (debounceTimer) clearTimeout(debounceTimer)
    
    debounceTimer = setTimeout(() => {
        const params = {}
        if (search.value) params.search = search.value
        if (selectedDepartment.value) params.department = selectedDepartment.value
        if (selectedStatus.value) params.status = selectedStatus.value
        
        router.get(route('teams.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        })
    }, 300)
})
</script>

<template>
    <AppLayout
        title="Team Management"
        page-title="Team Management"
        page-description="Manage teams and collaborate across departments"
        :page-actions="pageActions"
    >

        <div class="space-y-6">
            <!-- Team Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <DashboardCard 
                    title="Total Teams" 
                    :value="stats.total_teams || 0"
                    icon="fas fa-users"
                    color="blue"
                    subtitle="All teams"
                />
                
                <DashboardCard 
                    title="Active Teams" 
                    :value="stats.active_teams || 0"
                    icon="fas fa-check-circle"
                    color="emerald"
                    subtitle="Currently active"
                />
                
                <DashboardCard 
                    title="Team Members" 
                    :value="stats.total_members || 0"
                    icon="fas fa-user-friends"
                    color="purple"
                    subtitle="Total across all teams"
                />
                
                <DashboardCard 
                    title="Departments" 
                    :value="Object.keys(stats.departments || {}).length"
                    icon="fas fa-building"
                    color="orange"
                    subtitle="Active departments"
                />
            </div>

            <!-- Filters & Search -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <!-- Search -->
                    <div class="flex-1 max-w-md">
                        <SearchInput 
                            v-model="search"
                            placeholder="Search teams by name or description..."
                            class="w-full"
                        />
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                        <!-- Department Filter -->
                        <select v-model="selectedDepartment"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">All Departments</option>
                            <option v-for="dept in departments" :key="dept.value" :value="dept.value">
                                {{ dept.label }}
                            </option>
                        </select>
                        
                        <!-- Status Filter -->
                        <select v-model="selectedStatus"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        
                        <!-- Clear Filters -->
                        <button @click="clearFilters"
                                v-if="search || selectedDepartment || selectedStatus"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm px-3 py-2">
                            <i class="fas fa-times mr-1"></i>
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Teams Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="team in teams.data" :key="team.id"
                     class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
                    
                    <!-- Team Header -->
                    <div class="flex items-start space-x-3 mb-4">
                        <!-- Team Icon -->
                        <div :class="[
                            'w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0',
                            getDepartmentClasses(team.department).bg
                        ]">
                            <i :class="[
                                getDepartmentIcon(team.department),
                                'text-lg',
                                getDepartmentClasses(team.department).text
                            ]"></i>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-1">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                    {{ team.name || 'Unnamed Team' }}
                                </h3>
                                <!-- Department Badge -->
                                <span :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full flex-shrink-0',
                                    getDepartmentClasses(team.department).bg,
                                    getDepartmentClasses(team.department).text
                                ]">
                                    {{ team.department || 'General' }}
                                </span>
                            </div>
                            
                            <p v-if="team.description" 
                               class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-2">
                                {{ team.description }}
                            </p>
                            
                            <!-- Member Count -->
                            <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-users"></i>
                                    <span>{{ team.member_count || 0 }} member{{ (team.member_count || 0) === 1 ? '' : 's' }}</span>
                                </div>
                                <!-- Only show performance if it exists -->
                                <div v-if="team.productivity_score !== undefined && team.productivity_score !== null"
                                     class="flex items-center space-x-1">
                                    <i class="fas fa-chart-line"></i>
                                    <span :class="getScoreClasses(team.productivity_score).text">{{ team.productivity_score }}% performance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team Lead -->
                    <div v-if="team.team_lead" class="mb-6">
                        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Team Lead</div>
                        <div class="flex items-center space-x-2">
                            <img :src="team.team_lead.avatar" 
                                 :alt="team.team_lead.name"
                                 class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ team.team_lead.name }}</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-3">
                        <!-- View & Join/Leave Buttons -->
                        <div class="flex space-x-2">
                            <!-- View Details Button -->
                            <button @click="viewDetails(team)"
                                    class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded-lg border border-blue-200 dark:border-blue-800 transition-all duration-200">
                                <i class="fas fa-eye text-sm"></i>
                                <span class="text-sm font-medium">View Details</span>
                            </button>
                            
                            <!-- Join/Leave Team Button -->
                            <template v-if="team.is_member">
                                <button @click="confirmLeaveTeam(team)"
                                        class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-700 dark:text-red-300 rounded-lg border border-red-200 dark:border-red-800 transition-all duration-200">
                                    <i class="fas fa-sign-out-alt text-sm"></i>
                                    <span class="text-sm font-medium">Leave Team</span>
                                </button>
                            </template>
                            <template v-else-if="!team.max_members || team.member_count < team.max_members">
                                <button @click="joinTeam(team)"
                                        :disabled="isJoining"
                                        class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 rounded-lg border border-emerald-200 dark:border-emerald-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-spinner fa-spin text-sm" v-if="isJoining"></i>
                                    <i class="fas fa-plus text-sm" v-else></i>
                                    <span class="text-sm font-medium">{{ isJoining ? 'Joining...' : 'Join Team' }}</span>
                                </button>
                            </template>
                            <template v-else>
                                <div class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-gray-50 text-gray-400 rounded-lg border border-gray-200">
                                    <span class="text-sm font-medium">Team Full</span>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Edit Button -->
                        <div v-if="team.can_manage || team.can_edit">
                            <button @click="editTeam(team)"
                                    class="w-full flex items-center justify-center space-x-2 px-3 py-2 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/40 text-purple-700 dark:text-purple-300 rounded-lg border border-purple-200 dark:border-purple-800 transition-all duration-200">
                                <i class="fas fa-edit text-sm"></i>
                                <span class="text-sm font-medium">Edit Team</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="teams.data.length === 0" 
                 class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="max-w-sm mx-auto">
                    <i class="fas fa-users text-gray-300 dark:text-gray-600 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        No teams found
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ (search || selectedDepartment || selectedStatus) 
                            ? "Try adjusting your filters or search terms." 
                            : "Get started by creating your first team." }}
                    </p>
                    
                    <div class="flex justify-center space-x-3">
                        <button v-if="search || selectedDepartment || selectedStatus"
                                @click="clearFilters"
                                class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                            Clear Filters
                        </button>
                        
                        <button v-if="can_create_team"
                                @click="$inertia.visit(route('teams.create'))"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            Create First Team
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="teams.links && teams.data.length > 0">
                <PaginationLinks :links="teams.links" />
            </div>
        </div>

        <!-- Leave Team Confirmation Modal -->
        <Modal :show="showLeaveModal" @close="showLeaveModal = false">
            <div v-if="teamToLeave" class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-orange-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Leave Team</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you want to leave "{{ teamToLeave.name || 'this team' }}"?
                        </p>
                    </div>
                </div>
                
                <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6">
                    <p class="text-orange-800 dark:text-orange-300 text-sm">
                        You will lose access to team resources and need to request to join again.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showLeaveModal = false">Cancel</SecondaryButton>
                    <DangerButton @click="leaveTeam">Leave Team</DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>