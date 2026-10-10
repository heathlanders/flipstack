<script setup>
import { computed } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    bin: { type: Object, required: true },
    zone: { type: Object, default: null },
    levels: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const fullCode = computed(() => `${props.zone?.code ?? ''}-${props.bin.code}`)
const fillPct = computed(() => {
    if (!props.bin.max_capacity || props.bin.max_capacity <= 0) return null
    return Math.min(100, Math.round((props.bin.current_count / props.bin.max_capacity) * 100))
})

function imageUrl(product) {
    if (!product) return null
    if (product.primary_image?.url) return product.primary_image.url
    if (product.featured_image) return product.featured_image
    return null
}

function sortBy(col) {
    const sameCol = props.filters.sort === col
    const dir = sameCol ? (props.filters.dir === 'asc' ? 'desc' : 'asc') : 'asc'
    router.get(route('inventory.warehouse.bins.show', props.bin.id), { sort: col, dir }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function sortIcon(col) {
    if (props.filters.sort !== col) return ''
    return props.filters.dir === 'asc' ? ' ↑' : ' ↓'
}
</script>

<template>
    <Head :title="`Bin ${fullCode}`" />

    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="text-sm text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2 flex-wrap">
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: zone?.location_id })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Warehouse
                    </Link>
                    <span>/</span>
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: zone?.location_id })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Zones
                    </Link>
                    <span>/</span>
                    <Link v-if="zone" :href="route('inventory.warehouse.zones.show', zone.id)" class="hover:text-indigo-600 dark:hover:text-indigo-400 font-medium">
                        {{ zone.code }} {{ zone.name }}
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-gray-200 font-medium font-mono">{{ bin.code }}</span>
                </nav>

                <!-- Bin banner -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6 border-l-4 border-indigo-500">
                    <div class="flex items-center gap-5 flex-wrap">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Bin</div>
                            <div class="text-3xl font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ fullCode }}</div>
                        </div>
                        <div class="flex-1 min-w-[200px] border-l border-gray-200 dark:border-gray-700 pl-5">
                            <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Zone</div>
                            <Link v-if="zone" :href="route('inventory.warehouse.zones.show', zone.id)" class="text-lg font-semibold text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ zone.code }}</span>
                                <span class="ml-2">{{ zone.name }}</span>
                            </Link>
                            <div class="text-sm text-gray-500 dark:text-gray-400 capitalize mt-1">
                                <span v-if="zone?.type" class="inline-block px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs mr-2">{{ zone.type }}</span>
                                <span v-if="zone?.location?.name">{{ zone.location.name }}</span>
                                <span v-if="bin.label" class="ml-2">&middot; {{ bin.label }}</span>
                                <span v-if="!bin.is_active" class="ml-2 px-2 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 text-xs">Inactive</span>
                            </div>
                        </div>
                        <Link v-if="zone" :href="route('inventory.warehouse.zones.show', zone.id)" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            &larr; Back to {{ zone.code }}
                        </Link>
                    </div>
                </div>

                <!-- Bin metadata -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                        <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Bin</div>
                        <dl class="grid grid-cols-2 gap-y-2 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Code</dt>
                            <dd class="font-mono text-gray-900 dark:text-gray-100">{{ bin.code }}</dd>
                            <dt class="text-gray-500 dark:text-gray-400">Barcode</dt>
                            <dd class="font-mono text-gray-900 dark:text-gray-100">{{ bin.barcode || '-' }}</dd>
                            <dt class="text-gray-500 dark:text-gray-400">Position</dt>
                            <dd class="text-gray-900 dark:text-gray-100">
                                {{ [bin.aisle ? `Aisle ${bin.aisle}` : null, bin.rack ? `Rack ${bin.rack}` : null, bin.shelf ? `Shelf ${bin.shelf}` : null, bin.bin_position ? `Pos ${bin.bin_position}` : null].filter(Boolean).join(', ') || '-' }}
                            </dd>
                            <dt class="text-gray-500 dark:text-gray-400">Capacity</dt>
                            <dd class="text-gray-900 dark:text-gray-100">
                                <div v-if="bin.max_capacity" class="flex items-center gap-2">
                                    <div class="w-32 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500" :style="{ width: fillPct + '%' }"></div>
                                    </div>
                                    <span class="text-xs">{{ bin.current_count }}/{{ bin.max_capacity }}</span>
                                </div>
                                <span v-else>{{ bin.current_count }} / &infin;</span>
                            </dd>
                        </dl>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                        <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Contents</div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Distinct Products</div>
                                <div class="text-2xl font-bold text-indigo-600">{{ stats.distinct_products?.toLocaleString() ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">On Hand</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_qty?.toLocaleString() ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Reserved</div>
                                <div class="text-2xl font-bold text-amber-600">{{ stats.total_reserved?.toLocaleString() ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products in bin -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Products in this bin</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th @click="sortBy('product_name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Product{{ sortIcon('product_name') }}</th>
                                <th @click="sortBy('product_sku')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">SKU{{ sortIcon('product_sku') }}</th>
                                <th @click="sortBy('product_brand')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Brand{{ sortIcon('product_brand') }}</th>
                                <th @click="sortBy('quantity_on_hand')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">On Hand{{ sortIcon('quantity_on_hand') }}</th>
                                <th @click="sortBy('quantity_reserved')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Reserved{{ sortIcon('quantity_reserved') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Available</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="level in levels.data" :key="level.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 flex-shrink-0 rounded bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                            <img
                                                v-if="imageUrl(level.product)"
                                                :src="imageUrl(level.product)"
                                                :alt="level.product?.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="h-full w-full flex items-center justify-center text-gray-400 text-xs">N/A</div>
                                        </div>
                                        <div class="min-w-0">
                                            <Link
                                                v-if="level.product"
                                                :href="route('inventory.products.show', level.product.id)"
                                                class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline truncate block"
                                            >
                                                {{ level.product.name }}
                                            </Link>
                                            <span v-else class="text-sm text-gray-400">Deleted product</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-xs font-mono text-gray-600 dark:text-gray-400">{{ level.product?.sku || '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-400">{{ level.product?.brand?.name || '-' }}</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-gray-100 font-medium">{{ level.quantity_on_hand }}</td>
                                <td class="px-6 py-3 text-sm text-right text-amber-600">{{ level.quantity_reserved }}</td>
                                <td class="px-6 py-3 text-sm text-right text-emerald-600 font-medium">{{ level.quantity_available }}</td>
                                <td class="px-6 py-3 text-right">
                                    <Link
                                        v-if="level.product"
                                        :href="route('inventory.products.show', level.product.id)"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                                    >
                                        View &rsaquo;
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="levels.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No products in this bin yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="levels.last_page > 1" class="mt-4 flex justify-center">
                    <nav class="flex gap-1">
                        <Link v-for="link in levels.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded text-sm"
                            :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'" />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
