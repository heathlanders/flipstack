import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/receiving_models.dart';
import '../providers/receiving_provider.dart';

class ReceivingListPage extends ConsumerWidget {
  const ReceivingListPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(receivingListProvider);
    final notifier = ref.read(receivingListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Receive POs'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.refresh(),
          ),
        ],
      ),
      body: Column(
        children: [
          _buildStatsBar(state.stats),
          Expanded(child: _buildContent(context, state, notifier)),
        ],
      ),
    );
  }

  Widget _buildStatsBar(ReceivingStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.indigo.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Open', stats.totalOrders.toString(), Colors.indigo),
          _statItem('Submitted', stats.submitted.toString(), Colors.blue),
          _statItem('Partial', stats.partiallyReceived.toString(), Colors.orange),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value, Color color) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color)),
        Text(label, style: TextStyle(fontSize: 12, color: Colors.grey[600])),
      ],
    );
  }

  Widget _buildContent(BuildContext context, ReceivingListState state, ReceivingListNotifier notifier) {
    if (state.isLoading && state.purchaseOrders.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.purchaseOrders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading purchase orders', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(state.error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: () => notifier.refresh(), child: const Text('Retry')),
          ],
        ),
      );
    }

    if (state.purchaseOrders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.inbox_outlined, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text('No open purchase orders', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('Submitted POs awaiting delivery appear here.', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.purchaseOrders.length,
        itemBuilder: (context, index) => _buildPoCard(context, state.purchaseOrders[index]),
      ),
    );
  }

  Widget _buildPoCard(BuildContext context, ReceivingPo po) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/receiving/${po.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(po.poNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  ),
                  _statusBadge(po.status),
                ],
              ),
              const SizedBox(height: 8),
              if (po.supplierName != null)
                Text(po.supplierName!, style: const TextStyle(fontSize: 14), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 8),
              Row(
                children: [
                  if (po.location != null) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(color: Colors.grey[100], borderRadius: BorderRadius.circular(4)),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.location_on, size: 14, color: Colors.grey[600]),
                          const SizedBox(width: 4),
                          Text(po.location!.name, style: TextStyle(fontSize: 12, color: Colors.grey[700])),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Text('${po.itemsCount} lines', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: po.progressPercent,
                        backgroundColor: Colors.grey[200],
                        color: po.totalQuantityReceived >= po.totalQuantityOrdered ? Colors.green : Colors.indigo,
                        minHeight: 6,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    '${po.totalQuantityReceived}/${po.totalQuantityOrdered} received',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _statusBadge(String status) {
    final Color color;
    final String label;
    switch (status) {
      case 'submitted':
        color = Colors.blue;
        label = 'Submitted';
        break;
      case 'partially_received':
        color = Colors.orange;
        label = 'Partial';
        break;
      case 'received':
        color = Colors.green;
        label = 'Received';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}
