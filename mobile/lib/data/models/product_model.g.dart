// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Map<String, dynamic> _$ProductToJson(Product instance) => <String, dynamic>{
      'id': Product._idToJson(instance.id),
      'name': instance.name,
      'sku': instance.sku,
      'description': instance.description,
      'price': instance.price,
      'quantity': instance.quantity,
      'category': instance.category,
      'category_id': instance.categoryId,
      'brand': instance.brand,
      'brand_id': instance.brandId,
      'image': instance.image,
      'barcode': instance.barcode,
      'status': instance.status,
      'condition': instance.condition,
      'cost_price': instance.costPrice,
      'is_in_stock': instance.apiIsInStock,
      'is_low_stock': instance.apiIsLowStock,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

ProductCategory _$ProductCategoryFromJson(Map<String, dynamic> json) =>
    ProductCategory(
      id: json['id'],
      name: json['name'] as String,
      slug: json['slug'] as String?,
      description: json['description'] as String?,
      parentId: json['parent_id'],
      productsCount: (json['products_count'] as num?)?.toInt(),
      createdAt: ProductCategory._parseDateTime(json['created_at']),
      updatedAt: ProductCategory._parseDateTime(json['updated_at']),
    );

Map<String, dynamic> _$ProductCategoryToJson(ProductCategory instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'slug': instance.slug,
      'description': instance.description,
      'parent_id': instance.parentId,
      'products_count': instance.productsCount,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

InventoryLocation _$InventoryLocationFromJson(Map<String, dynamic> json) =>
    InventoryLocation(
      id: json['id'],
      name: json['name'] as String,
      code: json['code'] as String?,
      type: json['type'] as String?,
      address: json['address'] as String?,
      isDefault: json['is_default'] as bool?,
      createdAt: InventoryLocation._parseDateTime(json['created_at']),
      updatedAt: InventoryLocation._parseDateTime(json['updated_at']),
    );

Map<String, dynamic> _$InventoryLocationToJson(InventoryLocation instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'code': instance.code,
      'type': instance.type,
      'address': instance.address,
      'is_default': instance.isDefault,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

Map<String, dynamic> _$ProductsResponseToJson(ProductsResponse instance) =>
    <String, dynamic>{
      'data': instance.products.map((p) => p.toJson()).toList(),
      'total': instance.total,
      'current_page': instance.currentPage,
      'last_page': instance.lastPage,
    };

CreateProductRequest _$CreateProductRequestFromJson(
        Map<String, dynamic> json) =>
    CreateProductRequest(
      name: json['name'] as String,
      sku: json['sku'] as String,
      description: json['description'] as String,
      price: (json['selling_price'] as num).toDouble(),
      quantity: (json['stock_quantity'] as num).toInt(),
      categoryId: json['category_id'],
      brandId: json['brand_id'],
      barcode: json['barcode'] as String?,
      condition: json['condition'] as String?,
      costPrice: (json['cost_price'] as num?)?.toDouble(),
    );

Map<String, dynamic> _$CreateProductRequestToJson(
        CreateProductRequest instance) =>
    <String, dynamic>{
      'name': instance.name,
      'sku': instance.sku,
      'description': instance.description,
      'selling_price': instance.price,
      'stock_quantity': instance.quantity,
      'category_id': instance.categoryId,
      'brand_id': instance.brandId,
      'barcode': instance.barcode,
      'condition': instance.condition,
      'cost_price': instance.costPrice,
    };

UpdateProductRequest _$UpdateProductRequestFromJson(
        Map<String, dynamic> json) =>
    UpdateProductRequest(
      name: json['name'] as String?,
      sku: json['sku'] as String?,
      description: json['description'] as String?,
      price: (json['selling_price'] as num?)?.toDouble(),
      quantity: (json['stock_quantity'] as num?)?.toInt(),
      categoryId: json['category_id'],
      brandId: json['brand_id'],
      barcode: json['barcode'] as String?,
      condition: json['condition'] as String?,
      status: json['status'] as String?,
      costPrice: (json['cost_price'] as num?)?.toDouble(),
    );

Map<String, dynamic> _$UpdateProductRequestToJson(
        UpdateProductRequest instance) =>
    <String, dynamic>{
      'name': instance.name,
      'sku': instance.sku,
      'description': instance.description,
      'selling_price': instance.price,
      'stock_quantity': instance.quantity,
      'category_id': instance.categoryId,
      'brand_id': instance.brandId,
      'barcode': instance.barcode,
      'condition': instance.condition,
      'status': instance.status,
      'cost_price': instance.costPrice,
    };
