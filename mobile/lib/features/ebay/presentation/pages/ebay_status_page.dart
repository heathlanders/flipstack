import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';

// eBay status data model
class EbayStatusData {
  final bool isConnected;
  final String? accountName;
  final String? marketplace;
  final DateTime? lastSyncAt;
  final int totalListings;
  final int activeListings;
  final int pendingListings;
  final int errorListings;
  final double dailyApiUsage;

  EbayStatusData({
    this.isConnected = false,
    this.accountName,
    this.marketplace,
    this.lastSyncAt,
    this.totalListings = 0,
    this.activeListings = 0,
    this.pendingListings = 0,
    this.errorListings = 0,
    this.dailyApiUsage = 0.0,
  });

  factory EbayStatusData.fromJson(Map<String, dynamic> json) {
    return EbayStatusData(
      isConnected: json['is_connected'] ?? false,
      accountName: json['account_name'],
      marketplace: json['marketplace'],
      lastSyncAt: json['last_sync_at'] != null
          ? DateTime.tryParse(json['last_sync_at'])
          : null,
      totalListings: json['total_listings'] ?? 0,
      activeListings: json['active_listings'] ?? 0,
      pendingListings: json['pending_listings'] ?? 0,
      errorListings: json['error_listings'] ?? 0,
      dailyApiUsage: (json['daily_api_usage'] ?? 0.0).toDouble(),
    );
  }
}

// eBay status state
class EbayStatusState {
  final EbayStatusData data;
  final bool isLoading;
  final String? error;

  const EbayStatusState({
    required this.data,
    this.isLoading = false,
    this.error,
  });

  EbayStatusState copyWith({
    EbayStatusData? data,
    bool? isLoading,
    String? error,
  }) {
    return EbayStatusState(
      data: data ?? this.data,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

// eBay status notifier
class EbayStatusNotifier extends StateNotifier<EbayStatusState> {
  final ApiService _apiService;

  EbayStatusNotifier(this._apiService)
      : super(EbayStatusState(data: EbayStatusData())) {
    loadStatus();
  }

  Future<void> loadStatus() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      await _apiService.initialize();
      final response = await _apiService.get('${ApiEndpoints.api}/ebay/status');

      if (response.data['success'] == true) {
        final statusData = EbayStatusData.fromJson(response.data['data'] ?? {});
        state = state.copyWith(
          data: statusData,
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Failed to load eBay status',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to load eBay status',
      );
    }
  }
}

// Provider
final ebayStatusProvider = StateNotifierProvider<EbayStatusNotifier, EbayStatusState>((ref) {
  return EbayStatusNotifier(ApiService());
});

class EbayStatusPage extends ConsumerWidget {
  const EbayStatusPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statusState = ref.watch(ebayStatusProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('eBay Integration'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(ebayStatusProvider.notifier).loadStatus();
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(ebayStatusProvider.notifier).loadStatus(),
        child: _buildBody(context, statusState),
      ),
    );
  }

  Widget _buildBody(BuildContext context, EbayStatusState state) {
    if (state.isLoading && !state.data.isConnected) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && !state.data.isConnected) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.error!),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Connection status card
          _buildConnectionStatusCard(context, state.data),

          const SizedBox(height: 16),

          if (state.data.isConnected) ...[
            // Listings summary
            _buildListingsSummaryCard(context, state.data),

            const SizedBox(height: 16),

            // API usage
            _buildApiUsageCard(context, state.data),

            const SizedBox(height: 16),

            // Quick actions
            _buildQuickActionsCard(context),
          ] else ...[
            // Connect prompt
            _buildConnectPromptCard(context),
          ],
        ],
      ),
    );
  }

  Widget _buildConnectionStatusCard(BuildContext context, EbayStatusData data) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: data.isConnected
                        ? Colors.green.withOpacity(0.1)
                        : Colors.grey.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    data.isConnected ? Icons.check_circle : Icons.link_off,
                    color: data.isConnected ? Colors.green : Colors.grey,
                    size: 32,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        data.isConnected ? 'Connected' : 'Not Connected',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      if (data.accountName != null)
                        Text(
                          data.accountName!,
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                      if (data.marketplace != null)
                        Text(
                          'Marketplace: ${data.marketplace}',
                          style: TextStyle(
                            color: Colors.grey[600],
                            fontSize: 12,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
            if (data.lastSyncAt != null) ...[
              const Divider(height: 24),
              Row(
                children: [
                  const Icon(Icons.sync, size: 16, color: Colors.grey),
                  const SizedBox(width: 8),
                  Text(
                    'Last synced: ${_formatDateTime(data.lastSyncAt!)}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildListingsSummaryCard(BuildContext context, EbayStatusData data) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Listings Summary',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: _buildStatItem(
                    'Total',
                    data.totalListings.toString(),
                    Icons.list_alt,
                    Colors.blue,
                  ),
                ),
                Expanded(
                  child: _buildStatItem(
                    'Active',
                    data.activeListings.toString(),
                    Icons.check_circle_outline,
                    Colors.green,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _buildStatItem(
                    'Pending',
                    data.pendingListings.toString(),
                    Icons.hourglass_empty,
                    Colors.orange,
                  ),
                ),
                Expanded(
                  child: _buildStatItem(
                    'Errors',
                    data.errorListings.toString(),
                    Icons.error_outline,
                    Colors.red,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatItem(String label, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.05),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                  color: color,
                ),
              ),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  color: Colors.grey[600],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildApiUsageCard(BuildContext context, EbayStatusData data) {
    final usagePercent = data.dailyApiUsage.clamp(0.0, 100.0);
    final color = usagePercent > 80
        ? Colors.red
        : usagePercent > 50
            ? Colors.orange
            : Colors.green;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'API Usage Today',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  '${usagePercent.toStringAsFixed(1)}%',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: usagePercent / 100,
                backgroundColor: Colors.grey[200],
                valueColor: AlwaysStoppedAnimation(color),
                minHeight: 8,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              usagePercent > 80
                  ? 'High usage - Consider reducing sync frequency'
                  : 'Usage within normal limits',
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionsCard(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Quick Actions',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            _buildActionTile(
              context,
              'View Listings',
              'Browse all your eBay listings',
              Icons.list,
              () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Listings page coming soon')),
                );
              },
            ),
            const Divider(),
            _buildActionTile(
              context,
              'Sync Now',
              'Manually sync inventory with eBay',
              Icons.sync,
              () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Sync initiated')),
                );
              },
            ),
            const Divider(),
            _buildActionTile(
              context,
              'View Logs',
              'Check recent sync activity',
              Icons.history,
              () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Logs page coming soon')),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildConnectPromptCard(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.blue.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.storefront,
                size: 48,
                color: Colors.blue,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Connect Your eBay Account',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Link your eBay seller account to sync inventory and manage listings from the app.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Please connect via the web app settings'),
                  ),
                );
              },
              icon: const Icon(Icons.link),
              label: const Text('Connect eBay'),
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 12,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionTile(
    BuildContext context,
    String title,
    String subtitle,
    IconData icon,
    VoidCallback onTap,
  ) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: Theme.of(context).primaryColor),
      title: Text(title),
      subtitle: Text(
        subtitle,
        style: TextStyle(fontSize: 12, color: Colors.grey[600]),
      ),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }

  String _formatDateTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'Just now';
    } else if (difference.inMinutes < 60) {
      return '${difference.inMinutes}m ago';
    } else if (difference.inHours < 24) {
      return '${difference.inHours}h ago';
    } else {
      return '${dateTime.month}/${dateTime.day} ${dateTime.hour}:${dateTime.minute.toString().padLeft(2, '0')}';
    }
  }
}
