/// Models for the mobile warehouse bin scan / lookup workflow.

class WarehouseBin {
  final String id;
  final String code;
  final String fullCode;
  final String? barcode;
  final String? aisle;
  final String? rack;
  final String? shelf;
  final bool isActive;
  final int? maxCapacity;
  final int currentCount;
  final int itemsCount;
  final BinZone? zone;
  final List<BinContent> contents;

  const WarehouseBin({
    required this.id,
    required this.code,
    required this.fullCode,
    this.barcode,
    this.aisle,
    this.rack,
    this.shelf,
    required this.isActive,
    this.maxCapacity,
    required this.currentCount,
    required this.itemsCount,
    this.zone,
    this.contents = const [],
  });

  factory WarehouseBin.fromJson(Map<String, dynamic> json) {
    return WarehouseBin(
      id: json['id']?.toString() ?? '',
      code: json['code'] ?? '',
      fullCode: json['full_code'] ?? json['code'] ?? '',
      barcode: json['barcode'],
      aisle: json['aisle'],
      rack: json['rack'],
      shelf: json['shelf'],
      isActive: json['is_active'] ?? true,
      maxCapacity: json['max_capacity'],
      currentCount: json['current_count'] ?? 0,
      itemsCount: json['items_count'] ?? json['current_count'] ?? 0,
      zone: json['zone'] != null ? BinZone.fromJson(json['zone']) : null,
      contents: (json['contents'] as List<dynamic>?)
              ?.map((e) => BinContent.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  bool get isAtCapacity => maxCapacity != null && currentCount >= maxCapacity!;
  String get capacityDisplay => maxCapacity != null ? '$currentCount/$maxCapacity' : '$currentCount items';
}

class BinContent {
  final String inventoryLevelId;
  final String? productId;
  final String? productName;
  final String? sku;
  final String? barcode;
  final int quantityOnHand;
  final int quantityAvailable;

  const BinContent({
    required this.inventoryLevelId,
    this.productId,
    this.productName,
    this.sku,
    this.barcode,
    required this.quantityOnHand,
    required this.quantityAvailable,
  });

  factory BinContent.fromJson(Map<String, dynamic> json) {
    return BinContent(
      inventoryLevelId: json['inventory_level_id']?.toString() ?? '',
      productId: json['product_id']?.toString(),
      productName: json['product_name'],
      sku: json['sku'],
      barcode: json['barcode'],
      quantityOnHand: json['quantity_on_hand'] ?? 0,
      quantityAvailable: json['quantity_available'] ?? 0,
    );
  }
}

class BinZone {
  final String id;
  final String name;
  final String? code;

  const BinZone({required this.id, required this.name, this.code});

  factory BinZone.fromJson(Map<String, dynamic> json) {
    return BinZone(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}
