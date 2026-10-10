import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/fulfillment_order_models.dart';
import '../../../../data/models/pick_models.dart';
import '../providers/order_provider.dart';

class OrderDetailPage extends ConsumerStatefulWidget {
  final String orderId;

  const OrderDetailPage({super.key, required this.orderId});

  @override
  ConsumerState<OrderDetailPage> createState() => _OrderDetailPageState();
}

class _OrderDetailPageState extends ConsumerState<OrderDetailPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(orderDetailProvider.notifier).loadOrder(widget.orderId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(orderDetailProvider);
    final listNotifier = ref.read(orderListProvider.notifier);

    // Listen for action results from the list provider
    ref.listen<OrderListState>(orderListProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.successMessage!), backgroundColor: Colors.green),
        );
        listNotifier.clearMessages();
        // Reload the detail
        ref.read(orderDetailProvider.notifier).loadOrder(widget.orderId);
      }
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.error!), backgroundColor: Colors.red),
        );
        listNotifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: Text(state.order?.marketplaceOrderId ?? 'Order Details'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
      ),
      body: _buildBody(state),
      bottomNavigationBar: state.order != null ? _buildActionBar(state.order!) : null,
    );
  }

  Widget _buildBody(OrderDetailState state) {
    if (state.isLoading && state.order == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.order == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading order', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(state.error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref.read(orderDetailProvider.notifier).loadOrder(widget.orderId),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final order = state.order;
    if (order == null) return const SizedBox.shrink();

    return RefreshIndicator(
      onRefresh: () => ref.read(orderDetailProvider.notifier).loadOrder(widget.orderId),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Order header
          _buildHeaderSection(order),
          const SizedBox(height: 16),

          // Buyer info
          if (order.buyerName != null) ...[
            _buildBuyerSection(order),
            const SizedBox(height: 16),
          ],

          // Items
          _buildItemsSection(order),
          const SizedBox(height: 16),

          // Hold reason
          if (order.holdReason != null && order.status == 'on_hold') ...[
            _buildHoldSection(order),
            const SizedBox(height: 16),
          ],

          // Order info
          _buildInfoSection(order),
          const SizedBox(height: 80), // Space for bottom bar
        ],
      ),
    );
  }

  Widget _buildHeaderSection(FulfillmentOrder order) {
    final isOverdue = order.shipByDate != null && order.shipByDate!.isBefore(DateTime.now());

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    order.marketplaceOrderId,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                ),
                _statusBadge(order.status),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _marketplaceBadge(order.marketplace),
                const SizedBox(width: 8),
                _priorityBadge(order.priority),
              ],
            ),
            if (order.shipByDate != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.schedule, size: 16, color: isOverdue ? Colors.red : Colors.grey[600]),
                  const SizedBox(width: 4),
                  Text(
                    'Ship by: ${_formatDateTime(order.shipByDate!)}',
                    style: TextStyle(
                      color: isOverdue ? Colors.red : Colors.grey[600],
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
                      child: const Text('OVERDUE', style: TextStyle(color: Colors.red, fontSize: 10, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ],
              ),
            ],
            const SizedBox(height: 4),
            Text(
              'Total: \$${order.totalAmount.toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBuyerSection(FulfillmentOrder order) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Buyer', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.person_outlined, size: 18, color: Colors.grey),
                const SizedBox(width: 8),
                Text(order.buyerName ?? 'Unknown'),
              ],
            ),
            if (order.locationName != null) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(Icons.location_on_outlined, size: 18, color: Colors.grey),
                  const SizedBox(width: 8),
                  Text(order.locationName!),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildItemsSection(FulfillmentOrder order) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Items (${order.items.length})',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            if (order.items.isEmpty)
              Text('${order.itemsCount} items', style: TextStyle(color: Colors.grey[600]))
            else
              ...order.items.map((item) => _buildItemRow(item)),
          ],
        ),
      ),
    );
  }

  Widget _buildItemRow(FulfillmentOrderItem item) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.productName,
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
                if (item.productSku != null)
                  Text(
                    'SKU: ${item.productSku}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('x${item.quantity}', style: const TextStyle(fontWeight: FontWeight.w600)),
              Text(
                '\$${item.price.toStringAsFixed(2)}',
                style: TextStyle(color: Colors.grey[600], fontSize: 13),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildHoldSection(FulfillmentOrder order) {
    return Card(
      color: Colors.orange.shade50,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.pause_circle_outline, color: Colors.orange[700]),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('On Hold', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.orange[700])),
                  const SizedBox(height: 4),
                  Text(order.holdReason!, style: TextStyle(color: Colors.orange[800])),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoSection(FulfillmentOrder order) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Order Info', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            _infoRow('Created', _formatDateTime(order.createdAt)),
            _infoRow('Marketplace', order.marketplace),
            _infoRow('Status', order.status),
            _infoRow('Priority', order.priority),
            if (order.locationName != null) _infoRow('Location', order.locationName!),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          SizedBox(
            width: 100,
            child: Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 13)),
          ),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 13))),
        ],
      ),
    );
  }

  Widget _buildActionBar(FulfillmentOrder order) {
    final listNotifier = ref.read(orderListProvider.notifier);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 4, offset: const Offset(0, -2)),
        ],
      ),
      child: Row(
        children: [
          // Assign button (if pending)
          if (order.status == 'pending')
            Expanded(
              child: ElevatedButton.icon(
                onPressed: () => _showAssignDialog(order.id, listNotifier),
                icon: const Icon(Icons.assignment_ind),
                label: const Text('Assign'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.deepPurple,
                  foregroundColor: Colors.white,
                ),
              ),
            ),

          // Hold button (if not on hold and not shipped)
          if (order.status != 'on_hold' && order.status != 'shipped') ...[
            if (order.status == 'pending') const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _showHoldDialog(order.id, listNotifier),
                icon: const Icon(Icons.pause_circle_outline),
                label: const Text('Hold'),
                style: OutlinedButton.styleFrom(foregroundColor: Colors.orange),
              ),
            ),
          ],

          // Release button (if on hold)
          if (order.status == 'on_hold')
            Expanded(
              child: ElevatedButton.icon(
                onPressed: () => listNotifier.releaseOrder(order.id),
                icon: const Icon(Icons.play_circle_outline),
                label: const Text('Release'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                ),
              ),
            ),
        ],
      ),
    );
  }

  void _showAssignDialog(String orderId, OrderListNotifier notifier) {
    final locationController = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Assign Order'),
        content: TextField(
          controller: locationController,
          decoration: const InputDecoration(
            labelText: 'Location ID',
            hintText: 'Enter fulfillment location',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final locationId = locationController.text.trim();
              if (locationId.isNotEmpty) {
                Navigator.of(ctx).pop();
                notifier.assignOrder(orderId, locationId);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.deepPurple),
            child: const Text('Assign'),
          ),
        ],
      ),
    );
  }

  void _showHoldDialog(String orderId, OrderListNotifier notifier) {
    final reasonController = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hold Order'),
        content: TextField(
          controller: reasonController,
          decoration: const InputDecoration(
            labelText: 'Reason',
            hintText: 'Why is this order being held?',
          ),
          maxLines: 3,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final reason = reasonController.text.trim();
              if (reason.isNotEmpty) {
                Navigator.of(ctx).pop();
                notifier.holdOrder(orderId, reason);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.orange),
            child: const Text('Hold'),
          ),
        ],
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w600),
      ),
    );
  }

  Widget _priorityBadge(String priority) {
    if (priority == 'normal' || priority == 'standard') return const SizedBox.shrink();

    final Color color;
    switch (priority) {
      case 'urgent':
        color = Colors.red;
        break;
      case 'high':
        color = Colors.orange;
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
        priority.toUpperCase(),
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
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        marketplace,
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }

  String _formatDateTime(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} '
        '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }
}
