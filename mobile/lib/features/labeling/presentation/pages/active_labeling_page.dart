import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/labeling_models.dart';
import '../providers/labeling_provider.dart';

class ActiveLabelingPage extends ConsumerStatefulWidget {
  final String workOrderId;

  const ActiveLabelingPage({super.key, required this.workOrderId});

  @override
  ConsumerState<ActiveLabelingPage> createState() => _ActiveLabelingPageState();
}

class _ActiveLabelingPageState extends ConsumerState<ActiveLabelingPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(activeLabelingProvider.notifier).load(widget.workOrderId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(activeLabelingProvider);
    final notifier = ref.read(activeLabelingProvider.notifier);

    ref.listen<ActiveLabelingState>(activeLabelingProvider, (prev, next) {
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

    final wo = state.workOrder;

    return Scaffold(
      appBar: AppBar(
        title: Text(wo?.workOrderNumber ?? 'Labeling'),
        backgroundColor: Colors.brown,
        foregroundColor: Colors.white,
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/labeling')),
        actions: [
          if (wo != null && wo.canComplete)
            TextButton(
              onPressed: () => notifier.markAllLabeled(),
              child: const Text('Label All', style: TextStyle(color: Colors.white)),
            ),
        ],
      ),
      body: state.isLoading && wo == null
          ? const Center(child: CircularProgressIndicator())
          : wo == null
              ? _buildError(state, notifier)
              : _buildBody(wo, notifier),
      bottomNavigationBar: wo != null && wo.canComplete ? _buildBottomBar(wo, notifier) : null,
    );
  }

  Widget _buildError(ActiveLabelingState state, ActiveLabelingNotifier notifier) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(state.error ?? 'Work order not found'),
          const SizedBox(height: 16),
          ElevatedButton(onPressed: () => notifier.load(widget.workOrderId), child: const Text('Retry')),
        ],
      ),
    );
  }

  Widget _buildBody(LabelingWorkOrder wo, ActiveLabelingNotifier notifier) {
    return RefreshIndicator(
      onRefresh: () => notifier.load(wo.id),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildHeader(wo),
          if (wo.isOpen) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => notifier.start(),
                icon: const Icon(Icons.play_arrow),
                label: const Text('Start Work Order'),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.brown, foregroundColor: Colors.white),
              ),
            ),
          ],
          const SizedBox(height: 16),
          ...wo.items.map((item) => _buildItemCard(item, notifier)),
        ],
      ),
    );
  }

  Widget _buildHeader(LabelingWorkOrder wo) {
    final p = wo.progress;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Status: ${wo.status}', style: TextStyle(color: Colors.grey[700])),
            if (wo.location != null) Text('Location: ${wo.location!.name}', style: TextStyle(color: Colors.grey[700])),
            const SizedBox(height: 12),
            LinearProgressIndicator(
              value: p.progressPercent,
              backgroundColor: Colors.grey[200],
              color: wo.isCompleted ? Colors.green : Colors.brown,
              minHeight: 6,
            ),
            const SizedBox(height: 6),
            Text('${p.shelved} shelved · ${p.labeled} labeled · ${p.pending} pending · ${p.skipped} skipped'),
          ],
        ),
      ),
    );
  }

  Widget _buildItemCard(LabelingItem item, ActiveLabelingNotifier notifier) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(item.product?.name ?? 'Item', style: const TextStyle(fontWeight: FontWeight.w600))),
                _itemStatusBadge(item.status),
              ],
            ),
            if (item.product?.sku != null) Text('SKU: ${item.product!.sku}', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            Text('Qty: ${item.quantity}', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            if (item.bin != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    Icon(Icons.shelves, size: 14, color: Colors.brown[400]),
                    const SizedBox(width: 4),
                    Text('Bin: ${item.bin!.display}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500)),
                    if (item.binVerified) ...[
                      const SizedBox(width: 6),
                      const Icon(Icons.verified, size: 14, color: Colors.green),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 8),
            _buildItemActions(item, notifier),
          ],
        ),
      ),
    );
  }

  Widget _buildItemActions(LabelingItem item, ActiveLabelingNotifier notifier) {
    if (item.isShelved || item.isSkipped) {
      return Align(
        alignment: Alignment.centerRight,
        child: Text(
          item.isShelved ? 'Shelved' : 'Skipped${item.skipReason != null ? ': ${item.skipReason}' : ''}',
          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
        ),
      );
    }

    return Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        TextButton(
          onPressed: () => _promptSkip(item, notifier),
          child: const Text('Skip', style: TextStyle(fontSize: 13)),
        ),
        const SizedBox(width: 4),
        if (item.isPending)
          ElevatedButton(
            onPressed: () => notifier.markItemLabeled(item.id),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.brown, padding: const EdgeInsets.symmetric(horizontal: 14)),
            child: const Text('Label', style: TextStyle(fontSize: 13)),
          ),
        if (item.isLabelPrinted)
          ElevatedButton.icon(
            onPressed: () => _promptScanBin(item, notifier),
            icon: const Icon(Icons.qr_code_scanner, size: 16),
            label: const Text('Scan Bin', style: TextStyle(fontSize: 13)),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(horizontal: 12)),
          ),
      ],
    );
  }

  Widget _itemStatusBadge(String status) {
    final Color color;
    final String label;
    switch (status) {
      case 'pending':
        color = Colors.grey;
        label = 'Pending';
        break;
      case 'label_printed':
        color = Colors.orange;
        label = 'Labeled';
        break;
      case 'shelved':
        color = Colors.green;
        label = 'Shelved';
        break;
      case 'skipped':
        color = Colors.red;
        label = 'Skipped';
        break;
      default:
        color = Colors.grey;
        label = status;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }

  Widget _buildBottomBar(LabelingWorkOrder wo, ActiveLabelingNotifier notifier) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: SizedBox(
          height: 48,
          child: ElevatedButton.icon(
            onPressed: () => _confirmComplete(wo, notifier),
            icon: const Icon(Icons.done_all),
            label: const Text('Complete Work Order'),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
          ),
        ),
      ),
    );
  }

  void _promptSkip(LabelingItem item, ActiveLabelingNotifier notifier) {
    final controller = TextEditingController();
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Skip item'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Reason', border: OutlineInputBorder()),
          autofocus: true,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final reason = controller.text.trim();
              if (reason.isEmpty) return;
              Navigator.of(dialogContext).pop();
              notifier.skipItem(item.id, reason);
            },
            child: const Text('Skip'),
          ),
        ],
      ),
    );
  }

  void _promptScanBin(LabelingItem item, ActiveLabelingNotifier notifier) {
    final controller = TextEditingController();
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Scan bin barcode'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Bin barcode', border: OutlineInputBorder()),
          autofocus: true,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final barcode = controller.text.trim();
              if (barcode.isEmpty) return;
              Navigator.of(dialogContext).pop();
              notifier.scanBin(item.id, barcode);
            },
            child: const Text('Shelve'),
          ),
        ],
      ),
    );
  }

  void _confirmComplete(LabelingWorkOrder wo, ActiveLabelingNotifier notifier) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Complete work order?'),
        content: Text('Mark ${wo.workOrderNumber} as completed.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              notifier.complete();
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
            child: const Text('Complete'),
          ),
        ],
      ),
    );
  }
}
