import 'package:json_annotation/json_annotation.dart';

part 'order_model.g.dart';

enum OrderStatus {
  pending,
  processing,
  shipped,
  delivered,
  cancelled,
  refunded;
  
  String get displayName {
    switch (this) {
      case OrderStatus.pending:
        return 'Pending';
      case OrderStatus.processing:
        return 'Processing';
      case OrderStatus.shipped:
        return 'Shipped';
      case OrderStatus.delivered:
        return 'Delivered';
      case OrderStatus.cancelled:
        return 'Cancelled';
      case OrderStatus.refunded:
        return 'Refunded';
    }
  }
}

@JsonSerializable()
class Order {
  final int id;
  @JsonKey(name: 'order_number')
  final String orderNumber;
  @JsonKey(name: 'user_id')
  final int userId;
  final OrderStatus status;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  @JsonKey(name: 'shipping_address')
  final String? shippingAddress;
  @JsonKey(name: 'billing_address')
  final String? billingAddress;
  @JsonKey(name: 'payment_method')
  final String? paymentMethod;
  @JsonKey(name: 'payment_status')
  final String? paymentStatus;
  final String? notes;
  @JsonKey(name: 'shipped_at')
  final DateTime? shippedAt;
  @JsonKey(name: 'delivered_at')
  final DateTime? deliveredAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;
  final List<OrderItem>? items;

  const Order({
    required this.id,
    required this.orderNumber,
    required this.userId,
    required this.status,
    required this.totalAmount,
    this.shippingAddress,
    this.billingAddress,
    this.paymentMethod,
    this.paymentStatus,
    this.notes,
    this.shippedAt,
    this.deliveredAt,
    required this.createdAt,
    required this.updatedAt,
    this.items,
  });

  factory Order.fromJson(Map<String, dynamic> json) => _$OrderFromJson(json);
  Map<String, dynamic> toJson() => _$OrderToJson(this);

  Order copyWith({
    int? id,
    String? orderNumber,
    int? userId,
    OrderStatus? status,
    double? totalAmount,
    String? shippingAddress,
    String? billingAddress,
    String? paymentMethod,
    String? paymentStatus,
    String? notes,
    DateTime? shippedAt,
    DateTime? deliveredAt,
    DateTime? createdAt,
    DateTime? updatedAt,
    List<OrderItem>? items,
  }) {
    return Order(
      id: id ?? this.id,
      orderNumber: orderNumber ?? this.orderNumber,
      userId: userId ?? this.userId,
      status: status ?? this.status,
      totalAmount: totalAmount ?? this.totalAmount,
      shippingAddress: shippingAddress ?? this.shippingAddress,
      billingAddress: billingAddress ?? this.billingAddress,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      notes: notes ?? this.notes,
      shippedAt: shippedAt ?? this.shippedAt,
      deliveredAt: deliveredAt ?? this.deliveredAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      items: items ?? this.items,
    );
  }

  bool get isActive => status != OrderStatus.cancelled && status != OrderStatus.refunded;
  bool get isCompleted => status == OrderStatus.delivered;
  bool get canBeCancelled => status == OrderStatus.pending || status == OrderStatus.processing;
}

@JsonSerializable()
class OrderItem {
  final int id;
  @JsonKey(name: 'order_id')
  final int orderId;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String productName;
  @JsonKey(name: 'product_sku')
  final String productSku;
  final int quantity;
  @JsonKey(name: 'unit_price')
  final double unitPrice;
  @JsonKey(name: 'total_price')
  final double totalPrice;

  const OrderItem({
    required this.id,
    required this.orderId,
    required this.productId,
    required this.productName,
    required this.productSku,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) => _$OrderItemFromJson(json);
  Map<String, dynamic> toJson() => _$OrderItemToJson(this);
}

@JsonSerializable()
class OrdersResponse {
  final List<Order> orders;
  final int total;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;

  const OrdersResponse({
    required this.orders,
    required this.total,
    required this.currentPage,
    required this.lastPage,
  });

  factory OrdersResponse.fromJson(Map<String, dynamic> json) => _$OrdersResponseFromJson(json);
  Map<String, dynamic> toJson() => _$OrdersResponseToJson(this);
}

@JsonSerializable()
class CreateOrderRequest {
  @JsonKey(name: 'shipping_address')
  final String shippingAddress;
  @JsonKey(name: 'billing_address')
  final String? billingAddress;
  @JsonKey(name: 'payment_method')
  final String paymentMethod;
  final List<CreateOrderItem> items;
  final String? notes;

  const CreateOrderRequest({
    required this.shippingAddress,
    this.billingAddress,
    required this.paymentMethod,
    required this.items,
    this.notes,
  });

  factory CreateOrderRequest.fromJson(Map<String, dynamic> json) => _$CreateOrderRequestFromJson(json);
  Map<String, dynamic> toJson() => _$CreateOrderRequestToJson(this);
}

@JsonSerializable()
class CreateOrderItem {
  @JsonKey(name: 'product_id')
  final int productId;
  final int quantity;
  @JsonKey(name: 'unit_price')
  final double unitPrice;

  const CreateOrderItem({
    required this.productId,
    required this.quantity,
    required this.unitPrice,
  });

  factory CreateOrderItem.fromJson(Map<String, dynamic> json) => _$CreateOrderItemFromJson(json);
  Map<String, dynamic> toJson() => _$CreateOrderItemToJson(this);
}
