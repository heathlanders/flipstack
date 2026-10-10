/// Models for the mobile purchase-order receiving workflow.

class ReceivingPo {
  final String id;
  final String poNumber;
  final String status;
  final String? supplierName;
  final DateTime? expectedDeliveryDate;
  final int itemsCount;
  final int totalQuantityOrdered;
  final int totalQuantityReceived;
  final double receivingProgress;
  final ReceivingLocation? location;
  final bool canReceive;
  final String? notes;
  final List<ReceivingPoItem> items;

  const ReceivingPo({
    required this.id,
    required this.poNumber,
    required this.status,
    this.supplierName,
    this.expectedDeliveryDate,
    required this.itemsCount,
    required this.totalQuantityOrdered,
    required this.totalQuantityReceived,
    required this.receivingProgress,
    this.location,
    this.canReceive = false,
    this.notes,
    this.items = const [],
  });

  factory ReceivingPo.fromJson(Map<String, dynamic> json) {
    return ReceivingPo(
      id: json['id']?.toString() ?? '',
      poNumber: json['po_number'] ?? '',
      status: json['status'] ?? '',
      supplierName: json['supplier_name'],
      expectedDeliveryDate: json['expected_delivery_date'] != null
          ? DateTime.tryParse(json['expected_delivery_date'])
          : null,
      itemsCount: json['items_count'] ?? 0,
      totalQuantityOrdered: json['total_quantity_ordered'] ?? 0,
      totalQuantityReceived: json['total_quantity_received'] ?? 0,
      receivingProgress: _parseDouble(json['receiving_progress']),
      location: json['location'] != null
          ? ReceivingLocation.fromJson(json['location'])
          : null,
      canReceive: json['can_receive'] ?? false,
      notes: json['notes'],
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => ReceivingPoItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  double get progressPercent =>
      totalQuantityOrdered > 0 ? totalQuantityReceived / totalQuantityOrdered : 0.0;

  bool get isSubmitted => status == 'submitted';
  bool get isPartiallyReceived => status == 'partially_received';
  bool get isReceived => status == 'received';

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class ReceivingPoItem {
  final String id;
  final String? productId;
  final String? description;
  final String? sku;
  final String? barcode;
  final int quantityOrdered;
  final int quantityReceived;
  final int remainingQuantity;
  final bool isFullyReceived;
  final double unitCost;

  const ReceivingPoItem({
    required this.id,
    this.productId,
    this.description,
    this.sku,
    this.barcode,
    required this.quantityOrdered,
    required this.quantityReceived,
    required this.remainingQuantity,
    required this.isFullyReceived,
    required this.unitCost,
  });

  factory ReceivingPoItem.fromJson(Map<String, dynamic> json) {
    return ReceivingPoItem(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString(),
      description: json['description'],
      sku: json['sku'],
      barcode: json['barcode'],
      quantityOrdered: json['quantity_ordered'] ?? 0,
      quantityReceived: json['quantity_received'] ?? 0,
      remainingQuantity: json['remaining_quantity'] ?? 0,
      isFullyReceived: json['is_fully_received'] ?? false,
      unitCost: _parseDouble(json['unit_cost']),
    );
  }

  String get label => description?.isNotEmpty == true
      ? description!
      : (sku ?? 'Item');

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class ReceivingLocation {
  final String id;
  final String name;
  final String? code;

  const ReceivingLocation({required this.id, required this.name, this.code});

  factory ReceivingLocation.fromJson(Map<String, dynamic> json) {
    return ReceivingLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}

class ReceivingStats {
  final int totalOrders;
  final int submitted;
  final int partiallyReceived;

  const ReceivingStats({
    this.totalOrders = 0,
    this.submitted = 0,
    this.partiallyReceived = 0,
  });

  factory ReceivingStats.fromJson(Map<String, dynamic> json) {
    return ReceivingStats(
      totalOrders: json['total_orders'] ?? 0,
      submitted: json['submitted'] ?? 0,
      partiallyReceived: json['partially_received'] ?? 0,
    );
  }
}

class ReceivingScanResult {
  final bool matched;
  final String? matchType;
  final String? expectedSku;
  final String scannedBarcode;

  const ReceivingScanResult({
    required this.matched,
    this.matchType,
    this.expectedSku,
    required this.scannedBarcode,
  });

  factory ReceivingScanResult.fromJson(Map<String, dynamic> json) {
    return ReceivingScanResult(
      matched: json['matched'] ?? false,
      matchType: json['match_type'],
      expectedSku: json['expected_sku'],
      scannedBarcode: json['scanned_barcode'] ?? '',
    );
  }
}
