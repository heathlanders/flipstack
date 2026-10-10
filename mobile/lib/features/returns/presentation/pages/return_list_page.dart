import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/return_models.dart';
import '../providers/return_provider.dart';

class ReturnListPage extends ConsumerStatefulWidget {
  const ReturnListPage({super.key});

  @override
  ConsumerState<ReturnListPage> createState() => _ReturnListPageState();
}

class _ReturnListPageState extends ConsumerState<ReturnListPage> {
  static const _statusFilters = [
    null,
    'open',
    'approved',
    'received',
    'inspected',
    'refunded',
    'denied',
  ];

  static const _statusLabels = [
    'All',
    'Open',
    'Approved',
    'Received',
    'Inspected',
    'Refunded',
    'Denied',
  ];

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(returnListProvider);
    final notifier = ref.read(returnListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Returns'),
        backgroundColor: Colors.deepOrange,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.refresh(),
          ),
        ],
      ),
      body: Column(
        children: [
          _buildStatsBar(state.stats),
          _buildStatusFilters(state.statusFilter, notifier),
          Expanded(
            child: _buildContent(state, notifier),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBar(ReturnStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.deepOrange.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Open', stats.open.toString(), Colors.deepOrange),
          _statItem('Awaiting\nReceipt', stats.awaitingReceipt.toString(), Colors.orange),
          _statItem('Awaiting\nInspection', stats.awaitingInspection.toString(), Colors.blue),
          _statItem('Refunded\nThis Month', '\$${stats.refundedThisMonth.toStringAsFixed(0)}', Colors.green),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value, Color color) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: TextStyle(fontSize: 10, color: Colors.grey[600]),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildStatusFilters(String? currentFilter, ReturnListNotifier notifier) {
    return Container(
      height: 48,
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        itemCount: _statusFilters.length,
        itemBuilder: (context, index) {
          final filter = _statusFilters[index];
          final label = _statusLabels[index];
          final isSelected = currentFilter == filter;

          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: FilterChip(
              label: Text(label),
              selected: isSelected,
              onSelected: (_) => notifier.setStatusFilter(filter),
              selectedColor: Colors.deepOrange.shade100,
              checkmarkColor: Colors.deepOrange,
              labelStyle: TextStyle(
                fontSize: 12,
                color: isSelected ? Colors.deepOrange : Colors.grey[700],
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildContent(ReturnListState state, ReturnListNotifier notifier) {
    if (state.isLoading && state.returns.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.returns.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading returns', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(state.error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => notifier.refresh(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state.returns.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.assignment_return_outlined, size: 64, color: Colors.green[300]),
            const SizedBox(height: 16),
            Text('No returns found', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('No returns match the current filter', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.returns.length,
        itemBuilder: (context, index) => _buildReturnCard(state.returns[index]),
      ),
    );
  }

  Widget _buildReturnCard(ReturnRequest returnRequest) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/returns/${returnRequest.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: RMA + Status
              Row(
                children: [
                  Expanded(
                    child: Text(
                      returnRequest.rmaNumber,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  _statusBadge(returnRequest.status),
                ],
              ),
              const SizedBox(height: 8),

              // Order ID
              if (returnRequest.marketplaceOrderId != null)
                Row(
                  children: [
                    Icon(Icons.receipt_outlined, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(
                      'Order: ${returnRequest.marketplaceOrderId}',
                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                    ),
                  ],
                ),

              // Item title
              if (returnRequest.itemTitle != null) ...[
                const SizedBox(height: 4),
                Text(
                  returnRequest.itemTitle!,
                  style: const TextStyle(fontSize: 14),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],

              // Reason
              if (returnRequest.reason != null) ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.info_outlined, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        returnRequest.reason!,
                        style: TextStyle(color: Colors.grey[600], fontSize: 13),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],

              // Created date
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.schedule, size: 16, color: Colors.grey[600]),
                  const SizedBox(width: 4),
                  Text(
                    _formatDate(returnRequest.createdAt),
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                ],
              ),
            ],
          ),
        ),
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

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final diff = now.difference(date);
    if (diff.inDays == 0) return 'Today';
    if (diff.inDays == 1) return 'Yesterday';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${date.month}/${date.day}/${date.year}';
  }
}
