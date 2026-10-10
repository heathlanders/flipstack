import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/labeling_models.dart';
import '../providers/labeling_provider.dart';

class LabelingListPage extends ConsumerWidget {
  const LabelingListPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(labelingListProvider);
    final notifier = ref.read(labelingListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Labeling'),
        backgroundColor: Colors.brown,
        foregroundColor: Colors.white,
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: () => notifier.refresh()),
        ],
      ),
      body: Column(
        children: [
          _buildStatsBar(state.stats),
          Expanded(child: _buildContent(context, state, notifier)),
        ],
      ),
    );
  }

  Widget _buildStatsBar(LabelingStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.brown.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Open', stats.open.toString(), Colors.blue),
          _statItem('In Progress', stats.inProgress.toString(), Colors.orange),
          _statItem('Pending Label', stats.itemsPendingLabel.toString(), Colors.brown),
          _statItem('Done Today', stats.completedToday.toString(), Colors.green),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value, Color color) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color)),
        Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
      ],
    );
  }

  Widget _buildContent(BuildContext context, LabelingListState state, LabelingListNotifier notifier) {
    if (state.isLoading && state.workOrders.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.workOrders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading work orders', style: Theme.of(context).textTheme.titleMedium),
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

    if (state.workOrders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.label_outline, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text('No labeling work orders', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('Work orders appear after receiving or intake.', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.workOrders.length,
        itemBuilder: (context, index) => _buildCard(context, state.workOrders[index]),
      ),
    );
  }

  Widget _buildCard(BuildContext context, LabelingWorkOrder wo) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/labeling/${wo.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(wo.workOrderNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16))),
                  if (wo.priority == 'urgent' || wo.priority == 'high') _priorityBadge(wo.priority),
                  const SizedBox(width: 6),
                  _statusBadge(wo.status),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  if (wo.location != null) ...[
                    Icon(Icons.location_on, size: 14, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(wo.location!.name, style: TextStyle(fontSize: 12, color: Colors.grey[700])),
                    const SizedBox(width: 12),
                  ],
                  Text('${wo.itemsCount} items', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: wo.totalItems > 0 ? wo.shelvedItems / wo.totalItems : 0,
                        backgroundColor: Colors.grey[200],
                        color: wo.shelvedItems >= wo.totalItems ? Colors.green : Colors.brown,
                        minHeight: 6,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text('${wo.shelvedItems}/${wo.totalItems} shelved', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _priorityBadge(String priority) {
    final color = priority == 'urgent' ? Colors.red : Colors.deepOrange;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(priority.toUpperCase(), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold)),
    );
  }

  Widget _statusBadge(String status) {
    final Color color;
    final String label;
    switch (status) {
      case 'open':
        color = Colors.blue;
        label = 'Open';
        break;
      case 'in_progress':
        color = Colors.orange;
        label = 'In Progress';
        break;
      case 'completed':
        color = Colors.green;
        label = 'Completed';
        break;
      case 'partially_completed':
        color = Colors.teal;
        label = 'Partial';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}
