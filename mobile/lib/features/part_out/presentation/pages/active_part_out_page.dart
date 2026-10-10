import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../data/models/part_out_models.dart';
import '../providers/part_out_provider.dart';

class ActivePartOutPage extends ConsumerStatefulWidget {
  final String workOrderId;

  const ActivePartOutPage({super.key, required this.workOrderId});

  @override
  ConsumerState<ActivePartOutPage> createState() => _ActivePartOutPageState();
}

class _ActivePartOutPageState extends ConsumerState<ActivePartOutPage> {
  MobileScannerController? _scannerController;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(activePartOutProvider.notifier).loadWorkOrder(widget.workOrderId);
    });
  }

  @override
  void dispose() {
    _scannerController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(activePartOutProvider);
    final notifier = ref.read(activePartOutProvider.notifier);

    // Listen for success/error messages
    ref.listen<ActivePartOutState>(activePartOutProvider, (prev, next) {
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
        title: Text(state.workOrder?.workOrderNumber ?? 'Part-Out'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/part-out'),
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
      body: state.isLoading && state.workOrder == null
          ? const Center(child: CircularProgressIndicator())
          : state.workOrder == null
              ? _buildError(state.error)
              : _buildWorkOrderView(state, notifier),
    );
  }

  Widget _buildError(String? error) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(error ?? 'Failed to load work order'),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => ref.read(activePartOutProvider.notifier).loadWorkOrder(widget.workOrderId),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildWorkOrderView(ActivePartOutState state, ActivePartOutNotifier notifier) {
    final wo = state.workOrder!;

    return Column(
      children: [
        // Barcode scanner (collapsible)
        if (state.scannerActive) _buildScanner(state, notifier),

        // Source product header
        _buildSourceProductHeader(wo),

        // Progress row
        _buildProgressRow(wo.progress),

        // Items list
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: wo.items.length,
            itemBuilder: (context, index) {
              final item = wo.items[index];
              return _buildItemCard(item, wo, notifier);
            },
          ),
        ),

        // Bottom action bar
        _buildBottomBar(wo, notifier, state.isLoading),
      ],
    );
  }

  Widget _buildScanner(ActivePartOutState state, ActivePartOutNotifier notifier) {
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
          if (state.currentScanItemId != null)
            Positioned(
              bottom: 8,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.teal,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Text(
                    'Scan barcode to verify and extract component',
                    style: TextStyle(color: Colors.white, fontSize: 12),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  void _handleBarcodeScan(String barcode, ActivePartOutNotifier notifier) async {
    final state = ref.read(activePartOutProvider);
    final wo = state.workOrder;
    if (wo == null) return;

    // If scanning for a specific item, verify against that item
    if (state.currentScanItemId != null) {
      final result = await notifier.scanVerify(state.currentScanItemId!, barcode);
      if (result.matched) {
        await notifier.extractItem(state.currentScanItemId!, barcode: barcode);
        notifier.setCurrentScanItem(null);
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Barcode does not match. Expected: ${result.expected ?? "unknown"}'),
              backgroundColor: Colors.red,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      }
      return;
    }

    // Auto-match: find the pending item that matches this barcode
    for (final item in wo.items.where((i) => i.isPending)) {
      final result = await notifier.scanVerify(item.id, barcode);
      if (result.matched) {
        await notifier.extractItem(item.id, barcode: barcode);
        return;
      }
    }

    // No match found
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('No pending component matches barcode: $barcode'),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Widget _buildSourceProductHeader(PartOutWorkOrder wo) {
    final source = wo.sourceProduct;
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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Source product image
          if (source?.imageUrl != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.network(
                source!.imageUrl!,
                width: 60,
                height: 60,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => Container(
                  width: 60,
                  height: 60,
                  color: Colors.grey[200],
                  child: Icon(Icons.image, color: Colors.grey[400]),
                ),
              ),
            )
          else
            Container(
              width: 60,
              height: 60,
              decoration: BoxDecoration(
                color: Colors.grey[200],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(Icons.content_cut, color: Colors.grey[400]),
            ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  source?.name ?? 'Unknown Product',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                if (source?.sku != null)
                  Text(
                    'SKU: ${source!.sku}',
                    style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                  ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text(
                      'Cost: \$${wo.sourceTotalCost.toStringAsFixed(2)}',
                      style: TextStyle(fontSize: 12, color: Colors.grey[700]),
                    ),
                    const SizedBox(width: 16),
                    Text(
                      'Qty: ${wo.sourceQuantity}',
                      style: TextStyle(fontSize: 12, color: Colors.grey[700]),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProgressRow(PartOutProgress progress) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.teal.shade50,
      child: Column(
        children: [
          Row(
            children: [
              _progressChip(Icons.check_circle, '${progress.extracted}', 'Extracted', Colors.green),
              const SizedBox(width: 12),
              _progressChip(Icons.remove_circle, '${progress.skipped}', 'Skipped', Colors.grey),
              const SizedBox(width: 12),
              _progressChip(Icons.pending, '${progress.pending}', 'Pending', Colors.orange),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: progress.progressPercent,
              backgroundColor: Colors.grey[200],
              color: progress.pending == 0 ? Colors.green : Colors.teal,
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

  Widget _buildItemCard(PartOutItem item, PartOutWorkOrder wo, ActivePartOutNotifier notifier) {
    final Color statusColor;
    final IconData statusIcon;
    switch (item.extractionStatus) {
      case 'extracted':
        statusColor = Colors.green;
        statusIcon = Icons.check_circle;
        break;
      case 'skipped':
        statusColor = Colors.grey;
        statusIcon = Icons.remove_circle;
        break;
      default:
        statusColor = Colors.orange;
        statusIcon = Icons.radio_button_unchecked;
    }

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      color: item.isExtracted
          ? Colors.green.shade50
          : item.isSkipped
              ? Colors.grey.shade100
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
                        item.name,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),
                      // Part number, manufacturer, category, qty
                      Wrap(
                        spacing: 12,
                        children: [
                          if (item.partNumber != null)
                            Text(
                              'Part: ${item.partNumber}',
                              style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                            ),
                          if (item.manufacturer != null)
                            Text(
                              item.manufacturer!,
                              style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                            ),
                          Text(
                            'Qty: ${item.quantity}',
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                      if (item.category != null) ...[
                        const SizedBox(height: 4),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: Colors.teal.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            item.category!,
                            style: const TextStyle(fontSize: 11, color: Colors.teal),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                // Notes button
                IconButton(
                  icon: Icon(
                    Icons.note_add_outlined,
                    size: 20,
                    color: item.extractionNotes != null ? Colors.teal : Colors.grey[400],
                  ),
                  tooltip: 'Notes',
                  onPressed: () => _showNotesDialog(item, notifier),
                ),
              ],
            ),

            // Extracted status info
            if (item.isExtracted) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const SizedBox(width: 36),
                  if (item.extractedAt != null)
                    Text(
                      'Extracted at ${_formatTime(item.extractedAt!)}',
                      style: TextStyle(fontSize: 12, color: Colors.green[700]),
                    ),
                  if (item.barcodeVerified) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.green[100],
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.verified, size: 12, color: Colors.green[700]),
                          const SizedBox(width: 4),
                          Text(
                            'Barcode Verified',
                            style: TextStyle(fontSize: 10, color: Colors.green[700], fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ],

            // Skipped status info
            if (item.isSkipped && item.extractionNotes != null) ...[
              const SizedBox(height: 8),
              Padding(
                padding: const EdgeInsets.only(left: 36),
                child: Text(
                  'Skipped: ${item.extractionNotes}',
                  style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                ),
              ),
            ],

            // Action buttons (only for pending items when work order is in progress)
            if (item.isPending && wo.isInProgress) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  // Scan to verify + extract
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        notifier.setCurrentScanItem(item.id);
                        if (!ref.read(activePartOutProvider).scannerActive) {
                          notifier.toggleScanner();
                        }
                      },
                      icon: const Icon(Icons.qr_code_scanner, size: 18),
                      label: const Text('Scan', style: TextStyle(fontSize: 13)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.teal,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // Extract without scan
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => notifier.extractItem(item.id),
                      icon: const Icon(Icons.check, size: 18),
                      label: const Text('Extract', style: TextStyle(fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // Skip
                  SizedBox(
                    width: 80,
                    child: OutlinedButton(
                      onPressed: () => _showSkipDialog(item, notifier),
                      style: OutlinedButton.styleFrom(foregroundColor: Colors.grey[700]),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Icon(Icons.skip_next, size: 16),
                          SizedBox(width: 2),
                          Text('Skip', style: TextStyle(fontSize: 13)),
                        ],
                      ),
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

  void _showSkipDialog(PartOutItem item, ActivePartOutNotifier notifier) {
    final reasonController = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Skip Component'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Item: ${item.name}', style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                labelText: 'Reason',
                hintText: 'e.g., Damaged, Missing, Not salvageable',
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
              notifier.skipItem(item.id, reason);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.grey[700]),
            child: const Text('Skip'),
          ),
        ],
      ),
    );
  }

  void _showNotesDialog(PartOutItem item, ActivePartOutNotifier notifier) {
    final notesController = TextEditingController(text: item.extractionNotes ?? '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Extraction Notes'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Item: ${item.name}', style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            TextField(
              controller: notesController,
              decoration: const InputDecoration(
                labelText: 'Notes',
                hintText: 'Add notes about extraction...',
                border: OutlineInputBorder(),
              ),
              maxLines: 4,
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
              final notes = notesController.text.trim();
              Navigator.pop(ctx);
              notifier.updateItemNotes(item.id, notes);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.teal),
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  void _showCompleteConfirmation(ActivePartOutNotifier notifier) {
    final wo = ref.read(activePartOutProvider).workOrder;
    if (wo == null) return;

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Complete Work Order'),
        content: Text(
          'This will create ${wo.progress.extracted} component products and update inventory. Continue?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final success = await notifier.completeWorkOrder();
              if (success && mounted) {
                context.go('/part-out');
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            child: const Text('Complete'),
          ),
        ],
      ),
    );
  }

  void _showCancelConfirmation(ActivePartOutNotifier notifier) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Work Order'),
        content: const Text(
          'Are you sure? All extraction progress will be lost.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Go Back'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final success = await notifier.cancelWorkOrder();
              if (success && mounted) {
                context.go('/part-out');
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Cancel Work Order'),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomBar(PartOutWorkOrder wo, ActivePartOutNotifier notifier, bool isLoading) {
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
            // Cancel button (secondary)
            if (wo.canCancel)
              TextButton(
                onPressed: isLoading ? null : () => _showCancelConfirmation(notifier),
                style: TextButton.styleFrom(foregroundColor: Colors.red),
                child: const Text('Cancel'),
              ),
            const SizedBox(width: 8),
            // Primary action
            if (wo.isDraft)
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: isLoading ? null : () => notifier.startWorkOrder(),
                  icon: const Icon(Icons.play_arrow),
                  label: const Text('Start Work Order'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.teal,
                    minimumSize: const Size(0, 48),
                  ),
                ),
              ),
            if (wo.isInProgress && wo.progress.pending > 0)
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: null,
                  icon: const Icon(Icons.check_circle),
                  label: Text('${wo.progress.pending} items remaining'),
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size(0, 48),
                  ),
                ),
              ),
            if (wo.isInProgress && wo.progress.pending == 0)
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: isLoading ? null : () => _showCompleteConfirmation(notifier),
                  icon: const Icon(Icons.check_circle),
                  label: const Text('Complete Work Order'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    minimumSize: const Size(0, 48),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _formatTime(DateTime dt) {
    final hour = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
    final period = dt.hour >= 12 ? 'PM' : 'AM';
    final minute = dt.minute.toString().padLeft(2, '0');
    return '$hour:$minute $period';
  }
}
