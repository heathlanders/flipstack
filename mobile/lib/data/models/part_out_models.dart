/// Models for the mobile part-out (product disassembly) workflow.

class PartOutWorkOrder {
  final String id;
  final String workOrderNumber;
  final String status;
  final PartOutSourceProduct? sourceProduct;
  final PartOutLocation? location;
  final PartOutUser? assignedTo;
  final String? notes;
  final int sourceQuantity;
  final double sourceTotalCost;
  final String costAllocationMethod;
  final bool parentContinues;
  final String? parentNewName;
  final List<PartOutItem> items;
  final PartOutProgress progress;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final DateTime createdAt;

  const PartOutWorkOrder({
    required this.id,
    required this.workOrderNumber,
    required this.status,
    this.sourceProduct,
    this.location,
    this.assignedTo,
    this.notes,
    required this.sourceQuantity,
    required this.sourceTotalCost,
    required this.costAllocationMethod,
    required this.parentContinues,
    this.parentNewName,
    required this.items,
    required this.progress,
    this.startedAt,
    this.completedAt,
    required this.createdAt,
  });

  factory PartOutWorkOrder.fromJson(Map<String, dynamic> json) {
    return PartOutWorkOrder(
      id: json['id']?.toString() ?? '',
      workOrderNumber: json['work_order_number'] ?? '',
      status: json['status'] ?? '',
      sourceProduct: json['source_product'] != null
          ? PartOutSourceProduct.fromJson(json['source_product'])
          : null,
      location: json['location'] != null
          ? PartOutLocation.fromJson(json['location'])
          : null,
      assignedTo: json['assigned_to'] != null
          ? PartOutUser.fromJson(json['assigned_to'])
          : null,
      notes: json['notes'],
      sourceQuantity: json['source_quantity'] ?? 0,
      sourceTotalCost: _parseDouble(json['source_total_cost']),
      costAllocationMethod: json['cost_allocation_method'] ?? 'equal',
      parentContinues: json['parent_continues'] ?? false,
      parentNewName: json['parent_new_name'],
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => PartOutItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      progress: json['progress'] != null
          ? PartOutProgress.fromJson(json['progress'])
          : const PartOutProgress(),
      startedAt: json['started_at'] != null
          ? DateTime.tryParse(json['started_at'])
          : null,
      completedAt: json['completed_at'] != null
          ? DateTime.tryParse(json['completed_at'])
          : null,
      createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
    );
  }

  bool get canStart => status == 'draft';
  bool get canComplete => status == 'in_progress' && allItemsProcessed;
  bool get canCancel => status == 'draft' || status == 'in_progress';
  bool get isDraft => status == 'draft';
  bool get isInProgress => status == 'in_progress';
  bool get allItemsProcessed => progress.pending == 0;

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class PartOutItem {
  final String id;
  final String workOrderId;
  final String name;
  final String? partNumber;
  final String? manufacturer;
  final String? category;
  final int quantity;
  final double allocatedCost;
  final double allocatedCostPerUnit;
  final double? suggestedSellingPrice;
  final String? condition;
  final String? notes;
  final String extractionStatus;
  final DateTime? extractedAt;
  final PartOutUser? extractedBy;
  final String? extractionNotes;
  final bool barcodeVerified;
  final String? scannedBarcode;

  const PartOutItem({
    required this.id,
    required this.workOrderId,
    required this.name,
    this.partNumber,
    this.manufacturer,
    this.category,
    required this.quantity,
    required this.allocatedCost,
    required this.allocatedCostPerUnit,
    this.suggestedSellingPrice,
    this.condition,
    this.notes,
    required this.extractionStatus,
    this.extractedAt,
    this.extractedBy,
    this.extractionNotes,
    required this.barcodeVerified,
    this.scannedBarcode,
  });

  factory PartOutItem.fromJson(Map<String, dynamic> json) {
    return PartOutItem(
      id: json['id']?.toString() ?? '',
      workOrderId: json['work_order_id']?.toString() ?? '',
      name: json['name'] ?? '',
      partNumber: json['part_number'],
      manufacturer: json['manufacturer'],
      category: json['category'],
      quantity: json['quantity'] ?? 1,
      allocatedCost: _parseDouble(json['allocated_cost']),
      allocatedCostPerUnit: _parseDouble(json['allocated_cost_per_unit']),
      suggestedSellingPrice: json['suggested_selling_price'] != null
          ? _parseDouble(json['suggested_selling_price'])
          : null,
      condition: json['condition'],
      notes: json['notes'],
      extractionStatus: json['extraction_status'] ?? 'pending',
      extractedAt: json['extracted_at'] != null
          ? DateTime.tryParse(json['extracted_at'])
          : null,
      extractedBy: json['extracted_by'] != null
          ? PartOutUser.fromJson(json['extracted_by'])
          : null,
      extractionNotes: json['extraction_notes'],
      barcodeVerified: json['barcode_verified'] ?? false,
      scannedBarcode: json['scanned_barcode'],
    );
  }

  bool get isPending => extractionStatus == 'pending';
  bool get isExtracted => extractionStatus == 'extracted';
  bool get isSkipped => extractionStatus == 'skipped';

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class PartOutSourceProduct {
  final String id;
  final String name;
  final String? sku;
  final String? barcode;
  final String? imageUrl;
  final double costPrice;
  final int availableQuantity;

  const PartOutSourceProduct({
    required this.id,
    required this.name,
    this.sku,
    this.barcode,
    this.imageUrl,
    required this.costPrice,
    required this.availableQuantity,
  });

  factory PartOutSourceProduct.fromJson(Map<String, dynamic> json) {
    return PartOutSourceProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      sku: json['sku'],
      barcode: json['barcode'],
      imageUrl: json['image_url'],
      costPrice: _parseDouble(json['cost_price']),
      availableQuantity: json['available_quantity'] ?? 0,
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

class PartOutLocation {
  final String id;
  final String name;
  final String? code;

  const PartOutLocation({
    required this.id,
    required this.name,
    this.code,
  });

  factory PartOutLocation.fromJson(Map<String, dynamic> json) {
    return PartOutLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}

class PartOutUser {
  final String id;
  final String name;

  const PartOutUser({required this.id, required this.name});

  factory PartOutUser.fromJson(Map<String, dynamic> json) {
    return PartOutUser(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
    );
  }
}

class PartOutProgress {
  final int total;
  final int extracted;
  final int skipped;
  final int pending;

  const PartOutProgress({
    this.total = 0,
    this.extracted = 0,
    this.skipped = 0,
    this.pending = 0,
  });

  factory PartOutProgress.fromJson(Map<String, dynamic> json) {
    return PartOutProgress(
      total: json['total'] ?? 0,
      extracted: json['extracted'] ?? 0,
      skipped: json['skipped'] ?? 0,
      pending: json['pending'] ?? 0,
    );
  }

  double get progressPercent =>
      total > 0 ? (extracted + skipped) / total : 0.0;
}

class PartOutStats {
  final int total;
  final int draft;
  final int inProgress;
  final int completed;

  const PartOutStats({
    this.total = 0,
    this.draft = 0,
    this.inProgress = 0,
    this.completed = 0,
  });

  factory PartOutStats.fromJson(Map<String, dynamic> json) {
    return PartOutStats(
      total: json['total'] ?? 0,
      draft: json['draft'] ?? 0,
      inProgress: json['in_progress'] ?? 0,
      completed: json['completed'] ?? 0,
    );
  }
}

class PartOutScanVerifyResult {
  final bool matched;
  final String? matchType;
  final String? expected;
  final String scannedBarcode;

  const PartOutScanVerifyResult({
    required this.matched,
    this.matchType,
    this.expected,
    required this.scannedBarcode,
  });

  factory PartOutScanVerifyResult.fromJson(Map<String, dynamic> json) {
    return PartOutScanVerifyResult(
      matched: json['matched'] ?? false,
      matchType: json['match_type'],
      expected: json['expected'],
      scannedBarcode: json['scanned_barcode'] ?? '',
    );
  }
}
