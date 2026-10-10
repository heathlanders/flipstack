/// Models for the fulfillment order workflow.

class FulfillmentOrder {
  final String id;
  final String marketplaceOrderId;
  final String marketplace;
  final String status;
  final String priority;
  final String? buyerName;
  final double totalAmount;
  final int itemsCount;
  final String? locationId;
  final String? locationName;
  final String? holdReason;
  final DateTime? shipByDate;
  final DateTime createdAt;
  final List<FulfillmentOrderItem> items;

  const FulfillmentOrder({
    required this.id,
    required this.marketplaceOrderId,
    required this.marketplace,
    required this.status,
    required this.priority,
    this.buyerName,
    required this.totalAmount,
    required this.itemsCount,
    this.locationId,
    this.locationName,
    this.holdReason,
    this.shipByDate,
    required this.createdAt,
    this.items = const [],
  });

  factory FulfillmentOrder.fromJson(Map<String, dynamic> json) {
    return FulfillmentOrder(
      id: json['id']?.toString() ?? '',
      marketplaceOrderId: json['marketplace_order_id'] ?? '',
      marketplace: json['marketplace'] ?? '',
      status: json['status'] ?? '',
      priority: json['priority'] ?? 'normal',
      buyerName: json['buyer_name'],
      totalAmount: (json['total_amount'] ?? 0).toDouble(),
      itemsCount: json['items_count'] ?? 0,
      locationId: json['location_id']?.toString(),
      locationName: json['location_name'],
      holdReason: json['hold_reason'],
      shipByDate: _parseDateTime(json['ship_by_date']),
      createdAt: _parseDateTime(json['created_at']) ?? DateTime.now(),
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => FulfillmentOrderItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    if (value is String) return DateTime.tryParse(value);
    return null;
  }
}

class FulfillmentOrderItem {
  final String id;
  final String productId;
  final String productName;
  final String? productSku;
  final int quantity;
  final double price;

  const FulfillmentOrderItem({
    required this.id,
    required this.productId,
    required this.productName,
    this.productSku,
    required this.quantity,
    required this.price,
  });

  factory FulfillmentOrderItem.fromJson(Map<String, dynamic> json) {
    return FulfillmentOrderItem(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      productName: json['product_name'] ?? '',
      productSku: json['product_sku'],
      quantity: json['quantity'] ?? 0,
      price: (json['price'] ?? 0).toDouble(),
    );
  }
}

class OrderStats {
  final int pending;
  final int processing;
  final int onHold;
  final int completed;
  final int total;

  const OrderStats({
    this.pending = 0,
    this.processing = 0,
    this.onHold = 0,
    this.completed = 0,
    this.total = 0,
  });

  factory OrderStats.fromJson(Map<String, dynamic> json) {
    return OrderStats(
      pending: json['pending'] ?? 0,
      processing: json['processing'] ?? 0,
      onHold: json['on_hold'] ?? 0,
      completed: json['completed'] ?? 0,
      total: json['total'] ?? 0,
    );
  }
}
