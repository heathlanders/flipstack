import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

// Preference keys
class _PrefKeys {
  static const String lowStockAlerts = 'pref_low_stock_alerts';
  static const String orderUpdates = 'pref_order_updates';
  static const String shippingUpdates = 'pref_shipping_updates';
  static const String returnRequests = 'pref_return_requests';
  static const String systemNotifications = 'pref_system_notifications';
}

// Notification preferences state
class NotificationPreferencesState {
  final bool lowStockAlerts;
  final bool orderUpdates;
  final bool shippingUpdates;
  final bool returnRequests;
  final bool systemNotifications;
  final bool isLoading;

  const NotificationPreferencesState({
    this.lowStockAlerts = true,
    this.orderUpdates = true,
    this.shippingUpdates = true,
    this.returnRequests = true,
    this.systemNotifications = true,
    this.isLoading = true,
  });

  NotificationPreferencesState copyWith({
    bool? lowStockAlerts,
    bool? orderUpdates,
    bool? shippingUpdates,
    bool? returnRequests,
    bool? systemNotifications,
    bool? isLoading,
  }) {
    return NotificationPreferencesState(
      lowStockAlerts: lowStockAlerts ?? this.lowStockAlerts,
      orderUpdates: orderUpdates ?? this.orderUpdates,
      shippingUpdates: shippingUpdates ?? this.shippingUpdates,
      returnRequests: returnRequests ?? this.returnRequests,
      systemNotifications: systemNotifications ?? this.systemNotifications,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

// Notification preferences notifier
class NotificationPreferencesNotifier extends StateNotifier<NotificationPreferencesState> {
  NotificationPreferencesNotifier() : super(const NotificationPreferencesState()) {
    _loadPreferences();
  }

  Future<void> _loadPreferences() async {
    final prefs = await SharedPreferences.getInstance();

    state = NotificationPreferencesState(
      lowStockAlerts: prefs.getBool(_PrefKeys.lowStockAlerts) ?? true,
      orderUpdates: prefs.getBool(_PrefKeys.orderUpdates) ?? true,
      shippingUpdates: prefs.getBool(_PrefKeys.shippingUpdates) ?? true,
      returnRequests: prefs.getBool(_PrefKeys.returnRequests) ?? true,
      systemNotifications: prefs.getBool(_PrefKeys.systemNotifications) ?? true,
      isLoading: false,
    );
  }

  Future<void> setLowStockAlerts(bool value) async {
    state = state.copyWith(lowStockAlerts: value);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_PrefKeys.lowStockAlerts, value);
  }

  Future<void> setOrderUpdates(bool value) async {
    state = state.copyWith(orderUpdates: value);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_PrefKeys.orderUpdates, value);
  }

  Future<void> setShippingUpdates(bool value) async {
    state = state.copyWith(shippingUpdates: value);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_PrefKeys.shippingUpdates, value);
  }

  Future<void> setReturnRequests(bool value) async {
    state = state.copyWith(returnRequests: value);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_PrefKeys.returnRequests, value);
  }

  Future<void> setSystemNotifications(bool value) async {
    state = state.copyWith(systemNotifications: value);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_PrefKeys.systemNotifications, value);
  }
}

// Provider
final notificationPreferencesProvider =
    StateNotifierProvider<NotificationPreferencesNotifier, NotificationPreferencesState>((ref) {
  return NotificationPreferencesNotifier();
});

class NotificationPreferencesPage extends ConsumerWidget {
  const NotificationPreferencesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final prefsState = ref.watch(notificationPreferencesProvider);
    final prefsNotifier = ref.read(notificationPreferencesProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notification Preferences'),
      ),
      body: prefsState.isLoading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              children: [
                const Padding(
                  padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: Text(
                    'Choose which notifications you would like to receive.',
                    style: TextStyle(color: Colors.grey, fontSize: 14),
                  ),
                ),
                const Divider(),
                _buildPreferenceTile(
                  title: 'Low Stock Alerts',
                  subtitle: 'Get notified when products are running low on stock',
                  icon: Icons.inventory_2,
                  iconColor: Colors.orange,
                  value: prefsState.lowStockAlerts,
                  onChanged: (value) => prefsNotifier.setLowStockAlerts(value),
                ),
                const Divider(height: 1, indent: 72),
                _buildPreferenceTile(
                  title: 'Order Updates',
                  subtitle: 'Receive updates about new and changed orders',
                  icon: Icons.shopping_bag,
                  iconColor: Colors.blue,
                  value: prefsState.orderUpdates,
                  onChanged: (value) => prefsNotifier.setOrderUpdates(value),
                ),
                const Divider(height: 1, indent: 72),
                _buildPreferenceTile(
                  title: 'Shipping Updates',
                  subtitle: 'Track shipping status changes and delivery confirmations',
                  icon: Icons.local_shipping,
                  iconColor: Colors.green,
                  value: prefsState.shippingUpdates,
                  onChanged: (value) => prefsNotifier.setShippingUpdates(value),
                ),
                const Divider(height: 1, indent: 72),
                _buildPreferenceTile(
                  title: 'Return Requests',
                  subtitle: 'Get alerted when return requests are submitted',
                  icon: Icons.assignment_return,
                  iconColor: Colors.red,
                  value: prefsState.returnRequests,
                  onChanged: (value) => prefsNotifier.setReturnRequests(value),
                ),
                const Divider(height: 1, indent: 72),
                _buildPreferenceTile(
                  title: 'System Notifications',
                  subtitle: 'Important system updates and maintenance alerts',
                  icon: Icons.settings,
                  iconColor: Colors.grey,
                  value: prefsState.systemNotifications,
                  onChanged: (value) => prefsNotifier.setSystemNotifications(value),
                ),
                const Divider(),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(
                    'Preferences are stored locally on this device.',
                    style: TextStyle(color: Colors.grey[500], fontSize: 12),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildPreferenceTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color iconColor,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return SwitchListTile(
      secondary: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: iconColor.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, color: iconColor, size: 24),
      ),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w500)),
      subtitle: Text(subtitle, style: const TextStyle(fontSize: 13)),
      value: value,
      onChanged: onChanged,
    );
  }
}
