import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/message_models.dart';
import '../providers/message_provider.dart';

class InboxPage extends ConsumerWidget {
  const InboxPage({super.key});

  static const _marketplaceColors = {
    'ebay': Colors.blue,
    'shopify': Colors.green,
    'amazon': Colors.orange,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(inboxProvider);
    final notifier = ref.read(inboxProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: () => notifier.refresh()),
        ],
      ),
      body: Column(
        children: [
          _buildStatsBar(state.stats),
          _buildFilters(state, notifier),
          Expanded(child: _buildContent(context, state, notifier)),
        ],
      ),
    );
  }

  Widget _buildStatsBar(MessageStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.indigo.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Unread', stats.unread.toString(), Colors.red),
          _statItem('Total', stats.total.toString(), Colors.indigo),
          _statItem('eBay', (stats.byMarketplace['ebay']?.unread ?? 0).toString(), Colors.blue),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value, Color color) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color)),
        Text(label, style: TextStyle(fontSize: 12, color: Colors.grey[600])),
      ],
    );
  }

  Widget _buildFilters(InboxState state, InboxNotifier notifier) {
    return SizedBox(
      height: 48,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        children: [
          _chip('All', state.statusFilter == null && state.marketplaceFilter == null, () {
            notifier.setStatusFilter(null);
            notifier.setMarketplaceFilter(null);
          }),
          _chip('Unread', state.statusFilter == 'unread', () => notifier.setStatusFilter(state.statusFilter == 'unread' ? null : 'unread')),
          _chip('eBay', state.marketplaceFilter == 'ebay', () => notifier.setMarketplaceFilter(state.marketplaceFilter == 'ebay' ? null : 'ebay')),
          _chip('Shopify', state.marketplaceFilter == 'shopify', () => notifier.setMarketplaceFilter(state.marketplaceFilter == 'shopify' ? null : 'shopify')),
          _chip('Amazon', state.marketplaceFilter == 'amazon', () => notifier.setMarketplaceFilter(state.marketplaceFilter == 'amazon' ? null : 'amazon')),
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
        selectedColor: Colors.indigo.shade100,
      ),
    );
  }

  Widget _buildContent(BuildContext context, InboxState state, InboxNotifier notifier) {
    if (state.isLoading && state.messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.messages.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            const Text('Error loading messages'),
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

    if (state.messages.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.mail_outline, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            const Text('No messages'),
            const SizedBox(height: 8),
            Text('Buyer messages from your marketplaces appear here.', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.separated(
        itemCount: state.messages.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) => _buildTile(context, state.messages[index]),
      ),
    );
  }

  Widget _buildTile(BuildContext context, MarketplaceMessage msg) {
    final unread = msg.isUnread;
    final mpColor = _marketplaceColors[msg.marketplace] ?? Colors.grey;

    return ListTile(
      onTap: () => context.go('/messages/${msg.id}'),
      leading: CircleAvatar(
        backgroundColor: mpColor.withOpacity(0.15),
        child: Icon(Icons.storefront, color: mpColor, size: 20),
      ),
      title: Row(
        children: [
          if (unread)
            Container(
              width: 8,
              height: 8,
              margin: const EdgeInsets.only(right: 6),
              decoration: const BoxDecoration(color: Colors.blue, shape: BoxShape.circle),
            ),
          Expanded(
            child: Text(
              msg.senderName ?? 'Buyer',
              style: TextStyle(fontWeight: unread ? FontWeight.bold : FontWeight.normal),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          _mpBadge(msg.marketplace, mpColor),
        ],
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (msg.subject != null)
            Text(msg.subject!, style: TextStyle(fontWeight: unread ? FontWeight.w600 : FontWeight.normal), maxLines: 1, overflow: TextOverflow.ellipsis),
          if (msg.body != null)
            Text(msg.body!, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.grey[600], fontSize: 13)),
          if (msg.product != null || msg.order != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Wrap(
                spacing: 6,
                children: [
                  if (msg.product != null) _tag(msg.product!.name, Colors.purple),
                  if (msg.order != null) _tag(msg.order!.marketplaceOrderId ?? 'Order', Colors.blueGrey),
                ],
              ),
            ),
        ],
      ),
      isThreeLine: true,
      trailing: msg.isReplied ? const Icon(Icons.reply, size: 16, color: Colors.green) : null,
    );
  }

  Widget _mpBadge(String marketplace, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(marketplace, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
    );
  }

  Widget _tag(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(3)),
      child: Text(label, style: TextStyle(color: color, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
    );
  }
}
