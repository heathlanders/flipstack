import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

// Authentication pages
import '../../features/authentication/presentation/pages/login_page.dart';
import '../../features/authentication/presentation/pages/register_page.dart';
import '../../features/authentication/presentation/pages/splash_page.dart';
import '../../features/authentication/presentation/pages/forgot_password_page.dart';
import '../../features/authentication/presentation/pages/two_factor_challenge_page.dart';

// Inventory pages
import '../../features/inventory/presentation/pages/inventory_page.dart';
import '../../features/inventory/presentation/pages/add_product_page.dart';
import '../../features/inventory/presentation/pages/product_detail_page.dart';
import '../../features/inventory/presentation/pages/product_edit_page.dart';
import '../../features/inventory/presentation/pages/categories_page.dart';
import '../../features/inventory/presentation/pages/locations_page.dart';
import '../../features/inventory/presentation/pages/barcode_lookup_page.dart';

// Profile pages
import '../../features/profile/presentation/pages/profile_page.dart';
import '../../features/profile/presentation/pages/edit_profile_page.dart';
import '../../features/profile/presentation/pages/change_password_page.dart';
import '../../features/profile/presentation/pages/sessions_page.dart';

// Dashboard pages
import '../../features/dashboard/presentation/pages/owner_dashboard_page.dart';

// Notifications pages
import '../../features/notifications/presentation/pages/notifications_page.dart';
import '../../features/notifications/presentation/pages/notification_preferences_page.dart';

// Create product page
import '../../features/inventory/presentation/pages/create_product_page.dart';

// Picking pages
import '../../features/picking/presentation/pages/pick_list_page.dart';
import '../../features/picking/presentation/pages/active_pick_page.dart';

// Orders pages
import '../../features/orders/presentation/pages/order_list_page.dart';
import '../../features/orders/presentation/pages/order_detail_page.dart';

// Packing pages
import '../../features/packing/presentation/pages/pack_list_page.dart';
import '../../features/packing/presentation/pages/active_pack_page.dart';

// Part-Out pages
import '../../features/part_out/presentation/pages/part_out_list_page.dart';
import '../../features/part_out/presentation/pages/active_part_out_page.dart';

// Receiving pages
import '../../features/receiving/presentation/pages/receiving_list_page.dart';
import '../../features/receiving/presentation/pages/active_receiving_page.dart';

// Labeling pages
import '../../features/labeling/presentation/pages/labeling_list_page.dart';
import '../../features/labeling/presentation/pages/active_labeling_page.dart';

// Messaging pages
import '../../features/messaging/presentation/pages/inbox_page.dart';
import '../../features/messaging/presentation/pages/thread_page.dart';

// Bins pages
import '../../features/bins/presentation/pages/bin_lookup_page.dart';

// Bundles pages
import '../../features/bundles/presentation/pages/bundle_list_page.dart';

// eBay listings page
import '../../features/ebay/presentation/pages/ebay_listings_page.dart';

// Shipping pages
import '../../features/shipping/presentation/pages/ship_list_page.dart';

// Printing pages
import '../../features/printing/presentation/pages/printing_page.dart';
import '../../features/shipping/presentation/pages/shipment_detail_page.dart';

// Returns pages
import '../../features/returns/presentation/pages/return_list_page.dart';
import '../../features/returns/presentation/pages/return_detail_page.dart';

// Stock operations pages
import '../../features/stock_ops/presentation/pages/stock_adjust_page.dart';
import '../../features/stock_ops/presentation/pages/stock_transfer_page.dart';

// Alerts pages
import '../../features/alerts/presentation/pages/alerts_page.dart';

// Marketplace pages
import '../../features/marketplace/presentation/pages/marketplace_status_page.dart';

// Analytics pages
import '../../features/analytics/presentation/pages/analytics_page.dart';

// Movement history page
import '../../features/inventory/presentation/pages/movement_history_page.dart';

// Shared widgets
import '../../shared/widgets/main_layout.dart';
import '../../features/authentication/presentation/providers/auth_provider.dart';

enum AppRoute {
  // Auth routes
  splash('/'),
  login('/login'),
  register('/register'),
  forgotPassword('/forgot-password'),
  twoFactorChallenge('/two-factor'),

  // Main routes
  home('/home'),
  dashboard('/dashboard'),

  // Inventory routes
  inventory('/inventory'),
  addProduct('/inventory/add-product'),
  productDetail('/inventory/products/:id'),
  productEdit('/inventory/products/:id/edit'),
  categories('/inventory/categories'),
  locations('/inventory/locations'),
  barcodeLookup('/inventory/scan-lookup'),

  // Fulfillment routes
  orders('/orders'),
  orderDetail('/orders/:id'),
  pickList('/picking'),
  activePick('/picking/:id'),
  packList('/packing'),
  activePack('/packing/:id'),
  shipList('/shipping'),
  shipmentDetail('/shipping/:id'),
  returnList('/returns'),
  returnDetail('/returns/:id'),

  // Stock operations
  stockAdjust('/stock/adjust'),
  stockTransfer('/stock/transfer'),

  // Alerts
  inventoryAlerts('/alerts/inventory'),

  // Analytics
  analyticsRoute('/analytics'),

  // Movement History
  movementHistory('/inventory/movements'),

  // Marketplace
  marketplace('/marketplace'),

  // Profile routes
  profile('/profile'),
  editProfile('/profile/edit'),
  changePassword('/profile/change-password'),
  sessions('/profile/sessions'),

  // Notifications
  notifications('/notifications'),
  notificationPreferences('/notifications/preferences'),

  // Create product
  createProduct('/inventory/create-product'),

  // Printing
  printing('/printing'),

  // Part-Out routes
  partOutList('/part-out'),
  activePartOut('/part-out/:id'),

  // Receiving routes
  receivingList('/receiving'),
  activeReceiving('/receiving/:id'),

  // Labeling routes
  labelingList('/labeling'),
  activeLabeling('/labeling/:id'),

  // Messaging routes
  messageInbox('/messages'),
  messageThread('/messages/:id'),

  // Bins route
  binLookup('/bins'),

  // Bundles route
  bundleList('/bundles'),

  // eBay routes (legacy, redirects to marketplace)
  ebayStatus('/ebay'),
  ebayListings('/ebay/listings');

  const AppRoute(this.path);
  final String path;
}

final routerProvider = Provider<GoRouter>((ref) {
  // Bridge authProvider into a Listenable so the router is built ONCE and only
  // re-runs its redirect when auth changes - instead of rebuilding the whole
  // GoRouter (which reset navigation to splash on every auth state change).
  final authListenable = ValueNotifier<AsyncValue<AuthUser?>>(const AsyncLoading());
  ref.listen<AsyncValue<AuthUser?>>(
    authProvider,
    (_, next) => authListenable.value = next,
    fireImmediately: true,
  );
  ref.onDispose(authListenable.dispose);

  return GoRouter(
    initialLocation: AppRoute.splash.path,
    refreshListenable: authListenable,
    redirect: (context, state) {
      final authState = authListenable.value;

      // Auth still resolving on cold start: hold on the splash screen.
      if (authState.isLoading) {
        return null;
      }

      final isLoggedIn = authState.maybeWhen(
        data: (auth) => auth != null,
        orElse: () => false,
      );

      final isOnLoginPage = state.matchedLocation == AppRoute.login.path;
      final isOnRegisterPage = state.matchedLocation == AppRoute.register.path;
      final isOnSplashPage = state.matchedLocation == AppRoute.splash.path;
      final isOnForgotPasswordPage = state.matchedLocation == AppRoute.forgotPassword.path;
      final isOnTwoFactorPage = state.matchedLocation == AppRoute.twoFactorChallenge.path;

      final isAuthRoute = isOnLoginPage || isOnRegisterPage || isOnSplashPage ||
                          isOnForgotPasswordPage || isOnTwoFactorPage;

      if (!isLoggedIn && !isAuthRoute) {
        return AppRoute.login.path;
      }

      // Resolved as logged-out while sitting on the splash gate -> login.
      if (!isLoggedIn && isOnSplashPage) {
        return AppRoute.login.path;
      }

      if (isLoggedIn && (isOnLoginPage || isOnRegisterPage || isOnSplashPage)) {
        return AppRoute.home.path;
      }

      return null;
    },
    routes: [
      // Auth routes (no shell)
      GoRoute(
        path: AppRoute.splash.path,
        builder: (context, state) => const SplashPage(),
      ),
      GoRoute(
        path: AppRoute.login.path,
        builder: (context, state) => const LoginPage(),
      ),
      GoRoute(
        path: AppRoute.register.path,
        builder: (context, state) => const RegisterPage(),
      ),
      GoRoute(
        path: AppRoute.forgotPassword.path,
        builder: (context, state) => const ForgotPasswordPage(),
      ),
      GoRoute(
        path: AppRoute.twoFactorChallenge.path,
        builder: (context, state) {
          final email = state.uri.queryParameters['email'] ?? '';
          return TwoFactorChallengePage(email: email);
        },
      ),

      // Main app routes with shell (bottom nav)
      ShellRoute(
        builder: (context, state, child) => MainLayout(child: child),
        routes: [
          // Home/Dashboard
          GoRoute(
            path: AppRoute.home.path,
            builder: (context, state) => const OwnerDashboardPage(),
          ),
          GoRoute(
            path: AppRoute.dashboard.path,
            builder: (context, state) => const OwnerDashboardPage(),
          ),

          // Inventory
          GoRoute(
            path: AppRoute.inventory.path,
            builder: (context, state) => const InventoryPage(),
          ),
          GoRoute(
            path: AppRoute.addProduct.path,
            name: 'add-product',
            builder: (context, state) => const AddProductPage(),
          ),
          GoRoute(
            path: AppRoute.productDetail.path,
            name: 'product-detail',
            builder: (context, state) {
              final productId = state.pathParameters['id'];
              return ProductDetailPage(productId: productId);
            },
          ),
          GoRoute(
            path: AppRoute.productEdit.path,
            name: 'product-edit',
            builder: (context, state) {
              final productId = state.pathParameters['id'];
              return ProductEditPage(productId: productId);
            },
          ),
          GoRoute(
            path: AppRoute.categories.path,
            builder: (context, state) => const CategoriesPage(),
          ),
          GoRoute(
            path: AppRoute.locations.path,
            builder: (context, state) => const LocationsPage(),
          ),
          GoRoute(
            path: AppRoute.barcodeLookup.path,
            builder: (context, state) => const BarcodeLookupPage(),
          ),

          // Fulfillment - Orders
          GoRoute(
            path: AppRoute.orders.path,
            builder: (context, state) => const OrderListPage(),
          ),
          GoRoute(
            path: AppRoute.orderDetail.path,
            name: 'order-detail',
            builder: (context, state) {
              final orderId = state.pathParameters['id'] ?? '';
              return OrderDetailPage(orderId: orderId);
            },
          ),

          // Fulfillment - Picking
          GoRoute(
            path: AppRoute.pickList.path,
            builder: (context, state) => const PickListPage(),
          ),
          GoRoute(
            path: AppRoute.activePick.path,
            name: 'active-pick',
            builder: (context, state) {
              final orderId = state.pathParameters['id'] ?? '';
              return ActivePickPage(orderId: orderId);
            },
          ),

          // Fulfillment - Packing
          GoRoute(
            path: AppRoute.packList.path,
            builder: (context, state) => const PackListPage(),
          ),
          GoRoute(
            path: AppRoute.activePack.path,
            name: 'active-pack',
            builder: (context, state) {
              final orderId = state.pathParameters['id'] ?? '';
              return ActivePackPage(orderId: orderId);
            },
          ),

          // Part-Out
          GoRoute(
            path: AppRoute.partOutList.path,
            builder: (context, state) => const PartOutListPage(),
          ),
          GoRoute(
            path: AppRoute.activePartOut.path,
            name: 'active-part-out',
            builder: (context, state) {
              final workOrderId = state.pathParameters['id'] ?? '';
              return ActivePartOutPage(workOrderId: workOrderId);
            },
          ),

          // Receiving
          GoRoute(
            path: AppRoute.receivingList.path,
            builder: (context, state) => const ReceivingListPage(),
          ),
          GoRoute(
            path: AppRoute.activeReceiving.path,
            name: 'active-receiving',
            builder: (context, state) {
              final purchaseOrderId = state.pathParameters['id'] ?? '';
              return ActiveReceivingPage(purchaseOrderId: purchaseOrderId);
            },
          ),

          // Labeling
          GoRoute(
            path: AppRoute.labelingList.path,
            builder: (context, state) => const LabelingListPage(),
          ),
          GoRoute(
            path: AppRoute.activeLabeling.path,
            name: 'active-labeling',
            builder: (context, state) {
              final workOrderId = state.pathParameters['id'] ?? '';
              return ActiveLabelingPage(workOrderId: workOrderId);
            },
          ),

          // Messaging
          GoRoute(
            path: AppRoute.messageInbox.path,
            builder: (context, state) => const InboxPage(),
          ),
          GoRoute(
            path: AppRoute.messageThread.path,
            name: 'message-thread',
            builder: (context, state) {
              final messageId = state.pathParameters['id'] ?? '';
              return ThreadPage(messageId: messageId);
            },
          ),

          // Bins
          GoRoute(
            path: AppRoute.binLookup.path,
            builder: (context, state) => const BinLookupPage(),
          ),

          // Fulfillment - Shipping
          GoRoute(
            path: AppRoute.shipList.path,
            builder: (context, state) => const ShipListPage(),
          ),
          GoRoute(
            path: AppRoute.shipmentDetail.path,
            name: 'shipment-detail',
            builder: (context, state) {
              final shipmentId = state.pathParameters['id'] ?? '';
              return ShipmentDetailPage(shipmentId: shipmentId);
            },
          ),

          // Fulfillment - Returns
          GoRoute(
            path: AppRoute.returnList.path,
            builder: (context, state) => const ReturnListPage(),
          ),
          GoRoute(
            path: AppRoute.returnDetail.path,
            name: 'return-detail',
            builder: (context, state) {
              final returnId = state.pathParameters['id'] ?? '';
              return ReturnDetailPage(returnId: returnId);
            },
          ),

          // Stock Operations
          GoRoute(
            path: AppRoute.stockAdjust.path,
            builder: (context, state) => const StockAdjustPage(),
          ),
          GoRoute(
            path: AppRoute.stockTransfer.path,
            builder: (context, state) => const StockTransferPage(),
          ),

          // Printing
          GoRoute(
            path: AppRoute.printing.path,
            builder: (context, state) => const PrintingPage(),
          ),

          // Alerts
          GoRoute(
            path: AppRoute.inventoryAlerts.path,
            builder: (context, state) => const AlertsPage(),
          ),

          // Analytics
          GoRoute(
            path: AppRoute.analyticsRoute.path,
            builder: (context, state) => const AnalyticsPage(),
          ),

          // Movement History
          GoRoute(
            path: AppRoute.movementHistory.path,
            builder: (context, state) => const MovementHistoryPage(),
          ),

          // Marketplace
          GoRoute(
            path: AppRoute.marketplace.path,
            builder: (context, state) => const MarketplaceStatusPage(),
          ),

          // Profile
          GoRoute(
            path: AppRoute.profile.path,
            builder: (context, state) => const ProfilePage(),
          ),
          GoRoute(
            path: AppRoute.editProfile.path,
            builder: (context, state) => const EditProfilePage(),
          ),
          GoRoute(
            path: AppRoute.changePassword.path,
            builder: (context, state) => const ChangePasswordPage(),
          ),
          GoRoute(
            path: AppRoute.sessions.path,
            builder: (context, state) => const SessionsPage(),
          ),

          // Notifications
          GoRoute(
            path: AppRoute.notifications.path,
            builder: (context, state) => const NotificationsPage(),
          ),
          GoRoute(
            path: AppRoute.notificationPreferences.path,
            builder: (context, state) => const NotificationPreferencesPage(),
          ),

          // Create Product (manual form)
          GoRoute(
            path: AppRoute.createProduct.path,
            builder: (context, state) => const CreateProductPage(),
          ),

          // Bundles
          GoRoute(
            path: AppRoute.bundleList.path,
            builder: (context, state) => const BundleListPage(),
          ),

          // eBay (status redirects to marketplace; listings is a real management screen)
          GoRoute(
            path: AppRoute.ebayStatus.path,
            builder: (context, state) => const MarketplaceStatusPage(),
          ),
          GoRoute(
            path: AppRoute.ebayListings.path,
            builder: (context, state) => const EbayListingsPage(),
          ),
        ],
      ),
    ],
    errorBuilder: (context, state) => Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(
              'Page not found',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            Text(
              'The page you are looking for does not exist.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => context.go(AppRoute.home.path),
              child: const Text('Go Home'),
            ),
          ],
        ),
      ),
    ),
  );
});
