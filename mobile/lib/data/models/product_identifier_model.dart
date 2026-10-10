// Simple model classes without freezed

class ProductIdentifierModel {
  final String type;
  final String value;
  final String source;
  final bool isPrimary;
  final bool verified;
  final int? confidenceScore;
  final Map<String, dynamic>? metadata;

  const ProductIdentifierModel({
    required this.type,
    required this.value,
    this.source = 'mobile_app',
    this.isPrimary = false,
    this.verified = false,
    this.confidenceScore,
    this.metadata,
  });

  ProductIdentifierModel copyWith({
    String? type,
    String? value,
    String? source,
    bool? isPrimary,
    bool? verified,
    int? confidenceScore,
    Map<String, dynamic>? metadata,
  }) {
    return ProductIdentifierModel(
      type: type ?? this.type,
      value: value ?? this.value,
      source: source ?? this.source,
      isPrimary: isPrimary ?? this.isPrimary,
      verified: verified ?? this.verified,
      confidenceScore: confidenceScore ?? this.confidenceScore,
      metadata: metadata ?? this.metadata,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'type': type,
      'value': value,
      'source': source,
      'is_primary': isPrimary,
      'verified': verified,
      'confidence_score': confidenceScore,
      'metadata': metadata,
    };
  }

  factory ProductIdentifierModel.fromJson(Map<String, dynamic> json) {
    return ProductIdentifierModel(
      type: json['type'] as String,
      value: json['value'] as String,
      source: json['source'] as String? ?? 'mobile_app',
      isPrimary: json['is_primary'] as bool? ?? false,
      verified: json['verified'] as bool? ?? false,
      confidenceScore: json['confidence_score'] as int?,
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }
}

class IdentifierType {
  final String key;
  final String name;
  final bool scannable;

  const IdentifierType({
    required this.key,
    required this.name,
    this.scannable = false,
  });

  Map<String, dynamic> toJson() {
    return {
      'key': key,
      'name': name,
      'scannable': scannable,
    };
  }

  factory IdentifierType.fromJson(Map<String, dynamic> json) {
    return IdentifierType(
      key: json['key'] as String,
      name: json['name'] as String,
      scannable: json['scannable'] as bool? ?? false,
    );
  }
}

class BarcodeDetectionResult {
  final String barcode;
  final String detectedType;
  final String formattedType;
  final bool existsInQueue;
  final bool existsAsProduct;
  final Map<String, dynamic>? queueItem;
  final Map<String, dynamic>? existingProduct;

  const BarcodeDetectionResult({
    required this.barcode,
    required this.detectedType,
    required this.formattedType,
    this.existsInQueue = false,
    this.existsAsProduct = false,
    this.queueItem,
    this.existingProduct,
  });

  Map<String, dynamic> toJson() {
    return {
      'barcode': barcode,
      'detected_type': detectedType,
      'formatted_type': formattedType,
      'exists_in_queue': existsInQueue,
      'exists_as_product': existsAsProduct,
      'queue_item': queueItem,
      'existing_product': existingProduct,
    };
  }

  factory BarcodeDetectionResult.fromJson(Map<String, dynamic> json) {
    return BarcodeDetectionResult(
      barcode: json['barcode'] as String,
      detectedType: json['detected_type'] as String,
      formattedType: json['formatted_type'] as String,
      existsInQueue: json['exists_in_queue'] as bool? ?? false,
      existsAsProduct: json['exists_as_product'] as bool? ?? false,
      queueItem: json['queue_item'] as Map<String, dynamic>?,
      existingProduct: json['existing_product'] as Map<String, dynamic>?,
    );
  }
}