import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../data/models/pick_models.dart';
import '../providers/stock_ops_provider.dart';

class StockAdjustPage extends ConsumerStatefulWidget {
  const StockAdjustPage({super.key});

  @override
  ConsumerState<StockAdjustPage> createState() => _StockAdjustPageState();
}

class _StockAdjustPageState extends ConsumerState<StockAdjustPage> {
  final _productController = TextEditingController();
  final _quantityController = TextEditingController();
  String? _selectedLocationId;
  String _selectedReason = 'Recount';
  bool _showScanner = false;
  MobileScannerController? _scannerController;

  static const List<String> _reasons = [
    'Recount',
    'Damaged',
    'Received',
    'Theft',
    'Found',
    'Other',
  ];

  @override
  void dispose() {
    _productController.dispose();
    _quantityController.dispose();
    _scannerController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(stockOpsProvider);
    final notifier = ref.read(stockOpsProvider.notifier);

    // Listen for success/error messages
    ref.listen<StockOpsState>(stockOpsProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.successMessage!),
            backgroundColor: Colors.green,
          ),
        );
        notifier.clearMessages();
        _resetForm();
      }
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
          ),
        );
        notifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Stock Adjustment'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
      ),
      body: _showScanner ? _buildScanner() : _buildForm(state),
    );
  }

  Widget _buildScanner() {
    _scannerController ??= MobileScannerController();
    return Column(
      children: [
        Expanded(
          child: MobileScanner(
            controller: _scannerController,
            onDetect: (capture) {
              final barcodes = capture.barcodes;
              if (barcodes.isNotEmpty && barcodes.first.rawValue != null) {
                setState(() {
                  _productController.text = barcodes.first.rawValue!;
                  _showScanner = false;
                });
                _scannerController?.dispose();
                _scannerController = null;
              }
            },
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: ElevatedButton(
            onPressed: () {
              setState(() {
                _showScanner = false;
              });
              _scannerController?.dispose();
              _scannerController = null;
            },
            child: const Text('Cancel Scan'),
          ),
        ),
      ],
    );
  }

  Widget _buildForm(StockOpsState state) {
    if (state.isLoading && state.locations.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.locations.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading data', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(state.error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref.read(stockOpsProvider.notifier).loadLocations(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Product search field
          TextField(
            controller: _productController,
            decoration: InputDecoration(
              labelText: 'Product SKU or Name',
              hintText: 'Enter SKU or scan barcode',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                icon: const Icon(Icons.qr_code_scanner),
                tooltip: 'Scan Barcode',
                onPressed: () {
                  setState(() {
                    _showScanner = true;
                  });
                },
              ),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            ),
          ),
          const SizedBox(height: 16),

          // Location dropdown
          DropdownButtonFormField<String>(
            value: _selectedLocationId,
            decoration: InputDecoration(
              labelText: 'Location',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
            ),
            items: state.locations.map((loc) {
              return DropdownMenuItem<String>(
                value: loc.id,
                child: Text('${loc.name}${loc.code != null ? ' (${loc.code})' : ''}'),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                _selectedLocationId = value;
              });
            },
          ),
          const SizedBox(height: 16),

          // Quantity field
          TextField(
            controller: _quantityController,
            keyboardType: const TextInputType.numberWithOptions(signed: true),
            decoration: InputDecoration(
              labelText: 'Quantity (+ or -)',
              hintText: 'e.g. 5 or -3',
              prefixIcon: const Icon(Icons.numbers),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            ),
          ),
          const SizedBox(height: 16),

          // Reason dropdown
          DropdownButtonFormField<String>(
            value: _selectedReason,
            decoration: InputDecoration(
              labelText: 'Reason',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
            ),
            items: _reasons.map((reason) {
              return DropdownMenuItem<String>(
                value: reason,
                child: Text(reason),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                _selectedReason = value ?? 'Recount';
              });
            },
          ),
          const SizedBox(height: 24),

          // Submit button
          SizedBox(
            height: 48,
            child: ElevatedButton(
              onPressed: state.isLoading ? null : _handleSubmit,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.deepPurple,
                foregroundColor: Colors.white,
              ),
              child: state.isLoading
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Submit Adjustment', style: TextStyle(fontSize: 16)),
            ),
          ),
        ],
      ),
    );
  }

  void _handleSubmit() {
    final productId = _productController.text.trim();
    final quantityText = _quantityController.text.trim();

    if (productId.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a product SKU or name'), backgroundColor: Colors.orange),
      );
      return;
    }
    if (_selectedLocationId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a location'), backgroundColor: Colors.orange),
      );
      return;
    }
    final quantity = int.tryParse(quantityText);
    if (quantity == null || quantity == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a valid non-zero quantity'), backgroundColor: Colors.orange),
      );
      return;
    }

    ref.read(stockOpsProvider.notifier).adjustStock(
      productId: productId,
      locationId: _selectedLocationId!,
      quantity: quantity,
      reason: _selectedReason,
    );
  }

  void _resetForm() {
    _productController.clear();
    _quantityController.clear();
    setState(() {
      _selectedLocationId = null;
      _selectedReason = 'Recount';
    });
  }
}
