import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/bin_models.dart';
import '../providers/bin_provider.dart';

class BinLookupPage extends ConsumerStatefulWidget {
  const BinLookupPage({super.key});

  @override
  ConsumerState<BinLookupPage> createState() => _BinLookupPageState();
}

class _BinLookupPageState extends ConsumerState<BinLookupPage> {
  final _binController = TextEditingController();

  @override
  void dispose() {
    _binController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(binLookupProvider);
    final notifier = ref.read(binLookupProvider.notifier);

    ref.listen<BinLookupState>(binLookupProvider, (prev, next) {
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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bin Lookup'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
        actions: [
          if (state.bin != null)
            IconButton(icon: const Icon(Icons.clear), tooltip: 'Clear', onPressed: () {
              _binController.clear();
              notifier.clear();
            }),
        ],
      ),
      body: Column(
        children: [
          _buildScanBar(state, notifier),
          Expanded(child: _buildBody(state, notifier)),
        ],
      ),
    );
  }

  Widget _buildScanBar(BinLookupState state, BinLookupNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _binController,
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                labelText: 'Scan / enter bin barcode',
                prefixIcon: Icon(Icons.qr_code_scanner),
                border: OutlineInputBorder(),
                isDense: true,
              ),
              onSubmitted: (value) {
                final code = value.trim();
                if (code.isNotEmpty) notifier.scanBin(code);
              },
            ),
          ),
          const SizedBox(width: 8),
          ElevatedButton(
            onPressed: () {
              final code = _binController.text.trim();
              if (code.isNotEmpty) notifier.scanBin(code);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.teal, foregroundColor: Colors.white),
            child: const Text('Look up'),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(BinLookupState state, BinLookupNotifier notifier) {
    if (state.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.bin == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.shelves, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            const Text('Scan a bin to see its contents'),
          ],
        ),
      );
    }

    final bin = state.bin!;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _buildBinHeader(bin),
        const SizedBox(height: 12),
        if (bin.isActive)
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: state.isAssigning ? null : () => _promptAssign(notifier),
              icon: state.isAssigning
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.add),
              label: const Text('Place product in this bin'),
              style: ElevatedButton.styleFrom(backgroundColor: Colors.teal, foregroundColor: Colors.white),
            ),
          ),
        const SizedBox(height: 16),
        Text('Contents (${bin.contents.length})', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        const SizedBox(height: 8),
        if (bin.contents.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 24),
            child: Center(child: Text('Bin is empty', style: TextStyle(color: Colors.grey[600]))),
          )
        else
          ...bin.contents.map(_buildContentCard),
      ],
    );
  }

  Widget _buildBinHeader(WarehouseBin bin) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.shelves, color: Colors.teal),
                const SizedBox(width: 8),
                Expanded(child: Text(bin.fullCode, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
                if (!bin.isActive) _badge('Inactive', Colors.red),
                if (bin.isAtCapacity) _badge('Full', Colors.orange),
              ],
            ),
            const SizedBox(height: 6),
            if (bin.zone != null) Text('Zone: ${bin.zone!.name}', style: TextStyle(color: Colors.grey[700])),
            Text('Capacity: ${bin.capacityDisplay}', style: TextStyle(color: Colors.grey[700])),
          ],
        ),
      ),
    );
  }

  Widget _badge(String label, Color color) {
    return Container(
      margin: const EdgeInsets.only(left: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }

  Widget _buildContentCard(BinContent c) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(c.productName ?? 'Product', maxLines: 2, overflow: TextOverflow.ellipsis),
        subtitle: c.sku != null ? Text('SKU: ${c.sku}') : null,
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text('${c.quantityOnHand}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            Text('on hand', style: TextStyle(fontSize: 10, color: Colors.grey[600])),
          ],
        ),
      ),
    );
  }

  void _promptAssign(BinLookupNotifier notifier) {
    final controller = TextEditingController();
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Place product'),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: const InputDecoration(
            labelText: 'Scan / enter product barcode',
            prefixIcon: Icon(Icons.qr_code_scanner),
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final code = controller.text.trim();
              if (code.isEmpty) return;
              Navigator.of(dialogContext).pop();
              notifier.assignProduct(code);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.teal, foregroundColor: Colors.white),
            child: const Text('Place'),
          ),
        ],
      ),
    );
  }
}
