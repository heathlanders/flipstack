<script setup>
const props = defineProps({ data: { type: Object, required: true } });
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Lister / Owner Cross-Reference</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            eBay SKU parser maps lister initials to <code>products.attributes.lister_initials</code>
            but does <strong>not</strong> create a supplier link. This panel surfaces overlap so
            mis-attributed inventory can be reviewed.
        </p>
        <dl class="space-y-3">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Org Known Lister Codes</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    <span v-if="!data.known_lister_codes.length" class="text-sm text-gray-400">none configured</span>
                    <span
                        v-for="code in data.known_lister_codes"
                        :key="code"
                        class="px-2 py-0.5 text-xs rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200"
                    >{{ code }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Codes Seen on This Supplier's Products</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    <span v-if="!data.supplier_lister_codes.length" class="text-sm text-gray-400">none</span>
                    <span
                        v-for="code in data.supplier_lister_codes"
                        :key="code"
                        class="px-2 py-0.5 text-xs rounded bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300"
                    >{{ code }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Org-wide Products Tagged with Lister Code but No Supplier Link</dt>
                <dd class="text-sm font-semibold" :class="data.org_unlinked_lister_products > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white'">
                    {{ data.org_unlinked_lister_products }}
                </dd>
            </div>
        </dl>
    </div>
</template>
