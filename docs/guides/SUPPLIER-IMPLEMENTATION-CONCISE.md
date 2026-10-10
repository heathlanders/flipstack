# Supplier Frontend Implementation Guide
*Laravel 11 + Vue.js 3 + Inertia.js*

## 🚀 Quick Start

### 1. Infrastructure Analysis
```bash
# Copy analysis scripts to Laravel root
chmod +x setup-analysis.sh
./setup-analysis.sh
cat infrastructure-summary-report.txt
```

### 2. Database Setup
```bash
# Create migrations
docker compose exec app php artisan make:migration create_suppliers_table
docker compose exec app php artisan make:migration create_purchase_orders_table
```

**Suppliers Migration:**
```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('phone')->nullable();
    $table->text('address')->nullable();
    $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
    $table->decimal('credit_limit', 15, 2)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**Purchase Orders Migration:**
```php
Schema::create('purchase_orders', function (Blueprint $table) {
    $table->id();
    $table->string('po_number')->unique();
    $table->foreignId('supplier_id')->constrained();
    $table->enum('status', ['draft', 'sent', 'confirmed', 'received'])->default('draft');
    $table->decimal('total_amount', 15, 2);
    $table->date('order_date');
    $table->timestamps();
});
```

### 3. Laravel Backend

**Supplier Model:**
```php
class Supplier extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = ['name', 'email', 'phone', 'address', 'status', 'credit_limit'];
    
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
```

**Supplier Controller:**
```php
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->when($request->search, fn($q, $search) => 
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%"))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->withCount('purchaseOrders')
            ->paginate(15);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = Supplier::create($request->validated());
        return redirect()->route('suppliers.show', $supplier);
    }
}
```

**Form Request:**
```php
class StoreSupplierRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:suppliers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive,pending',
            'credit_limit' => 'nullable|numeric|min:0',
        ];
    }
}
```

**Routes:**
```php
Route::resource('suppliers', SupplierController::class);
Route::resource('purchase-orders', PurchaseOrderController::class);
```

### 4. Frontend Setup

**Install Packages:**
```bash
docker compose exec app npm install @headlessui/vue @heroicons/vue @vueuse/core
```

**Directory Structure:**
```
resources/js/
├── Pages/
│   └── Suppliers/
│       ├── Index.vue
│       ├── Create.vue
│       └── Show.vue
└── Components/
    └── Supplier/
        └── SupplierCard.vue
```

**Supplier Index Page:**
```vue
<template>
  <AppLayout title="Suppliers">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Search -->
        <div class="mb-6 flex gap-4">
          <input
            v-model="filters.search"
            @input="debounceSearch"
            placeholder="Search suppliers..."
            class="flex-1 rounded-md border-gray-300 shadow-sm"
          />
          <select v-model="filters.status" @change="performSearch" class="rounded-md border-gray-300">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="pending">Pending</option>
          </select>
        </div>

        <!-- Suppliers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <SupplierCard
            v-for="supplier in suppliers.data"
            :key="supplier.id"
            :supplier="supplier"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import SupplierCard from '@/Components/Supplier/SupplierCard.vue'

const props = defineProps(['suppliers', 'filters'])
const filters = ref({ ...props.filters })

const performSearch = () => {
  router.get(route('suppliers.index'), filters.value, {
    preserveState: true,
    replace: true,
  })
}

const debounceSearch = useDebounceFn(performSearch, 300)
</script>
```

**Supplier Card Component:**
```vue
<template>
  <div class="bg-white shadow rounded-lg p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">{{ supplier.name }}</h3>
      <StatusBadge :status="supplier.status" />
    </div>
    
    <div class="space-y-2 mb-4 text-sm text-gray-600">
      <div>{{ supplier.email }}</div>
      <div v-if="supplier.phone">{{ supplier.phone }}</div>
    </div>

    <div class="text-center mb-4">
      <div class="text-2xl font-bold">{{ supplier.purchase_orders_count || 0 }}</div>
      <div class="text-sm text-gray-500">Orders</div>
    </div>

    <div class="flex space-x-2">
      <Link
        :href="route('suppliers.show', supplier.id)"
        class="flex-1 bg-blue-500 text-white text-center py-2 rounded hover:bg-blue-600"
      >
        View
      </Link>
      <Link
        :href="route('suppliers.edit', supplier.id)"
        class="flex-1 bg-gray-100 text-gray-700 text-center py-2 rounded hover:bg-gray-200"
      >
        Edit
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import StatusBadge from '@/Components/StatusBadge.vue'

defineProps(['supplier'])
</script>
```

**Supplier Create Form:**
```vue
<template>
  <AppLayout title="Add Supplier">
    <div class="py-12">
      <div class="max-w-2xl mx-auto">
        <form @submit.prevent="form.post(route('suppliers.store'))" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700">Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            />
            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
              {{ form.errors.name }}
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Email *</label>
            <input
              v-model="form.email"
              type="email"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            />
            <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">
              {{ form.errors.email }}
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Phone</label>
            <input
              v-model="form.phone"
              type="tel"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Status *</label>
            <select
              v-model="form.status"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
            >
              <option value="">Select Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </select>
          </div>

          <div class="flex justify-end space-x-4">
            <Link href="/suppliers" class="px-4 py-2 text-gray-600 hover:text-gray-800">
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              {{ form.processing ? 'Creating...' : 'Create Supplier' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
  name: '',
  email: '',
  phone: '',
  status: 'pending',
})
</script>
```

### 5. Testing

**Backend Test:**
```php
public function test_can_create_supplier()
{
    $response = $this->actingAs($this->user)
        ->post('/suppliers', [
            'name' => 'Test Supplier',
            'email' => 'test@supplier.com',
            'status' => 'active',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', ['name' => 'Test Supplier']);
}
```

**Frontend Test:**
```javascript
import { mount } from '@vue/test-utils'
import SupplierCard from '@/Components/Supplier/SupplierCard.vue'

test('renders supplier information', () => {
  const supplier = { id: 1, name: 'Test Supplier', email: 'test@test.com', status: 'active' }
  const wrapper = mount(SupplierCard, { props: { supplier } })
  
  expect(wrapper.find('h3').text()).toBe('Test Supplier')
  expect(wrapper.text()).toContain('test@test.com')
})
```

### 6. Run & Deploy

```bash
# Run migrations
docker compose exec app php artisan migrate

# Build frontend
docker compose exec app npm run build

# Run tests
docker compose exec app php artisan test
docker compose exec app npm test
```

## 🎯 Next Steps

1. Run infrastructure analysis
2. Implement backend models/controllers
3. Create Vue.js frontend pages
4. Add search/filtering
5. Implement purchase orders
6. Add analytics dashboard

Complete implementation takes ~1-2 weeks following this guide systematically.
