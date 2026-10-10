import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/alert_models.dart';
import '../providers/alert_provider.dart';

class AlertsPage extends ConsumerStatefulWidget {
  const AlertsPage({super.key});

  @override
  ConsumerState<AlertsPage> createState() => _AlertsPageState();
}

class _AlertsPageState extends ConsumerState<AlertsPage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(alertProvider);
    final notifier = ref.read(alertProvider.notifier);

    // Listen for success/error messages
    ref.listen<AlertState>(alertProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.successMessage!),
            backgroundColor: Colors.green,
          ),
        );
        notifier.clearMessages();
      }
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
          ),
        );
        notifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory Alerts'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
        actions: [
          if (state.alerts.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.done_all),
              tooltip: 'Dismiss All',
              onPressed: () => _confirmDismissAll(notifier),
            ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.refresh(),
          ),
        ],
      ),
      body: Column(
        children: [
          // Summary cards
          _buildSummaryBar(state.summary),

          // Filter chips
          _buildFilterChips(state, notifier),

          // Content
          Expanded(child: _buildContent(state, notifier)),
        ],
      ),
    );
  }

  Widget _buildSummaryBar(AlertSummary summary) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.deepPurple.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _summaryCard('Low Stock', summary.lowStock, Colors.orange),
          _summaryCard('Out of Stock', summary.outOfStock, Colors.red),
          _summaryCard('Overstocked', summary.overstocked, Colors.blue),
        ],
      ),
    );
  }

  Widget _summaryCard(String label, int count, Color color) {
    return Column(
      children: [
        Text(
          count.toString(),
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.bold,
            color: count > 0 ? color : Colors.grey,
          ),
        ),
        Text(
          label,
          style: TextStyle(fontSize: 11, color: Colors.grey[600]),
        ),
      ],
    );
  }

  Widget _buildFilterChips(AlertState state, AlertNotifier notifier) {
    final filters = <String?, String>{
      null: 'All',
      'low_stock': 'Low Stock',
      'out_of_stock': 'Out of Stock',
      'overstocked': 'Overstocked',
    };

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Row(
        children: filters.entries.map((entry) {
          final isSelected = state.typeFilter == entry.key;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilterChip(
              label: Text(entry.value),
              selected: isSelected,
              onSelected: (_) => notifier.setTypeFilter(isSelected ? null : entry.key),
              selectedColor: Colors.deepPurple.shade100,
              checkmarkColor: Colors.deepPurple,
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildContent(AlertState state, AlertNotifier notifier) {
    if (state.isLoading && state.alerts.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.alerts.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading alerts', style: Theme.of(context).textTheme.titleMedium),
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

    if (state.alerts.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.check_circle_outline, size: 64, color: Colors.green[300]),
            const SizedBox(height: 16),
            Text('No alerts', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('All inventory levels are healthy', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.alerts.length,
        itemBuilder: (context, index) => _buildAlertCard(state.alerts[index], notifier),
      ),
    );
  }

  Widget _buildAlertCard(InventoryAlert alert, AlertNotifier notifier) {
    return Dismissible(
      key: Key(alert.id),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        color: Colors.red,
        child: const Icon(Icons.delete_outline, color: Colors.white),
      ),
      onDismissed: (_) => notifier.dismissAlert(alert.id),
      child: Card(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: product name + alert type badge
              Row(
                children: [
                  Expanded(
                    child: Text(
                      alert.productName,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  _alertTypeBadge(alert.alertType),
                ],
              ),
              const SizedBox(height: 8),

              // SKU
              Row(
                children: [
                  Icon(Icons.tag, size: 14, color: Colors.grey[600]),
                  const SizedBox(width: 4),
                  Text(
                    'SKU: ${alert.productSku}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                ],
              ),
              const SizedBox(height: 4),

              // Quantity info
              Row(
                children: [
                  Icon(Icons.inventory_2_outlined, size: 14, color: Colors.grey[600]),
                  const SizedBox(width: 4),
                  Text(
                    'Current: ${alert.currentQuantity}',
                    style: TextStyle(
                      color: alert.currentQuantity == 0 ? Colors.red : Colors.grey[700],
                      fontSize: 13,
                      fontWeight: alert.currentQuantity == 0 ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Text(
                    'Threshold: ${alert.threshold}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                ],
              ),

              // Location
              if (alert.locationName != null) ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.location_on_outlined, size: 14, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(
                      alert.locationName!,
                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                    ),
                  ],
                ),
              ],

              const SizedBox(height: 8),

              // Dismiss button
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: () => notifier.dismissAlert(alert.id),
                  icon: const Icon(Icons.close, size: 16),
                  label: const Text('Dismiss'),
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.grey[700],
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _alertTypeBadge(String alertType) {
    final Color color;
    final String label;
    switch (alertType) {
      case 'out_of_stock':
        color = Colors.red;
        label = 'OUT OF STOCK';
        break;
      case 'low_stock':
        color = Colors.orange;
        label = 'LOW STOCK';
        break;
      case 'overstocked':
        color = Colors.blue;
        label = 'OVERSTOCKED';
        break;
      default:
        color = Colors.grey;
        label = alertType.toUpperCase();
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
      ),
    );
  }

  void _confirmDismissAll(AlertNotifier notifier) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Dismiss All Alerts'),
        content: const Text('Are you sure you want to dismiss all alerts?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              notifier.dismissAll();
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Dismiss All'),
          ),
        ],
      ),
    );
  }
}
