import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/receiving_models.dart';
import '../providers/receiving_provider.dart';

class ActiveReceivingPage extends ConsumerStatefulWidget {
  final String purchaseOrderId;

  const ActiveReceivingPage({super.key, required this.purchaseOrderId});

  @override
  ConsumerState<ActiveReceivingPage> createState() => _ActiveReceivingPageState();
}

class _ActiveReceivingPageState extends ConsumerState<ActiveReceivingPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(activeReceivingProvider.notifier).loadPurchaseOrder(widget.purchaseOrderId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(activeReceivingProvider);
    final notifier = ref.read(activeReceivingProvider.notifier);

    ref.listen<ActiveReceivingState>(activeReceivingProvider, (prev, next) {
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

    final po = state.purchaseOrder;

    return Scaffold(
      appBar: AppBar(
        title: Text(po?.poNumber ?? 'Receiving'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/receiving'),
        ),
      ),
      body: state.isLoading && po == null
          ? const Center(child: CircularProgressIndicator())
          : po == null
              ? _buildError(state, notifier)
              : _buildBody(po, notifier),
      bottomNavigationBar: po != null && po.canReceive
          ? _buildBottomBar(po, notifier)
          : null,
    );
  }

  Widget _buildError(ActiveReceivingState state, ActiveReceivingNotifier notifier) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(state.error ?? 'Purchase order not found'),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => notifier.loadPurchaseOrder(widget.purchaseOrderId),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(ReceivingPo po, ActiveReceivingNotifier notifier) {
    return RefreshIndicator(
      onRefresh: () => notifier.loadPurchaseOrder(po.id),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildHeader(po),
          const SizedBox(height: 16),
          ...po.items.map((item) => _buildItemCard(po, item, notifier)),
        ],
      ),
    );
  }

  Widget _buildHeader(ReceivingPo po) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (po.supplierName != null)
              Text(po.supplierName!, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Text('Status: ${po.status}', style: TextStyle(color: Colors.grey[700])),
            if (po.location != null) Text('Location: ${po.location!.name}', style: TextStyle(color: Colors.grey[700])),
            const SizedBox(height: 12),
            LinearProgressIndicator(
              value: po.progressPercent,
              backgroundColor: Colors.grey[200],
              color: po.isReceived ? Colors.green : Colors.indigo,
              minHeight: 6,
            ),
            const SizedBox(height: 6),
            Text('${po.totalQuantityReceived} of ${po.totalQuantityOrdered} units received'),
          ],
        ),
      ),
    );
  }

  Widget _buildItemCard(ReceivingPo po, ReceivingPoItem item, ActiveReceivingNotifier notifier) {
    final done = item.isFullyReceived;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(item.label, style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
                if (done)
                  const Icon(Icons.check_circle, color: Colors.green, size: 20),
              ],
            ),
            if (item.sku != null) Text('SKU: ${item.sku}', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            const SizedBox(height: 8),
            Row(
              children: [
                _qtyChip('Ordered', item.quantityOrdered, Colors.grey),
                const SizedBox(width: 8),
                _qtyChip('Received', item.quantityReceived, Colors.green),
                const SizedBox(width: 8),
                _qtyChip('Remaining', item.remainingQuantity, Colors.orange),
                const Spacer(),
                if (!done && po.canReceive)
                  ElevatedButton(
                    onPressed: () => _openReceiveSheet(po, item, notifier),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.indigo,
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                    ),
                    child: const Text('Receive', style: TextStyle(fontSize: 13)),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _qtyChip(String label, int value, Color color) {
    return Column(
      children: [
        Text('$value', style: TextStyle(fontWeight: FontWeight.bold, color: color)),
        Text(label, style: TextStyle(fontSize: 10, color: Colors.grey[600])),
      ],
    );
  }

  Widget _buildBottomBar(ReceivingPo po, ActiveReceivingNotifier notifier) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: SizedBox(
          height: 48,
          child: ElevatedButton.icon(
            onPressed: () => _confirmComplete(po, notifier),
            icon: const Icon(Icons.done_all),
            label: const Text('Receive All Remaining'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
          ),
        ),
      ),
    );
  }

  void _openReceiveSheet(ReceivingPo po, ReceivingPoItem item, ActiveReceivingNotifier notifier) {
    final qtyController = TextEditingController(text: item.remainingQuantity.toString());
    final barcodeController = TextEditingController();

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) {
        return Padding(
          padding: EdgeInsets.only(
            left: 16,
            right: 16,
            top: 16,
            bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 16,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Receive ${item.label}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Text('Remaining: ${item.remainingQuantity}', style: TextStyle(color: Colors.grey[600])),
              const SizedBox(height: 16),
              TextField(
                controller: qtyController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Quantity received', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: barcodeController,
                decoration: InputDecoration(
                  labelText: 'Scan / enter barcode (optional)',
                  border: const OutlineInputBorder(),
                  suffixIcon: item.barcode != null
                      ? IconButton(
                          icon: const Icon(Icons.qr_code_scanner),
                          tooltip: 'Verify expected barcode',
                          onPressed: () async {
                            final result = await notifier.scanVerify(item.id, item.barcode ?? '');
                            if (!sheetContext.mounted) return;
                            ScaffoldMessenger.of(sheetContext).showSnackBar(
                              SnackBar(
                                content: Text(result.matched ? 'Barcode matches' : 'Barcode does not match'),
                                backgroundColor: result.matched ? Colors.green : Colors.red,
                              ),
                            );
                          },
                        )
                      : null,
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: () async {
                    final qty = int.tryParse(qtyController.text.trim()) ?? 0;
                    if (qty <= 0) {
                      ScaffoldMessenger.of(sheetContext).showSnackBar(
                        const SnackBar(content: Text('Enter a valid quantity'), backgroundColor: Colors.red),
                      );
                      return;
                    }
                    final barcode = barcodeController.text.trim();
                    Navigator.of(sheetContext).pop();
                    await notifier.receiveItem(
                      item.id,
                      quantity: qty,
                      barcode: barcode.isEmpty ? null : barcode,
                      locationId: po.location?.id,
                    );
                  },
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.indigo, foregroundColor: Colors.white),
                  child: const Text('Confirm Receive'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _confirmComplete(ReceivingPo po, ActiveReceivingNotifier notifier) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Receive all remaining?'),
        content: Text('This will receive every outstanding unit on ${po.poNumber} in good condition.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              notifier.completePurchaseOrder();
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
            child: const Text('Receive All'),
          ),
        ],
      ),
    );
  }
}
