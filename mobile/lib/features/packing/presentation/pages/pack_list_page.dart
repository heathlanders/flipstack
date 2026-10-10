import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/pack_models.dart';
import '../providers/pack_provider.dart';

class PackListPage extends ConsumerStatefulWidget {
  const PackListPage({super.key});

  @override
  ConsumerState<PackListPage> createState() => _PackListPageState();
}

class _PackListPageState extends ConsumerState<PackListPage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(packListProvider);
    final notifier = ref.read(packListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pack Queue'),
        backgroundColor: Colors.teal,
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
          Expanded(
            child: _buildContent(state, notifier),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBar(PackStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.teal.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Awaiting Pack', stats.awaitingPack.toString(), Colors.teal),
          _statItem('Packing', stats.packing.toString(), Colors.orange),
          _statItem('Awaiting Label', stats.awaitingLabel.toString(), Colors.blue),
          _statItem('Ready to Ship', stats.readyToShip.toString(), Colors.green),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value, Color color) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: TextStyle(fontSize: 11, color: Colors.grey[600]),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildContent(PackListState state, PackListNotifier notifier) {
    if (state.isLoading && state.orders.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.orders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading pack queue', style: Theme.of(context).textTheme.titleMedium),
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

    if (state.orders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.inventory_2_outlined, size: 64, color: Colors.green[300]),
            const SizedBox(height: 16),
            Text('No orders to pack', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('All caught up!', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.orders.length,
        itemBuilder: (context, index) => _buildOrderCard(state.orders[index]),
      ),
    );
  }

  Widget _buildOrderCard(PackOrder order) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/packing/${order.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: Order ID + Priority + Status
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.marketplaceOrderId,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  if (order.priority == 'urgent')
                    _priorityBadge('URGENT', Colors.red),
                  if (order.priority == 'high')
                    _priorityBadge('HIGH', Colors.orange),
                  const SizedBox(width: 8),
                  _statusBadge(order.status),
                ],
              ),
              const SizedBox(height: 8),

              // Buyer name
              if (order.buyerName != null)
                Row(
                  children: [
                    Icon(Icons.person_outlined, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        order.buyerName!,
                        style: TextStyle(color: Colors.grey[600], fontSize: 13),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),

              const SizedBox(height: 8),

              // Items count + action
              Row(
                children: [
                  Text(
                    '${order.itemCount} item${order.itemCount == 1 ? '' : 's'}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                  const Spacer(),
                  if (order.status == 'picked' || order.status == 'awaiting_pack')
                    SizedBox(
                      height: 32,
                      child: ElevatedButton(
                        onPressed: () => context.go('/packing/${order.id}'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.teal,
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        child: const Text('Start Packing', style: TextStyle(fontSize: 13)),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _priorityBadge(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _statusBadge(String status) {
    final Color color;
    final String label;
    switch (status) {
      case 'picked':
      case 'awaiting_pack':
        color = Colors.teal;
        label = 'Awaiting Pack';
        break;
      case 'packing':
        color = Colors.orange;
        label = 'Packing';
        break;
      case 'awaiting_label':
        color = Colors.blue;
        label = 'Awaiting Label';
        break;
      case 'ready_to_ship':
        color = Colors.green;
        label = 'Ready to Ship';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}
