import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../data/models/pick_models.dart';
import '../providers/stock_ops_provider.dart';

class StockTransferPage extends ConsumerStatefulWidget {
  const StockTransferPage({super.key});

  @override
  ConsumerState<StockTransferPage> createState() => _StockTransferPageState();
}

class _StockTransferPageState extends ConsumerState<StockTransferPage> {
  final _productController = TextEditingController();
  final _quantityController = TextEditingController();
  final _reasonController = TextEditingController();
  String? _fromLocationId;
  String? _toLocationId;
  bool _showScanner = false;
  MobileScannerController? _scannerController;

  @override
  void dispose() {
    _productController.dispose();
    _quantityController.dispose();
    _reasonController.dispose();
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
        title: const Text('Stock Transfer'),
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

          // From location dropdown
          DropdownButtonFormField<String>(
            value: _fromLocationId,
            decoration: InputDecoration(
              labelText: 'From Location',
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
                _fromLocationId = value;
              });
            },
          ),
          const SizedBox(height: 16),

          // To location dropdown
          DropdownButtonFormField<String>(
            value: _toLocationId,
            decoration: InputDecoration(
              labelText: 'To Location',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
            ),
            items: state.locations
                .where((loc) => loc.id != _fromLocationId)
                .map((loc) {
              return DropdownMenuItem<String>(
                value: loc.id,
                child: Text('${loc.name}${loc.code != null ? ' (${loc.code})' : ''}'),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                _toLocationId = value;
              });
            },
          ),
          const SizedBox(height: 16),

          // Quantity field
          TextField(
            controller: _quantityController,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(
              labelText: 'Quantity',
              hintText: 'Number of units to transfer',
              prefixIcon: const Icon(Icons.numbers),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            ),
          ),
          const SizedBox(height: 16),

          // Optional reason field
          TextField(
            controller: _reasonController,
            decoration: InputDecoration(
              labelText: 'Reason (optional)',
              hintText: 'Why is this transfer needed?',
              prefixIcon: const Icon(Icons.note_outlined),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            ),
            maxLines: 2,
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
                  : const Text('Transfer Stock', style: TextStyle(fontSize: 16)),
            ),
          ),
        ],
      ),
    );
  }

  void _handleSubmit() {
    final productId = _productController.text.trim();
    final quantityText = _quantityController.text.trim();
    final reason = _reasonController.text.trim();

    if (productId.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a product SKU or name'), backgroundColor: Colors.orange),
      );
      return;
    }
    if (_fromLocationId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a source location'), backgroundColor: Colors.orange),
      );
      return;
    }
    if (_toLocationId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a destination location'), backgroundColor: Colors.orange),
      );
      return;
    }
    if (_fromLocationId == _toLocationId) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Source and destination must be different'), backgroundColor: Colors.orange),
      );
      return;
    }
    final quantity = int.tryParse(quantityText);
    if (quantity == null || quantity <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a positive quantity'), backgroundColor: Colors.orange),
      );
      return;
    }

    ref.read(stockOpsProvider.notifier).transferStock(
      productId: productId,
      fromLocationId: _fromLocationId!,
      toLocationId: _toLocationId!,
      quantity: quantity,
      reason: reason.isNotEmpty ? reason : null,
    );
  }

  void _resetForm() {
    _productController.clear();
    _quantityController.clear();
    _reasonController.clear();
    setState(() {
      _fromLocationId = null;
      _toLocationId = null;
    });
  }
}
