import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/bundle_models.dart';
import '../providers/bundle_provider.dart';

class BundleListPage extends ConsumerWidget {
  const BundleListPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(bundleListProvider);
    final notifier = ref.read(bundleListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bundles'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: () => notifier.refresh()),
        ],
      ),
      body: _buildContent(context, state, notifier),
    );
  }

  Widget _buildContent(BuildContext context, BundleListState state, BundleListNotifier notifier) {
    if (state.isLoading && state.bundles.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.bundles.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            const Text('Error loading bundles'),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(state.error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: () => notifier.refresh(), child: const Text('Retry')),
          ],
        ),
      );
    }

    if (state.bundles.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.widgets_outlined, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            const Text('No bundles'),
            const SizedBox(height: 8),
            Text('Create bundles from the web app.', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.bundles.length,
        itemBuilder: (context, index) => _buildCard(state.bundles[index]),
      ),
    );
  }

  Widget _buildCard(ProductBundle bundle) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: ExpansionTile(
        leading: CircleAvatar(
          backgroundColor: bundle.inStock ? Colors.green.shade50 : Colors.red.shade50,
          child: Icon(Icons.widgets, color: bundle.inStock ? Colors.green : Colors.red, size: 20),
        ),
        title: Text(bundle.name, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Row(
          children: [
            _badge(bundle.typeLabel, Colors.deepPurple),
            const SizedBox(width: 6),
            if (!bundle.isActive) _badge('Inactive', Colors.grey),
          ],
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '${bundle.availableQuantity}',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: bundle.inStock ? Colors.green : Colors.red),
            ),
            Text('buildable', style: TextStyle(fontSize: 10, color: Colors.grey[600])),
          ],
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        children: [
          if (bundle.product != null)
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'SKU: ${bundle.product!.sku ?? '-'}  ·  \$${bundle.product!.sellingPrice.toStringAsFixed(2)}',
                style: TextStyle(color: Colors.grey[700], fontSize: 13),
              ),
            ),
          const SizedBox(height: 8),
          ...bundle.components.map(_buildComponentRow),
          if (bundle.components.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text('Open in web app to view components', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
            ),
        ],
      ),
    );
  }

  Widget _buildComponentRow(BundleComponent c) {
    final scarce = c.isRequired && c.componentAvailable < c.quantity;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(
            c.isRequired ? Icons.check_circle_outline : Icons.radio_button_unchecked,
            size: 16,
            color: scarce ? Colors.red : Colors.grey[500],
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '${c.name ?? 'Component'}  x${c.quantity}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 13, color: scarce ? Colors.red : null),
            ),
          ),
          Text('${c.componentAvailable} avail', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
        ],
      ),
    );
  }

  Widget _badge(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}
