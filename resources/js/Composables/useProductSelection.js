import { ref, computed } from 'vue';

export function useProductSelection(products) {
    const selectedProducts = ref([]);

    const allSelected = computed(() => {
        if (!products.value || products.value.length === 0) return false;
        return products.value.every(p => selectedProducts.value.includes(p.id));
    });

    const someSelected = computed(() => {
        return selectedProducts.value.length > 0 && !allSelected.value;
    });

    const hasSelection = computed(() => selectedProducts.value.length > 0);

    const selectionCount = computed(() => selectedProducts.value.length);

    const isSelected = (productId) => {
        return selectedProducts.value.includes(productId);
    };

    const toggleSelection = (productId) => {
        const index = selectedProducts.value.indexOf(productId);
        if (index > -1) {
            selectedProducts.value.splice(index, 1);
        } else {
            selectedProducts.value.push(productId);
        }
    };

    const toggleSelectAll = () => {
        if (allSelected.value) {
            selectedProducts.value = [];
        } else {
            selectedProducts.value = products.value.map(p => p.id);
        }
    };

    const clearSelection = () => {
        selectedProducts.value = [];
    };

    const selectProducts = (productIds) => {
        selectedProducts.value = [...new Set([...selectedProducts.value, ...productIds])];
    };

    const deselectProducts = (productIds) => {
        selectedProducts.value = selectedProducts.value.filter(id => !productIds.includes(id));
    };

    return {
        selectedProducts,
        allSelected,
        someSelected,
        hasSelection,
        selectionCount,
        isSelected,
        toggleSelection,
        toggleSelectAll,
        clearSelection,
        selectProducts,
        deselectProducts,
    };
}
