<script setup>
import { ref, reactive, watch, computed } from 'vue';

const props = defineProps({
    credentialId: { type: [Number, String], default: null },
    initialConfig: { type: Object, default: null },
    initialParsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved']);

const defaultParser = () => ({
    enabled: true,
    prefix: '',
    mode: 'regex',
    template: {
        type: 'delimiter',
        delimiter: ' ',
        segments: [
            { field: 'owner', position: 0 },
            { field: 'sku', position: 1 },
            { field: 'location', position: 2 },
        ],
        ranges: [
            { field: 'owner', start: 0, length: 5 },
            { field: 'sku', start: 5, length: 10 },
            { field: 'location', start: 15, length: 20 },
        ],
    },
    regex: {
        pattern: '/^(?P<owner>\\S+)\\s+(?P<sku>\\S+)\\s+(?P<location>.+)$/',
    },
});

// Initialize parsers from props
const initParsers = () => {
    if (props.initialParsers && props.initialParsers.length > 0) {
        return props.initialParsers.map(p => ({
            ...defaultParser(),
            ...p,
            template: { ...defaultParser().template, ...(p.template || {}) },
            regex: { ...defaultParser().regex, ...(p.regex || {}) },
        }));
    }
    if (props.initialConfig) {
        return [{
            ...defaultParser(),
            ...props.initialConfig,
            template: { ...defaultParser().template, ...(props.initialConfig.template || {}) },
            regex: { ...defaultParser().regex, ...(props.initialConfig.regex || {}) },
        }];
    }
    return [];
};

const parsers = reactive(initParsers());
const activeIndex = ref(parsers.length > 0 ? 0 : -1);

const activeParser = computed(() => {
    if (activeIndex.value >= 0 && activeIndex.value < parsers.length) {
        return parsers[activeIndex.value];
    }
    return null;
});

const addParser = () => {
    parsers.push(defaultParser());
    activeIndex.value = parsers.length - 1;
};

const removeParser = (index) => {
    parsers.splice(index, 1);
    if (activeIndex.value >= parsers.length) {
        activeIndex.value = parsers.length - 1;
    }
};

const presets = {
    arc: {
        name: 'ARC SKU',
        description: 'ARC-prefixed SKUs: ARC [initials] <product_id> [(xN)] <location>',
        prefix: 'ARC',
        pattern: '/^(?P<owner>ARC)\\s+(?:[A-Z]{2,3}\\s+)?(?P<sku>[A-Z][A-Z0-9]*-[A-Z0-9][\\w-]*(?:-[A-Z])?)(?:\\s*\\(x\\d+\\))?(?:\\s+(?P<location>.+))?$/',
        samples: [
            'ARC IND-18979-9 SHELF CM RM 133 TUB DUDEBRO',
            'ARC ABC PO-1033-1022-312 FOH C5',
            'ARC ABC CS-1043-1020-102 (x12) C3 / BOOST',
            'ARC PO-1033-1031-104 Shelf ET 1,2,3',
        ],
    },
    owner_location: {
        name: 'Owner + Location',
        description: 'OWNER <location info> -- first word is supplier, rest is location',
        prefix: '',
        pattern: '/^(?P<owner>[A-Z][A-Z0-9]{1,5})\\s+(?P<location>.+)$/',
        samples: [
            'HAWK RM 132 SHELF M GROUND LVL',
            'PINE RM 134 SHELF BO TOP TUB TENET',
            'OAKS BF4 89679',
            'COVE RM 132 SHELF 4 TUB NOV3',
            'REEF6 ABC 90508 MANCHURIANBROCCOLI EB',
        ],
    },
};

/**
 * Ready-to-use named templates surfaced as a top-level "Apply Template"
 * dropdown. Each template seeds a complete parser entry (regex, prefix,
 * samples) so an org can go from zero to working without knowing regex.
 */
const templates = [
    {
        key: 'house-assets',
        label: 'House Assets',
        description: 'OWNER (HAWK/PINE/OAKS/etc.) followed by RM/SHELF/TUB/LVL location keywords.',
        preset: 'owner_location',
        prefix: '',
        sample: 'HAWK RM 132 SHELF M GROUND LVL',
    },
    {
        key: 'arc_seller',
        label: 'ARC Marketplace Seller',
        description: 'ARC <consignor> <product-id> <location>. Use for ARC-prefixed listings.',
        preset: 'arc',
        prefix: 'ARC',
        sample: 'ARC ABC IND-18979-9 SHELF CM RM 133 TUB DUDEBRO',
    },
    {
        key: 'generic_owner',
        label: 'Generic Owner + Location',
        description: 'Any seller using OWNER + free-form location text. Same engine as the House Assets preset.',
        preset: 'owner_location',
        prefix: '',
        sample: 'COVE RM 132 SHELF 4 TUB NOV3',
    },
];

const selectedTemplateKey = ref('');

const applyTemplate = (key) => {
    if (!key) return;
    const tpl = templates.find((t) => t.key === key);
    if (!tpl) return;
    const preset = presets[tpl.preset];
    if (!preset) return;

    const parser = defaultParser();
    parser.enabled = true;
    parser.mode = 'regex';
    parser.prefix = tpl.prefix || preset.prefix || '';
    parser.regex.pattern = preset.pattern;

    parsers.push(parser);
    activeIndex.value = parsers.length - 1;
    sampleSku.value = tpl.sample || preset.samples[0] || '';
    selectedTemplateKey.value = '';
};

const sampleSku = ref('');
const testResult = ref(null);
const testing = ref(false);
const saving = ref(false);
const saveMessage = ref('');
const saveError = ref('');

const fieldOptions = [
    { value: 'owner', label: 'Owner / Supplier' },
    { value: 'sku', label: 'Internal SKU' },
    { value: 'location', label: 'Storage Location' },
];

const applyPreset = (key) => {
    const preset = presets[key];
    if (!preset || !activeParser.value) return;
    activeParser.value.enabled = true;
    activeParser.value.mode = 'regex';
    activeParser.value.regex.pattern = preset.pattern;
    if (preset.prefix) {
        activeParser.value.prefix = preset.prefix;
    }
    sampleSku.value = preset.samples[0];
};

const loadSample = (sample) => {
    sampleSku.value = sample;
};

const configPayload = (parser) => ({
    enabled: parser.enabled,
    prefix: parser.prefix || '',
    mode: parser.mode,
    template: parser.mode === 'template' ? {
        type: parser.template.type,
        delimiter: parser.template.type === 'delimiter' ? parser.template.delimiter : undefined,
        segments: parser.template.type === 'delimiter' ? parser.template.segments : undefined,
        ranges: parser.template.type === 'fixed' ? parser.template.ranges : undefined,
    } : undefined,
    regex: parser.mode === 'regex' ? { pattern: parser.regex.pattern } : undefined,
});

const allParsersPayload = computed(() => parsers.map(p => configPayload(p)));

const testParser = async () => {
    if (!sampleSku.value || !props.credentialId || !activeParser.value) return;
    testing.value = true;
    testResult.value = null;
    try {
        const response = await fetch(route('marketplaces.ebay.sku-parser.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ config: configPayload(activeParser.value), sample_sku: sampleSku.value }),
        });
        testResult.value = await response.json();
    } catch (e) {
        testResult.value = { success: false, error: e.message };
    } finally {
        testing.value = false;
    }
};

const saveConfig = async () => {
    if (!props.credentialId) return;
    saving.value = true;
    saveMessage.value = '';
    saveError.value = '';
    try {
        const response = await fetch(route('marketplaces.ebay.sku-parser.save'), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                config: parsers.length > 0 ? configPayload(parsers[0]) : { enabled: false },
                parsers: allParsersPayload.value,
            }),
        });
        const data = await response.json();
        if (data.success) {
            saveMessage.value = `${parsers.length} parser(s) saved.`;
            emit('saved', allParsersPayload.value);
        } else {
            saveError.value = (data.errors || []).join(' ') || 'Failed to save.';
        }
    } catch (e) {
        saveError.value = e.message;
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">SKU Parser Configuration</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Define parser patterns per owner prefix. During import, each SKU is tested against parsers in order.
                </p>
            </div>
            <button
                @click="addParser"
                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium"
            >
                + Add Parser
            </button>
        </div>

        <!-- Apply Template -->
        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <label class="block text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">
                Quick start: apply a ready-made template
            </label>
            <div class="flex items-center gap-3">
                <select
                    v-model="selectedTemplateKey"
                    @change="applyTemplate(selectedTemplateKey)"
                    class="flex-1 rounded border-blue-300 dark:border-blue-700 dark:bg-gray-700 dark:text-gray-200 text-sm"
                >
                    <option value="">Select a template...</option>
                    <option v-for="tpl in templates" :key="tpl.key" :value="tpl.key">
                        {{ tpl.label }} &mdash; {{ tpl.description }}
                    </option>
                </select>
            </div>
            <p class="text-xs text-blue-700 dark:text-blue-300 mt-2">
                Each template adds a new parser to the list, pre-filled with regex and a sample SKU. You can tweak it after.
            </p>
        </div>

        <!-- Parser Tabs -->
        <div v-if="parsers.length > 0" class="mb-4">
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
                <button
                    v-for="(parser, idx) in parsers"
                    :key="idx"
                    @click="activeIndex = idx"
                    class="px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
                    :class="activeIndex === idx
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                >
                    {{ parser.prefix || '(no prefix)' }}
                    <span v-if="!parser.enabled" class="ml-1 text-xs text-gray-400">(off)</span>
                </button>
            </div>
        </div>

        <div v-if="parsers.length === 0" class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">
            No parsers configured. Click "Add Parser" to create one.
        </div>

        <!-- Active Parser Config -->
        <div v-if="activeParser" class="space-y-6">
            <div class="flex items-center gap-4">
                <!-- Enable Toggle -->
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        v-model="activeParser.enabled"
                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                    />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Enabled</span>
                </label>

                <!-- Prefix -->
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600 dark:text-gray-400">Match Prefix:</label>
                    <input
                        v-model="activeParser.prefix"
                        type="text"
                        placeholder="e.g. ARC"
                        class="w-28 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm font-mono"
                    />
                </div>

                <!-- Remove -->
                <button
                    @click="removeParser(activeIndex)"
                    class="ml-auto text-xs text-red-500 hover:text-red-700 dark:text-red-400"
                >
                    Remove
                </button>
            </div>

            <div v-if="activeParser.enabled" class="space-y-6">
                <!-- Mode Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Parsing Mode</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="activeParser.mode" value="template" class="text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Simple Template</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="activeParser.mode" value="regex" class="text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Advanced Regex</span>
                        </label>
                    </div>
                </div>

                <!-- Template Mode -->
                <div v-if="activeParser.mode === 'template'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Template Type</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" v-model="activeParser.template.type" value="delimiter" class="text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Delimiter-based</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" v-model="activeParser.template.type" value="fixed" class="text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Fixed-width</span>
                            </label>
                        </div>
                    </div>

                    <!-- Delimiter Config -->
                    <div v-if="activeParser.template.type === 'delimiter'" class="space-y-3">
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Delimiter Character</label>
                            <input
                                v-model="activeParser.template.delimiter"
                                type="text"
                                maxlength="5"
                                class="w-20 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                placeholder=" "
                            />
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2">Segment Mapping</label>
                            <div class="space-y-2">
                                <div v-for="(seg, idx) in activeParser.template.segments" :key="idx"
                                     class="flex items-center gap-3">
                                    <select v-model="seg.field"
                                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm w-40">
                                        <option v-for="opt in fieldOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                    <span class="text-xs text-gray-500">Position:</span>
                                    <input v-model.number="seg.position" type="number" min="0"
                                           class="w-16 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fixed-Width Config -->
                    <div v-if="activeParser.template.type === 'fixed'" class="space-y-2">
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2">Range Mapping</label>
                        <div v-for="(range, idx) in activeParser.template.ranges" :key="idx"
                             class="flex items-center gap-3">
                            <select v-model="range.field"
                                    class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm w-40">
                                <option v-for="opt in fieldOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <span class="text-xs text-gray-500">Start:</span>
                            <input v-model.number="range.start" type="number" min="0"
                                   class="w-16 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                            <span class="text-xs text-gray-500">Length:</span>
                            <input v-model.number="range.length" type="number" min="1"
                                   class="w-16 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                        </div>
                    </div>
                </div>

                <!-- Regex Mode -->
                <div v-if="activeParser.mode === 'regex'" class="space-y-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Preset Patterns</label>
                        <select
                            @change="applyPreset($event.target.value); $event.target.value = ''"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                        >
                            <option value="">Apply a preset...</option>
                            <option v-for="(preset, key) in presets" :key="key" :value="key">{{ preset.name }} - {{ preset.description }}</option>
                        </select>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Regex Pattern</label>
                    <textarea
                        v-model="activeParser.regex.pattern"
                        rows="2"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm font-mono"
                        placeholder="/^(?P<owner>\S+)\s+(?P<sku>\S+)\s+(?P<location>.+)$/"
                    ></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Use named groups: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">(?P&lt;owner&gt;...)</code>,
                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">(?P&lt;sku&gt;...)</code>,
                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">(?P&lt;location&gt;...)</code>
                    </p>
                </div>

                <!-- Test Panel -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Test Parser</label>
                    <div class="flex gap-2">
                        <input
                            v-model="sampleSku"
                            type="text"
                            class="flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                            placeholder="Enter a sample eBay SKU..."
                        />
                        <button
                            @click="testParser"
                            :disabled="!sampleSku || testing"
                            class="px-3 py-2 bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white rounded text-sm"
                        >
                            {{ testing ? 'Testing...' : 'Test' }}
                        </button>
                    </div>
                    <div v-if="testResult" class="mt-3 p-3 rounded text-sm"
                         :class="testResult.success ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300' : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300'">
                        <div v-if="testResult.success && testResult.result">
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <span class="font-medium">Owner:</span>
                                    <span class="ml-1">{{ testResult.result.owner ?? '(none)' }}</span>
                                </div>
                                <div>
                                    <span class="font-medium">SKU:</span>
                                    <span class="ml-1">{{ testResult.result.sku ?? '(none)' }}</span>
                                </div>
                                <div>
                                    <span class="font-medium">Location:</span>
                                    <span class="ml-1">{{ testResult.result.location ?? '(none)' }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else>{{ testResult.error }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="mt-6 flex items-center gap-3">
            <button
                @click="saveConfig"
                :disabled="saving"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium"
            >
                {{ saving ? 'Saving...' : 'Save All Parsers' }}
            </button>
            <span v-if="saveMessage" class="text-sm text-emerald-600 dark:text-emerald-400">{{ saveMessage }}</span>
            <span v-if="saveError" class="text-sm text-red-600 dark:text-red-400">{{ saveError }}</span>
        </div>
    </div>
</template>
