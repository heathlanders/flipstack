/// Models for packing workflow.

class PackageDimensions {
  final double? weightOz;
  final double? length;
  final double? width;
  final double? height;

  const PackageDimensions({
    this.weightOz,
    this.length,
    this.width,
    this.height,
  });

  Map<String, dynamic> toJson() {
    final data = <String, dynamic>{};
    if (weightOz != null) data['weight_oz'] = weightOz;
    if (length != null) data['length'] = length;
    if (width != null) data['width'] = width;
    if (height != null) data['height'] = height;
    return data;
  }
}

class PackOrder {
  final String id;
  final String marketplaceOrderId;
  final String marketplace;
  final String status;
  final String priority;
  final DateTime? shipByDate;
  final bool isOverdue;
  final String? buyerName;
  final double totalAmount;
  final int itemsCount;
  final PackLocation? location;
  final List<PackOrderItem> items;
  final PackShipment? shipment;
  final String? shippingAddress;

  const PackOrder({
    required this.id,
    required this.marketplaceOrderId,
    required this.marketplace,
    required this.status,
    required this.priority,
    this.shipByDate,
    required this.isOverdue,
    this.buyerName,
    required this.totalAmount,
    required this.itemsCount,
    this.location,
    required this.items,
    this.shipment,
    this.shippingAddress,
  });

  factory PackOrder.fromJson(Map<String, dynamic> json) {
    return PackOrder(
      id: json['id']?.toString() ?? '',
      marketplaceOrderId: json['marketplace_order_id'] ?? '',
      marketplace: json['marketplace'] ?? '',
      status: json['status'] ?? '',
      priority: json['priority'] ?? 'standard',
      shipByDate: json['ship_by_date'] != null
          ? DateTime.tryParse(json['ship_by_date'])
          : null,
      isOverdue: json['is_overdue'] ?? false,
      buyerName: json['buyer_name'],
      totalAmount: _parseDouble(json['total_amount']),
      itemsCount: json['items_count'] ?? 0,
      location: json['location'] != null
          ? PackLocation.fromJson(json['location'])
          : null,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => PackOrderItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      shipment: json['shipment'] != null
          ? PackShipment.fromJson(json['shipment'])
          : null,
      shippingAddress: json['shipping_address'],
    );
  }

  // Convenience getters that delegate to shipment
  String? get trackingNumber => shipment?.trackingNumber;
  String? get labelUrl => shipment?.labelUrl;
  int get itemCount => itemsCount;

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class PackOrderItem {
  final String id;
  final String? sku;
  final String title;
  final int quantity;
  final String? pickStatus;
  final String? imageUrl;

  const PackOrderItem({
    required this.id,
    this.sku,
    required this.title,
    required this.quantity,
    this.pickStatus,
    this.imageUrl,
  });

  factory PackOrderItem.fromJson(Map<String, dynamic> json) {
    return PackOrderItem(
      id: json['id']?.toString() ?? '',
      sku: json['sku'],
      title: json['title'] ?? '',
      quantity: json['quantity'] ?? 1,
      pickStatus: json['pick_status'],
      imageUrl: json['image_url'],
    );
  }
}

class PackShipment {
  final String id;
  final String status;
  final double? weightOz;
  final double? length;
  final double? width;
  final double? height;
  final String? trackingNumber;
  final String? labelUrl;
  final String? carrier;

  const PackShipment({
    required this.id,
    required this.status,
    this.weightOz,
    this.length,
    this.width,
    this.height,
    this.trackingNumber,
    this.labelUrl,
    this.carrier,
  });

  factory PackShipment.fromJson(Map<String, dynamic> json) {
    return PackShipment(
      id: json['id']?.toString() ?? '',
      status: json['status'] ?? '',
      weightOz: _parseNullableDouble(json['weight_oz']),
      length: _parseNullableDouble(json['length']),
      width: _parseNullableDouble(json['width']),
      height: _parseNullableDouble(json['height']),
      trackingNumber: json['tracking_number'],
      labelUrl: json['label_url'],
      carrier: json['carrier'],
    );
  }

  static double? _parseNullableDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }
}

class PackLocation {
  final String id;
  final String name;
  final String? code;

  const PackLocation({
    required this.id,
    required this.name,
    this.code,
  });

  factory PackLocation.fromJson(Map<String, dynamic> json) {
    return PackLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}

class ShippingRate {
  final String id;
  final String carrier;
  final String service;
  final double rate;
  final int estimatedDays;
  final String? deliveryDate;
  final String currency;

  const ShippingRate({
    required this.id,
    required this.carrier,
    required this.service,
    required this.rate,
    required this.estimatedDays,
    this.deliveryDate,
    required this.currency,
  });

  factory ShippingRate.fromJson(Map<String, dynamic> json) {
    return ShippingRate(
      id: json['id']?.toString() ?? '',
      carrier: json['carrier'] ?? '',
      service: json['service'] ?? '',
      rate: _parseDouble(json['rate']),
      estimatedDays: json['estimated_days'] ?? 0,
      deliveryDate: json['delivery_date'],
      currency: json['currency'] ?? 'USD',
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class PackStats {
  final int awaitingPack;
  final int packing;
  final int awaitingLabel;
  final int readyToShip;

  const PackStats({
    this.awaitingPack = 0,
    this.packing = 0,
    this.awaitingLabel = 0,
    this.readyToShip = 0,
  });

  factory PackStats.fromJson(Map<String, dynamic> json) {
    return PackStats(
      awaitingPack: json['awaiting_pack'] ?? 0,
      packing: json['packing'] ?? 0,
      awaitingLabel: json['awaiting_label'] ?? 0,
      readyToShip: json['ready_to_ship'] ?? 0,
    );
  }
}
