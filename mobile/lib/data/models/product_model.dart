import 'package:json_annotation/json_annotation.dart';

part 'product_model.g.dart';

@JsonSerializable()
class Product {
  // Support both int and String IDs (API returns UUIDs)
  @JsonKey(fromJson: _parseId, toJson: _idToJson)
  final dynamic id;
  final String name;
  final String sku;
  final String description;
  @JsonKey(fromJson: _parseDouble)
  final double price;
  @JsonKey(name: 'quantity', fromJson: _parseInt)
  final int quantity;
  final String category;
  @JsonKey(name: 'category_id')
  final dynamic categoryId;
  final String? brand;
  @JsonKey(name: 'brand_id')
  final dynamic brandId;
  final String? image;
  // Full image gallery (detail endpoint). Built from images[]/gallery_images plus
  // the primary image. Excluded from json_serializable; populated in fromJson.
  @JsonKey(includeFromJson: false, includeToJson: false)
  final List<String> images;
  final String? barcode;
  final String? status;
  final String? condition;
  @JsonKey(name: 'cost_price', fromJson: _parseDoubleNullable)
  final double? costPrice;
  @JsonKey(name: 'is_in_stock')
  final bool? apiIsInStock;
  @JsonKey(name: 'is_low_stock')
  final bool? apiIsLowStock;
  @JsonKey(name: 'created_at', fromJson: _parseDateTime)
  final DateTime createdAt;
  @JsonKey(name: 'updated_at', fromJson: _parseDateTime)
  final DateTime updatedAt;

  const Product({
    required this.id,
    required this.name,
    required this.sku,
    required this.description,
    required this.price,
    required this.quantity,
    required this.category,
    this.categoryId,
    this.brand,
    this.brandId,
    this.image,
    this.images = const [],
    this.barcode,
    this.status,
    this.condition,
    this.costPrice,
    this.apiIsInStock,
    this.apiIsLowStock,
    required this.createdAt,
    required this.updatedAt,
  });

  // Computed properties for inventory status
  bool get isInStock => apiIsInStock ?? quantity > 0;
  bool get isLowStock => apiIsLowStock ?? (quantity > 0 && quantity <= 10);
  bool get isOutOfStock => quantity <= 0;

  // Images to display: the full gallery when present, otherwise the single image.
  List<String> get displayImages {
    if (images.isNotEmpty) return images;
    final single = image;
    return (single != null && single.isNotEmpty) ? [single] : const [];
  }

  // Helper methods for ID parsing
  static dynamic _parseId(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is String) return value;
    return value.toString();
  }

  static dynamic _idToJson(dynamic value) => value;

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  static double? _parseDoubleNullable(dynamic value) {
    if (value == null) return null;
    return _parseDouble(value);
  }

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) return int.tryParse(value) ?? 0;
    return 0;
  }

  static DateTime _parseDateTime(dynamic value) {
    if (value == null) return DateTime.now();
    if (value is DateTime) return value;
    if (value is String) {
      return DateTime.tryParse(value) ?? DateTime.now();
    }
    return DateTime.now();
  }

  // Build the gallery from the detail endpoint's images[]/gallery_images, with the
  // primary image first. URLs are already absolute (normalised server-side).
  static List<String> _parseImages(Map<String, dynamic> json) {
    final result = <String>[];

    void add(String? url) {
      if (url != null && url.isNotEmpty && !result.contains(url)) {
        result.add(url);
      }
    }

    final primary = json['image'] ?? json['featured_image'];
    if (primary is String) add(primary);

    final imgs = json['images'];
    if (imgs is List) {
      for (final it in imgs) {
        if (it is Map && it['url'] != null) {
          add(it['url'].toString());
        } else if (it is String) {
          add(it);
        }
      }
    }

    final gallery = json['gallery_images'];
    if (gallery is List) {
      for (final g in gallery) {
        if (g != null) add(g.toString());
      }
    }

    return result;
  }

  factory Product.fromJson(Map<String, dynamic> json) {
    // Handle different API response formats
    return Product(
      id: _parseId(json['id']),
      name: json['name'] ?? '',
      sku: json['sku'] ?? '',
      description: json['description'] ?? json['short_description'] ?? '',
      price: _parseDouble(json['price'] ?? json['selling_price']),
      quantity: _parseInt(json['quantity'] ?? json['stock_quantity']),
      category: json['category'] ?? 'Uncategorized',
      categoryId: json['category_id'],
      brand: json['brand'],
      brandId: json['brand_id'],
      image: json['image'] ?? json['featured_image'],
      images: _parseImages(json),
      barcode: json['barcode'],
      status: json['status'],
      condition: json['condition'],
      costPrice: _parseDoubleNullable(json['cost_price']),
      apiIsInStock: json['is_in_stock'],
      apiIsLowStock: json['is_low_stock'],
      createdAt: _parseDateTime(json['created_at']),
      updatedAt: _parseDateTime(json['updated_at']),
    );
  }

  Map<String, dynamic> toJson() => _$ProductToJson(this);

  Product copyWith({
    dynamic id,
    String? name,
    String? sku,
    String? description,
    double? price,
    int? quantity,
    String? category,
    dynamic categoryId,
    String? brand,
    dynamic brandId,
    String? image,
    List<String>? images,
    String? barcode,
    String? status,
    String? condition,
    double? costPrice,
    bool? apiIsInStock,
    bool? apiIsLowStock,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Product(
      id: id ?? this.id,
      name: name ?? this.name,
      sku: sku ?? this.sku,
      description: description ?? this.description,
      price: price ?? this.price,
      quantity: quantity ?? this.quantity,
      category: category ?? this.category,
      categoryId: categoryId ?? this.categoryId,
      brand: brand ?? this.brand,
      brandId: brandId ?? this.brandId,
      image: image ?? this.image,
      images: images ?? this.images,
      barcode: barcode ?? this.barcode,
      status: status ?? this.status,
      condition: condition ?? this.condition,
      costPrice: costPrice ?? this.costPrice,
      apiIsInStock: apiIsInStock ?? this.apiIsInStock,
      apiIsLowStock: apiIsLowStock ?? this.apiIsLowStock,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}

@JsonSerializable()
class ProductCategory {
  final dynamic id;
  final String name;
  final String? slug;
  final String? description;
  @JsonKey(name: 'parent_id')
  final dynamic parentId;
  @JsonKey(name: 'products_count')
  final int? productsCount;
  @JsonKey(name: 'created_at', fromJson: _parseDateTime)
  final DateTime createdAt;
  @JsonKey(name: 'updated_at', fromJson: _parseDateTime)
  final DateTime updatedAt;

  ProductCategory({
    required this.id,
    required this.name,
    this.slug,
    this.description,
    this.parentId,
    this.productsCount,
    required this.createdAt,
    required this.updatedAt,
  });

  static DateTime _parseDateTime(dynamic value) {
    if (value == null) return DateTime.now();
    if (value is DateTime) return value;
    if (value is String) {
      return DateTime.tryParse(value) ?? DateTime.now();
    }
    return DateTime.now();
  }

  factory ProductCategory.fromJson(Map<String, dynamic> json) => _$ProductCategoryFromJson(json);
  Map<String, dynamic> toJson() => _$ProductCategoryToJson(this);
}

@JsonSerializable()
class InventoryLocation {
  final dynamic id;
  final String name;
  final String? code;
  final String? type;
  final String? address;
  @JsonKey(name: 'is_default')
  final bool? isDefault;
  @JsonKey(name: 'created_at', fromJson: _parseDateTime)
  final DateTime createdAt;
  @JsonKey(name: 'updated_at', fromJson: _parseDateTime)
  final DateTime updatedAt;

  InventoryLocation({
    required this.id,
    required this.name,
    this.code,
    this.type,
    this.address,
    this.isDefault,
    required this.createdAt,
    required this.updatedAt,
  });

  static DateTime _parseDateTime(dynamic value) {
    if (value == null) return DateTime.now();
    if (value is DateTime) return value;
    if (value is String) {
      return DateTime.tryParse(value) ?? DateTime.now();
    }
    return DateTime.now();
  }

  factory InventoryLocation.fromJson(Map<String, dynamic> json) => _$InventoryLocationFromJson(json);
  Map<String, dynamic> toJson() => _$InventoryLocationToJson(this);
}

@JsonSerializable()
class ProductsResponse {
  @JsonKey(name: 'data')
  final List<Product> products;
  final int total;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;

  ProductsResponse({
    required this.products,
    required this.total,
    required this.currentPage,
    required this.lastPage,
  });

  factory ProductsResponse.fromJson(Map<String, dynamic> json) {
    final List<dynamic> productsJson = json['data'] ?? json['products'] ?? [];
    final meta = json['meta'] ?? json;

    return ProductsResponse(
      products: productsJson.map((p) => Product.fromJson(p)).toList(),
      total: meta['total'] ?? productsJson.length,
      currentPage: meta['current_page'] ?? 1,
      lastPage: meta['last_page'] ?? 1,
    );
  }

  Map<String, dynamic> toJson() => _$ProductsResponseToJson(this);
}

@JsonSerializable()
class CreateProductRequest {
  final String name;
  final String sku;
  final String description;
  @JsonKey(name: 'selling_price')
  final double price;
  @JsonKey(name: 'stock_quantity')
  final int quantity;
  @JsonKey(name: 'category_id')
  final dynamic categoryId;
  @JsonKey(name: 'brand_id')
  final dynamic brandId;
  final String? barcode;
  final String? condition;
  @JsonKey(name: 'cost_price')
  final double? costPrice;

  CreateProductRequest({
    required this.name,
    required this.sku,
    required this.description,
    required this.price,
    required this.quantity,
    this.categoryId,
    this.brandId,
    this.barcode,
    this.condition,
    this.costPrice,
  });

  factory CreateProductRequest.fromJson(Map<String, dynamic> json) => _$CreateProductRequestFromJson(json);
  Map<String, dynamic> toJson() => _$CreateProductRequestToJson(this);
}

@JsonSerializable()
class UpdateProductRequest {
  final String? name;
  final String? sku;
  final String? description;
  @JsonKey(name: 'selling_price')
  final double? price;
  @JsonKey(name: 'stock_quantity')
  final int? quantity;
  @JsonKey(name: 'category_id')
  final dynamic categoryId;
  @JsonKey(name: 'brand_id')
  final dynamic brandId;
  final String? barcode;
  final String? condition;
  final String? status;
  @JsonKey(name: 'cost_price')
  final double? costPrice;

  UpdateProductRequest({
    this.name,
    this.sku,
    this.description,
    this.price,
    this.quantity,
    this.categoryId,
    this.brandId,
    this.barcode,
    this.condition,
    this.status,
    this.costPrice,
  });

  factory UpdateProductRequest.fromJson(Map<String, dynamic> json) => _$UpdateProductRequestFromJson(json);
  Map<String, dynamic> toJson() => _$UpdateProductRequestToJson(this);
}
