import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/ship_models.dart';
import '../providers/ship_provider.dart';

class ShipListPage extends ConsumerStatefulWidget {
  const ShipListPage({super.key});

  @override
  ConsumerState<ShipListPage> createState() => _ShipListPageState();
}

class _ShipListPageState extends ConsumerState<ShipListPage> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(shipListProvider);
    final notifier = ref.read(shipListProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Ship Queue'),
        backgroundColor: Colors.indigo,
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
          Expanded(
            child: _buildContent(state, notifier),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBar(ShipStats stats) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: Colors.indigo.shade50,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _statItem('Ready to Ship', stats.readyToShip.toString(), Colors.indigo),
          _statItem('Shipped Today', stats.shippedToday.toString(), Colors.blue),
          _statItem('In Transit', stats.inTransit.toString(), Colors.orange),
          _statItem('Delivered', stats.deliveredToday.toString(), Colors.green),
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
          style: TextStyle(fontSize: 11, color: Colors.grey[600]),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildContent(ShipListState state, ShipListNotifier notifier) {
    if (state.isLoading && state.shipments.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.shipments.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
            const SizedBox(height: 16),
            Text('Error loading shipments', style: Theme.of(context).textTheme.titleMedium),
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

    if (state.shipments.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.local_shipping_outlined, size: 64, color: Colors.green[300]),
            const SizedBox(height: 16),
            Text('No shipments pending', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text('All caught up!', style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => notifier.refresh(),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.shipments.length,
        itemBuilder: (context, index) => _buildShipmentCard(state.shipments[index]),
      ),
    );
  }

  Widget _buildShipmentCard(ShipmentDetail shipment) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => context.go('/shipping/${shipment.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: Order ID + Status
              Row(
                children: [
                  Expanded(
                    child: Text(
                      shipment.marketplaceOrderId ?? shipment.shipmentNumber ?? 'Shipment',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  _statusBadge(shipment.status),
                ],
              ),
              const SizedBox(height: 8),

              // Carrier + Tracking
              if (shipment.carrier != null || shipment.trackingNumber != null)
                Row(
                  children: [
                    if (shipment.carrier != null) ...[
                      Icon(Icons.local_shipping_outlined, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 4),
                      Text(
                        shipment.carrier!,
                        style: TextStyle(color: Colors.grey[600], fontSize: 13),
                      ),
                      const SizedBox(width: 16),
                    ],
                    if (shipment.trackingNumber != null) ...[
                      Icon(Icons.confirmation_number_outlined, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          shipment.trackingNumber!,
                          style: TextStyle(color: Colors.blue[700], fontSize: 13),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ],
                ),

              // Buyer + location
              if (shipment.buyerName != null) ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.person_outlined, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(
                      shipment.buyerName!,
                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                    ),
                    if (shipment.buyerCityState != null) ...[
                      const SizedBox(width: 8),
                      Text(
                        shipment.buyerCityState!,
                        style: TextStyle(color: Colors.grey[500], fontSize: 13),
                      ),
                    ],
                  ],
                ),
              ],

              const SizedBox(height: 12),

              // Action button
              if (shipment.status == 'ready_to_ship')
                Align(
                  alignment: Alignment.centerRight,
                  child: SizedBox(
                    height: 32,
                    child: ElevatedButton.icon(
                      onPressed: () => context.go('/shipping/${shipment.id}'),
                      icon: const Icon(Icons.check, size: 16),
                      label: const Text('Confirm Shipped', style: TextStyle(fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.indigo,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                      ),
                    ),
                  ),
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
      case 'ready_to_ship':
        color = Colors.indigo;
        label = 'Ready to Ship';
        break;
      case 'shipped':
        color = Colors.blue;
        label = 'Shipped';
        break;
      case 'in_transit':
        color = Colors.orange;
        label = 'In Transit';
        break;
      case 'delivered':
        color = Colors.green;
        label = 'Delivered';
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
