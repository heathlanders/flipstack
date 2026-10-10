// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order _$OrderFromJson(Map<String, dynamic> json) => Order(
  id: (json['id'] as num).toInt(),
  orderNumber: json['order_number'] as String,
  userId: (json['user_id'] as num).toInt(),
  status: $enumDecode(_$OrderStatusEnumMap, json['status']),
  totalAmount: (json['total_amount'] as num).toDouble(),
  shippingAddress: json['shipping_address'] as String?,
  billingAddress: json['billing_address'] as String?,
  paymentMethod: json['payment_method'] as String?,
  paymentStatus: json['payment_status'] as String?,
  notes: json['notes'] as String?,
  shippedAt: json['shipped_at'] == null
      ? null
      : DateTime.parse(json['shipped_at'] as String),
  deliveredAt: json['delivered_at'] == null
      ? null
      : DateTime.parse(json['delivered_at'] as String),
  createdAt: DateTime.parse(json['created_at'] as String),
  updatedAt: DateTime.parse(json['updated_at'] as String),
  items: (json['items'] as List<dynamic>?)
      ?.map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$OrderToJson(Order instance) => <String, dynamic>{
  'id': instance.id,
  'order_number': instance.orderNumber,
  'user_id': instance.userId,
  'status': _$OrderStatusEnumMap[instance.status]!,
  'total_amount': instance.totalAmount,
  'shipping_address': instance.shippingAddress,
  'billing_address': instance.billingAddress,
  'payment_method': instance.paymentMethod,
  'payment_status': instance.paymentStatus,
  'notes': instance.notes,
  'shipped_at': instance.shippedAt?.toIso8601String(),
  'delivered_at': instance.deliveredAt?.toIso8601String(),
  'created_at': instance.createdAt.toIso8601String(),
  'updated_at': instance.updatedAt.toIso8601String(),
  'items': instance.items,
};

const _$OrderStatusEnumMap = {
  OrderStatus.pending: 'pending',
  OrderStatus.processing: 'processing',
  OrderStatus.shipped: 'shipped',
  OrderStatus.delivered: 'delivered',
  OrderStatus.cancelled: 'cancelled',
  OrderStatus.refunded: 'refunded',
};

OrderItem _$OrderItemFromJson(Map<String, dynamic> json) => OrderItem(
  id: (json['id'] as num).toInt(),
  orderId: (json['order_id'] as num).toInt(),
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String,
  productSku: json['product_sku'] as String,
  quantity: (json['quantity'] as num).toInt(),
  unitPrice: (json['unit_price'] as num).toDouble(),
  totalPrice: (json['total_price'] as num).toDouble(),
);

Map<String, dynamic> _$OrderItemToJson(OrderItem instance) => <String, dynamic>{
  'id': instance.id,
  'order_id': instance.orderId,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'product_sku': instance.productSku,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'total_price': instance.totalPrice,
};

OrdersResponse _$OrdersResponseFromJson(Map<String, dynamic> json) =>
    OrdersResponse(
      orders: (json['orders'] as List<dynamic>)
          .map((e) => Order.fromJson(e as Map<String, dynamic>))
          .toList(),
      total: (json['total'] as num).toInt(),
      currentPage: (json['current_page'] as num).toInt(),
      lastPage: (json['last_page'] as num).toInt(),
    );

Map<String, dynamic> _$OrdersResponseToJson(OrdersResponse instance) =>
    <String, dynamic>{
      'orders': instance.orders,
      'total': instance.total,
      'current_page': instance.currentPage,
      'last_page': instance.lastPage,
    };

CreateOrderRequest _$CreateOrderRequestFromJson(Map<String, dynamic> json) =>
    CreateOrderRequest(
      shippingAddress: json['shipping_address'] as String,
      billingAddress: json['billing_address'] as String?,
      paymentMethod: json['payment_method'] as String,
      items: (json['items'] as List<dynamic>)
          .map((e) => CreateOrderItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      notes: json['notes'] as String?,
    );

Map<String, dynamic> _$CreateOrderRequestToJson(CreateOrderRequest instance) =>
    <String, dynamic>{
      'shipping_address': instance.shippingAddress,
      'billing_address': instance.billingAddress,
      'payment_method': instance.paymentMethod,
      'items': instance.items,
      'notes': instance.notes,
    };

CreateOrderItem _$CreateOrderItemFromJson(Map<String, dynamic> json) =>
    CreateOrderItem(
      productId: (json['product_id'] as num).toInt(),
      quantity: (json['quantity'] as num).toInt(),
      unitPrice: (json['unit_price'] as num).toDouble(),
    );

Map<String, dynamic> _$CreateOrderItemToJson(CreateOrderItem instance) =>
    <String, dynamic>{
      'product_id': instance.productId,
      'quantity': instance.quantity,
      'unit_price': instance.unitPrice,
    };
