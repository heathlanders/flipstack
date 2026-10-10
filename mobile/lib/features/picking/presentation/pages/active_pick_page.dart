import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../data/models/pick_models.dart';
import '../providers/pick_provider.dart';

class ActivePickPage extends ConsumerStatefulWidget {
  final String orderId;

  const ActivePickPage({super.key, required this.orderId});

  @override
  ConsumerState<ActivePickPage> createState() => _ActivePickPageState();
}

class _ActivePickPageState extends ConsumerState<ActivePickPage> {
  MobileScannerController? _scannerController;
  String? _pendingScanItemId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(activePickProvider.notifier).loadOrder(widget.orderId);
    });
  }

  @override
  void dispose() {
    _scannerController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(activePickProvider);
    final notifier = ref.read(activePickProvider.notifier);

    // Listen for success/error messages
    ref.listen<ActivePickState>(activePickProvider, (prev, next) {
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
        title: Text(state.order?.marketplaceOrderId ?? 'Picking'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/picking'),
        ),
        actions: [
          // Scanner toggle
          IconButton(
            icon: Icon(state.scannerActive ? Icons.qr_code : Icons.qr_code_scanner),
            tooltip: state.scannerActive ? 'Close scanner' : 'Open scanner',
            onPressed: () => notifier.toggleScanner(),
          ),
        ],
      ),
      body: state.isLoading && state.order == null
          ? const Center(child: CircularProgressIndicator())
          : state.order == null
              ? _buildError(state.error)
              : _buildPickingView(state, notifier),
    );
  }

  Widget _buildError(String? error) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(error ?? 'Failed to load order'),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => ref.read(activePickProvider.notifier).loadOrder(widget.orderId),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildPickingView(ActivePickState state, ActivePickNotifier notifier) {
    final order = state.order!;

    return Column(
      children: [
        // Barcode scanner (collapsible)
        if (state.scannerActive) _buildScanner(notifier),

        // Order header with progress
        _buildOrderHeader(order),

        // Items list
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: order.items.length,
            itemBuilder: (context, index) {
              final item = order.items[index];
              return _buildItemCard(item, order, notifier);
            },
          ),
        ),

        // Bottom action bar
        _buildBottomBar(order, notifier, state.isLoading),
      ],
    );
  }

  Widget _buildScanner(ActivePickNotifier notifier) {
    _scannerController ??= MobileScannerController(
      detectionSpeed: DetectionSpeed.normal,
      facing: CameraFacing.back,
    );

    return Container(
      height: 200,
      color: Colors.black,
      child: Stack(
        children: [
          MobileScanner(
            controller: _scannerController!,
            onDetect: (capture) {
              final barcode = capture.barcodes.firstOrNull?.rawValue;
              if (barcode != null) {
                _handleBarcodeScan(barcode, notifier);
              }
            },
          ),
          // Scanner overlay
          Center(
            child: Container(
              width: 250,
              height: 100,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white, width: 2),
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          // Close button
          Positioned(
            top: 8,
            right: 8,
            child: IconButton(
              icon: const Icon(Icons.close, color: Colors.white),
              onPressed: () {
                _scannerController?.dispose();
                _scannerController = null;
                notifier.toggleScanner();
              },
            ),
          ),
          if (_pendingScanItemId != null)
            Positioned(
              bottom: 8,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.blue,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Text(
                    'Scan item barcode to verify and pick',
                    style: TextStyle(color: Colors.white, fontSize: 12),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  void _handleBarcodeScan(String barcode, ActivePickNotifier notifier) async {
    final state = ref.read(activePickProvider);
    final order = state.order;
    if (order == null) return;

    // If scanning for a specific item, verify against that item
    if (_pendingScanItemId != null) {
      final result = await notifier.scanVerify(_pendingScanItemId!, barcode);
      if (result.matched) {
        await notifier.pickItem(_pendingScanItemId!, barcode: barcode);
        setState(() => _pendingScanItemId = null);
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Barcode does not match. Expected SKU: ${result.expectedSku}'),
              backgroundColor: Colors.red,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      }
      return;
    }

    // Auto-match: find the pending item that matches this barcode
    for (final item in order.items.where((i) => i.isPending)) {
      final result = await notifier.scanVerify(item.id, barcode);
      if (result.matched) {
        await notifier.pickItem(item.id, barcode: barcode);
        return;
      }
    }

    // No match found
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('No pending item matches barcode: $barcode'),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Widget _buildOrderHeader(PickOrder order) {
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
          // Marketplace + priority
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.grey[100],
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  order.marketplace.toUpperCase(),
                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
              if (order.isUrgent || order.isHighPriority) ...[
                const SizedBox(width: 8),
                Icon(
                  Icons.priority_high,
                  size: 18,
                  color: order.isUrgent ? Colors.red : Colors.orange,
                ),
                Text(
                  order.priority.toUpperCase(),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: order.isUrgent ? Colors.red : Colors.orange,
                  ),
                ),
              ],
              const Spacer(),
              if (order.location != null)
                Row(
                  children: [
                    Icon(Icons.location_on, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(order.location!.name, style: TextStyle(color: Colors.grey[600])),
                  ],
                ),
            ],
          ),
          const SizedBox(height: 12),

          // Progress
          Row(
            children: [
              _progressChip(Icons.check_circle, '${order.pickProgress.picked}', 'Picked', Colors.green),
              const SizedBox(width: 12),
              _progressChip(Icons.cancel, '${order.pickProgress.shorted}', 'Short', Colors.red),
              const SizedBox(width: 12),
              _progressChip(Icons.pending, '${order.pickProgress.pending}', 'Pending', Colors.orange),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: order.pickProgress.progressPercent,
              backgroundColor: Colors.grey[200],
              color: order.allItemsProcessed ? Colors.green : Colors.deepPurple,
              minHeight: 8,
            ),
          ),
        ],
      ),
    );
  }

  Widget _progressChip(IconData icon, String count, String label, Color color) {
    return Row(
      children: [
        Icon(icon, size: 16, color: color),
        const SizedBox(width: 4),
        Text(
          '$count $label',
          style: TextStyle(fontSize: 13, color: color, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }

  Widget _buildItemCard(PickItem item, PickOrder order, ActivePickNotifier notifier) {
    final Color statusColor;
    final IconData statusIcon;
    switch (item.pickStatus) {
      case 'picked':
        statusColor = Colors.green;
        statusIcon = Icons.check_circle;
        break;
      case 'short':
        statusColor = Colors.red;
        statusIcon = Icons.cancel;
        break;
      default:
        statusColor = Colors.orange;
        statusIcon = Icons.radio_button_unchecked;
    }

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      color: item.isPicked
          ? Colors.green.shade50
          : item.isShort
              ? Colors.red.shade50
              : null,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status icon + Title
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(statusIcon, color: statusColor, size: 24),
                const SizedBox(width: 12),
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
                      // SKU + Location + Qty
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
                          if (item.location != null)
                            Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.location_on, size: 14, color: Colors.grey[600]),
                                Text(
                                  item.locationCode,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Colors.blue[700],
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                // Item image
                if (item.imageUrl != null)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: Image.network(
                      item.imageUrl!,
                      width: 48,
                      height: 48,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        width: 48,
                        height: 48,
                        color: Colors.grey[200],
                        child: Icon(Icons.image, color: Colors.grey[400]),
                      ),
                    ),
                  ),
              ],
            ),

            // Action buttons (only for pending items when order is being picked)
            if (item.isPending && order.isPicking) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  // Scan to verify + pick
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        setState(() => _pendingScanItemId = item.id);
                        ref.read(activePickProvider.notifier)
                          ..clearMessages();
                        if (!ref.read(activePickProvider).scannerActive) {
                          ref.read(activePickProvider.notifier).toggleScanner();
                        }
                      },
                      icon: const Icon(Icons.qr_code_scanner, size: 18),
                      label: const Text('Scan', style: TextStyle(fontSize: 13)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.deepPurple,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // Pick without scan
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => notifier.pickItem(item.id),
                      icon: const Icon(Icons.check, size: 18),
                      label: const Text('Pick', style: TextStyle(fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // Short
                  SizedBox(
                    width: 80,
                    child: OutlinedButton(
                      onPressed: () => _showShortDialog(item, notifier),
                      style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                      child: const Text('Short', style: TextStyle(fontSize: 13)),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  void _showShortDialog(PickItem item, ActivePickNotifier notifier) {
    final reasonController = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Mark Item Short'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Item: ${item.title}', style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                labelText: 'Reason',
                hintText: 'e.g., Out of stock, Damaged, Cannot locate',
                border: OutlineInputBorder(),
              ),
              maxLines: 2,
              autofocus: true,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final reason = reasonController.text.trim();
              if (reason.isEmpty) return;
              Navigator.pop(ctx);
              notifier.shortItem(item.id, reason);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Mark Short'),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomBar(PickOrder order, ActivePickNotifier notifier, bool isLoading) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.2),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Row(
          children: [
            if (order.isAssigned)
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: isLoading ? null : () => notifier.startPicking(),
                  icon: const Icon(Icons.play_arrow),
                  label: const Text('Start Picking'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.deepPurple,
                    minimumSize: const Size(0, 48),
                  ),
                ),
              ),
            if (order.isPicking && order.allItemsProcessed)
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: isLoading
                      ? null
                      : () async {
                          final success = await notifier.completePicking();
                          if (success && mounted) {
                            context.go('/picking');
                          }
                        },
                  icon: const Icon(Icons.check_circle),
                  label: const Text('Complete Pick'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    minimumSize: const Size(0, 48),
                  ),
                ),
              ),
            if (order.isPicking && !order.allItemsProcessed)
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: null,
                  icon: const Icon(Icons.check_circle),
                  label: Text('${order.pickProgress.pending} items remaining'),
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size(0, 48),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
