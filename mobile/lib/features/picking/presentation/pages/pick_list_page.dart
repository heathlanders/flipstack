import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/pick_models.dart';
import '../providers/pick_provider.dart';
import '../../../../shared/routes/app_router.dart';

class PickListPage extends ConsumerStatefulWidget {
  const PickListPage({super.key});

  @override
  ConsumerState<PickListPage> createState() => _PickListPageState();
}

class _PickListPageState extends ConsumerState<PickListPage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(pickListProvider);
    final notifier = ref.read(pickListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pick List'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
        actions: [
          // My picks toggle
          IconButton(
            icon: Icon(
              state.myPicksOnly ? Icons.person : Icons.people_outlined,
            ),
            tooltip: state.myPicksOnly ? 'Show all' : 'My picks only',
            onPressed: () => notifier.toggleMyPicks(),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.refresh(),
          ),
        ],
      ),
      body: Column(
        children: [
          // Stats bar
          _buildStatsBar(state.stats),

          // Location filter
          if (state.locations.isNotEmpty) _buildLocationFilter(state, notifier),

          // Content
          Expanded(
            child: _buildContent(state, notifier),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBar(PickStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.deepPurple.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Orders', stats.totalOrders.toString(), Colors.deepPurple),
          _statItem('Items', stats.totalItems.toString(), Colors.blue),
          _statItem(
            'Urgent',
            stats.urgent.toString(),
            stats.urgent > 0 ? Colors.red : Colors.grey,
          ),
          _statItem(
            'Overdue',
            stats.overdue.toString(),
            stats.overdue > 0 ? Colors.orange : Colors.grey,
          ),
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
          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
        ),
      ],
    );
  }

  Widget _buildLocationFilter(PickListState state, PickListNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: DropdownButtonFormField<String?>(
        value: state.selectedLocationId,
        decoration: InputDecoration(
          labelText: 'Filter by location',
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          isDense: true,
        ),
        items: [
          const DropdownMenuItem<String?>(
            value: null,
            child: Text('All locations'),
          ),
          ...state.locations.map(
            (loc) => DropdownMenuItem<String?>(
              value: loc.id,
              child: Text('${loc.name} (${loc.code ?? ""})'),
            ),
          ),
        ],
        onChanged: (value) => notifier.setLocationFilter(value),
      ),
    );
  }

  Widget _buildContent(PickListState state, PickListNotifier notifier) {
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
            Text('Error loading pick list', style: Theme.of(context).textTheme.titleMedium),
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
            Icon(Icons.check_circle_outline, size: 64, color: Colors.green[300]),
            const SizedBox(height: 16),
            Text('No orders to pick', style: Theme.of(context).textTheme.titleMedium),
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
        itemBuilder: (context, index) => _buildOrderCard(state.orders[index], notifier),
      ),
    );
  }

  Widget _buildOrderCard(PickOrder order, PickListNotifier notifier) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/picking/${order.id}'),
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
                  if (order.isUrgent) _priorityBadge('URGENT', Colors.red),
                  if (order.isHighPriority) _priorityBadge('HIGH', Colors.orange),
                  const SizedBox(width: 8),
                  _statusBadge(order.status),
                ],
              ),
              const SizedBox(height: 8),

              // Location + Buyer
              Row(
                children: [
                  if (order.location != null) ...[
                    Icon(Icons.location_on_outlined, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(
                      order.location!.name,
                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                    ),
                    const SizedBox(width: 16),
                  ],
                  if (order.buyerName != null) ...[
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
                ],
              ),

              // Ship by date
              if (order.shipByDate != null) ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(
                      Icons.schedule,
                      size: 16,
                      color: order.isOverdue ? Colors.red : Colors.grey[600],
                    ),
                    const SizedBox(width: 4),
                    Text(
                      'Ship by: ${_formatDate(order.shipByDate!)}',
                      style: TextStyle(
                        color: order.isOverdue ? Colors.red : Colors.grey[600],
                        fontSize: 13,
                        fontWeight: order.isOverdue ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                    if (order.isOverdue) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: Colors.red[100],
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: const Text(
                          'OVERDUE',
                          style: TextStyle(color: Colors.red, fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ],
                ),
              ],

              const SizedBox(height: 12),

              // Progress bar
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: order.pickProgress.progressPercent,
                        backgroundColor: Colors.grey[200],
                        color: order.pickProgress.pending == 0
                            ? Colors.green
                            : Colors.deepPurple,
                        minHeight: 6,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    '${order.pickProgress.picked + order.pickProgress.shorted}/${order.pickProgress.total}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ],
              ),

              const SizedBox(height: 8),

              // Items summary
              Row(
                children: [
                  Text(
                    '${order.items.length} items',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                  const Spacer(),
                  // Action button
                  if (order.isAssigned)
                    SizedBox(
                      height: 32,
                      child: ElevatedButton(
                        onPressed: () async {
                          final success = await notifier.startPicking(order.id);
                          if (success && mounted) {
                            context.go('/picking/${order.id}');
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.deepPurple,
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        child: const Text('Start Picking', style: TextStyle(fontSize: 13)),
                      ),
                    ),
                  if (order.isPicking)
                    SizedBox(
                      height: 32,
                      child: ElevatedButton(
                        onPressed: () => context.go('/picking/${order.id}'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        child: const Text('Continue', style: TextStyle(fontSize: 13)),
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
      case 'assigned':
        color = Colors.blue;
        label = 'Ready';
        break;
      case 'picking':
        color = Colors.orange;
        label = 'Picking';
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

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final diff = date.difference(now);
    if (diff.isNegative) {
      final days = diff.inDays.abs();
      if (days == 0) return 'Today';
      if (days == 1) return 'Yesterday';
      return '${days}d overdue';
    } else {
      final days = diff.inDays;
      if (days == 0) return 'Today';
      if (days == 1) return 'Tomorrow';
      return 'In ${days}d';
    }
  }
}
