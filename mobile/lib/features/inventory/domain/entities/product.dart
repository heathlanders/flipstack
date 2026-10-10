class Product {
  final int id;
  final String name;
  final String sku;
  final String description;
  final double price;
  final int quantity;
  final String category;
  final String? image;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Product({
    required this.id,
    required this.name,
    required this.sku,
    required this.description,
    required this.price,
    required this.quantity,
    required this.category,
    this.image,
    required this.createdAt,
    required this.updatedAt,
  });

  // Computed properties for inventory status
  bool get isInStock => quantity > 0;
  bool get isLowStock => quantity > 0 && quantity <= 10;
  bool get isOutOfStock => quantity <= 0;

  // Factory constructor from JSON
  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int,
      name: json['name'] as String,
      sku: json['sku'] as String,
      description: json['description'] as String? ?? '',
      price: (json['price'] as num).toDouble(),
      quantity: json['quantity'] as int,
      category: json['category'] as String? ?? '',
      image: json['image'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  // Convert to JSON
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'sku': sku,
      'description': description,
      'price': price,
      'quantity': quantity,
      'category': category,
      'image': image,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  // Copy with method for updates
  Product copyWith({
    int? id,
    String? name,
    String? sku,
    String? description,
    double? price,
    int? quantity,
    String? category,
    String? image,
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
      image: image ?? this.image,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Product && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() {
    return 'Product(id: $id, name: $name, sku: $sku, price: $price, quantity: $quantity)';
  }
}