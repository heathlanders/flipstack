<template>
    <div class="space-y-6">
        <!-- Overall Score Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Performance Score</h3>
                <span :class="scoreColorClass" class="text-3xl font-bold">
                    {{ overallScore.toFixed(1) }}%
                </span>
            </div>

            <!-- Score Gauge -->
            <div class="relative pt-1 mb-4">
                <div class="flex mb-2 items-center justify-between">
                    <div class="text-xs text-gray-500 dark:text-gray-400">0</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">50</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">100</div>
                </div>
                <div class="overflow-hidden h-4 text-xs flex rounded-full bg-gray-200 dark:bg-gray-700">
                    <div 
                        :class="scoreBackgroundClass"
                        class="transition-all duration-500 ease-out rounded-full"
                        :style="{ width: overallScore + '%' }"
                    ></div>
                </div>
                <!-- Tier thresholds -->
                <div class="relative h-2 mt-1">
                    <div 
                        v-for="threshold in tierThresholds" 
                        :key="threshold.score"
                        class="absolute w-px h-2 bg-gray-400 dark:bg-gray-500"
                        :style="{ left: threshold.score + '%' }"
                        :title="threshold.label"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Metric Categories -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Submission Metrics -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center space-x-2 mb-4">
                    <DocumentPlusIcon class="w-5 h-5 text-blue-500" />
                    <h4 class="font-medium text-gray-900 dark:text-white">Submission Quality</h4>
                </div>
                
                <MetricRow 
                    label="Acceptance Rate" 
                    :value="metrics.acceptance_rate" 
                    :target="85"
                    format="percent"
                    :weight="weights.acceptance_rate"
                />
                <MetricRow 
                    label="Items Submitted" 
                    :value="metrics.items_submitted" 
                    :target="100"
                    format="number"
                />
                <MetricRow 
                    label="Rejection Rate" 
                    :value="metrics.rejection_rate" 
                    :target="15"
                    :inverse="true"
                    format="percent"
                />
            </div>

            <!-- Sales Metrics -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center space-x-2 mb-4">
                    <CurrencyDollarIcon class="w-5 h-5 text-green-500" />
                    <h4 class="font-medium text-gray-900 dark:text-white">Sales Performance</h4>
                </div>
                
                <MetricRow 
                    label="Items Sold" 
                    :value="metrics.items_sold" 
                    :target="50"
                    format="number"
                    :weight="weights.items_sold"
                />
                <MetricRow 
                    label="Total Sales" 
                    :value="metrics.total_sales" 
                    :target="5000"
                    format="currency"
                />
                <MetricRow 
                    label="Avg Sale Price" 
                    :value="metrics.average_sale_price" 
                    :target="100"
                    format="currency"
                />
            </div>

            <!-- Return Metrics -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center space-x-2 mb-4">
                    <ArrowUturnLeftIcon class="w-5 h-5 text-orange-500" />
                    <h4 class="font-medium text-gray-900 dark:text-white">Returns & Quality</h4>
                </div>
                
                <MetricRow 
                    label="Return Rate" 
                    :value="metrics.return_rate" 
                    :target="5"
                    :inverse="true"
                    format="percent"
                    :weight="weights.return_rate"
                />
                <MetricRow 
                    label="Defect Rate" 
                    :value="metrics.defect_rate" 
                    :target="2"
                    :inverse="true"
                    format="percent"
                />
                <MetricRow 
                    label="Items Returned" 
                    :value="metrics.items_returned" 
                    format="number"
                />
            </div>

            <!-- Customer Satisfaction -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center space-x-2 mb-4">
                    <StarIcon class="w-5 h-5 text-yellow-500" />
                    <h4 class="font-medium text-gray-900 dark:text-white">Customer Satisfaction</h4>
                </div>
                
                <MetricRow 
                    label="Avg Rating" 
                    :value="metrics.average_rating" 
                    :target="4.5"
                    :max="5"
                    format="rating"
                    :weight="weights.customer_rating"
                />
                <MetricRow 
                    label="Review Count" 
                    :value="metrics.review_count" 
                    :target="20"
                    format="number"
                />
                <MetricRow 
                    label="Response Time" 
                    :value="metrics.response_time_hours" 
                    :target="24"
                    :inverse="true"
                    format="hours"
                />
            </div>
        </div>

        <!-- Score Breakdown -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h4 class="font-medium text-gray-900 dark:text-white mb-4">Score Breakdown</h4>
            
            <div class="space-y-4">
                <div v-for="(component, key) in scoreBreakdown" :key="key" class="flex items-center">
                    <div class="w-40 text-sm text-gray-600 dark:text-gray-400">{{ component.label }}</div>
                    <div class="flex-1 mx-4">
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div 
                                :class="getScoreClass(component.score)"
                                class="h-2 rounded-full transition-all duration-500"
                                :style="{ width: component.score + '%' }"
                            ></div>
                        </div>
                    </div>
                    <div class="w-20 text-right">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ component.score.toFixed(1) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                            ({{ component.weight }}%)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Weighted Total -->
            <div class="flex items-center mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="w-40 text-sm font-medium text-gray-900 dark:text-white">Weighted Total</div>
                <div class="flex-1 mx-4"></div>
                <div class="w-20 text-right">
                    <span :class="scoreColorClass" class="text-lg font-bold">
                        {{ overallScore.toFixed(1) }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { 
    DocumentPlusIcon, 
    CurrencyDollarIcon, 
    ArrowUturnLeftIcon,
    StarIcon 
} from '@heroicons/vue/24/outline';

// MetricRow sub-component
const MetricRow = {
    props: ['label', 'value', 'target', 'format', 'weight', 'inverse', 'max'],
    template: `
        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ label }}</span>
                <span v-if="weight" class="text-xs text-gray-400 dark:text-gray-500">({{ weight }}%)</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ formatValue(value) }}</span>
                <span v-if="target" :class="getStatusClass()" class="w-2 h-2 rounded-full"></span>
            </div>
        </div>
    `,
    methods: {
        formatValue(val) {
            if (val === null || val === undefined) return 'N/A';
            switch (this.format) {
                case 'percent': return val.toFixed(1) + '%';
                case 'currency': return '$' + val.toLocaleString();
                case 'rating': return val.toFixed(1) + ' / ' + (this.max || 5);
                case 'hours': return val + 'h';
                default: return val.toLocaleString();
            }
        },
        getStatusClass() {
            if (!this.target) return 'bg-gray-300';
            const ratio = this.inverse 
                ? this.target / this.value 
                : this.value / this.target;
            if (ratio >= 1) return 'bg-green-500';
            if (ratio >= 0.8) return 'bg-yellow-500';
            return 'bg-red-500';
        }
    }
};

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({})
    },
    weights: {
        type: Object,
        default: () => ({
            acceptance_rate: 25,
            items_sold: 20,
            return_rate: 20,
            customer_rating: 20,
            compliance: 15
        })
    },
    tierThresholds: {
        type: Array,
        default: () => [
            { score: 65, label: 'Bronze' },
            { score: 75, label: 'Silver' },
            { score: 85, label: 'Gold' },
            { score: 95, label: 'Platinum' }
        ]
    }
});

const overallScore = computed(() => {
    return props.metrics.overall_score || 0;
});

const scoreColorClass = computed(() => {
    if (overallScore.value >= 95) return 'text-purple-600 dark:text-purple-400';
    if (overallScore.value >= 85) return 'text-yellow-600 dark:text-yellow-400';
    if (overallScore.value >= 75) return 'text-gray-600 dark:text-gray-400';
    if (overallScore.value >= 65) return 'text-orange-600 dark:text-orange-400';
    return 'text-red-600 dark:text-red-400';
});

const scoreBackgroundClass = computed(() => {
    if (overallScore.value >= 95) return 'bg-purple-500';
    if (overallScore.value >= 85) return 'bg-yellow-500';
    if (overallScore.value >= 75) return 'bg-gray-500';
    if (overallScore.value >= 65) return 'bg-orange-500';
    return 'bg-red-500';
});

const scoreBreakdown = computed(() => ({
    submission: {
        label: 'Submission Quality',
        score: (props.metrics.acceptance_rate || 0),
        weight: props.weights.acceptance_rate
    },
    sales: {
        label: 'Sales Performance',
        score: Math.min((props.metrics.items_sold || 0) / 50 * 100, 100),
        weight: props.weights.items_sold
    },
    returns: {
        label: 'Return Rate',
        score: Math.max(100 - (props.metrics.return_rate || 0) * 10, 0),
        weight: props.weights.return_rate
    },
    satisfaction: {
        label: 'Customer Satisfaction',
        score: ((props.metrics.average_rating || 0) / 5) * 100,
        weight: props.weights.customer_rating
    },
    compliance: {
        label: 'Compliance',
        score: props.metrics.compliance_score || 100,
        weight: props.weights.compliance
    }
}));

function getScoreClass(score) {
    if (score >= 80) return 'bg-green-500';
    if (score >= 60) return 'bg-yellow-500';
    return 'bg-red-500';
}
</script>
