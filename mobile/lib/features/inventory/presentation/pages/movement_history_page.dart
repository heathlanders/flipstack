import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/inventory_ops_models.dart';
import '../providers/movement_provider.dart';

class MovementHistoryPage extends ConsumerStatefulWidget {
  const MovementHistoryPage({super.key});

  @override
  ConsumerState<MovementHistoryPage> createState() => _MovementHistoryPageState();
}

class _MovementHistoryPageState extends ConsumerState<MovementHistoryPage> {
  final ScrollController _scrollController = ScrollController();

  static const _movementTypes = [
    'all',
    'adjustment',
    'transfer_in',
    'transfer_out',
    'purchase',
    'sale',
    'return',
    'damaged',
  ];

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(movementHistoryProvider.notifier).loadMore();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(movementHistoryProvider);
    final notifier = ref.read(movementHistoryProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Movement History'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          // Filter bar
          _buildFilterBar(state, notifier),

          // Movement list
          Expanded(
            child: _buildBody(state, notifier),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterBar(MovementHistoryState state, MovementHistoryNotifier notifier) {
    final selectedType = state.typeFilter ?? 'all';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Icon(Icons.filter_list, size: 20, color: Colors.grey[600]),
          const SizedBox(width: 8),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: selectedType,
                isExpanded: true,
                isDense: true,
                style: TextStyle(fontSize: 14, color: Colors.grey[800]),
                items: _movementTypes.map((type) {
                  return DropdownMenuItem(
                    value: type,
                    child: Text(_formatTypeName(type)),
                  );
                }).toList(),
                onChanged: (value) => notifier.setTypeFilter(value),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(MovementHistoryState state, MovementHistoryNotifier notifier) {
    if (state.isLoading && state.movements.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.movements.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
              const SizedBox(height: 16),
              Text('Failed to load movements',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(state.error!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => notifier.refresh(),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    if (state.movements.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.swap_vert, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text('No movements found',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              state.typeFilter != null
                  ? 'Try a different filter'
                  : 'Movements will appear here as inventory changes',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(8),
        itemCount: state.movements.length + (state.hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index >= state.movements.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          return _buildMovementTile(state.movements[index]);
        },
      ),
    );
  }

  Widget _buildMovementTile(InventoryMovement movement) {
    final isPositive = movement.quantity > 0;
    final typeColor = _typeColor(movement.type);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top row: type badge + quantity
            Row(
              children: [
                _typeBadge(movement.type, typeColor),
                const Spacer(),
                Text(
                  '${isPositive ? "+" : ""}${movement.quantity}',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 18,
                    color: isPositive ? Colors.green[700] : Colors.red[700],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),

            // Product info (if available from reason/context)
            if (movement.productId.isNotEmpty)
              Text(
                'Product: ${movement.productId}',
                style: TextStyle(fontSize: 13, color: Colors.grey[700]),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),

            // Balance change
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(Icons.swap_horiz, size: 14, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text(
                  'Balance: ${movement.balanceBefore} -> ${movement.balanceAfter}',
                  style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                ),
              ],
            ),

            // Reason
            if (movement.reason != null && movement.reason!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.notes, size: 14, color: Colors.grey[500]),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      movement.reason!,
                      style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ],

            // Bottom row: user + timestamp
            const SizedBox(height: 8),
            Row(
              children: [
                if (movement.createdByName != null) ...[
                  Icon(Icons.person_outline, size: 14, color: Colors.grey[500]),
                  const SizedBox(width: 4),
                  Text(
                    movement.createdByName!,
                    style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                  ),
                  const Spacer(),
                ] else
                  const Spacer(),
                Icon(Icons.access_time, size: 14, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text(
                  _formatTimestamp(movement.createdAt),
                  style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _typeBadge(String type, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        _formatTypeName(type),
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w600,
          fontSize: 12,
        ),
      ),
    );
  }

  Color _typeColor(String type) {
    switch (type.toLowerCase()) {
      case 'adjustment':
        return Colors.blue;
      case 'transfer_in':
        return Colors.green;
      case 'transfer_out':
        return Colors.orange;
      case 'purchase':
        return Colors.teal;
      case 'sale':
        return Colors.purple;
      case 'return':
        return Colors.indigo;
      case 'damaged':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _formatTypeName(String type) {
    if (type == 'all') return 'All Types';
    return type
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
        .join(' ');
  }

  String _formatTimestamp(DateTime date) {
    final diff = DateTime.now().difference(date);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${date.month}/${date.day}/${date.year}';
  }
}
