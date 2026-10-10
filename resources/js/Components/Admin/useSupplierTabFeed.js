import { ref } from 'vue';
import axios from 'axios';

export function useSupplierTabFeed(routeName, supplierId, defaults = {}) {
    const loading = ref(false);
    const page = ref(1);
    const filters = ref({ ...defaults });
    const result = ref({ data: [], links: [], meta: null });

    async function load() {
        loading.value = true;
        try {
            const { route } = await import('ziggy-js');
            const url = route(routeName, supplierId);
            const { data } = await axios.get(url, {
                params: { ...filters.value, page: page.value },
            });
            result.value = data;
        } finally {
            loading.value = false;
        }
    }

    function goTo(p) {
        page.value = p;
        load();
    }

    function setFilter(key, value) {
        filters.value[key] = value;
        page.value = 1;
        load();
    }

    return { loading, page, filters, result, load, goTo, setFilter };
}
