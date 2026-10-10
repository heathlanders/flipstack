import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/analytics_provider.dart';
import '../../../../data/models/analytics_models.dart';

class AnalyticsPage extends ConsumerStatefulWidget {
  const AnalyticsPage({super.key});

  @override
  ConsumerState<AnalyticsPage> createState() => _AnalyticsPageState();
}

class _AnalyticsPageState extends ConsumerState<AnalyticsPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _tabController.addListener(_onTabChanged);
  }

  void _onTabChanged() {
    if (!_tabController.indexIsChanging) return;
    final notifier = ref.read(analyticsProvider.notifier);
    final state = ref.read(analyticsProvider);
    if (_tabController.index == 1 && state.abcAnalysis == null && !state.isLoadingAbc) {
      notifier.loadABCAnalysis();
    } else if (_tabController.index == 2 &&
        state.turnoverAnalysis == null &&
        !state.isLoadingTurnover) {
      notifier.loadTurnoverAnalysis();
    }
  }

  @override
  void dispose() {
    _tabController.removeListener(_onTabChanged);
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Analytics'),
        backgroundColor: Colors.deepPurple,
        foregroundColor: Colors.white,
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Overview'),
            Tab(text: 'ABC Analysis'),
            Tab(text: 'Turnover'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _OverviewTab(),
          _ABCTab(),
          _TurnoverTab(),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Overview Tab
// ---------------------------------------------------------------------------
class _OverviewTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(analyticsProvider);

    if (state.isLoading && state.analytics == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.analytics == null) {
      return _buildError(context, state.error!);
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(analyticsProvider.notifier).refresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildKpiCards(context, state),
          const SizedBox(height: 20),
          if (state.stockHealth != null) ...[
            _buildStockHealthBar(context, state.stockHealth!),
            const SizedBox(height: 20),
          ],
          if (state.topPerformers.isNotEmpty)
            _buildTopPerformers(context, state.topPerformers),
          if (state.analytics != null &&
              state.analytics!.movementSummary.isNotEmpty) ...[
            const SizedBox(height: 20),
            _buildMovementSection(context, state.analytics!.movementSummary),
          ],
          if (state.valuation != null &&
              state.valuation!.byLocation.isNotEmpty) ...[
            const SizedBox(height: 20),
            _buildValuationSection(context, state.valuation!),
          ],
        ],
      ),
    );
  }

  Widget _buildKpiCards(BuildContext context, AnalyticsState state) {
    final analytics = state.analytics;
    final valuation = state.valuation;
    final health = state.stockHealth;

    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [
        _kpiCard(
          context,
          title: 'Total Products',
          value: '${health?.totalProducts ?? analytics?.totalProducts ?? 0}',
          icon: Icons.inventory_2_outlined,
          color: Colors.blue,
        ),
        _kpiCard(
          context,
          title: 'Health Score',
          value: '${(health?.healthScore ?? 0).toStringAsFixed(0)}%',
          icon: Icons.health_and_safety_outlined,
          color: _healthColor(health?.healthScore ?? 0),
        ),
        _kpiCard(
          context,
          title: 'Inventory Value',
          value: '\$${(valuation?.totalValue ?? 0).toStringAsFixed(2)}',
          icon: Icons.attach_money,
          color: Colors.green,
        ),
        _kpiCard(
          context,
          title: 'Low Stock',
          value: '${health?.lowStock ?? analytics?.lowStockAlerts ?? 0}',
          icon: Icons.warning_amber_outlined,
          color: Colors.orange,
        ),
      ],
    );
  }

  Color _healthColor(double score) {
    if (score >= 80) return Colors.green;
    if (score >= 50) return Colors.orange;
    return Colors.red;
  }

  Widget _kpiCard(
    BuildContext context, {
    required String title,
    required String value,
    required IconData icon,
    required Color color,
  }) {
    final cardWidth = (MediaQuery.of(context).size.width - 44) / 2;
    return SizedBox(
      width: cardWidth,
      child: Card(
        elevation: 2,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: color, size: 28),
              const SizedBox(height: 8),
              Text(
                value,
                style: Theme.of(context)
                    .textTheme
                    .titleLarge
                    ?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 4),
              Text(
                title,
                style: TextStyle(color: Colors.grey[600], fontSize: 13),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStockHealthBar(BuildContext context, StockHealth health) {
    final total = health.totalProducts;
    if (total == 0) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Stock Health',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: SizedBox(
                    height: 24,
                    child: Row(
                      children: [
                        _healthSegment(health.inStock / total, Colors.green),
                        _healthSegment(health.lowStock / total, Colors.orange),
                        _healthSegment(health.outOfStock / total, Colors.red),
                        _healthSegment(
                            health.overstocked / total, Colors.blue),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _legendItem('In Stock', Colors.green, health.inStock),
                    _legendItem('Low', Colors.orange, health.lowStock),
                    _legendItem('Out', Colors.red, health.outOfStock),
                    _legendItem('Over', Colors.blue, health.overstocked),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _healthSegment(double fraction, Color color) {
    if (fraction <= 0) return const SizedBox.shrink();
    return Expanded(
      flex: (fraction * 100).round().clamp(1, 100),
      child: Container(color: color),
    );
  }

  Widget _legendItem(String label, Color color, int count) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 10, color: color),
        const SizedBox(width: 4),
        Text('$label ($count)', style: const TextStyle(fontSize: 11)),
      ],
    );
  }

  Widget _buildTopPerformers(BuildContext context, List<TopPerformer> performers) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Top 5 Performers',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        Card(
          child: Column(
            children: performers.asMap().entries.map((entry) {
              final idx = entry.key;
              final p = entry.value;
              return ListTile(
                leading: CircleAvatar(
                  backgroundColor: Colors.deepPurple.withOpacity(0.1),
                  child: Text(
                    '#${idx + 1}',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Colors.deepPurple,
                    ),
                  ),
                ),
                title: Text(p.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: Text(p.sku),
                trailing: Text(
                  '\$${p.metricValue.toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  Widget _buildMovementSection(
    BuildContext context,
    Map<String, dynamic> movementSummary,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Movement Summary',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: movementSummary.entries.map((entry) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _formatKey(entry.key),
                        style: TextStyle(color: Colors.grey[700]),
                      ),
                      Text(
                        '${entry.value}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildValuationSection(
    BuildContext context,
    InventoryValuation valuation,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Valuation by Location',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        ...valuation.byLocation.map((loc) {
          return Card(
            child: ListTile(
              leading: const Icon(Icons.location_on_outlined,
                  color: Colors.deepPurple),
              title: Text(
                loc.locationName.isNotEmpty ? loc.locationName : 'Unknown',
              ),
              subtitle: Text('${loc.units} units'),
              trailing: Text(
                '\$${loc.value.toStringAsFixed(2)}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildError(BuildContext context, String error) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Failed to load analytics',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(error, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }

  String _formatKey(String key) {
    return key
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
        .join(' ');
  }
}

// ---------------------------------------------------------------------------
// ABC Analysis Tab
// ---------------------------------------------------------------------------
class _ABCTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(analyticsProvider);

    // Trigger load if not yet loaded
    if (state.abcAnalysis == null && !state.isLoadingAbc && state.abcError == null) {
      Future.microtask(() => ref.read(analyticsProvider.notifier).loadABCAnalysis());
    }

    if (state.isLoadingAbc && state.abcAnalysis == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.abcError != null && state.abcAnalysis == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
              const SizedBox(height: 16),
              Text('Failed to load ABC analysis',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(state.abcError!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () =>
                    ref.read(analyticsProvider.notifier).loadABCAnalysis(),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final abc = state.abcAnalysis;
    if (abc == null) return const SizedBox.shrink();

    return RefreshIndicator(
      onRefresh: () => ref.read(analyticsProvider.notifier).loadABCAnalysis(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Summary cards
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _abcSummaryCard(context, 'A Items', abc.aCount,
                  '\$${abc.aValue.toStringAsFixed(0)}', Colors.green),
              _abcSummaryCard(context, 'B Items', abc.bCount,
                  '\$${abc.bValue.toStringAsFixed(0)}', Colors.blue),
              _abcSummaryCard(context, 'C Items', abc.cCount,
                  '\$${abc.cValue.toStringAsFixed(0)}', Colors.orange),
            ],
          ),
          const SizedBox(height: 20),

          Text(
            'Products',
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),

          ...abc.products.map((p) => _abcProductTile(context, p)),
        ],
      ),
    );
  }

  Widget _abcSummaryCard(
    BuildContext context,
    String label,
    int count,
    String value,
    Color color,
  ) {
    final cardWidth = (MediaQuery.of(context).size.width - 52) / 3;
    return SizedBox(
      width: cardWidth,
      child: Card(
        elevation: 2,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: color.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  label,
                  style: TextStyle(
                      color: color,
                      fontWeight: FontWeight.bold,
                      fontSize: 13),
                ),
              ),
              const SizedBox(height: 8),
              Text('$count',
                  style: const TextStyle(
                      fontSize: 20, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Text(value,
                  style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            ],
          ),
        ),
      ),
    );
  }

  Widget _abcProductTile(BuildContext context, ABCProduct product) {
    final color = switch (product.abcClass) {
      'A' => Colors.green,
      'B' => Colors.blue,
      _ => Colors.orange,
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 6),
      child: ListTile(
        leading: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: color.withOpacity(0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          alignment: Alignment.center,
          child: Text(
            product.abcClass,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.bold,
              fontSize: 16,
            ),
          ),
        ),
        title: Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis),
        subtitle: Text(
          '${product.sku}  --  ${product.percentageOfTotal.toStringAsFixed(1)}% of total',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: Text(
          '\$${product.metricValue.toStringAsFixed(2)}',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Turnover Tab
// ---------------------------------------------------------------------------
class _TurnoverTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(analyticsProvider);

    // Trigger load if not yet loaded
    if (state.turnoverAnalysis == null &&
        !state.isLoadingTurnover &&
        state.turnoverError == null) {
      Future.microtask(
          () => ref.read(analyticsProvider.notifier).loadTurnoverAnalysis());
    }

    if (state.isLoadingTurnover && state.turnoverAnalysis == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.turnoverError != null && state.turnoverAnalysis == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
              const SizedBox(height: 16),
              Text('Failed to load turnover data',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(state.turnoverError!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref
                    .read(analyticsProvider.notifier)
                    .loadTurnoverAnalysis(),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final turnover = state.turnoverAnalysis;
    if (turnover == null) return const SizedBox.shrink();

    return RefreshIndicator(
      onRefresh: () =>
          ref.read(analyticsProvider.notifier).loadTurnoverAnalysis(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Summary cards
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _turnoverSummaryCard(
                context,
                title: 'Avg Turnover',
                value: turnover.averageTurnover.toStringAsFixed(2),
                icon: Icons.loop,
                color: Colors.teal,
              ),
              _turnoverSummaryCard(
                context,
                title: 'Total Revenue',
                value: '\$${turnover.totalRevenue.toStringAsFixed(0)}',
                icon: Icons.attach_money,
                color: Colors.green,
              ),
              _turnoverSummaryCard(
                context,
                title: 'Units Sold',
                value: '${turnover.totalUnitsSold}',
                icon: Icons.shopping_cart_outlined,
                color: Colors.blue,
              ),
            ],
          ),
          const SizedBox(height: 20),

          Text(
            'Products by Turnover',
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),

          ...turnover.products.map((p) => _turnoverProductTile(context, p)),
        ],
      ),
    );
  }

  Widget _turnoverSummaryCard(
    BuildContext context, {
    required String title,
    required String value,
    required IconData icon,
    required Color color,
  }) {
    final cardWidth = (MediaQuery.of(context).size.width - 52) / 3;
    return SizedBox(
      width: cardWidth,
      child: Card(
        elevation: 2,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(height: 6),
              Text(
                value,
                style:
                    const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 4),
              Text(title,
                  style: TextStyle(fontSize: 11, color: Colors.grey[600])),
            ],
          ),
        ),
      ),
    );
  }

  Widget _turnoverProductTile(BuildContext context, TurnoverProduct product) {
    return Card(
      margin: const EdgeInsets.only(bottom: 6),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    product.name,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Text(
                  'Turnover: ${product.annualizedTurnover.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Colors.teal,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(product.sku, style: TextStyle(color: Colors.grey[600], fontSize: 13)),
            const SizedBox(height: 8),
            Row(
              children: [
                _miniStat('Stock', '${product.stockQuantity}'),
                _miniStat('Sold', '${product.unitsSold}'),
                _miniStat('DSI', '${product.daysSalesInventory.toStringAsFixed(0)}d'),
                _miniStat('Revenue', '\$${product.revenue.toStringAsFixed(0)}'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _miniStat(String label, String value) {
    return Expanded(
      child: Column(
        children: [
          Text(value,
              style:
                  const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
          Text(label,
              style: TextStyle(fontSize: 10, color: Colors.grey[500])),
        ],
      ),
    );
  }
}
