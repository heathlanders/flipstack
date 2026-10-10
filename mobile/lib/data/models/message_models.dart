/// Models for the mobile buyer-messaging (marketplace inbox) workflow.

class MarketplaceMessage {
  final String id;
  final String marketplace;
  final String? direction;
  final String? subject;
  final String? body;
  final String? senderName;
  final String status;
  final bool isUnread;
  final DateTime? receivedAt;
  final DateTime? createdAt;
  final MessageCustomer? customer;
  final MessageOrder? order;
  final MessageProduct? product;
  final List<MessageReply> replies;

  const MarketplaceMessage({
    required this.id,
    required this.marketplace,
    this.direction,
    this.subject,
    this.body,
    this.senderName,
    required this.status,
    required this.isUnread,
    this.receivedAt,
    this.createdAt,
    this.customer,
    this.order,
    this.product,
    this.replies = const [],
  });

  factory MarketplaceMessage.fromJson(Map<String, dynamic> json) {
    return MarketplaceMessage(
      id: json['id']?.toString() ?? '',
      marketplace: json['marketplace'] ?? '',
      direction: json['direction'],
      subject: json['subject'],
      body: json['body'],
      senderName: json['sender_name'],
      status: json['status'] ?? 'unread',
      isUnread: json['is_unread'] ?? (json['status'] == 'unread'),
      receivedAt: json['received_at'] != null ? DateTime.tryParse(json['received_at']) : null,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
      customer: json['customer'] != null ? MessageCustomer.fromJson(json['customer']) : null,
      order: json['order'] != null ? MessageOrder.fromJson(json['order']) : null,
      product: json['product'] != null ? MessageProduct.fromJson(json['product']) : null,
      replies: (json['replies'] as List<dynamic>?)
              ?.map((e) => MessageReply.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  bool get isInbound => direction == 'inbound';
  bool get isReplied => status == 'replied';
}

class MessageReply {
  final String id;
  final String body;
  final String status;
  final DateTime? sentAt;
  final String? senderName;

  const MessageReply({
    required this.id,
    required this.body,
    required this.status,
    this.sentAt,
    this.senderName,
  });

  factory MessageReply.fromJson(Map<String, dynamic> json) {
    return MessageReply(
      id: json['id']?.toString() ?? '',
      body: json['body'] ?? '',
      status: json['status'] ?? 'sent',
      sentAt: json['sent_at'] != null ? DateTime.tryParse(json['sent_at']) : null,
      senderName: json['sender'] is Map ? json['sender']['name'] : null,
    );
  }

  bool get isFailed => status == 'failed';
}

class MessageCustomer {
  final String id;
  final String name;

  const MessageCustomer({required this.id, required this.name});

  factory MessageCustomer.fromJson(Map<String, dynamic> json) {
    return MessageCustomer(id: json['id']?.toString() ?? '', name: json['name'] ?? '');
  }
}

class MessageOrder {
  final String id;
  final String? marketplaceOrderId;

  const MessageOrder({required this.id, this.marketplaceOrderId});

  factory MessageOrder.fromJson(Map<String, dynamic> json) {
    return MessageOrder(
      id: json['id']?.toString() ?? '',
      marketplaceOrderId: json['marketplace_order_id'],
    );
  }
}

class MessageProduct {
  final String id;
  final String name;
  final String? sku;

  const MessageProduct({required this.id, required this.name, this.sku});

  factory MessageProduct.fromJson(Map<String, dynamic> json) {
    return MessageProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      sku: json['sku'],
    );
  }
}

class MessageStats {
  final int unread;
  final int total;
  final Map<String, MessageMarketplaceStat> byMarketplace;

  const MessageStats({this.unread = 0, this.total = 0, this.byMarketplace = const {}});

  factory MessageStats.fromJson(Map<String, dynamic> json) {
    final byMp = <String, MessageMarketplaceStat>{};
    if (json['by_marketplace'] is Map) {
      (json['by_marketplace'] as Map).forEach((key, value) {
        if (value is Map) {
          byMp[key.toString()] = MessageMarketplaceStat.fromJson(Map<String, dynamic>.from(value));
        }
      });
    }
    return MessageStats(
      unread: json['unread'] ?? 0,
      total: json['total'] ?? 0,
      byMarketplace: byMp,
    );
  }
}

class MessageMarketplaceStat {
  final int total;
  final int unread;

  const MessageMarketplaceStat({this.total = 0, this.unread = 0});

  factory MessageMarketplaceStat.fromJson(Map<String, dynamic> json) {
    return MessageMarketplaceStat(total: json['total'] ?? 0, unread: json['unread'] ?? 0);
  }
}
