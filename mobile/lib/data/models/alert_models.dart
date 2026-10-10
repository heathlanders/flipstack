/// Models for inventory alerts.

class InventoryAlert {
  final String id;
  final String productId;
  final String productName;
  final String productSku;
  final String alertType;
  final int currentQuantity;
  final int threshold;
  final String? locationName;
  final DateTime createdAt;

  const InventoryAlert({
    required this.id,
    required this.productId,
    required this.productName,
    required this.productSku,
    required this.alertType,
    required this.currentQuantity,
    required this.threshold,
    this.locationName,
    required this.createdAt,
  });

  factory InventoryAlert.fromJson(Map<String, dynamic> json) {
    return InventoryAlert(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      productName: json['product_name'] ?? '',
      productSku: json['product_sku'] ?? '',
      alertType: json['alert_type'] ?? '',
      currentQuantity: json['current_quantity'] ?? 0,
      threshold: json['threshold'] ?? 0,
      locationName: json['location_name'],
      createdAt: _parseDateTime(json['created_at']),
    );
  }

  /// Returns severity: critical if out_of_stock, warning if low_stock,
  /// info if overstocked.
  String get severity {
    switch (alertType) {
      case 'out_of_stock':
        return 'critical';
      case 'low_stock':
        return 'warning';
      case 'overstocked':
        return 'info';
      default:
        return 'info';
    }
  }

  static DateTime _parseDateTime(dynamic value) {
    if (value == null) return DateTime.now();
    if (value is DateTime) return value;
    if (value is String) return DateTime.tryParse(value) ?? DateTime.now();
    return DateTime.now();
  }
}

class AlertSummary {
  final int lowStock;
  final int outOfStock;
  final int overstocked;
  final int total;

  const AlertSummary({
    this.lowStock = 0,
    this.outOfStock = 0,
    this.overstocked = 0,
    this.total = 0,
  });

  factory AlertSummary.fromJson(Map<String, dynamic> json) {
    return AlertSummary(
      lowStock: json['low_stock'] ?? 0,
      outOfStock: json['out_of_stock'] ?? 0,
      overstocked: json['overstocked'] ?? 0,
      total: json['total'] ?? 0,
    );
  }
}

class AlertConfig {
  final int lowStockThreshold;
  final int overstockThreshold;
  final bool emailNotifications;

  const AlertConfig({
    this.lowStockThreshold = 10,
    this.overstockThreshold = 100,
    this.emailNotifications = true,
  });

  factory AlertConfig.fromJson(Map<String, dynamic> json) {
    return AlertConfig(
      lowStockThreshold: json['low_stock_threshold'] ?? 10,
      overstockThreshold: json['overstock_threshold'] ?? 100,
      emailNotifications: json['email_notifications'] ?? true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'low_stock_threshold': lowStockThreshold,
      'overstock_threshold': overstockThreshold,
      'email_notifications': emailNotifications,
    };
  }
}
