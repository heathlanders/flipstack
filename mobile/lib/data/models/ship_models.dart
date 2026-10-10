/// Models for shipping workflow.

class ShipmentDetail {
  final String id;
  final String? shipmentNumber;
  final String status;
  final String? trackingNumber;
  final String? carrier;
  final String? labelUrl;
  final double? weightOz;
  final DateTime? shippedAt;
  final DateTime? estimatedDelivery;
  final ShipOrder? order;
  final ShipLocation? location;

  const ShipmentDetail({
    required this.id,
    this.shipmentNumber,
    required this.status,
    this.trackingNumber,
    this.carrier,
    this.labelUrl,
    this.weightOz,
    this.shippedAt,
    this.estimatedDelivery,
    this.order,
    this.location,
  });

  factory ShipmentDetail.fromJson(Map<String, dynamic> json) {
    return ShipmentDetail(
      id: json['id']?.toString() ?? '',
      shipmentNumber: json['shipment_number'],
      status: json['status'] ?? '',
      trackingNumber: json['tracking_number'],
      carrier: json['carrier'],
      labelUrl: json['label_url'],
      weightOz: _parseNullableDouble(json['weight_oz']),
      shippedAt: json['shipped_at'] != null
          ? DateTime.tryParse(json['shipped_at'])
          : null,
      estimatedDelivery: json['estimated_delivery'] != null
          ? DateTime.tryParse(json['estimated_delivery'])
          : null,
      order: json['order'] != null
          ? ShipOrder.fromJson(json['order'])
          : null,
      location: json['location'] != null
          ? ShipLocation.fromJson(json['location'])
          : null,
    );
  }

  // Convenience getters for nested order fields used by UI
  String? get marketplaceOrderId => order?.marketplaceOrderId;
  String? get buyerName => order?.buyerName;
  String? get shippingAddress {
    final city = order?.shippingCity;
    final state = order?.shippingState;
    if (city != null && state != null) return '$city, $state';
    return city ?? state;
  }

  String? get buyerCityState => shippingAddress;
  List<ShipOrderItem> get items => order?.items ?? [];

  static double? _parseNullableDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }
}

class ShipOrder {
  final String id;
  final String marketplaceOrderId;
  final String? buyerName;
  final String? shippingCity;
  final String? shippingState;
  final String priority;
  final String status;
  final List<ShipOrderItem> items;

  const ShipOrder({
    required this.id,
    required this.marketplaceOrderId,
    this.buyerName,
    this.shippingCity,
    this.shippingState,
    required this.priority,
    required this.status,
    required this.items,
  });

  factory ShipOrder.fromJson(Map<String, dynamic> json) {
    return ShipOrder(
      id: json['id']?.toString() ?? '',
      marketplaceOrderId: json['marketplace_order_id'] ?? '',
      buyerName: json['buyer_name'],
      shippingCity: json['shipping_city'],
      shippingState: json['shipping_state'],
      priority: json['priority'] ?? 'standard',
      status: json['status'] ?? '',
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => ShipOrderItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class ShipOrderItem {
  final String id;
  final String title;
  final String? sku;
  final int quantity;

  const ShipOrderItem({
    required this.id,
    required this.title,
    this.sku,
    required this.quantity,
  });

  factory ShipOrderItem.fromJson(Map<String, dynamic> json) {
    return ShipOrderItem(
      id: json['id']?.toString() ?? '',
      title: json['title'] ?? '',
      sku: json['sku'],
      quantity: json['quantity'] ?? 1,
    );
  }
}

class ShipLocation {
  final String id;
  final String name;
  final String? code;

  const ShipLocation({
    required this.id,
    required this.name,
    this.code,
  });

  factory ShipLocation.fromJson(Map<String, dynamic> json) {
    return ShipLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}

class ShipStats {
  final int readyToShip;
  final int shippedToday;
  final int inTransit;
  final int deliveredToday;

  const ShipStats({
    this.readyToShip = 0,
    this.shippedToday = 0,
    this.inTransit = 0,
    this.deliveredToday = 0,
  });

  factory ShipStats.fromJson(Map<String, dynamic> json) {
    return ShipStats(
      readyToShip: json['ready_to_ship'] ?? 0,
      shippedToday: json['shipped_today'] ?? 0,
      inTransit: json['in_transit'] ?? 0,
      deliveredToday: json['delivered_today'] ?? 0,
    );
  }
}
