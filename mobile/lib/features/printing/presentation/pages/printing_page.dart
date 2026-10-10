import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/print_models.dart';
import '../providers/print_provider.dart';

class PrintingPage extends ConsumerStatefulWidget {
  const PrintingPage({super.key});

  @override
  ConsumerState<PrintingPage> createState() => _PrintingPageState();
}

class _PrintingPageState extends ConsumerState<PrintingPage> {
  final _skuController = TextEditingController();
  final _batchController = TextEditingController();
  final _quantityController = TextEditingController(text: '1');

  @override
  void dispose() {
    _skuController.dispose();
    _batchController.dispose();
    _quantityController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(printProvider);
    final notifier = ref.read(printProvider.notifier);

    ref.listen<PrintState>(printProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.successMessage!),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
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
        title: const Text('Label Printing'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
      ),
      body: RefreshIndicator(
        onRefresh: () => notifier.discoverPrinters(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildQuickPrintCard(state, notifier),
            const SizedBox(height: 16),
            _buildBatchPrintCard(state, notifier),
            const SizedBox(height: 16),
            _buildPrinterDiscoveryCard(state, notifier),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickPrintCard(PrintState state, PrintNotifier notifier) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.print, color: Colors.teal[700]),
                const SizedBox(width: 8),
                Text(
                  'Quick Print',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.teal[700],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Print a label for a single product by ID or SKU',
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _skuController,
              decoration: const InputDecoration(
                labelText: 'Product ID or SKU',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.qr_code),
                hintText: 'Enter product ID',
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                SizedBox(
                  width: 100,
                  child: TextField(
                    controller: _quantityController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Qty',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: state.isLoading
                        ? null
                        : () {
                            final id = _skuController.text.trim();
                            if (id.isEmpty) return;
                            final qty = int.tryParse(_quantityController.text) ?? 1;
                            notifier.printLabel(id, quantity: qty);
                          },
                    icon: state.isLoading
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.print),
                    label: Text(state.isLoading ? 'Printing...' : 'Print Label'),
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.teal,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBatchPrintCard(PrintState state, PrintNotifier notifier) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.dynamic_feed, color: Colors.indigo[700]),
                const SizedBox(width: 8),
                Text(
                  'Batch Print',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.indigo[700],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Print labels for multiple products at once',
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _batchController,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Product IDs (one per line)',
                border: OutlineInputBorder(),
                prefixIcon: Padding(
                  padding: EdgeInsets.only(bottom: 40),
                  child: Icon(Icons.list),
                ),
                hintText: 'product-id-1\nproduct-id-2\nproduct-id-3',
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: state.isLoading
                    ? null
                    : () {
                        final text = _batchController.text.trim();
                        if (text.isEmpty) return;
                        final ids = text
                            .split('\n')
                            .map((s) => s.trim())
                            .where((s) => s.isNotEmpty)
                            .toList();
                        if (ids.isEmpty) return;
                        notifier.printBatch(ids);
                      },
                icon: const Icon(Icons.print),
                label: const Text('Print Batch'),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.indigo,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPrinterDiscoveryCard(PrintState state, PrintNotifier notifier) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.wifi_find, color: Colors.orange[700]),
                const SizedBox(width: 8),
                Text(
                  'Printers',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.orange[700],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Discover and manage printers on your network',
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: state.isDiscovering ? null : () => notifier.discoverPrinters(),
                icon: state.isDiscovering
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.search),
                label: Text(state.isDiscovering ? 'Discovering...' : 'Discover Printers'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
              ),
            ),
            if (state.discoveredPrinters.isNotEmpty) ...[
              const SizedBox(height: 16),
              const Divider(),
              const SizedBox(height: 8),
              Text(
                'Found ${state.discoveredPrinters.length} printer(s)',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: Colors.grey[700],
                ),
              ),
              const SizedBox(height: 8),
              ...state.discoveredPrinters.map((printer) => _buildPrinterTile(printer, notifier)),
            ],
            if (state.discoveredPrinters.isEmpty && !state.isDiscovering) ...[
              const SizedBox(height: 16),
              Center(
                child: Column(
                  children: [
                    Icon(Icons.print_disabled, size: 48, color: Colors.grey[400]),
                    const SizedBox(height: 8),
                    Text(
                      'No printers discovered yet',
                      style: TextStyle(color: Colors.grey[500]),
                    ),
                    Text(
                      'Tap Discover to scan your network',
                      style: TextStyle(color: Colors.grey[400], fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildPrinterTile(DiscoveredPrinter printer, PrintNotifier notifier) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(8),
      ),
      child: ListTile(
        leading: Icon(
          Icons.print,
          color: printer.isOnline ? Colors.green : Colors.grey,
        ),
        title: Text(printer.name),
        subtitle: Text(
          '${printer.type} -- ${printer.connectionString}',
          style: const TextStyle(fontSize: 12),
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              icon: const Icon(Icons.speed, size: 20),
              tooltip: 'Test',
              onPressed: () => notifier.testPrinter(printer.id),
            ),
            IconButton(
              icon: const Icon(Icons.add_circle_outline, size: 20),
              tooltip: 'Add',
              onPressed: () => notifier.addPrinter({
                'name': printer.name,
                'type': printer.type,
                'connection_string': printer.connectionString,
                'driver': printer.driver,
              }),
            ),
          ],
        ),
      ),
    );
  }
}
