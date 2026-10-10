/// Models for the mobile labeling / put-away workflow.

class LabelingWorkOrder {
  final String id;
  final String workOrderNumber;
  final String status;
  final String priority;
  final String? sourceType;
  final String? notes;
  final int totalItems;
  final int labeledItems;
  final int shelvedItems;
  final int itemsCount;
  final LabelingLocation? location;
  final LabelingUser? assignedTo;
  final List<LabelingItem> items;
  final LabelingProgress progress;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final DateTime createdAt;

  const LabelingWorkOrder({
    required this.id,
    required this.workOrderNumber,
    required this.status,
    required this.priority,
    this.sourceType,
    this.notes,
    required this.totalItems,
    required this.labeledItems,
    required this.shelvedItems,
    required this.itemsCount,
    this.location,
    this.assignedTo,
    this.items = const [],
    this.progress = const LabelingProgress(),
    this.startedAt,
    this.completedAt,
    required this.createdAt,
  });

  factory LabelingWorkOrder.fromJson(Map<String, dynamic> json) {
    return LabelingWorkOrder(
      id: json['id']?.toString() ?? '',
      workOrderNumber: json['work_order_number'] ?? '',
      status: json['status'] ?? '',
      priority: json['priority'] ?? 'standard',
      sourceType: json['source_type'],
      notes: json['notes'],
      totalItems: json['total_items'] ?? 0,
      labeledItems: json['labeled_items'] ?? 0,
      shelvedItems: json['shelved_items'] ?? 0,
      itemsCount: json['items_count'] ?? (json['total_items'] ?? 0),
      location: json['location'] != null ? LabelingLocation.fromJson(json['location']) : null,
      assignedTo: json['assigned_to'] != null && json['assigned_to'] is Map
          ? LabelingUser.fromJson(json['assigned_to'])
          : null,
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => LabelingItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      progress: json['progress'] != null
          ? LabelingProgress.fromJson(json['progress'])
          : const LabelingProgress(),
      startedAt: json['started_at'] != null ? DateTime.tryParse(json['started_at']) : null,
      completedAt: json['completed_at'] != null ? DateTime.tryParse(json['completed_at']) : null,
      createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
    );
  }

  bool get isOpen => status == 'open';
  bool get isInProgress => status == 'in_progress';
  bool get isCompleted => status == 'completed' || status == 'partially_completed';
  bool get canStart => status == 'open';
  bool get canComplete => status == 'open' || status == 'in_progress';
}

class LabelingItem {
  final String id;
  final String workOrderId;
  final int quantity;
  final String status;
  final bool binVerified;
  final String? skipReason;
  final LabelingProduct? product;
  final LabelingBin? bin;

  const LabelingItem({
    required this.id,
    required this.workOrderId,
    required this.quantity,
    required this.status,
    required this.binVerified,
    this.skipReason,
    this.product,
    this.bin,
  });

  factory LabelingItem.fromJson(Map<String, dynamic> json) {
    return LabelingItem(
      id: json['id']?.toString() ?? '',
      workOrderId: json['work_order_id']?.toString() ?? '',
      quantity: json['quantity'] ?? 1,
      status: json['status'] ?? 'pending',
      binVerified: json['bin_verified'] ?? false,
      skipReason: json['skip_reason'],
      product: json['product'] != null ? LabelingProduct.fromJson(json['product']) : null,
      bin: json['bin'] != null ? LabelingBin.fromJson(json['bin']) : null,
    );
  }

  bool get isPending => status == 'pending';
  bool get isLabelPrinted => status == 'label_printed';
  bool get isShelved => status == 'shelved';
  bool get isSkipped => status == 'skipped';
}

class LabelingProduct {
  final String id;
  final String name;
  final String? sku;
  final String? barcode;
  final String? condition;

  const LabelingProduct({required this.id, required this.name, this.sku, this.barcode, this.condition});

  factory LabelingProduct.fromJson(Map<String, dynamic> json) {
    return LabelingProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      sku: json['sku'],
      barcode: json['barcode'],
      condition: json['condition'],
    );
  }
}

class LabelingBin {
  final String id;
  final String? code;
  final String? fullCode;
  final String? barcode;
  final String? zone;

  const LabelingBin({required this.id, this.code, this.fullCode, this.barcode, this.zone});

  factory LabelingBin.fromJson(Map<String, dynamic> json) {
    return LabelingBin(
      id: json['id']?.toString() ?? '',
      code: json['code'],
      fullCode: json['full_code'],
      barcode: json['barcode'],
      zone: json['zone'] is Map ? json['zone']['name'] : json['zone'],
    );
  }

  String get display => fullCode ?? code ?? barcode ?? 'Bin';
}

class LabelingLocation {
  final String id;
  final String name;
  final String? code;

  const LabelingLocation({required this.id, required this.name, this.code});

  factory LabelingLocation.fromJson(Map<String, dynamic> json) {
    return LabelingLocation(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      code: json['code'],
    );
  }
}

class LabelingUser {
  final String id;
  final String name;

  const LabelingUser({required this.id, required this.name});

  factory LabelingUser.fromJson(Map<String, dynamic> json) {
    return LabelingUser(id: json['id']?.toString() ?? '', name: json['name'] ?? '');
  }
}

class LabelingProgress {
  final int total;
  final int pending;
  final int labeled;
  final int shelved;
  final int skipped;

  const LabelingProgress({
    this.total = 0,
    this.pending = 0,
    this.labeled = 0,
    this.shelved = 0,
    this.skipped = 0,
  });

  factory LabelingProgress.fromJson(Map<String, dynamic> json) {
    return LabelingProgress(
      total: json['total'] ?? 0,
      pending: json['pending'] ?? 0,
      labeled: json['labeled'] ?? 0,
      shelved: json['shelved'] ?? 0,
      skipped: json['skipped'] ?? 0,
    );
  }

  double get progressPercent => total > 0 ? (shelved + skipped) / total : 0.0;
}

class LabelingStats {
  final int open;
  final int inProgress;
  final int completedToday;
  final int itemsPendingLabel;

  const LabelingStats({
    this.open = 0,
    this.inProgress = 0,
    this.completedToday = 0,
    this.itemsPendingLabel = 0,
  });

  factory LabelingStats.fromJson(Map<String, dynamic> json) {
    return LabelingStats(
      open: json['open'] ?? 0,
      inProgress: json['in_progress'] ?? 0,
      completedToday: json['completed_today'] ?? 0,
      itemsPendingLabel: json['items_pending_label'] ?? 0,
    );
  }
}

class LabelingVerifyResult {
  final bool matched;
  final String? expectedBarcode;
  final String scannedBarcode;

  const LabelingVerifyResult({required this.matched, this.expectedBarcode, required this.scannedBarcode});

  factory LabelingVerifyResult.fromJson(Map<String, dynamic> json) {
    return LabelingVerifyResult(
      matched: json['matched'] ?? false,
      expectedBarcode: json['expected_barcode'],
      scannedBarcode: json['scanned_barcode'] ?? '',
    );
  }
}
