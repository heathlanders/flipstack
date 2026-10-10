import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/fulfillment_order_models.dart';
import '../providers/order_provider.dart';

class OrderListPage extends ConsumerStatefulWidget {
  const OrderListPage({super.key});

  @override
  ConsumerState<OrderListPage> createState() => _OrderListPageState();
}

class _OrderListPageState extends ConsumerState<OrderListPage> {
  final _scrollController = ScrollController();

  static const List<MapEntry<String?, String>> _statusFilters = [
    MapEntry(null, 'All'),
    MapEntry('pending', 'Pending'),
    MapEntry('assigned', 'Assigned'),
    MapEntry('picking', 'Picking'),
    MapEntry('picked', 'Picked'),
    MapEntry('packing', 'Packing'),
    MapEntry('shipped', 'Shipped'),
    MapEntry('on_hold', 'On Hold'),
  ];

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(orderListProvider.notifier).nextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(orderListProvider);
    final notifier = ref.read(orderListProvider.notifier);

    // Listen for success/error messages
    ref.listen<OrderListState>(orderListProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.successMessage!), backgroundColor: Colors.green),
        );
        notifier.clearMessages();
      }
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.error!), backgroundColor: Colors.red),
        );
        notifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Orders'),
        backgroundColor: Colors.deepPurple,
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
          // Stats bar
          _buildStatsBar(state.stats),

          // Status filter chips
          _buildFilterChips(state, notifier),

          // Content
          Expanded(child: _buildContent(state, notifier)),
        ],
      ),
    );
  }

  Widget _buildStatsBar(OrderStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      color: Colors.deepPurple.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Pending', stats.pending.toString(), Colors.orange),
          _statItem('Processing', stats.processing.toString(), Colors.blue),
          _statItem('On Hold', stats.onHold.toString(), Colors.red),
          _statItem('Completed', stats.completed.toString(), Colors.green),
          _statItem('Total', stats.total.toString(), Colors.deepPurple),
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
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: TextStyle(fontSize: 10, color: Colors.grey[600]),
        ),
      ],
    );
  }

  Widget _buildFilterChips(OrderListState state, OrderListNotifier notifier) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Row(
        children: _statusFilters.map((entry) {
          final isSelected = state.statusFilter == entry.key;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilterChip(
              label: Text(entry.value),
              selected: isSelected,
              onSelected: (_) => notifier.setStatusFilter(isSelected ? null : entry.key),
              selectedColor: Colors.deepPurple.shade100,
              checkmarkColor: Colors.deepPurple,
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildContent(OrderListState state, OrderListNotifier notifier) {
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
            Text('Error loading orders', style: Theme.of(context).textTheme.titleMedium),
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
            Icon(Icons.inbox_outlined, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text('No orders found', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              state.statusFilter != null
                  ? 'No orders with this status'
                  : 'Orders will appear here',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.orders.length + (state.hasNextPage ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == state.orders.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          return _buildOrderCard(state.orders[index]);
        },
      ),
    );
  }

  Widget _buildOrderCard(FulfillmentOrder order) {
    final isOverdue = order.shipByDate != null && order.shipByDate!.isBefore(DateTime.now());

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/orders/${order.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: marketplace order ID + badges
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.marketplaceOrderId,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  _priorityBadge(order.priority),
                  const SizedBox(width: 6),
                  _statusBadge(order.status),
                ],
              ),
              const SizedBox(height: 8),

              // Buyer + marketplace
              Row(
                children: [
                  if (order.buyerName != null) ...[
                    Icon(Icons.person_outlined, size: 14, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        order.buyerName!,
                        style: TextStyle(color: Colors.grey[600], fontSize: 13),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                  _marketplaceBadge(order.marketplace),
                ],
              ),
              const SizedBox(height: 4),

              // Amount + items
              Row(
                children: [
                  Text(
                    '\$${order.totalAmount.toStringAsFixed(2)}',
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    '${order.itemsCount} item${order.itemsCount != 1 ? 's' : ''}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                  if (order.locationName != null) ...[
                    const SizedBox(width: 12),
                    Icon(Icons.location_on_outlined, size: 14, color: Colors.grey[600]),
                    const SizedBox(width: 2),
                    Text(
                      order.locationName!,
                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
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
                      size: 14,
                      color: isOverdue ? Colors.red : Colors.grey[600],
                    ),
                    const SizedBox(width: 4),
                    Text(
                      'Ship by: ${_formatDate(order.shipByDate!)}',
                      style: TextStyle(
                        color: isOverdue ? Colors.red : Colors.grey[600],
                        fontSize: 13,
                        fontWeight: isOverdue ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                    if (isOverdue) ...[
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

              // Hold reason
              if (order.holdReason != null && order.status == 'on_hold') ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.pause_circle_outline, size: 14, color: Colors.orange[700]),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        'Hold: ${order.holdReason}',
                        style: TextStyle(color: Colors.orange[700], fontSize: 13),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
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
      case 'pending':
        color = Colors.orange;
        label = 'Pending';
        break;
      case 'assigned':
        color = Colors.blue;
        label = 'Assigned';
        break;
      case 'picking':
        color = Colors.purple;
        label = 'Picking';
        break;
      case 'picked':
        color = Colors.teal;
        label = 'Picked';
        break;
      case 'packing':
        color = Colors.indigo;
        label = 'Packing';
        break;
      case 'shipped':
        color = Colors.green;
        label = 'Shipped';
        break;
      case 'on_hold':
        color = Colors.red;
        label = 'On Hold';
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
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }

  Widget _priorityBadge(String priority) {
    if (priority == 'normal' || priority == 'standard') return const SizedBox.shrink();

    final Color color;
    final String label;
    switch (priority) {
      case 'urgent':
        color = Colors.red;
        label = 'URGENT';
        break;
      case 'high':
        color = Colors.orange;
        label = 'HIGH';
        break;
      default:
        return const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
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

  Widget _marketplaceBadge(String marketplace) {
    final Color color;
    switch (marketplace.toLowerCase()) {
      case 'ebay':
        color = Colors.blue;
        break;
      case 'shopify':
        color = Colors.green;
        break;
      default:
        color = Colors.grey;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        marketplace,
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600),
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
