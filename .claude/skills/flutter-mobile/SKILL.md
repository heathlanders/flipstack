---
name: flutter-mobile
description: Flutter mobile app architecture, Riverpod state management, GoRouter navigation, API client patterns, model serialization, and page/service patterns
user-invocable: false
---

# Flutter Mobile App

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Entry** | `mobile/lib/main.dart` | App entry point, ProviderScope wrapper |
| **API Client** | `mobile/lib/core/network/api_client.dart` | Singleton Dio client, auth interceptor, token management |
| **Constants** | `mobile/lib/core/constants/app_constants.dart` | API URLs, endpoints, storage keys, enums |
| **Theme** | `mobile/lib/core/theme/app_theme.dart` | Material theme configuration |
| **Storage** | `mobile/lib/core/storage/secure_storage_service.dart` | FlutterSecureStorage for tokens |
| **Errors** | `mobile/lib/core/exceptions/api_exception.dart` | Custom exception with statusCode, isNetworkError helpers |
| **Router** | `mobile/lib/shared/routes/app_router.dart` | GoRouter with auth redirect, ShellRoute for bottom nav |
| **Layout** | `mobile/lib/shared/widgets/main_layout.dart` | BottomNavigationBar + Drawer shell |
| **Shared Providers** | `mobile/lib/core/providers/shared_providers.dart` | apiClientProvider, apiServiceProvider |
| **Models** | `mobile/lib/data/models/product_model.dart` | Product, ProductCategory, InventoryLocation |
| | `mobile/lib/data/models/user_model.dart` | User, UserProfile, ShippingAddress |
| | `mobile/lib/data/models/order_model.dart` | Order, OrderItem, OrderStatus enum |
| | `mobile/lib/data/models/auth_response_model.dart` | Auth token response |
| | `mobile/lib/data/models/product_identifier_model.dart` | Barcode/identifier types |
| **Services** | `mobile/lib/data/services/api_service.dart` | Generic HTTP with token refresh interceptor |
| | `mobile/lib/data/services/product_service.dart` | Product CRUD, search, import/export |
| | `mobile/lib/data/services/product_intake_service.dart` | Barcode scan, intake queue submission |
| | `mobile/lib/data/services/order_service.dart` | Order list, details, tracking |
| | `mobile/lib/data/services/user_service.dart` | Profile, password, addresses |
| **Auth** | `mobile/lib/features/authentication/presentation/providers/auth_provider.dart` | AsyncNotifier auth state |
| | `mobile/lib/features/authentication/domain/usecases/auth_usecases.dart` | Login, Register, Logout use cases |
| | `mobile/lib/features/authentication/data/datasources/auth_remote_data_source_impl.dart` | Auth API calls |
| **Inventory** | `mobile/lib/features/inventory/presentation/providers/inventory_provider.dart` | StateNotifier for product list |
| | `mobile/lib/features/inventory/presentation/providers/add_product_provider.dart` | 4-step intake wizard state |
| | `mobile/lib/features/inventory/presentation/pages/inventory_page.dart` | Product list with search |
| | `mobile/lib/features/inventory/presentation/pages/add_product_page.dart` | Barcode scan + intake wizard |
| | `mobile/lib/features/inventory/presentation/pages/product_detail_page.dart` | Product details |
| | `mobile/lib/features/inventory/presentation/pages/product_edit_page.dart` | Edit product form |
| **Dashboard** | `mobile/lib/features/dashboard/presentation/pages/owner_dashboard_page.dart` | KPI stats display |
| **Notifications** | `mobile/lib/features/notifications/presentation/pages/notifications_page.dart` | Notification list |
| **Picking** | `mobile/lib/data/models/pick_models.dart` | PickOrder, PickItem, PickProgress, PickStats, ScanVerifyResult |
| | `mobile/lib/data/services/pick_service.dart` | Picking API (list, start, pick/short, complete, scan-verify) |
| | `mobile/lib/features/picking/presentation/providers/pick_provider.dart` | PickListNotifier + ActivePickNotifier |
| | `mobile/lib/features/picking/presentation/pages/pick_list_page.dart` | Pick queue with location filter, stats, order cards |
| | `mobile/lib/features/picking/presentation/pages/active_pick_page.dart` | Active picking with barcode scanner, item cards |
| **Orders** | `mobile/lib/data/models/order_models.dart` | Order, OrderItem, OrderStatus models |
| | `mobile/lib/data/services/order_fulfillment_service.dart` | Orders API (list, detail, assign, hold/release) |
| | `mobile/lib/features/orders/presentation/providers/order_provider.dart` | Order list + detail state |
| | `mobile/lib/features/orders/presentation/pages/order_list_page.dart` | Filterable order list |
| | `mobile/lib/features/orders/presentation/pages/order_detail_page.dart` | Order detail with actions |
| **Packing** | `mobile/lib/data/models/pack_models.dart` | PackOrder, ShippingRate, PackagePreset models |
| | `mobile/lib/data/services/pack_service.dart` | Pack API (queue, start, rates, label, complete) |
| | `mobile/lib/features/packing/presentation/providers/pack_provider.dart` | Pack queue + active pack state |
| | `mobile/lib/features/packing/presentation/pages/pack_queue_page.dart` | Pack queue list |
| | `mobile/lib/features/packing/presentation/pages/active_pack_page.dart` | Active packing with rate shopping |
| **Shipping** | `mobile/lib/data/models/ship_models.dart` | ShipOrder, Shipment, ShipStats models |
| | `mobile/lib/data/services/ship_service.dart` | Ship API (queue, confirm, void label) |
| | `mobile/lib/features/shipping/presentation/providers/ship_provider.dart` | Ship queue + detail state |
| | `mobile/lib/features/shipping/presentation/pages/ship_queue_page.dart` | Ship queue list |
| | `mobile/lib/features/shipping/presentation/pages/ship_detail_page.dart` | Shipment detail with actions |
| **Returns** | `mobile/lib/data/models/return_models.dart` | ReturnRequest, ReturnItem, InspectionResult models |
| | `mobile/lib/data/services/return_service.dart` | Returns API (list, detail, approve, receive, inspect, restock) |
| | `mobile/lib/features/returns/presentation/providers/return_provider.dart` | Return list + detail state |
| | `mobile/lib/features/returns/presentation/pages/return_list_page.dart` | Filterable return list |
| | `mobile/lib/features/returns/presentation/pages/return_detail_page.dart` | Return detail with workflow actions |
| **Stock Ops** | `mobile/lib/data/models/stock_models.dart` | StockAdjustment, StockTransfer, MovementRecord models |
| | `mobile/lib/data/services/stock_service.dart` | Inventory API (adjust, transfer, movements, levels) |
| | `mobile/lib/features/stock_ops/presentation/providers/stock_provider.dart` | Stock operations state |
| | `mobile/lib/features/stock_ops/presentation/pages/stock_adjust_page.dart` | Stock adjustment form |
| | `mobile/lib/features/stock_ops/presentation/pages/stock_transfer_page.dart` | Stock transfer form |
| **Alerts** | `mobile/lib/data/models/alert_models.dart` | InventoryAlert, AlertSummary, AlertConfig models |
| | `mobile/lib/data/services/alert_service.dart` | Alerts API (list, summary, dismiss, config) |
| | `mobile/lib/features/alerts/presentation/providers/alert_provider.dart` | Alert list + summary state |
| | `mobile/lib/features/alerts/presentation/pages/alerts_page.dart` | Alert list with dismiss/restore |
| **Marketplace** | `mobile/lib/data/models/marketplace_models.dart` | MarketplaceStatus, ConnectionHealth models |
| | `mobile/lib/data/services/marketplace_service.dart` | Marketplace status API |
| | `mobile/lib/features/marketplace/presentation/providers/marketplace_provider.dart` | Marketplace status state |
| | `mobile/lib/features/marketplace/presentation/pages/marketplace_status_page.dart` | Marketplace connection health |
| **Part-Out** | `mobile/lib/features/part_out/presentation/providers/part_out_provider.dart` | Disassembly work order state |
| | `mobile/lib/features/part_out/presentation/pages/part_out_list_page.dart` | Work order list |
| | `mobile/lib/features/part_out/presentation/pages/active_part_out_page.dart` | Active disassembly (extract/skip/notes/scan-verify) via `Api\MobilePartOutController` |
| **Barcode Lookup** | `mobile/lib/features/inventory/presentation/pages/barcode_lookup_page.dart` | Scan-to-lookup product details |
| **Printing** | `mobile/lib/data/services/print_service.dart`, `mobile/lib/features/printing/presentation/pages/printing_page.dart` | Label printing (single + batch) + printer discovery via API |
| **Receiving** | `mobile/lib/data/services/receiving_service.dart`, `mobile/lib/features/receiving/...` | PO receiving: scan-verify, receive line, complete -- via `Api\MobileReceivingController` |
| **Labeling** | `mobile/lib/data/services/labeling_service.dart`, `mobile/lib/features/labeling/...` | Label/scan-bin/skip/complete via `Api\MobileLabelingController` |
| **Messaging** | `mobile/lib/data/services/message_service.dart`, `mobile/lib/features/messaging/...` | Buyer inbox + thread + reply/archive via `Api\MobileMessageController` |
| **Bins** | `mobile/lib/data/services/bin_service.dart`, `mobile/lib/features/bins/...` | Scan bin -> contents, place product into bin via `Api\MobileBinController` |
| **Bundles** | `mobile/lib/data/services/bundle_service.dart`, `mobile/lib/features/bundles/...` | Bundle availability view via `Api\MobileBundleController` (read-only) |
| **eBay** | `mobile/lib/features/ebay/presentation/pages/ebay_listings_page.dart`, `mobile/lib/data/services/ebay_listing_service.dart` | Listings list + sync (qty/price) + retry via `Api\Ebay\EbayApiController`. `ebay_status_page.dart` is a legacy stub |

## Dependencies (pubspec.yaml)

| Package | Version | Purpose |
|---------|---------|---------|
| `flutter_riverpod` | ^2.6.1 | State management |
| `dio` | ^5.7.0 | HTTP client |
| `go_router` | ^14.6.1 | Declarative routing |
| `mobile_scanner` | ^3.5.2 | Barcode scanning (camera) |
| `camera` | ^0.10.5+5 | Photo capture |
| `image_picker` | ^1.0.4 | Gallery access |
| `flutter_secure_storage` | ^9.2.2 | Encrypted token storage |
| `permission_handler` | ^11.1.0 | Camera/storage permissions |
| `json_annotation` | ^4.9.0 | JSON serialization |
| `freezed_annotation` | ^2.4.4 | Immutable data classes |
| `dartz` | ^0.10.1 | Functional error handling (Either) |

## Architecture

### Layer Structure (Clean Architecture)
```
features/{feature}/
  domain/
    entities/       -- Pure Dart classes (no framework deps)
    repositories/   -- Abstract repository interfaces
    usecases/       -- Single-responsibility business logic
  data/
    datasources/    -- API call implementations
    repositories/   -- Repository implementations
  presentation/
    pages/          -- ConsumerStatefulWidget screens
    providers/      -- Riverpod StateNotifier/AsyncNotifier
```

Only `authentication` uses full clean architecture. Other features use a simpler pattern:
```
features/{feature}/
  presentation/
    pages/        -- Screens
    providers/    -- State + API calls combined
```

### State Management Patterns

**Pattern 1: StateNotifier (most features)**
```dart
// State class - immutable with copyWith
class FeatureState {
  final List<Item> items;
  final bool isLoading;
  final String? error;
  const FeatureState({this.items = const [], this.isLoading = false, this.error});
  FeatureState copyWith({...}) => FeatureState(...);
}

// Notifier - mutates state via copyWith
class FeatureNotifier extends StateNotifier<FeatureState> {
  final ApiService _apiService;
  FeatureNotifier(this._apiService) : super(const FeatureState()) { _load(); }
  Future<void> _load() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiService.get(endpoint);
      state = state.copyWith(items: parsed, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }
}

// Provider declaration
final featureProvider = StateNotifierProvider<FeatureNotifier, FeatureState>((ref) {
  return FeatureNotifier(ref.watch(apiServiceProvider));
});
```

**Pattern 2: AsyncNotifier (auth only)**
```dart
class AuthNotifier extends AsyncNotifier<AuthUser?> {
  @override
  Future<AuthUser?> build() async => _checkStoredToken();
  Future<void> login(String email, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _loginUseCase(email, password));
  }
}
final authProvider = AsyncNotifierProvider<AuthNotifier, AuthUser?>(() => AuthNotifier());
```

**Pattern 3: FutureProvider.family (single item lookups)**
```dart
final productDetailProvider = FutureProvider.family<Product?, dynamic>((ref, id) async {
  final api = ref.watch(apiServiceProvider);
  await api.initialize();
  final response = await api.get('${ApiEndpoints.products}/$id');
  return response.data['success'] == true ? Product.fromJson(response.data['data']) : null;
});
```

### Page Pattern

All pages use `ConsumerStatefulWidget`:
```dart
class FeaturePage extends ConsumerStatefulWidget {
  const FeaturePage({super.key});
  @override
  ConsumerState<FeaturePage> createState() => _FeaturePageState();
}

class _FeaturePageState extends ConsumerState<FeaturePage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(featureProvider);           // Rebuilds on change
    final notifier = ref.read(featureProvider.notifier); // For calling methods
    // Loading/error/content pattern:
    if (state.isLoading && state.items.isEmpty) return Center(child: CircularProgressIndicator());
    if (state.error != null && state.items.isEmpty) return ErrorWidget(onRetry: notifier.refresh);
    return ListView.builder(...);
  }
}
```

### Model Serialization Pattern

```dart
@JsonSerializable()
class MyModel {
  @JsonKey(fromJson: _parseId, toJson: _idToJson)
  final dynamic id;                    // Supports UUID strings and ints

  @JsonKey(name: 'api_field_name')     // Snake_case API -> camelCase Dart
  final String dartFieldName;

  @JsonKey(fromJson: _parseDouble)     // Flexible type coercion
  final double price;

  @JsonKey(name: 'created_at', fromJson: _parseDateTime)
  final DateTime createdAt;

  factory MyModel.fromJson(Map<String, dynamic> json) => ...  // Manual with fallbacks
  Map<String, dynamic> toJson() => _$MyModelToJson(this);      // Generated
  MyModel copyWith({...}) => MyModel(...);
}
```

Key conventions:
- `dynamic` for IDs (Product uses UUID strings, others may use ints)
- Custom `_parseDouble`, `_parseInt`, `_parseDateTime` static helpers for type coercion
- Manual `fromJson` with field name fallbacks (e.g., `json['price'] ?? json['selling_price']`)
- `@JsonKey(name:)` for snake_case API field mapping

### Service Pattern

```dart
class FeatureService {
  final ApiClient _apiClient;
  FeatureService(this._apiClient);

  Future<FeatureResponse> doAction({required String param}) async {
    try {
      final response = await _apiClient.post('/endpoint', data: {'param': param});
      if (response.statusCode == 200 || response.statusCode == 201) {
        return FeatureResponse.fromJson(response.data);
      }
      throw ApiException(message: 'Unexpected status', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(message: 'Network error: ${e.message}', statusCode: e.response?.statusCode ?? 500);
    }
  }
}
```

For file uploads, use `FormData` with platform detection:
```dart
if (kIsWeb) {
  // Base64 encode for web
  formData.fields.add(MapEntry('photos_base64', jsonEncode(base64Photos)));
} else {
  // MultipartFile for mobile
  formData.files.add(MapEntry('photos[$i]', await MultipartFile.fromFile(path)));
}
```

### Navigation Pattern (GoRouter)

```dart
// Route enum
enum AppRoute {
  home('/home'), inventory('/inventory'), productDetail('/inventory/products/:id');
  const AppRoute(this.path);
  final String path;
}

// Router provider with auth redirect
final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);
  return GoRouter(
    redirect: (context, state) {
      final isLoggedIn = authState.when(data: (a) => a != null, loading: () => false, error: (_, __) => false);
      if (!isLoggedIn && !isAuthRoute) return AppRoute.login.path;
      if (isLoggedIn && isAuthRoute) return AppRoute.home.path;
      return null;
    },
    routes: [
      // Auth routes (no shell)
      GoRoute(path: '/login', builder: (_, __) => const LoginPage()),
      // Main routes (with bottom nav shell)
      ShellRoute(
        builder: (_, __, child) => MainLayout(child: child),
        routes: [
          GoRoute(path: '/home', builder: (_, __) => const DashboardPage()),
          GoRoute(path: '/inventory/products/:id',
            builder: (_, state) => ProductDetailPage(productId: state.pathParameters['id'])),
        ],
      ),
    ],
  );
});
```

### API Client Configuration

- **Base URL**: `mobile/lib/core/config/app_config.dart` → `AppConfig.baseUrl`, build-time switch via `--dart-define=API_BASE_URL=...`. Default LAN dev: `http://localhost:8090`.
- **Flavor**: `--dart-define=APP_FLAVOR=prod|develop` matches Android product flavor (`mobile/android/app/build.gradle.kts`). Develop flavor installs as `.develop` package id alongside prod.
- **Env helpers**: `AppConfig.isProduction` / `isStaging` / `isDevelopment` / `environmentLabel`.
- **Builds + CI**: see `docs/MOBILE_BUILDS.md` (CI workflow `.github/workflows/build-mobile.yml` builds both flavors; push to `main` deploys the prod APK, push to `develop` deploys the develop APK). Testers download from the `/download` landing page: prod `https://flipstack.app/download`, develop `https://develop.flipstack.app/download`.
- **Timeouts**: 30s connection, 30s send, 30s receive
- **Interceptors** (in order): Auth (Bearer token), Logging, Cookie Manager (mobile only)
- **Token refresh**: On 401, calls `/auth/refresh`, retries original request
- **Singleton**: `ApiClient()` factory returns same instance

### Bottom Navigation (4 tabs)

1. Dashboard -> `/home`
2. Inventory -> `/inventory`
3. Alerts -> `/notifications` (with unread badge)
4. Profile -> `/profile`

Drawer adds: Categories, Locations, eBay, Help & Support, Logout

## Adding a New Feature

1. Create `mobile/lib/features/{feature}/presentation/pages/{feature}_page.dart` (ConsumerStatefulWidget)
2. Create `mobile/lib/features/{feature}/presentation/providers/{feature}_provider.dart` (StateNotifier + State)
3. Add model in `mobile/lib/data/models/{feature}_model.dart` if new data types needed
4. Add service in `mobile/lib/data/services/{feature}_service.dart` if complex API calls
5. Add route in `mobile/lib/shared/routes/app_router.dart` (inside ShellRoute for main nav)
6. Add navigation entry in `mobile/lib/shared/widgets/main_layout.dart` (drawer or bottom nav)

## Current Limitations

- No offline support (no local database)
- iOS build pipeline not wired (Android-only APK distribution today; flavors live in `android/app/build.gradle.kts`)
- No real-time updates (no Pusher/WebSocket)
- No dark theme
- Web platform support exists but is secondary
- Push: **server-side dispatch IS built** (`app/Services/Notification/Fcm/FcmDispatcher`, `app/Notifications/Channels/FcmChannel`, `app/Models/Notification/DeviceToken`) and the app registers its device token (`POST /api/auth/fcm-token`). The remaining gap is the **Flutter client** -- no foreground/background message handling wired yet. Also: develop env must have its own `FCM_PROJECT_ID` to avoid leaking dev pushes to prod devices

## Audit Logging

Flutter app does NOT write directly to `user_activity_logs`. All mutations go through the Laravel mobile API (`app/Http/Controllers/Api/`), which emits audit rows with `metadata.source = 'mobile'` so Admin Audit Hub can filter mobile-originated activity.

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from the Laravel mobile API endpoints (see `mobile-api` skill) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Mobile-driven mutations contribute to `UserProductivityDaily` via the same action prefixes -- filtered by `metadata.source = 'mobile'` for mobile-only views.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
