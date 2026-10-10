/// Models for the mobile product-bundle availability view (read-only).

class ProductBundle {
  final String id;
  final String name;
  final String bundleType;
  final int? pickNQuantity;
  final bool isActive;
  final int itemsCount;
  final int availableQuantity;
  final BundleProduct? product;
  final List<BundleComponent> components;

  const ProductBundle({
    required this.id,
    required this.name,
    required this.bundleType,
    this.pickNQuantity,
    required this.isActive,
    required this.itemsCount,
    required this.availableQuantity,
    this.product,
    this.components = const [],
  });

  factory ProductBundle.fromJson(Map<String, dynamic> json) {
    return ProductBundle(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      bundleType: json['bundle_type'] ?? 'fixed',
      pickNQuantity: json['pick_n_quantity'],
      isActive: json['is_active'] ?? true,
      itemsCount: json['items_count'] ?? 0,
      availableQuantity: json['available_quantity'] ?? 0,
      product: json['product'] != null ? BundleProduct.fromJson(json['product']) : null,
      components: (json['components'] as List<dynamic>?)
              ?.map((e) => BundleComponent.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  bool get isPickN => bundleType == 'pick_n';
  bool get inStock => availableQuantity > 0;
  String get typeLabel => isPickN ? 'Pick ${pickNQuantity ?? ''}' : 'Fixed';
}

class BundleProduct {
  final String id;
  final String name;
  final String? sku;
  final double sellingPrice;

  const BundleProduct({required this.id, required this.name, this.sku, required this.sellingPrice});

  factory BundleProduct.fromJson(Map<String, dynamic> json) {
    return BundleProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      sku: json['sku'],
      sellingPrice: _parseDouble(json['selling_price']),
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

class BundleComponent {
  final String id;
  final String? productId;
  final String? name;
  final String? sku;
  final int quantity;
  final bool isRequired;
  final int componentAvailable;

  const BundleComponent({
    required this.id,
    this.productId,
    this.name,
    this.sku,
    required this.quantity,
    required this.isRequired,
    required this.componentAvailable,
  });

  factory BundleComponent.fromJson(Map<String, dynamic> json) {
    return BundleComponent(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString(),
      name: json['name'],
      sku: json['sku'],
      quantity: json['quantity'] ?? 1,
      isRequired: json['is_required'] ?? true,
      componentAvailable: json['component_available'] ?? 0,
    );
  }
}
