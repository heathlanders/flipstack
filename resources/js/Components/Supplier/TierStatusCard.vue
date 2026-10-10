<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Tier Header with Color -->
        <div 
            :class="tierColorClass" 
            class="px-6 py-4 text-white"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <component :is="tierIcon" class="w-8 h-8" />
                    <div>
                        <h3 class="text-lg font-semibold">{{ tier.name }} Tier</h3>
                        <p class="text-sm opacity-90">{{ tier.description }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold">{{ performanceScore }}%</div>
                    <div class="text-sm opacity-90">Performance Score</div>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="px-6 py-4">
            <!-- Current Score Progress -->
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Score Progress
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ performanceScore }}% / {{ tier.max_score || 100 }}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div 
                        :class="tierColorClass"
                        class="h-3 rounded-full transition-all duration-500"
                        :style="{ width: `${scorePercentage}%` }"
                    ></div>
                </div>
            </div>

            <!-- Next Tier Progress (if applicable) -->
            <div v-if="nextTier" class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Progress to {{ nextTier.name }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ pointsToNextTier }} points needed
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div 
                        class="bg-green-500 h-2 rounded-full transition-all duration-500"
                        :style="{ width: `${nextTierProgress}%` }"
                    ></div>
                </div>
            </div>

            <!-- Tier Benefits -->
            <div v-if="tier.benefits && tier.benefits.length > 0" class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Your Benefits
                </h4>
                <ul class="space-y-1">
                    <li 
                        v-for="(benefit, index) in tier.benefits.slice(0, showAllBenefits ? undefined : 3)" 
                        :key="index"
                        class="flex items-center text-sm text-gray-600 dark:text-gray-400"
                    >
                        <CheckCircleIcon class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" />
                        {{ benefit }}
                    </li>
                </ul>
                <button 
                    v-if="tier.benefits.length > 3"
                    @click="showAllBenefits = !showAllBenefits"
                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline mt-2"
                >
                    {{ showAllBenefits ? 'Show less' : `+${tier.benefits.length - 3} more benefits` }}
                </button>
            </div>

            <!-- Commission Rate -->
            <div class="flex items-center justify-between py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2">
                    <CurrencyDollarIcon class="w-5 h-5 text-gray-400" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Commission Rate</span>
                </div>
                <div class="text-right">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ effectiveCommissionRate }}%
                    </span>
                    <span 
                        v-if="tier.commission_adjustment !== 0"
                        :class="tier.commission_adjustment < 0 ? 'text-green-600' : 'text-red-600'"
                        class="text-xs ml-1"
                    >
                        ({{ tier.commission_adjustment > 0 ? '+' : '' }}{{ tier.commission_adjustment }}%)
                    </span>
                </div>
            </div>

            <!-- Settlement Frequency -->
            <div class="flex items-center justify-between py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2">
                    <CalendarIcon class="w-5 h-5 text-gray-400" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Settlement</span>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                    {{ tier.settlement_frequency }}
                </span>
            </div>

            <!-- Time in Tier -->
            <div class="flex items-center justify-between py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2">
                    <ClockIcon class="w-5 h-5 text-gray-400" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Time in Tier</span>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ timeInTier }}
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex space-x-3">
                <Link 
                    :href="route('supplier.performance.index')"
                    class="flex-1 text-center px-4 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-600 rounded-lg hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors"
                >
                    View Performance
                </Link>
                <button 
                    @click="$emit('compare-tiers')"
                    class="flex-1 text-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    Compare Tiers
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    CheckCircleIcon, 
    CurrencyDollarIcon, 
    CalendarIcon, 
    ClockIcon,
    StarIcon,
    SparklesIcon,
    ShieldCheckIcon,
    TrophyIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    tier: {
        type: Object,
        required: true
    },
    nextTier: {
        type: Object,
        default: null
    },
    performanceScore: {
        type: Number,
        required: true
    },
    baseCommissionRate: {
        type: Number,
        default: 20
    },
    tierStartDate: {
        type: String,
        default: null
    }
});

defineEmits(['compare-tiers']);

const showAllBenefits = ref(false);

const tierColorClass = computed(() => {
    const colors = {
        'platinum': 'bg-gradient-to-r from-purple-600 to-indigo-600',
        'gold': 'bg-gradient-to-r from-yellow-500 to-amber-500',
        'silver': 'bg-gradient-to-r from-gray-400 to-gray-500',
        'bronze': 'bg-gradient-to-r from-orange-600 to-amber-700',
        'probation': 'bg-gradient-to-r from-red-500 to-red-600'
    };
    return colors[props.tier.slug] || 'bg-gray-500';
});

const tierIcon = computed(() => {
    const icons = {
        'platinum': SparklesIcon,
        'gold': TrophyIcon,
        'silver': ShieldCheckIcon,
        'bronze': StarIcon,
        'probation': ExclamationTriangleIcon
    };
    return icons[props.tier.slug] || StarIcon;
});

const scorePercentage = computed(() => {
    const max = props.tier.max_score || 100;
    return Math.min((props.performanceScore / max) * 100, 100);
});

const effectiveCommissionRate = computed(() => {
    return props.baseCommissionRate + (props.tier.commission_adjustment || 0);
});

const pointsToNextTier = computed(() => {
    if (!props.nextTier) return 0;
    return Math.max(0, props.nextTier.min_score - props.performanceScore);
});

const nextTierProgress = computed(() => {
    if (!props.nextTier) return 100;
    const currentTierMin = props.tier.min_score || 0;
    const nextTierMin = props.nextTier.min_score;
    const range = nextTierMin - currentTierMin;
    const progress = props.performanceScore - currentTierMin;
    return Math.min(Math.max((progress / range) * 100, 0), 100);
});

const timeInTier = computed(() => {
    if (!props.tierStartDate) return 'N/A';
    
    const start = new Date(props.tierStartDate);
    const now = new Date();
    const diffMs = now - start;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays < 1) return 'Today';
    if (diffDays === 1) return '1 day';
    if (diffDays < 30) return `${diffDays} days`;
    
    const months = Math.floor(diffDays / 30);
    if (months === 1) return '1 month';
    if (months < 12) return `${months} months`;
    
    const years = Math.floor(months / 12);
    const remainingMonths = months % 12;
    if (remainingMonths === 0) return `${years} year${years > 1 ? 's' : ''}`;
    return `${years}y ${remainingMonths}m`;
});
</script>
