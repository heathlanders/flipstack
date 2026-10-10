/// Models for the mobile picking workflow.

class PickOrder {
  final String id;
  final String marketplaceOrderId;
  final String marketplace;
  final String status;
  final String priority;
  final DateTime? shipByDate;
  final bool isOverdue;
  final bool canBePicked;
  final String? buyerName;
  final double totalAmount;
  final int itemsCount;
  final PickLocation? location;
  final PickUser? assignedPicker;
  final List<PickItem> items;
  final PickProgress pickProgress;

  const PickOrder({
    required this.id,
    required this.marketplaceOrderId,
    required this.marketplace,
    required this.status,
    required this.priority,
    this.shipByDate,
    required this.isOverdue,
    required this.canBePicked,
    this.buyerName,
    required this.totalAmount,
    required this.itemsCount,
    this.location,
    this.assignedPicker,
    required this.items,
    required this.pickProgress,
  });

  factory PickOrder.fromJson(Map<String, dynamic> json) {
    return PickOrder(
      id: json['id']?.toString() ?? '',
      marketplaceOrderId: json['marketplace_order_id'] ?? '',
      marketplace: json['marketplace'] ?? '',
      status: json['status'] ?? '',
      priority: json['priority'] ?? 'standard',
      shipByDate: json['ship_by_date'] != null
          ? DateTime.tryParse(json['ship_by_date'])
          : null,
      isOverdue: json['is_overdue'] ?? false,
      canBePicked: json['can_be_picked'] ?? false,
      buyerName: json['buyer_name'],
      totalAmount: _parseDouble(json['total_amount']),
      itemsCount: json['items_count'] ?? 0,
      location: json['location'] != null
          ? PickLocation.fromJson(json['location'])
          : null,
      assignedPicker: json['assigned_picker'] != null
          ? PickUser.fromJson(json['assigned_picker'])
          : null,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => PickItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      pickProgress: json['pick_progress'] != null
          ? PickProgress.fromJson(json['pick_progress'])
          : const PickProgress(),
    );
  }

  bool get isPicking => status == 'picking';
  bool get isAssigned => status == 'assigned';
  bool get isUrgent => priority == 'urgent';
  bool get isHighPriority => priority == 'high';
  bool get allItemsProcessed => pickProgress.pending == 0;

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class PickItem {
  final String id;
  final String orderId;
  final String? productId;
  final String? sku;
  final String title;
  final int quantity;
  final String pickStatus;
  final DateTime? pickedAt;
  final String? imageUrl;
  final String? condition;
  final PickLocation? location;
  final PickProduct? product;

  const PickItem({
    required this.id,
    required this.orderId,
    this.productId,
    this.sku,
    required this.title,
    required this.quantity,
    required this.pickStatus,
    this.pickedAt,
    this.imageUrl,
    this.condition,
    this.location,
    this.product,
  });

  factory PickItem.fromJson(Map<String, dynamic> json) {
    return PickItem(
      id: json['id']?.toString() ?? '',
      orderId: json['order_id']?.toString() ?? '',
      productId: json['product_id']?.toString(),
      sku: json['sku'],
      title: json['title'] ?? '',
      quantity: json['quantity'] ?? 1,
      pickStatus: json['pick_status'] ?? 'pending',
      pickedAt: json['picked_at'] != null
          ? DateTime.tryParse(json['picked_at'])
          : null,
      imageUrl: json['image_url'],
      condition: json['condition'],
      location: json['location'] != null
          ? PickLocation.fromJson(json['location'])
          : null,
      product: json['product'] != null
          ? PickProduct.fromJson(json['product'])
          : null,
    );
  }

  bool get isPending => pickStatus == 'pending';
  bool get isPicked => pickStatus == 'picked';
  bool get isShort => pickStatus == 'short';

  /// The best barcode to match against for scan verification.
  String? get verificationBarcode =>
      product?.barcode ?? product?.sku ?? sku;

  /// Display location code (e.g., "A-1-3") for warehouse navigation.
  String get locationCode => location?.code ?? 'Unknown';
}

class PickProduct {
  final String id;
  final String name;
  final String sku;
  final String? internalSku;
  final String? barcode;

  const PickProduct({
    required this.id,
    required this.name,
    required this.sku,
    this.internalSku,
    this.barcode,
  });

  factory PickProduct.fromJson(Map<String, dynamic> json) {
    return PickProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      sku: json['sku'] ?? '',
      internalSku: json['internal_sku'],
      barcode: json['barcode'],
    );
  }
}

class PickLocation {
  final String id;
  final String name;
  final String? code;

  const PickLocation({
    required this.id,
    required this.name,
    this.code,
  });

  factory PickLocation.fromJson(Map<String, dynamic> json) {
    return PickLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}

class PickUser {
  final String id;
  final String name;

  const PickUser({required this.id, required this.name});

  factory PickUser.fromJson(Map<String, dynamic> json) {
    return PickUser(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
    );
  }
}

class PickProgress {
  final int total;
  final int picked;
  final int shorted;
  final int pending;

  const PickProgress({
    this.total = 0,
    this.picked = 0,
    this.shorted = 0,
    this.pending = 0,
  });

  factory PickProgress.fromJson(Map<String, dynamic> json) {
    return PickProgress(
      total: json['total'] ?? 0,
      picked: json['picked'] ?? 0,
      shorted: json['shorted'] ?? 0,
      pending: json['pending'] ?? 0,
    );
  }

  double get progressPercent =>
      total > 0 ? (picked + shorted) / total : 0.0;
}

class PickStats {
  final int totalOrders;
  final int totalItems;
  final int urgent;
  final int overdue;

  const PickStats({
    this.totalOrders = 0,
    this.totalItems = 0,
    this.urgent = 0,
    this.overdue = 0,
  });

  factory PickStats.fromJson(Map<String, dynamic> json) {
    return PickStats(
      totalOrders: json['total_orders'] ?? 0,
      totalItems: json['total_items'] ?? 0,
      urgent: json['urgent'] ?? 0,
      overdue: json['overdue'] ?? 0,
    );
  }
}

class ScanVerifyResult {
  final bool matched;
  final String? matchType;
  final String scannedBarcode;
  final String? expectedSku;

  const ScanVerifyResult({
    required this.matched,
    this.matchType,
    required this.scannedBarcode,
    this.expectedSku,
  });

  factory ScanVerifyResult.fromJson(Map<String, dynamic> json) {
    return ScanVerifyResult(
      matched: json['matched'] ?? false,
      matchType: json['match_type'],
      scannedBarcode: json['scanned_barcode'] ?? '',
      expectedSku: json['expected_sku'],
    );
  }
}
