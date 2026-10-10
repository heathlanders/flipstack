import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../routes/app_router.dart';
import '../providers/navigation_provider.dart';
import '../../core/providers/connectivity_provider.dart';
import '../../features/authentication/presentation/providers/auth_provider.dart';
import '../../features/notifications/presentation/pages/notifications_page.dart';

class MainLayout extends ConsumerWidget {
  final Widget child;

  const MainLayout({super.key, required this.child});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selectedIndex = ref.watch(navigationProvider);
    final notificationsState = ref.watch(notificationsProvider);
    final unreadCount = notificationsState.unreadCount;

    final isOnline = ref.watch(isOnlineProvider);

    return Scaffold(
      body: Column(
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            height: isOnline ? 0 : 32,
            color: Colors.red[700],
            child: isOnline
                ? const SizedBox.shrink()
                : const Center(
                    child: Text(
                      'Offline - scans will sync when connected',
                      style: TextStyle(color: Colors.white, fontSize: 12),
                    ),
                  ),
          ),
          Expanded(child: child),
        ],
      ),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        currentIndex: selectedIndex,
        onTap: (index) {
          ref.read(navigationProvider.notifier).setIndex(index);
          _navigateToPage(context, index);
        },
        items: [
          const BottomNavigationBarItem(
            icon: Icon(Icons.dashboard_outlined),
            activeIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.inventory_2_outlined),
            activeIcon: Icon(Icons.inventory_2),
            label: 'Inventory',
          ),
          BottomNavigationBarItem(
            icon: Stack(
              children: [
                const Icon(Icons.notifications_outlined),
                if (unreadCount > 0)
                  Positioned(
                    right: 0,
                    top: 0,
                    child: Container(
                      padding: const EdgeInsets.all(2),
                      decoration: BoxDecoration(
                        color: Colors.red,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      constraints: const BoxConstraints(
                        minWidth: 14,
                        minHeight: 14,
                      ),
                      child: Text(
                        unreadCount > 9 ? '9+' : unreadCount.toString(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 8,
                          fontWeight: FontWeight.bold,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
              ],
            ),
            activeIcon: Stack(
              children: [
                const Icon(Icons.notifications),
                if (unreadCount > 0)
                  Positioned(
                    right: 0,
                    top: 0,
                    child: Container(
                      padding: const EdgeInsets.all(2),
                      decoration: BoxDecoration(
                        color: Colors.red,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      constraints: const BoxConstraints(
                        minWidth: 14,
                        minHeight: 14,
                      ),
                      child: Text(
                        unreadCount > 9 ? '9+' : unreadCount.toString(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 8,
                          fontWeight: FontWeight.bold,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
              ],
            ),
            label: 'Alerts',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.person_outlined),
            activeIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
      drawer: _buildDrawer(context, ref),
    );
  }

  void _navigateToPage(BuildContext context, int index) {
    switch (index) {
      case 0:
        context.go(AppRoute.home.path);
        break;
      case 1:
        context.go(AppRoute.inventory.path);
        break;
      case 2:
        context.go(AppRoute.notifications.path);
        break;
      case 3:
        context.go(AppRoute.profile.path);
        break;
    }
  }

  Widget _buildDrawer(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);

    return Drawer(
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          DrawerHeader(
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primary,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const CircleAvatar(
                  radius: 30,
                  child: Icon(Icons.person, size: 35),
                ),
                const SizedBox(height: 12),
                authState.when(
                  data: (authUser) {
                    if (authUser != null) {
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            authUser.user.name,
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            authUser.user.email,
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: Colors.white.withOpacity(0.8),
                            ),
                          ),
                        ],
                      );
                    } else {
                      return const SizedBox.shrink();
                    }
                  },
                  loading: () => const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                      strokeWidth: 2,
                    ),
                  ),
                  error: (error, stackTrace) => const SizedBox.shrink(),
                ),
              ],
            ),
          ),

          // Main Navigation
          ListTile(
            leading: const Icon(Icons.dashboard_outlined),
            title: const Text('Dashboard'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.home.path);
            },
          ),

          const Divider(),

          // Inventory
          _buildSectionHeader(context, 'Inventory'),
          ListTile(
            leading: const Icon(Icons.inventory_2_outlined),
            title: const Text('Products'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.inventory.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.qr_code_scanner),
            title: const Text('Scan Lookup'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.barcodeLookup.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.category_outlined),
            title: const Text('Categories'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.categories.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.location_on_outlined),
            title: const Text('Locations'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.locations.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.warning_amber_outlined),
            title: const Text('Inventory Alerts'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.inventoryAlerts.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.bar_chart_outlined),
            title: const Text('Analytics'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.analyticsRoute.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.swap_vert),
            title: const Text('Movement History'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.movementHistory.path);
            },
          ),

          const Divider(),

          // Fulfillment
          _buildSectionHeader(context, 'Fulfillment'),
          ListTile(
            leading: const Icon(Icons.receipt_long_outlined),
            title: const Text('Orders'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.orders.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.assignment_outlined),
            title: const Text('Picking'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.pickList.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.inventory_outlined),
            title: const Text('Packing'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.packList.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.local_shipping_outlined),
            title: const Text('Shipping'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.shipList.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.assignment_return_outlined),
            title: const Text('Returns'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.returnList.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.swap_horiz_outlined),
            title: const Text('Stock Adjust'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.stockAdjust.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.move_down_outlined),
            title: const Text('Stock Transfer'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.stockTransfer.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.print_outlined),
            title: const Text('Label Printing'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.printing.path);
            },
          ),

          const Divider(),

          // Integrations
          _buildSectionHeader(context, 'Integrations'),
          ListTile(
            leading: const Icon(Icons.storefront_outlined),
            title: const Text('Marketplace Status'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.marketplace.path);
            },
          ),

          const Divider(),

          // Account & Settings
          _buildSectionHeader(context, 'Account'),
          ListTile(
            leading: const Icon(Icons.person_outlined),
            title: const Text('Edit Profile'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.editProfile.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.lock_outlined),
            title: const Text('Change Password'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.changePassword.path);
            },
          ),
          ListTile(
            leading: const Icon(Icons.notifications_outlined),
            title: const Text('Notifications'),
            onTap: () {
              Navigator.pop(context);
              context.go(AppRoute.notifications.path);
            },
          ),

          const Divider(),

          // Support
          ListTile(
            leading: const Icon(Icons.help_outline),
            title: const Text('Help & Support'),
            onTap: () {
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Help & Support coming soon!'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
          ),

          // Logout
          ListTile(
            leading: Icon(
              Icons.logout,
              color: Theme.of(context).colorScheme.error,
            ),
            title: Text(
              'Logout',
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
              ),
            ),
            onTap: () {
              Navigator.pop(context);
              _showLogoutDialog(context, ref);
            },
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(BuildContext context, String title) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Text(
        title.toUpperCase(),
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: Colors.grey[600],
          fontWeight: FontWeight.bold,
          letterSpacing: 1.2,
        ),
      ),
    );
  }

  void _showLogoutDialog(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Logout'),
          content: const Text('Are you sure you want to logout?'),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(context);
                await ref.read(authProvider.notifier).logout();
                // Navigation will be handled by the router based on auth state
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Theme.of(context).colorScheme.error,
              ),
              child: const Text('Logout'),
            ),
          ],
        );
      },
    );
  }
}
