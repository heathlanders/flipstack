import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../shared/routes/app_router.dart';
import '../../../authentication/presentation/providers/auth_provider.dart';

// Dashboard data model - flexible for all role types
class DashboardData {
  final Map<String, dynamic> businessMetrics;
  final Map<String, List<String>> alerts;
  final List<Map<String, dynamic>> recentActivity;
  final Map<String, dynamic>? shippingStats;
  final Map<String, dynamic>? queueStats;

  const DashboardData({
    this.businessMetrics = const {},
    this.alerts = const {},
    this.recentActivity = const [],
    this.shippingStats,
    this.queueStats,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    return DashboardData(
      businessMetrics: Map<String, dynamic>.from(json['businessMetrics'] ?? {}),
      alerts: _parseAlerts(json['alerts']),
      recentActivity: (json['recentActivity'] as List<dynamic>?)
              ?.map((a) => Map<String, dynamic>.from(a))
              .toList() ??
          [],
      shippingStats: json['shippingStats'] != null
          ? Map<String, dynamic>.from(json['shippingStats'])
          : null,
      queueStats: json['queueStats'] != null
          ? Map<String, dynamic>.from(json['queueStats'])
          : null,
    );
  }

  static Map<String, List<String>> _parseAlerts(dynamic alerts) {
    if (alerts == null) return {};
    final map = <String, List<String>>{};
    (alerts as Map<String, dynamic>).forEach((key, value) {
      map[key] = (value as List<dynamic>).map((e) => e.toString()).toList();
    });
    return map;
  }

  bool get hasCriticalAlerts => (alerts['critical'] ?? []).isNotEmpty;
  bool get hasWarningAlerts => (alerts['warning'] ?? []).isNotEmpty;
}

// Dashboard state
class DashboardState {
  final DashboardData data;
  final bool isLoading;
  final String? error;
  final String dashboardType;

  const DashboardState({
    this.data = const DashboardData(),
    this.isLoading = false,
    this.error,
    this.dashboardType = 'owner',
  });

  DashboardState copyWith({
    DashboardData? data,
    bool? isLoading,
    String? error,
    String? dashboardType,
    bool clearError = false,
  }) {
    return DashboardState(
      data: data ?? this.data,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      dashboardType: dashboardType ?? this.dashboardType,
    );
  }
}

// Dashboard notifier
class DashboardNotifier extends StateNotifier<DashboardState> {
  final ApiService _apiService;

  DashboardNotifier(this._apiService) : super(const DashboardState()) {
    loadDashboard();
  }

  Future<void> loadDashboard({String? type}) async {
    final dashboardType = type ?? state.dashboardType;
    state = state.copyWith(isLoading: true, clearError: true, dashboardType: dashboardType);

    try {
      await _apiService.initialize();
      final response = await _apiService.get(
        '${ApiEndpoints.api}/dashboard/$dashboardType',
      );

      final data = DashboardData.fromJson(response.data);
      state = state.copyWith(data: data, isLoading: false);
    } catch (e) {
      // Fallback: load product statistics for basic dashboard
      try {
        final statsResponse = await _apiService.get('${ApiEndpoints.products}/statistics');
        if (statsResponse.data['success'] == true) {
          final stats = statsResponse.data['data'];
          state = state.copyWith(
            data: DashboardData(businessMetrics: {
              'totalProducts': stats['total_products'] ?? 0,
              'lowStockAlerts': stats['low_stock_products'] ?? 0,
              'outOfStock': stats['out_of_stock_products'] ?? 0,
              'inventoryValue': (stats['total_inventory_value'] ?? 0.0),
            }),
            isLoading: false,
          );
        } else {
          state = state.copyWith(isLoading: false, error: 'Failed to load dashboard');
        }
      } catch (_) {
        state = state.copyWith(isLoading: false, error: 'Failed to load dashboard');
      }
    }
  }

  void switchDashboardType(String type) {
    loadDashboard(type: type);
  }
}

// Provider
final dashboardProvider = StateNotifierProvider<DashboardNotifier, DashboardState>((ref) {
  return DashboardNotifier(ApiService());
});

class OwnerDashboardPage extends ConsumerWidget {
  const OwnerDashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardState = ref.watch(dashboardProvider);
    final notifier = ref.read(dashboardProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: Text(_dashboardTitle(dashboardState.dashboardType)),
        actions: [
          // Role switcher
          PopupMenuButton<String>(
            icon: const Icon(Icons.swap_horiz),
            tooltip: 'Switch dashboard',
            onSelected: (type) => notifier.switchDashboardType(type),
            itemBuilder: (context) => [
              _roleMenuItem('owner', 'Owner', Icons.business, dashboardState.dashboardType),
              _roleMenuItem('buyer', 'Buyer', Icons.shopping_cart, dashboardState.dashboardType),
              _roleMenuItem('lister', 'Lister', Icons.list_alt, dashboardState.dashboardType),
              _roleMenuItem('fulfillment', 'Fulfillment', Icons.local_shipping, dashboardState.dashboardType),
            ],
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.loadDashboard(),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => notifier.loadDashboard(),
        child: _buildBody(context, dashboardState, ref),
      ),
    );
  }

  PopupMenuItem<String> _roleMenuItem(String value, String label, IconData icon, String current) {
    return PopupMenuItem(
      value: value,
      child: Row(
        children: [
          Icon(icon, size: 20, color: value == current ? Colors.deepPurple : Colors.grey),
          const SizedBox(width: 8),
          Text(label, style: TextStyle(fontWeight: value == current ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }

  String _dashboardTitle(String type) {
    switch (type) {
      case 'owner': return 'Owner Dashboard';
      case 'buyer': return 'Buyer Dashboard';
      case 'lister': return 'Lister Dashboard';
      case 'fulfillment': return 'Fulfillment Dashboard';
      default: return 'Dashboard';
    }
  }

  Widget _buildBody(BuildContext context, DashboardState state, WidgetRef ref) {
    if (state.isLoading && state.data.businessMetrics.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.data.businessMetrics.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.error!),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref.read(dashboardProvider.notifier).loadDashboard(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Alerts
          if (state.data.hasCriticalAlerts)
            _buildAlertBanner(context, state.data.alerts['critical']!, Colors.red),
          if (state.data.hasWarningAlerts)
            _buildAlertBanner(context, state.data.alerts['warning']!, Colors.orange),

          // Metrics grid
          _buildMetricsGrid(context, state),

          const SizedBox(height: 24),

          // Role-specific sections
          if (state.dashboardType == 'fulfillment' && state.data.shippingStats != null)
            _buildShippingStats(context, state.data.shippingStats!),

          if (state.dashboardType == 'lister' && state.data.queueStats != null)
            _buildQueueStats(context, state.data.queueStats!),

          // Recent activity
          if (state.data.recentActivity.isNotEmpty) ...[
            Text('Recent Activity', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...state.data.recentActivity.take(5).map((a) => _buildActivityItem(context, a)),
          ],

          const SizedBox(height: 24),

          // Quick actions
          Text('Quick Actions', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          _buildQuickActions(context, state.dashboardType),
        ],
      ),
    );
  }

  Widget _buildAlertBanner(BuildContext context, List<String> alerts, Color color) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: alerts.map((a) => Row(
          children: [
            Icon(Icons.warning, size: 16, color: color),
            const SizedBox(width: 8),
            Expanded(child: Text(a, style: TextStyle(color: color, fontSize: 13))),
          ],
        )).toList(),
      ),
    );
  }

  Widget _buildMetricsGrid(BuildContext context, DashboardState state) {
    final metrics = state.data.businessMetrics;
    final cards = <Widget>[];

    switch (state.dashboardType) {
      case 'owner':
        cards.addAll([
          _metricCard(context, 'Pending Orders', '${metrics['pendingOrders'] ?? 0}', Icons.receipt_long, Colors.blue),
          _metricCard(context, 'Low Stock', '${metrics['lowStockAlerts'] ?? 0}', Icons.warning, Colors.orange),
          _metricCard(context, 'Active Listings', '${metrics['activeListings'] ?? 0}', Icons.storefront, Colors.green),
          _metricCard(context, 'Intake Queue', '${metrics['pendingIntake'] ?? 0}', Icons.queue, Colors.purple),
        ]);
        break;
      case 'buyer':
        cards.addAll([
          _metricCard(context, 'High ROI', '${metrics['highROIOpportunities'] ?? 0}', Icons.trending_up, Colors.green),
          _metricCard(context, 'Avg ROI', '${metrics['averageROI'] ?? 0}%', Icons.analytics, Colors.blue),
          _metricCard(context, 'Active Suppliers', '${metrics['suppliersActive'] ?? 0}', Icons.people, Colors.purple),
          _metricCard(context, 'Sourcing Alerts', '${metrics['sourcingAlerts'] ?? 0}', Icons.notification_important, Colors.orange),
        ]);
        break;
      case 'lister':
        cards.addAll([
          _metricCard(context, 'Pending Items', '${metrics['pendingItems'] ?? 0}', Icons.pending, Colors.orange),
          _metricCard(context, 'Ready to List', '${metrics['readyForListing'] ?? 0}', Icons.check_circle, Colors.green),
          _metricCard(context, 'Completed Today', '${metrics['completedToday'] ?? 0}', Icons.done_all, Colors.blue),
          _metricCard(context, 'Avg Time', '${metrics['averageProcessingTime'] ?? "N/A"}', Icons.timer, Colors.purple),
        ]);
        break;
      case 'fulfillment':
        cards.addAll([
          _metricCard(context, 'Pending Orders', '${metrics['pendingOrders'] ?? 0}', Icons.receipt_long, Colors.blue),
          _metricCard(context, 'Picking Queue', '${metrics['pickingQueue'] ?? 0}', Icons.assignment, Colors.orange),
          _metricCard(context, 'Packing Queue', '${metrics['packingQueue'] ?? 0}', Icons.inventory, Colors.purple),
          _metricCard(context, 'Shipped Today', '${metrics['shippedToday'] ?? 0}', Icons.local_shipping, Colors.green),
          _metricCard(context, 'Returns', '${metrics['returnsProcessing'] ?? 0}', Icons.assignment_return, Colors.red),
          _metricCard(context, 'Overdue', '${metrics['overdueOrders'] ?? 0}', Icons.schedule, (metrics['overdueOrders'] ?? 0) > 0 ? Colors.red : Colors.grey),
        ]);
        break;
      default:
        metrics.forEach((key, value) {
          cards.add(_metricCard(context, _formatLabel(key), '$value', Icons.info, Colors.blue));
        });
    }

    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.3,
      children: cards,
    );
  }

  Widget _metricCard(BuildContext context, String title, String value, IconData icon, Color color) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(height: 8),
            Text(value, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
            Text(title, style: TextStyle(color: Colors.grey[600], fontSize: 12)),
          ],
        ),
      ),
    );
  }

  Widget _buildShippingStats(BuildContext context, Map<String, dynamic> stats) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Shipping', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Row(
          children: [
            _miniStat('Ready', '${stats['ready_to_ship'] ?? 0}', Colors.blue),
            _miniStat('In Transit', '${stats['in_transit'] ?? 0}', Colors.orange),
            _miniStat('Delivered', '${stats['delivered_today'] ?? 0}', Colors.green),
          ],
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _buildQueueStats(BuildContext context, Map<String, dynamic> stats) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Queue Status', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Row(
          children: [
            _miniStat('Pending', '${stats['pending'] ?? 0}', Colors.orange),
            _miniStat('In Progress', '${stats['in_progress'] ?? 0}', Colors.blue),
            _miniStat('Done Today', '${stats['completed_today'] ?? 0}', Colors.green),
          ],
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _miniStat(String label, String value, Color color) {
    return Expanded(
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 4),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          children: [
            Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color)),
            Text(label, style: TextStyle(fontSize: 11, color: color)),
          ],
        ),
      ),
    );
  }

  Widget _buildActivityItem(BuildContext context, Map<String, dynamic> activity) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 8, height: 8,
            margin: const EdgeInsets.only(top: 6),
            decoration: BoxDecoration(color: Colors.grey[400], shape: BoxShape.circle),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(activity['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                Text(activity['description'] ?? '', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
                Text(activity['timestamp'] ?? '', style: TextStyle(fontSize: 11, color: Colors.grey[400])),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickActions(BuildContext context, String dashboardType) {
    final actions = <_QuickAction>[];

    switch (dashboardType) {
      case 'owner':
        actions.addAll([
          _QuickAction('Add Product', Icons.add_box, AppRoute.addProduct.path),
          _QuickAction('Messages', Icons.mail_outline, AppRoute.messageInbox.path),
          _QuickAction('Bundles', Icons.widgets, AppRoute.bundleList.path),
        ]);
        break;
      case 'fulfillment':
        actions.addAll([
          _QuickAction('Pick Orders', Icons.assignment, AppRoute.pickList.path),
          _QuickAction('Pack Orders', Icons.inventory, AppRoute.packList.path),
          _QuickAction('Receive PO', Icons.move_to_inbox, AppRoute.receivingList.path),
          _QuickAction('Labeling', Icons.label, AppRoute.labelingList.path),
          _QuickAction('Bin Lookup', Icons.shelves, AppRoute.binLookup.path),
        ]);
        break;
      case 'lister':
        actions.addAll([
          _QuickAction('Intake Queue', Icons.queue, AppRoute.addProduct.path),
          _QuickAction('Scan Product', Icons.qr_code_scanner, AppRoute.barcodeLookup.path),
          _QuickAction('eBay Listings', Icons.sell, AppRoute.ebayListings.path),
        ]);
        break;
      case 'buyer':
        actions.addAll([
          _QuickAction('Products', Icons.inventory_2, AppRoute.inventory.path),
          _QuickAction('Receive PO', Icons.move_to_inbox, AppRoute.receivingList.path),
        ]);
        break;
    }

    return Row(
      children: actions.map((action) => Expanded(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4),
          child: InkWell(
            onTap: () => context.go(action.route),
            borderRadius: BorderRadius.circular(12),
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 16),
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey[300]!),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                children: [
                  Icon(action.icon, size: 32, color: Colors.deepPurple),
                  const SizedBox(height: 8),
                  Text(action.label, style: const TextStyle(fontSize: 12)),
                ],
              ),
            ),
          ),
        ),
      )).toList(),
    );
  }

  String _formatLabel(String camelCase) {
    return camelCase.replaceAllMapped(
      RegExp(r'([A-Z])'),
      (match) => ' ${match.group(0)}',
    ).trim();
  }
}

class _QuickAction {
  final String label;
  final IconData icon;
  final String route;
  const _QuickAction(this.label, this.icon, this.route);
}
