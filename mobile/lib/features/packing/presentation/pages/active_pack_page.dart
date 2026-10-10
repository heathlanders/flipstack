import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/pack_models.dart';
import '../../../../data/services/print_service.dart';
import '../../../../core/providers/shared_providers.dart';
import '../providers/pack_provider.dart';

class ActivePackPage extends ConsumerStatefulWidget {
  final String orderId;

  const ActivePackPage({super.key, required this.orderId});

  @override
  ConsumerState<ActivePackPage> createState() => _ActivePackPageState();
}

class _ActivePackPageState extends ConsumerState<ActivePackPage> {
  final _weightController = TextEditingController();
  final _lengthController = TextEditingController();
  final _widthController = TextEditingController();
  final _heightController = TextEditingController();
  String? _selectedRateId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(activePackProvider.notifier).loadOrder(widget.orderId);
    });
  }

  @override
  void dispose() {
    _weightController.dispose();
    _lengthController.dispose();
    _widthController.dispose();
    _heightController.dispose();
    super.dispose();
  }

  PackageDimensions? _getDimensions() {
    final weight = double.tryParse(_weightController.text);
    final length = double.tryParse(_lengthController.text);
    final width = double.tryParse(_widthController.text);
    final height = double.tryParse(_heightController.text);
    if (weight == null || length == null || width == null || height == null) {
      return null;
    }
    return PackageDimensions(
      weightOz: weight,
      length: length,
      width: width,
      height: height,
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(activePackProvider);
    final notifier = ref.read(activePackProvider.notifier);

    // Listen for success/error messages
    ref.listen<ActivePackState>(activePackProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.successMessage!),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
            duration: const Duration(seconds: 3),
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
        title: Text(state.order?.marketplaceOrderId ?? 'Packing'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/packing'),
        ),
      ),
      body: state.isLoading && state.order == null
          ? const Center(child: CircularProgressIndicator())
          : state.order == null
              ? _buildError(state.error)
              : _buildPackingView(state, notifier),
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
            onPressed: () => ref.read(activePackProvider.notifier).loadOrder(widget.orderId),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildPackingView(ActivePackState state, ActivePackNotifier notifier) {
    final order = state.order!;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Order header
          _buildOrderHeader(order),

          // Items list
          _buildItemsList(order),

          // Package dimensions form
          _buildDimensionsForm(),

          // Get rates button
          _buildGetRatesButton(state, notifier),

          // Rates list
          if (state.rates.isNotEmpty) _buildRatesList(state.rates),

          // Purchase label button
          if (_selectedRateId != null) _buildPurchaseLabelButton(state, notifier),

          // Tracking info
          if (order.trackingNumber != null) _buildTrackingInfo(order),

          // Complete packing button
          _buildBottomActions(order, state, notifier),

          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildOrderHeader(PackOrder order) {
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
                  order.marketplaceOrderId,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                ),
              ),
              _statusBadge(order.status),
            ],
          ),
          const SizedBox(height: 8),
          if (order.buyerName != null)
            Row(
              children: [
                Icon(Icons.person_outlined, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 4),
                Text(
                  order.buyerName!,
                  style: TextStyle(color: Colors.grey[600], fontSize: 14),
                ),
              ],
            ),
          if (order.shippingAddress != null) ...[
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(Icons.location_on_outlined, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    order.shippingAddress!,
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildItemsList(PackOrder order) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Items (${order.items.length})',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          ...order.items.map((item) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.check_circle, color: Colors.green, size: 20),
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
                            Text(
                              'Picked',
                              style: TextStyle(fontSize: 12, color: Colors.green[700]),
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

  Widget _buildDimensionsForm() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Package Dimensions',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _weightController,
                  decoration: const InputDecoration(
                    labelText: 'Weight (oz)',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _lengthController,
                  decoration: const InputDecoration(
                    labelText: 'Length (in)',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _widthController,
                  decoration: const InputDecoration(
                    labelText: 'Width (in)',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _heightController,
                  decoration: const InputDecoration(
                    labelText: 'Height (in)',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildGetRatesButton(ActivePackState state, ActivePackNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: state.isLoading
              ? null
              : () {
                  final dims = _getDimensions();
                  if (dims == null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Please enter all package dimensions'),
                        backgroundColor: Colors.orange,
                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                    return;
                  }
                  notifier.getRates(dims);
                },
          icon: const Icon(Icons.local_shipping_outlined),
          label: const Text('Get Shipping Rates'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.blue,
            minimumSize: const Size(0, 48),
          ),
        ),
      ),
    );
  }

  Widget _buildRatesList(List<ShippingRate> rates) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Shipping Rates',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          const SizedBox(height: 8),
          ...rates.map((rate) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            color: _selectedRateId == rate.id ? Colors.teal.shade50 : null,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
              side: _selectedRateId == rate.id
                  ? const BorderSide(color: Colors.teal, width: 2)
                  : BorderSide.none,
            ),
            child: InkWell(
              onTap: () => setState(() => _selectedRateId = rate.id),
              borderRadius: BorderRadius.circular(8),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Radio<String>(
                      value: rate.id,
                      groupValue: _selectedRateId,
                      onChanged: (value) => setState(() => _selectedRateId = value),
                      activeColor: Colors.teal,
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            rate.carrier,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                          ),
                          Text(
                            rate.service,
                            style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          '\$${rate.rate.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                            color: Colors.teal,
                          ),
                        ),
                        if (rate.estimatedDays != null)
                          Text(
                            '${rate.estimatedDays} day${rate.estimatedDays == 1 ? '' : 's'}',
                            style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          )),
        ],
      ),
    );
  }

  Widget _buildPurchaseLabelButton(ActivePackState state, ActivePackNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: state.isLoading
              ? null
              : () {
                  final dims = _getDimensions();
                  if (dims == null) return;
                  notifier.purchaseLabel(_selectedRateId!, dims);
                },
          icon: const Icon(Icons.receipt_long),
          label: const Text('Purchase Label'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.teal,
            minimumSize: const Size(0, 48),
          ),
        ),
      ),
    );
  }

  Widget _buildTrackingInfo(PackOrder order) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        children: [
          Card(
            color: Colors.green.shade50,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Label Purchased',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.green),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.confirmation_number_outlined, size: 16, color: Colors.green),
                      const SizedBox(width: 8),
                      Text(
                        'Tracking: ${order.trackingNumber}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  if (order.labelUrl != null) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(Icons.print_outlined, size: 16, color: Colors.blue),
                        const SizedBox(width: 8),
                        Text(
                          'Label URL available',
                          style: TextStyle(color: Colors.blue[700]),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (order.shipment?.labelUrl != null) ...[
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _printShippingLabel(order),
                icon: const Icon(Icons.print),
                label: const Text('Print Shipping Label'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(0, 44),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _printShippingLabel(PackOrder order) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final printService = PrintService(apiClient);
      final shipmentId = order.shipment?.id;
      if (shipmentId == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('No shipment found for this order'),
              backgroundColor: Colors.orange,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
        return;
      }
      await printService.printProductLabel(shipmentId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Shipping label sent to printer'),
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

  Widget _buildBottomActions(PackOrder order, ActivePackState state, ActivePackNotifier notifier) {
    if (order.trackingNumber == null) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.all(16),
      child: SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: state.isLoading
              ? null
              : () async {
                  final dims = _getDimensions();
                  if (dims == null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Please enter package dimensions'),
                        backgroundColor: Colors.orange,
                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                    return;
                  }
                  final success = await notifier.completePacking(dims);
                  if (success && mounted) {
                    context.go('/packing');
                  }
                },
          icon: const Icon(Icons.check_circle),
          label: const Text('Complete Packing'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.green,
            minimumSize: const Size(0, 48),
          ),
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
