import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/ship_models.dart';
import '../../../../data/services/print_service.dart';
import '../../../../core/providers/shared_providers.dart';
import '../providers/ship_provider.dart';

class ShipmentDetailPage extends ConsumerStatefulWidget {
  final String shipmentId;

  const ShipmentDetailPage({super.key, required this.shipmentId});

  @override
  ConsumerState<ShipmentDetailPage> createState() => _ShipmentDetailPageState();
}

class _ShipmentDetailPageState extends ConsumerState<ShipmentDetailPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(shipDetailProvider.notifier).loadShipment(widget.shipmentId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(shipDetailProvider);
    final notifier = ref.read(shipDetailProvider.notifier);

    // Listen for success/error messages
    ref.listen<ShipDetailState>(shipDetailProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.successMessage!),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
            duration: const Duration(seconds: 2),
          ),
        );
        notifier.clearMessages();
      }
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
          ),
        );
        notifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: Text(state.shipment?.shipmentNumber ?? 'ShipmentDetail Details'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/shipping'),
        ),
      ),
      body: state.isLoading && state.shipment == null
          ? const Center(child: CircularProgressIndicator())
          : state.shipment == null
              ? _buildError(state.error)
              : _buildDetailView(state, notifier),
    );
  }

  Widget _buildError(String? error) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(error ?? 'Failed to load shipment'),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => ref.read(shipDetailProvider.notifier).loadShipment(widget.shipmentId),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailView(ShipDetailState state, ShipDetailNotifier notifier) {
    final shipment = state.shipment!;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ShipmentDetail header
          _buildShipmentHeader(shipment),

          // Order info
          _buildOrderInfo(shipment),

          // Items list
          _buildItemsList(shipment),

          // Label section
          if (shipment.labelUrl != null) _buildLabelSection(shipment),

          // Action buttons
          _buildActions(shipment, state, notifier),

          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildShipmentHeader(ShipmentDetail shipment) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (shipment.shipmentNumber != null)
                      Text(
                        shipment.shipmentNumber!,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                      ),
                    const SizedBox(height: 4),
                    if (shipment.carrier != null)
                      Text(
                        shipment.carrier!,
                        style: TextStyle(color: Colors.grey[600], fontSize: 14),
                      ),
                  ],
                ),
              ),
              _statusBadge(shipment.status),
            ],
          ),
          if (shipment.trackingNumber != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey[200]!),
              ),
              child: Row(
                children: [
                  const Icon(Icons.confirmation_number_outlined, size: 18, color: Colors.indigo),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Tracking Number',
                          style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                        ),
                        Text(
                          shipment.trackingNumber!,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildOrderInfo(ShipmentDetail shipment) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Information',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  if (shipment.marketplaceOrderId != null)
                    _infoRow('Order ID', shipment.marketplaceOrderId!),
                  if (shipment.buyerName != null)
                    _infoRow('Buyer', shipment.buyerName!),
                  if (shipment.shippingAddress != null)
                    _infoRow('Address', shipment.shippingAddress!),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(
              label,
              style: TextStyle(fontSize: 13, color: Colors.grey[600]),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItemsList(ShipmentDetail shipment) {
    if (shipment.items.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Items (${shipment.items.length})',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          ...shipment.items.map((item) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.title,
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 12,
                          children: [
                            if (item.sku != null)
                              Text(
                                'SKU: ${item.sku}',
                                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                              ),
                            Text(
                              'Qty: ${item.quantity}',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          )),
        ],
      ),
    );
  }

  Widget _buildLabelSection(ShipmentDetail shipment) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Shipping Label',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          Card(
            color: Colors.blue.shade50,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  const Icon(Icons.receipt_long, color: Colors.blue, size: 32),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Label available',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Ready to print',
                          style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.print_outlined, color: Colors.blue),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => _printLabel(shipment),
              icon: const Icon(Icons.print),
              label: const Text('Print Label'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.blue,
                foregroundColor: Colors.white,
                minimumSize: const Size(0, 44),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _printLabel(ShipmentDetail shipment) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final printService = PrintService(apiClient);
      await printService.printProductLabel(shipment.id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Label sent to printer'),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to print label: $e'),
            backgroundColor: Colors.red,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    }
  }

  Widget _buildActions(ShipmentDetail shipment, ShipDetailState state, ShipDetailNotifier notifier) {
    final hasActions = shipment.status == 'ready_to_ship' || shipment.labelUrl != null;
    if (!hasActions) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          if (shipment.status == 'ready_to_ship')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: state.isLoading
                    ? null
                    : () async {
                        await notifier.confirmShipment();
                      },
                icon: const Icon(Icons.check_circle),
                label: const Text('Confirm Shipped'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.indigo,
                  minimumSize: const Size(0, 48),
                ),
              ),
            ),
          if (shipment.status == 'ready_to_ship' && shipment.labelUrl != null)
            const SizedBox(height: 12),
          if (shipment.labelUrl != null && shipment.status != 'shipped' && shipment.status != 'delivered')
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: state.isLoading
                    ? null
                    : () async {
                        final confirmed = await showDialog<bool>(
                          context: context,
                          builder: (ctx) => AlertDialog(
                            title: const Text('Void Label'),
                            content: const Text(
                              'Are you sure you want to void this shipping label? This action cannot be undone.',
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.pop(ctx, false),
                                child: const Text('Cancel'),
                              ),
                              ElevatedButton(
                                onPressed: () => Navigator.pop(ctx, true),
                                style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
                                child: const Text('Void Label'),
                              ),
                            ],
                          ),
                        );
                        if (confirmed == true) {
                          await notifier.voidLabel();
                        }
                      },
                icon: const Icon(Icons.cancel_outlined),
                label: const Text('Void Label'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red,
                  minimumSize: const Size(0, 48),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _statusBadge(String status) {
    final Color color;
    final String label;
    switch (status) {
      case 'ready_to_ship':
        color = Colors.indigo;
        label = 'Ready to Ship';
        break;
      case 'shipped':
        color = Colors.blue;
        label = 'Shipped';
        break;
      case 'in_transit':
        color = Colors.orange;
        label = 'In Transit';
        break;
      case 'delivered':
        color = Colors.green;
        label = 'Delivered';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
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
