import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/part_out_models.dart';
import '../providers/part_out_provider.dart';

class PartOutListPage extends ConsumerStatefulWidget {
  const PartOutListPage({super.key});

  @override
  ConsumerState<PartOutListPage> createState() => _PartOutListPageState();
}

class _PartOutListPageState extends ConsumerState<PartOutListPage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(partOutListProvider);
    final notifier = ref.read(partOutListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Part-Out'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
        actions: [
          // My orders toggle
          IconButton(
            icon: Icon(
              state.myOrdersOnly ? Icons.person : Icons.people_outlined,
            ),
            tooltip: state.myOrdersOnly ? 'Show all' : 'My orders only',
            onPressed: () => notifier.toggleMyOrders(),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => notifier.refresh(),
          ),
        ],
      ),
      body: Column(
        children: [
          // Stats bar
          _buildStatsBar(state.stats),

          // Location filter
          if (state.locations.isNotEmpty) _buildLocationFilter(state, notifier),

          // Content
          Expanded(
            child: _buildContent(state, notifier),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBar(PartOutStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.teal.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Draft', stats.draft.toString(), Colors.blue),
          _statItem('In Progress', stats.inProgress.toString(), Colors.orange),
          _statItem('Completed', stats.completed.toString(), Colors.green),
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
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
        ),
      ],
    );
  }

  Widget _buildLocationFilter(PartOutListState state, PartOutListNotifier notifier) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: DropdownButtonFormField<String?>(
        value: state.selectedLocationId,
        decoration: InputDecoration(
          labelText: 'Filter by location',
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          isDense: true,
        ),
        items: [
          const DropdownMenuItem<String?>(
            value: null,
            child: Text('All Locations'),
          ),
          ...state.locations.map(
            (loc) => DropdownMenuItem<String?>(
              value: loc.id,
              child: Text('${loc.name} (${loc.code ?? ""})'),
            ),
          ),
        ],
        onChanged: (value) => notifier.setLocationFilter(value),
      ),
    );
  }

  Widget _buildContent(PartOutListState state, PartOutListNotifier notifier) {
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
            ElevatedButton(
              onPressed: () => notifier.refresh(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state.workOrders.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.content_cut, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text('No work orders found', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('Create a part-out work order from the web app.', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.workOrders.length,
        itemBuilder: (context, index) => _buildWorkOrderCard(state.workOrders[index], notifier),
      ),
    );
  }

  Widget _buildWorkOrderCard(PartOutWorkOrder wo, PartOutListNotifier notifier) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/part-out/${wo.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: WO number + status badge
              Row(
                children: [
                  Expanded(
                    child: Text(
                      wo.workOrderNumber,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  _statusBadge(wo.status),
                ],
              ),
              const SizedBox(height: 8),

              // Source product name + SKU
              if (wo.sourceProduct != null) ...[
                Text(
                  wo.sourceProduct!.name,
                  style: const TextStyle(fontSize: 14),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (wo.sourceProduct!.sku != null)
                  Text(
                    'SKU: ${wo.sourceProduct!.sku}',
                    style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                  ),
                const SizedBox(height: 8),
              ],

              // Location chip + component count
              Row(
                children: [
                  if (wo.location != null) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.grey[100],
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.location_on, size: 14, color: Colors.grey[600]),
                          const SizedBox(width: 4),
                          Text(
                            wo.location!.name,
                            style: TextStyle(fontSize: 12, color: Colors.grey[700]),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Text(
                    '${wo.items.length} components',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Progress bar
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: wo.progress.progressPercent,
                        backgroundColor: Colors.grey[200],
                        color: wo.progress.pending == 0
                            ? Colors.green
                            : Colors.teal,
                        minHeight: 6,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    '${wo.progress.extracted}/${wo.progress.total} extracted',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ],
              ),

              // Skipped count
              if (wo.progress.skipped > 0) ...[
                const SizedBox(height: 4),
                Text(
                  '${wo.progress.skipped} skipped',
                  style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                ),
              ],

              const SizedBox(height: 8),

              // Action button
              Row(
                children: [
                  const Spacer(),
                  if (wo.isDraft)
                    SizedBox(
                      height: 32,
                      child: ElevatedButton(
                        onPressed: () async {
                          final success = await notifier.startWorkOrder(wo.id);
                          if (success && mounted) {
                            context.go('/part-out/${wo.id}');
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.teal,
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        child: const Text('Start', style: TextStyle(fontSize: 13)),
                      ),
                    ),
                  if (wo.isInProgress)
                    SizedBox(
                      height: 32,
                      child: ElevatedButton(
                        onPressed: () => context.go('/part-out/${wo.id}'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                        ),
                        child: const Text('Continue', style: TextStyle(fontSize: 13)),
                      ),
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
      case 'draft':
        color = Colors.blue;
        label = 'Draft';
        break;
      case 'in_progress':
        color = Colors.orange;
        label = 'In Progress';
        break;
      case 'completed':
        color = Colors.green;
        label = 'Completed';
        break;
      case 'cancelled':
        color = Colors.red;
        label = 'Cancelled';
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
}
