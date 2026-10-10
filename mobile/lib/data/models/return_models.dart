/// Models for returns management.

class ReturnRequest {
  final String id;
  final String rmaNumber;
  final String status;
  final String? reason;
  final String? reasonDetails;
  final String? condition;
  final String? inspectionNotes;
  final bool? restockable;
  final double? refundAmount;
  final String? source;
  final DateTime createdAt;
  final DateTime? receivedAt;
  final DateTime? inspectedAt;
  final DateTime? refundedAt;
  final ReturnOrder? order;
  final ReturnOrderItem? orderItem;
  final String? inspectedByName;
  final String? receivedByName;
  final List<ReturnLocation>? _availableLocations;

  const ReturnRequest({
    required this.id,
    required this.rmaNumber,
    required this.status,
    this.reason,
    this.reasonDetails,
    this.condition,
    this.inspectionNotes,
    this.restockable,
    this.refundAmount,
    this.source,
    required this.createdAt,
    this.receivedAt,
    this.inspectedAt,
    this.refundedAt,
    this.order,
    this.orderItem,
    this.inspectedByName,
    this.receivedByName,
    List<ReturnLocation>? availableLocations,
  }) : _availableLocations = availableLocations;

  factory ReturnRequest.fromJson(Map<String, dynamic> json) {
    return ReturnRequest(
      id: json['id']?.toString() ?? '',
      rmaNumber: json['rma_number'] ?? '',
      status: json['status'] ?? '',
      reason: json['reason'],
      reasonDetails: json['reason_details'],
      condition: json['condition'],
      inspectionNotes: json['inspection_notes'],
      restockable: json['restockable'],
      refundAmount: _parseNullableDouble(json['refund_amount']),
      source: json['source'],
      createdAt: _parseDateTime(json['created_at']),
      receivedAt: json['received_at'] != null
          ? DateTime.tryParse(json['received_at'])
          : null,
      inspectedAt: json['inspected_at'] != null
          ? DateTime.tryParse(json['inspected_at'])
          : null,
      refundedAt: json['refunded_at'] != null
          ? DateTime.tryParse(json['refunded_at'])
          : null,
      order: json['order'] != null
          ? ReturnOrder.fromJson(json['order'])
          : null,
      orderItem: json['order_item'] != null
          ? ReturnOrderItem.fromJson(json['order_item'])
          : null,
      inspectedByName: json['inspected_by_name'],
      receivedByName: json['received_by_name'],
      availableLocations: (json['available_locations'] as List<dynamic>?)
          ?.map((e) => ReturnLocation.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  // Convenience getters for nested order/item fields used by UI
  String? get marketplaceOrderId => order?.marketplaceOrderId;
  String? get buyerName => order?.buyerName;
  double? get total => order != null ? order!.totalAmount : null;
  String? get itemTitle => orderItem?.title;
  String? get itemSku => orderItem?.sku;
  int? get quantity => orderItem?.quantity;
  List<ReturnLocation>? get availableLocations => _availableLocations;

  bool get canApprove => status == 'open';
  bool get canReceive => status == 'approved' || status == 'shipped_back';
  bool get canInspect => status == 'received';
  bool get canRestock => status == 'inspected' && restockable == true;

  static DateTime _parseDateTime(dynamic value) {
    if (value == null) return DateTime.now();
    if (value is DateTime) return value;
    if (value is String) return DateTime.tryParse(value) ?? DateTime.now();
    return DateTime.now();
  }

  static double? _parseNullableDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }
}

class ReturnOrder {
  final String id;
  final String marketplaceOrderId;
  final String? buyerName;
  final double totalAmount;

  const ReturnOrder({
    required this.id,
    required this.marketplaceOrderId,
    this.buyerName,
    required this.totalAmount,
  });

  factory ReturnOrder.fromJson(Map<String, dynamic> json) {
    return ReturnOrder(
      id: json['id']?.toString() ?? '',
      marketplaceOrderId: json['marketplace_order_id'] ?? '',
      buyerName: json['buyer_name'],
      totalAmount: _parseDouble(json['total_amount']),
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

class ReturnOrderItem {
  final String id;
  final String title;
  final String? sku;
  final int quantity;

  const ReturnOrderItem({
    required this.id,
    required this.title,
    this.sku,
    required this.quantity,
  });

  factory ReturnOrderItem.fromJson(Map<String, dynamic> json) {
    return ReturnOrderItem(
      id: json['id']?.toString() ?? '',
      title: json['title'] ?? '',
      sku: json['sku'],
      quantity: json['quantity'] ?? 1,
    );
  }
}

class ReturnLocation {
  final String id;
  final String name;

  const ReturnLocation({required this.id, required this.name});

  factory ReturnLocation.fromJson(Map<String, dynamic> json) {
    return ReturnLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
    );
  }
}

class ReturnStats {
  final int open;
  final int awaitingReceipt;
  final int awaitingInspection;
  final double refundedThisMonth;

  const ReturnStats({
    this.open = 0,
    this.awaitingReceipt = 0,
    this.awaitingInspection = 0,
    this.refundedThisMonth = 0.0,
  });

  factory ReturnStats.fromJson(Map<String, dynamic> json) {
    return ReturnStats(
      open: json['open'] ?? 0,
      awaitingReceipt: json['awaiting_receipt'] ?? 0,
      awaitingInspection: json['awaiting_inspection'] ?? 0,
      refundedThisMonth: _parseDouble(json['refunded_this_month']),
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
