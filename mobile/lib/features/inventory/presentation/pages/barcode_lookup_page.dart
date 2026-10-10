import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../core/providers/connectivity_provider.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/storage/offline_scan_db.dart';
import '../../../../core/network/connectivity_service.dart';

class BarcodeLookupPage extends ConsumerStatefulWidget {
  const BarcodeLookupPage({super.key});

  @override
  ConsumerState<BarcodeLookupPage> createState() => _BarcodeLookupPageState();
}

class _BarcodeLookupPageState extends ConsumerState<BarcodeLookupPage> {
  MobileScannerController? _scannerController;
  bool _isScanning = true;
  bool _isLoading = false;
  String? _error;
  Map<String, dynamic>? _product;
  String? _scannedBarcode;

  @override
  void initState() {
    super.initState();
    _scannerController = MobileScannerController();
  }

  @override
  void dispose() {
    _scannerController?.dispose();
    super.dispose();
  }

  Future<void> _lookupBarcode(String barcode) async {
    setState(() {
      _isLoading = true;
      _error = null;
      _product = null;
      _scannedBarcode = barcode;
      _isScanning = false;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.get(
        '${ApiEndpoints.api}/products/barcode-lookup',
        queryParameters: {'barcode': barcode},
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        setState(() {
          _product = response.data['data'] as Map<String, dynamic>;
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = 'not_found';
          _isLoading = false;
        });
      }
    } catch (e) {
      // If offline, save to local database for later sync
      final isOnline = await ConnectivityService().checkConnectivity();
      if (!isOnline) {
        await OfflineScanDb.insertScan(barcode: barcode);
        ref.invalidate(pendingScanCountProvider);
        if (mounted) {
          setState(() {
            _isLoading = false;
            _error = null;
            _isScanning = true;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Offline -- scan saved for sync'),
              backgroundColor: Colors.blueGrey,
              behavior: SnackBarBehavior.floating,
            ),
          );
          _scannerController?.dispose();
          _scannerController = MobileScannerController();
        }
        return;
      }
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  void _resetScanner() {
    _scannerController?.dispose();
    _scannerController = MobileScannerController();
    setState(() {
      _isScanning = true;
      _isLoading = false;
      _error = null;
      _product = null;
      _scannedBarcode = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final pendingCount = ref.watch(pendingScanCountProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Barcode Lookup'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
        actions: [
          pendingCount.when(
            data: (count) {
              if (count == 0) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.only(right: 12),
                child: Chip(
                  label: Text(
                    '$count pending',
                    style: const TextStyle(fontSize: 12, color: Colors.white),
                  ),
                  backgroundColor: Colors.orange,
                  padding: EdgeInsets.zero,
                  visualDensity: VisualDensity.compact,
                ),
              );
            },
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
        ],
      ),
      body: _isScanning ? _buildScanner() : _buildResult(),
    );
  }

  Widget _buildScanner() {
    return Stack(
      children: [
        MobileScanner(
          controller: _scannerController,
          onDetect: (capture) {
            if (!_isScanning) return;
            final barcodes = capture.barcodes;
            if (barcodes.isNotEmpty && barcodes.first.rawValue != null) {
              _lookupBarcode(barcodes.first.rawValue!);
            }
          },
        ),
        // Overlay with scan instructions
        Positioned(
          bottom: 80,
          left: 0,
          right: 0,
          child: Center(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.6),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                'Point camera at a barcode',
                style: TextStyle(color: Colors.white, fontSize: 16),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildResult() {
    if (_isLoading) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: 16),
            Text(
              'Looking up barcode: $_scannedBarcode',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      );
    }

    if (_error == 'not_found') {
      return _buildNotFound();
    }

    if (_error != null) {
      return _buildError();
    }

    if (_product != null) {
      return _buildProductDetails();
    }

    return const SizedBox.shrink();
  }

  Widget _buildNotFound() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.search_off, size: 72, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text('Product Not Found', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(
              'No product matches barcode: $_scannedBarcode',
              style: TextStyle(color: Colors.grey[600]),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _resetScanner,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scan Again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.deepPurple,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () {
                // Navigate to intake queue with the barcode pre-filled
                // The parent route handles this; for now show a snackbar
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Barcode $_scannedBarcode queued for intake'),
                    backgroundColor: Colors.blue,
                  ),
                );
              },
              icon: const Icon(Icons.add_circle_outline),
              label: const Text('Add to Intake Queue'),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.deepPurple,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Lookup Failed', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _resetScanner,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scan Again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.deepPurple,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProductDetails() {
    final p = _product!;
    final name = p['name'] ?? 'Unknown Product';
    final sku = p['sku'] ?? '-';
    final price = p['price'];
    final quantity = p['quantity_available'] ?? p['stock'] ?? 0;
    final imageUrl = p['image_url'] as String?;
    final locationName = p['location_name'] as String?;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Product image
          if (imageUrl != null && imageUrl.isNotEmpty)
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.network(
                imageUrl,
                height: 200,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => Container(
                  height: 200,
                  color: Colors.grey[200],
                  child: const Icon(Icons.image_not_supported, size: 64, color: Colors.grey),
                ),
              ),
            )
          else
            Container(
              height: 150,
              decoration: BoxDecoration(
                color: Colors.grey[200],
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.inventory_2, size: 64, color: Colors.grey),
            ),
          const SizedBox(height: 16),

          // Product name
          Text(
            name,
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),

          // Details card
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  _detailRow('SKU', sku),
                  _detailRow('Barcode', _scannedBarcode ?? '-'),
                  if (price != null)
                    _detailRow('Price', '\$${double.tryParse(price.toString())?.toStringAsFixed(2) ?? price}'),
                  _detailRow('Stock', quantity.toString()),
                  if (locationName != null)
                    _detailRow('Location', locationName),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),

          // Stock indicator
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: _stockColor(quantity).withOpacity(0.1),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: _stockColor(quantity).withOpacity(0.3)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(_stockIcon(quantity), color: _stockColor(quantity), size: 20),
                const SizedBox(width: 8),
                Text(
                  _stockLabel(quantity),
                  style: TextStyle(
                    color: _stockColor(quantity),
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Scan again button
          SizedBox(
            height: 48,
            child: ElevatedButton.icon(
              onPressed: _resetScanner,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scan Again', style: TextStyle(fontSize: 16)),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.deepPurple,
                foregroundColor: Colors.white,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          SizedBox(
            width: 90,
            child: Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 14)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
          ),
        ],
      ),
    );
  }

  Color _stockColor(dynamic quantity) {
    final qty = quantity is int ? quantity : int.tryParse(quantity.toString()) ?? 0;
    if (qty == 0) return Colors.red;
    if (qty < 10) return Colors.orange;
    return Colors.green;
  }

  IconData _stockIcon(dynamic quantity) {
    final qty = quantity is int ? quantity : int.tryParse(quantity.toString()) ?? 0;
    if (qty == 0) return Icons.cancel_outlined;
    if (qty < 10) return Icons.warning_amber;
    return Icons.check_circle_outline;
  }

  String _stockLabel(dynamic quantity) {
    final qty = quantity is int ? quantity : int.tryParse(quantity.toString()) ?? 0;
    if (qty == 0) return 'Out of Stock';
    if (qty < 10) return 'Low Stock ($qty available)';
    return 'In Stock ($qty available)';
  }
}
