<template>
    <AppLayout :title="category.name">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <Link :href="route('inventory.categories.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div class="flex items-center space-x-3">
                        <span
                            class="inline-flex items-center justify-center h-10 w-10 rounded-lg text-white text-lg"
                            :style="{ backgroundColor: category.color || '#6B7280' }"
                        >
                            <i v-if="category.icon" :class="category.icon"></i>
                            <i v-else class="fas fa-folder"></i>
                        </span>
                        <div>
                            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ category.name }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ category.path }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <Link
                        :href="route('inventory.categories.products', category.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <i class="fas fa-box mr-2"></i>Products
                    </Link>
                    <Link
                        v-if="canEdit"
                        :href="route('inventory.categories.edit', category.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <i class="fas fa-edit mr-2"></i>Edit
                    </Link>
                    <button
                        v-if="canEdit"
                        type="button"
                        @click="confirmDelete"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Analytics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Products</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ analytics.products_count || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active Products</div>
                        <div class="text-2xl font-semibold text-green-600 dark:text-green-400">{{ analytics.active_products_count || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Avg Price</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(analytics.avg_price) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Low Stock</div>
                        <div class="text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ analytics.low_stock_count || 0 }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Details + Subcategories -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Details</h3>
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Slug</dt>
                                    <dd class="font-mono text-gray-900 dark:text-white">{{ category.slug }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Depth</dt>
                                    <dd class="text-gray-900 dark:text-white">{{ category.depth }}</dd>
                                </div>
                                <div v-if="category.parent">
                                    <dt class="text-gray-500 dark:text-gray-400">Parent</dt>
                                    <dd>
                                        <Link :href="route('inventory.categories.show', category.parent.id)" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ category.parent.name }}
                                        </Link>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="category.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'">
                                            {{ category.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </dd>
                                </div>
                                <div v-if="category.description" class="md:col-span-2">
                                    <dt class="text-gray-500 dark:text-gray-400">Description</dt>
                                    <dd class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ category.description }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Subcategories -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Subcategories ({{ subcategories.length }})</h3>
                            <div v-if="subcategories.length" class="space-y-2">
                                <SubcategoryNode v-for="sub in subcategories" :key="sub.id" :node="sub" />
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No subcategories.</p>
                        </div>

                        <!-- Recent Products -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Products</h3>
                                <Link :href="route('inventory.categories.products', category.id)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    View all →
                                </Link>
                            </div>
                            <div v-if="category.products?.length" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div v-for="product in category.products" :key="product.id" class="py-3 flex items-center justify-between">
                                    <div>
                                        <Link :href="route('inventory.products.show', product.id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ product.name }}</Link>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ product.sku }}</div>
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(product.selling_price) }}</div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No products in this category.</p>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">Total Value</h3>
                            <div class="text-3xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(analytics.total_value) }}</div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sum of selling prices</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, h } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    category: { type: Object, required: true },
    analytics: { type: Object, default: () => ({}) },
    subcategories: { type: Array, default: () => [] },
})

const page = usePage()
const canEdit = computed(() => page.props.auth?.user?.permissions?.['admin.access'])

const formatCurrency = (val) => {
    if (val == null) return '—'
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val)
}

const confirmDelete = () => {
    if (confirm(`Delete category "${props.category.name}"? Cannot delete if it has products or subcategories.`)) {
        router.delete(route('inventory.categories.destroy', props.category.id))
    }
}

// Recursive subcategory node component (inline)
const SubcategoryNode = {
    props: ['node', 'depth'],
    setup(p) {
        const indent = (p.depth || 0) * 16
        return () =>
            h('div', { class: 'space-y-1' }, [
                h('div', {
                    class: 'flex items-center justify-between py-1.5 px-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50',
                    style: { paddingLeft: `${indent + 8}px` },
                }, [
                    h(Link, {
                        href: route('inventory.categories.show', p.node.id),
                        class: 'text-sm text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400',
                    }, () => p.node.name),
                    h('span', { class: 'text-xs text-gray-500 dark:text-gray-400' }, `${p.node.products_count || 0} products`),
                ]),
                ...(p.node.children || []).map(child =>
                    h(SubcategoryNode, { node: child, depth: (p.depth || 0) + 1, key: child.id })
                ),
            ])
    },
}
</script>
