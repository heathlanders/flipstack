/// Models for stock adjustments and transfers.

class StockLevel {
  final String id;
  final String productId;
  final String locationId;
  final int quantityOnHand;
  final int quantityReserved;
  final int quantityAvailable;
  final String? productName;
  final String? productSku;
  final String? locationName;
  final String? locationCode;

  const StockLevel({
    required this.id,
    required this.productId,
    required this.locationId,
    required this.quantityOnHand,
    required this.quantityReserved,
    required this.quantityAvailable,
    this.productName,
    this.productSku,
    this.locationName,
    this.locationCode,
  });

  factory StockLevel.fromJson(Map<String, dynamic> json) {
    return StockLevel(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      locationId: json['location_id']?.toString() ?? '',
      quantityOnHand: json['quantity_on_hand'] ?? 0,
      quantityReserved: json['quantity_reserved'] ?? 0,
      quantityAvailable: json['quantity_available'] ?? 0,
      productName: json['product_name'],
      productSku: json['product_sku'],
      locationName: json['location_name'],
      locationCode: json['location_code'],
    );
  }

  bool get isLowStock => quantityAvailable > 0 && quantityAvailable < 10;
  bool get isOutOfStock => quantityAvailable == 0;
}

class ReasonCode {
  final String code;
  final String label;

  const ReasonCode({required this.code, required this.label});

  factory ReasonCode.fromJson(Map<String, dynamic> json) {
    return ReasonCode(
      code: json['code'] ?? '',
      label: json['label'] ?? '',
    );
  }
}

class InventoryMovement {
  final String id;
  final String productId;
  final String locationId;
  final String type;
  final int quantity;
  final int balanceBefore;
  final int balanceAfter;
  final String? reason;
  final String? createdByName;
  final DateTime createdAt;

  const InventoryMovement({
    required this.id,
    required this.productId,
    required this.locationId,
    required this.type,
    required this.quantity,
    required this.balanceBefore,
    required this.balanceAfter,
    this.reason,
    this.createdByName,
    required this.createdAt,
  });

  factory InventoryMovement.fromJson(Map<String, dynamic> json) {
    return InventoryMovement(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      locationId: json['location_id']?.toString() ?? '',
      type: json['type'] ?? '',
      quantity: json['quantity'] ?? 0,
      balanceBefore: json['balance_before'] ?? 0,
      balanceAfter: json['balance_after'] ?? 0,
      reason: json['reason'],
      createdByName: json['created_by_name'],
      createdAt: _parseDateTime(json['created_at']),
    );
  }

  static DateTime _parseDateTime(dynamic value) {
    if (value == null) return DateTime.now();
    if (value is DateTime) return value;
    if (value is String) return DateTime.tryParse(value) ?? DateTime.now();
    return DateTime.now();
  }
}

class AdjustmentResult {
  final bool success;
  final String message;
  final InventoryMovement? movement;

  const AdjustmentResult({
    required this.success,
    required this.message,
    this.movement,
  });

  factory AdjustmentResult.fromJson(Map<String, dynamic> json) {
    return AdjustmentResult(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      movement: json['movement'] != null
          ? InventoryMovement.fromJson(json['movement'])
          : null,
    );
  }
}

class TransferResult {
  final bool success;
  final String message;
  final InventoryMovement? outMovement;
  final InventoryMovement? inMovement;

  const TransferResult({
    required this.success,
    required this.message,
    this.outMovement,
    this.inMovement,
  });

  factory TransferResult.fromJson(Map<String, dynamic> json) {
    return TransferResult(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      outMovement: json['out_movement'] != null
          ? InventoryMovement.fromJson(json['out_movement'])
          : null,
      inMovement: json['in_movement'] != null
          ? InventoryMovement.fromJson(json['in_movement'])
          : null,
    );
  }
}
