import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/return_models.dart';
import '../providers/return_provider.dart';

class ReturnDetailPage extends ConsumerStatefulWidget {
  final String returnId;

  const ReturnDetailPage({super.key, required this.returnId});

  @override
  ConsumerState<ReturnDetailPage> createState() => _ReturnDetailPageState();
}

class _ReturnDetailPageState extends ConsumerState<ReturnDetailPage> {
  String _selectedCondition = 'Like New';
  final _notesController = TextEditingController();
  bool _restockable = true;
  String? _selectedLocationId;

  static const _conditions = ['Like New', 'Good', 'Fair', 'Poor', 'Damaged'];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(returnDetailProvider.notifier).loadReturn(widget.returnId);
    });
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(returnDetailProvider);
    final notifier = ref.read(returnDetailProvider.notifier);

    // Listen for success/error messages
    ref.listen<ReturnDetailState>(returnDetailProvider, (prev, next) {
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
        title: Text(state.returnRequest?.rmaNumber ?? 'Return Details'),
        backgroundColor: Colors.deepOrange,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/returns'),
        ),
      ),
      body: state.isLoading && state.returnRequest == null
          ? const Center(child: CircularProgressIndicator())
          : state.returnRequest == null
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
          Text(error ?? 'Failed to load return'),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => ref.read(returnDetailProvider.notifier).loadReturn(widget.returnId),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailView(ReturnDetailState state, ReturnDetailNotifier notifier) {
    final returnRequest = state.returnRequest!;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Return header
          _buildReturnHeader(returnRequest),

          // Order info
          _buildOrderInfo(returnRequest),

          // Item info
          _buildItemInfo(returnRequest),

          // Inspection section (only if status is "received")
          if (returnRequest.status == 'received')
            _buildInspectionSection(state, notifier),

          // Restock section (only if inspected + restockable)
          if (returnRequest.status == 'inspected' && returnRequest.restockable == true)
            _buildRestockSection(returnRequest, state, notifier),

          // Action buttons based on status
          _buildActionButtons(returnRequest, state, notifier),

          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildReturnHeader(ReturnRequest returnRequest) {
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
                child: Text(
                  returnRequest.rmaNumber,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                ),
              ),
              _statusBadge(returnRequest.status),
            ],
          ),
          const SizedBox(height: 8),
          if (returnRequest.source != null)
            Row(
              children: [
                _sourceBadge(returnRequest.source!),
              ],
            ),
        ],
      ),
    );
  }

  Widget _buildOrderInfo(ReturnRequest returnRequest) {
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
                  if (returnRequest.marketplaceOrderId != null)
                    _infoRow('Order ID', returnRequest.marketplaceOrderId!),
                  if (returnRequest.buyerName != null)
                    _infoRow('Buyer', returnRequest.buyerName!),
                  if (returnRequest.total != null)
                    _infoRow('Total', '\$${returnRequest.total!.toStringAsFixed(2)}'),
                  if (returnRequest.reason != null)
                    _infoRow('Reason', returnRequest.reason!),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItemInfo(ReturnRequest returnRequest) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Item',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (returnRequest.itemTitle != null)
                    Text(
                      returnRequest.itemTitle!,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                    ),
                  const SizedBox(height: 4),
                  Wrap(
                    spacing: 12,
                    children: [
                      if (returnRequest.itemSku != null)
                        Text(
                          'SKU: ${returnRequest.itemSku}',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      if (returnRequest.quantity != null)
                        Text(
                          'Qty: ${returnRequest.quantity}',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                        ),
                      if (returnRequest.condition != null)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: _conditionColor(returnRequest.condition!).withOpacity(0.1),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            returnRequest.condition!,
                            style: TextStyle(
                              fontSize: 12,
                              color: _conditionColor(returnRequest.condition!),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInspectionSection(ReturnDetailState state, ReturnDetailNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Inspection',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Condition dropdown
                  DropdownButtonFormField<String>(
                    value: _selectedCondition,
                    decoration: InputDecoration(
                      labelText: 'Condition',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    items: _conditions.map((c) => DropdownMenuItem(
                      value: c,
                      child: Text(c),
                    )).toList(),
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _selectedCondition = value);
                      }
                    },
                  ),
                  const SizedBox(height: 12),

                  // Notes
                  TextField(
                    controller: _notesController,
                    decoration: InputDecoration(
                      labelText: 'Notes',
                      hintText: 'Inspection notes...',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    maxLines: 3,
                  ),
                  const SizedBox(height: 12),

                  // Restockable toggle
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Restockable', style: TextStyle(fontSize: 14)),
                      Switch(
                        value: _restockable,
                        onChanged: (value) => setState(() => _restockable = value),
                        activeColor: Colors.deepOrange,
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // Submit inspection button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: state.isLoading
                          ? null
                          : () {
                              notifier.inspect(
                                _selectedCondition,
                                _notesController.text.trim(),
                                _restockable,
                              );
                            },
                      icon: const Icon(Icons.search),
                      label: const Text('Submit Inspection'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.teal,
                        minimumSize: const Size(0, 44),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRestockSection(ReturnRequest returnRequest, ReturnDetailState state, ReturnDetailNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Restock',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Location dropdown
                  if (returnRequest.availableLocations != null && returnRequest.availableLocations!.isNotEmpty)
                    DropdownButtonFormField<String>(
                      value: _selectedLocationId,
                      decoration: InputDecoration(
                        labelText: 'Location',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      ),
                      items: returnRequest.availableLocations!.map((loc) => DropdownMenuItem(
                        value: loc.id,
                        child: Text(loc.name),
                      )).toList(),
                      onChanged: (value) => setState(() => _selectedLocationId = value),
                    ),
                  const SizedBox(height: 12),

                  // Restock button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: state.isLoading || _selectedLocationId == null
                          ? null
                          : () => notifier.restock(_selectedLocationId!),
                      icon: const Icon(Icons.inventory),
                      label: const Text('Restock'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        minimumSize: const Size(0, 44),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButtons(ReturnRequest returnRequest, ReturnDetailState state, ReturnDetailNotifier notifier) {
    final List<Widget> buttons = [];

    if (returnRequest.status == 'open') {
      buttons.add(
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: state.isLoading ? null : () => notifier.approve(),
            icon: const Icon(Icons.check_circle),
            label: const Text('Approve Return'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.blue,
              minimumSize: const Size(0, 48),
            ),
          ),
        ),
      );
    }

    if (returnRequest.status == 'approved' || returnRequest.status == 'shipped_back') {
      buttons.add(
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: state.isLoading ? null : () => notifier.receive(),
            icon: const Icon(Icons.move_to_inbox),
            label: const Text('Mark as Received'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.orange,
              minimumSize: const Size(0, 48),
            ),
          ),
        ),
      );
    }

    if (buttons.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: buttons.expand((w) => [w, const SizedBox(height: 12)]).toList()
          ..removeLast(),
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

  Widget _statusBadge(String status) {
    final Color color;
    final String label;
    switch (status) {
      case 'open':
        color = Colors.deepOrange;
        label = 'Open';
        break;
      case 'approved':
        color = Colors.blue;
        label = 'Approved';
        break;
      case 'shipped_back':
        color = Colors.purple;
        label = 'Shipped Back';
        break;
      case 'received':
        color = Colors.orange;
        label = 'Received';
        break;
      case 'inspected':
        color = Colors.teal;
        label = 'Inspected';
        break;
      case 'refunded':
        color = Colors.green;
        label = 'Refunded';
        break;
      case 'denied':
        color = Colors.red;
        label = 'Denied';
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

  Widget _sourceBadge(String source) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.grey[100],
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        source.toUpperCase(),
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
      ),
    );
  }

  Color _conditionColor(String condition) {
    switch (condition) {
      case 'Like New':
        return Colors.green;
      case 'Good':
        return Colors.blue;
      case 'Fair':
        return Colors.orange;
      case 'Poor':
        return Colors.deepOrange;
      case 'Damaged':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}
