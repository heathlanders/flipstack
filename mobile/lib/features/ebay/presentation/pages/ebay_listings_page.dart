import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/ebay_listing_models.dart';
import '../providers/ebay_listing_provider.dart';

class EbayListingsPage extends ConsumerWidget {
  const EbayListingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(ebayListingsProvider);
    final notifier = ref.read(ebayListingsProvider.notifier);

    ref.listen<EbayListingsState>(ebayListingsProvider, (prev, next) {
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
        title: const Text('eBay Listings'),
        backgroundColor: const Color(0xFF0064D2),
        foregroundColor: Colors.white,
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: () => notifier.refresh()),
        ],
      ),
      body: Column(
        children: [
          _buildFilters(state, notifier),
          Expanded(child: _buildContent(context, state, notifier)),
        ],
      ),
    );
  }

  Widget _buildFilters(EbayListingsState state, EbayListingsNotifier notifier) {
    return SizedBox(
      height: 48,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        children: [
          _chip('All', state.statusFilter == null, () => notifier.setStatusFilter(null)),
          _chip('Published', state.statusFilter == 'published', () => notifier.setStatusFilter('published')),
          _chip('Pending', state.statusFilter == 'pending', () => notifier.setStatusFilter('pending')),
          _chip('Errors', state.statusFilter == 'error', () => notifier.setStatusFilter('error')),
        ],
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        selectedColor: const Color(0xFF0064D2).withOpacity(0.15),
      ),
    );
  }

  Widget _buildContent(BuildContext context, EbayListingsState state, EbayListingsNotifier notifier) {
    if (state.isLoading && state.listings.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.listings.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            const Text('Error loading listings'),
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

    if (state.listings.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.sell_outlined, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            const Text('No listings'),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.listings.length,
        itemBuilder: (context, index) => _buildCard(context, state.listings[index], notifier),
      ),
    );
  }

  Widget _buildCard(BuildContext context, EbayListing listing, EbayListingsNotifier notifier) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(listing.product?.name ?? listing.ebaySku ?? 'Listing',
                      style: const TextStyle(fontWeight: FontWeight.w600), maxLines: 2, overflow: TextOverflow.ellipsis),
                ),
                _statusBadge(listing.status),
              ],
            ),
            if (listing.ebaySku != null) Text('SKU: ${listing.ebaySku}', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            const SizedBox(height: 6),
            Row(
              children: [
                if (listing.listedPrice != null) Text('\$${listing.listedPrice!.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(width: 12),
                Text('Qty: ${listing.availableQuantity}', style: TextStyle(color: Colors.grey[700])),
                const SizedBox(width: 12),
                Text('Sold: ${listing.soldQuantity}', style: TextStyle(color: Colors.grey[700])),
              ],
            ),
            if (listing.hasError && listing.lastErrorMessage != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(listing.lastErrorMessage!, style: const TextStyle(color: Colors.red, fontSize: 12), maxLines: 2, overflow: TextOverflow.ellipsis),
              ),
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (listing.canRetry)
                  TextButton.icon(
                    onPressed: () => notifier.retryListing(listing.id),
                    icon: const Icon(Icons.replay, size: 16),
                    label: const Text('Retry', style: TextStyle(fontSize: 13)),
                  ),
                PopupMenuButton<String>(
                  onSelected: (value) => notifier.updateListing(listing.id, value),
                  itemBuilder: (context) => const [
                    PopupMenuItem(value: 'quantity', child: Text('Push quantity')),
                    PopupMenuItem(value: 'price', child: Text('Push price')),
                    PopupMenuItem(value: 'both', child: Text('Push price + quantity')),
                  ],
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.sync, size: 16, color: Color(0xFF0064D2)),
                        SizedBox(width: 4),
                        Text('Sync', style: TextStyle(fontSize: 13, color: Color(0xFF0064D2))),
                      ],
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

  Widget _statusBadge(String status) {
    final Color color;
    switch (status) {
      case 'published':
        color = Colors.green;
        break;
      case 'pending':
      case 'inventory_created':
      case 'offer_created':
        color = Colors.orange;
        break;
      case 'error':
        color = Colors.red;
        break;
      case 'ended':
      case 'out_of_stock':
        color = Colors.grey;
        break;
      default:
        color = Colors.blueGrey;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(status, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}
