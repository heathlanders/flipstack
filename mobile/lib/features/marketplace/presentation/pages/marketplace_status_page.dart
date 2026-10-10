import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/marketplace_models.dart';
import '../providers/marketplace_provider.dart';

class MarketplaceStatusPage extends ConsumerStatefulWidget {
  const MarketplaceStatusPage({super.key});

  @override
  ConsumerState<MarketplaceStatusPage> createState() => _MarketplaceStatusPageState();
}

class _MarketplaceStatusPageState extends ConsumerState<MarketplaceStatusPage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(marketplaceProvider);
    final notifier = ref.read(marketplaceProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Marketplaces'),
        backgroundColor: Colors.blueGrey,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.refresh(),
          ),
        ],
      ),
      body: _buildContent(state, notifier),
    );
  }

  Widget _buildContent(MarketplaceState state, MarketplaceNotifier notifier) {
    if (state.isLoading && state.marketplaces.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.marketplaces.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading marketplaces', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(state.error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => notifier.refresh(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state.marketplaces.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.storefront_outlined, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text('No marketplaces connected', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(
                'Connect marketplaces from the web dashboard',
                style: TextStyle(color: Colors.grey[600]),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView(
        padding: const EdgeInsets.symmetric(vertical: 8),
        children: [
          // Marketplace cards
          ...state.marketplaces.map((m) => _buildMarketplaceCard(m, state)),

          // eBay stats summary (if available)
          if (state.ebayStats != null) ...[
            const SizedBox(height: 8),
            _buildEbayStatsSection(state.ebayStats!),
          ],

          // Sync logs section
          if (state.ebayLogs.isNotEmpty) ...[
            const SizedBox(height: 8),
            _buildSyncLogsSection(state.ebayLogs),
          ],
        ],
      ),
    );
  }

  Widget _buildMarketplaceCard(MarketplaceConnection marketplace, MarketplaceState state) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Platform icon
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: _platformColor(marketplace.platform).withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                _platformIcon(marketplace.platform),
                color: _platformColor(marketplace.platform),
                size: 28,
              ),
            ),
            const SizedBox(width: 16),

            // Info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        _platformName(marketplace.platform),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                      const SizedBox(width: 8),
                      _connectionBadge(marketplace.isActive),
                    ],
                  ),
                  const SizedBox(height: 8),

                  if (marketplace.storeName != null) ...[
                    Row(
                      children: [
                        Icon(Icons.store_outlined, size: 14, color: Colors.grey[600]),
                        const SizedBox(width: 4),
                        Text(
                          marketplace.storeName!,
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                  ],

                  if (marketplace.listingCount != null) ...[
                    Row(
                      children: [
                        Icon(Icons.list_alt, size: 14, color: Colors.grey[600]),
                        const SizedBox(width: 4),
                        Text(
                          '${marketplace.listingCount} active listings',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                  ],

                  if (marketplace.connectedAt != null)
                    Row(
                      children: [
                        Icon(Icons.link, size: 14, color: Colors.grey[600]),
                        const SizedBox(width: 4),
                        Text(
                          'Connected: ${_formatDate(marketplace.connectedAt!)}',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      ],
                    ),

                  if (marketplace.lastSyncAt != null) ...[
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(Icons.sync, size: 14, color: Colors.grey[600]),
                        const SizedBox(width: 4),
                        Text(
                          'Last sync: ${_formatRelativeTime(marketplace.lastSyncAt!)}',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                  ],

                  if (marketplace.recentErrorCount != null && marketplace.recentErrorCount! > 0) ...[
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(Icons.error_outline, size: 14, color: Colors.red[400]),
                        const SizedBox(width: 4),
                        Text(
                          '${marketplace.recentErrorCount} recent errors',
                          style: TextStyle(fontSize: 12, color: Colors.red[400]),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEbayStatsSection(EbayStats stats) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'eBay Listing Summary',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              _statChip('Total', stats.totalListings, Colors.blueGrey),
              const SizedBox(width: 8),
              _statChip('Active', stats.activeListings, Colors.green),
              const SizedBox(width: 8),
              _statChip('Draft', stats.draftListings, Colors.orange),
              const SizedBox(width: 8),
              _statChip('Error', stats.errorListings, Colors.red),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statChip(String label, int value, Color color) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
          child: Column(
            children: [
              Text(
                '$value',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                  color: color,
                ),
              ),
              const SizedBox(height: 2),
              Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSyncLogsSection(List<EbaySyncLog> logs) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Recent Sync Activity',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
          ),
          const SizedBox(height: 8),
          ...logs.take(10).map((log) => Card(
                margin: const EdgeInsets.only(bottom: 4),
                child: ListTile(
                  dense: true,
                  leading: Icon(
                    log.status == 'success' || log.status == 'completed'
                        ? Icons.check_circle_outline
                        : log.status == 'error' || log.status == 'failed'
                            ? Icons.error_outline
                            : Icons.info_outline,
                    color: log.status == 'success' || log.status == 'completed'
                        ? Colors.green
                        : log.status == 'error' || log.status == 'failed'
                            ? Colors.red
                            : Colors.blue,
                    size: 20,
                  ),
                  title: Text(
                    log.action,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  subtitle: log.message != null
                      ? Text(
                          log.message!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 11),
                        )
                      : null,
                  trailing: Text(
                    _formatRelativeTime(log.createdAt),
                    style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                  ),
                ),
              )),
        ],
      ),
    );
  }

  Widget _connectionBadge(bool isActive) {
    final color = isActive ? Colors.green : Colors.red;
    final label = isActive ? 'Active' : 'Inactive';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }

  IconData _platformIcon(String platform) {
    switch (platform.toLowerCase()) {
      case 'ebay':
        return Icons.shopping_bag_outlined;
      case 'shopify':
        return Icons.shopping_cart_outlined;
      case 'amazon':
        return Icons.local_shipping_outlined;
      default:
        return Icons.storefront_outlined;
    }
  }

  Color _platformColor(String platform) {
    switch (platform.toLowerCase()) {
      case 'ebay':
        return Colors.blue;
      case 'shopify':
        return Colors.green;
      case 'amazon':
        return Colors.orange;
      default:
        return Colors.blueGrey;
    }
  }

  String _platformName(String platform) {
    switch (platform.toLowerCase()) {
      case 'ebay':
        return 'eBay';
      case 'shopify':
        return 'Shopify';
      case 'amazon':
        return 'Amazon';
      default:
        return platform;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.month}/${date.day}/${date.year}';
  }

  String _formatRelativeTime(DateTime date) {
    final diff = DateTime.now().difference(date);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return _formatDate(date);
  }
}
