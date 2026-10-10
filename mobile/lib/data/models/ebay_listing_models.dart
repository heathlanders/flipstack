/// Models for mobile eBay listing management (list / update / retry).

class EbayListing {
  final String id;
  final String? ebaySku;
  final String? ebayListingId;
  final String status;
  final double? listedPrice;
  final int availableQuantity;
  final int soldQuantity;
  final int consecutiveErrors;
  final String? lastErrorMessage;
  final String? ebayListingUrl;
  final DateTime? lastSyncedAt;
  final EbayListingProduct? product;

  const EbayListing({
    required this.id,
    this.ebaySku,
    this.ebayListingId,
    required this.status,
    this.listedPrice,
    required this.availableQuantity,
    required this.soldQuantity,
    required this.consecutiveErrors,
    this.lastErrorMessage,
    this.ebayListingUrl,
    this.lastSyncedAt,
    this.product,
  });

  factory EbayListing.fromJson(Map<String, dynamic> json) {
    return EbayListing(
      id: json['id']?.toString() ?? '',
      ebaySku: json['ebay_sku'],
      ebayListingId: json['ebay_listing_id'],
      status: json['status'] ?? 'pending',
      listedPrice: json['listed_price'] != null ? _parseDouble(json['listed_price']) : null,
      availableQuantity: json['available_quantity'] ?? 0,
      soldQuantity: json['sold_quantity'] ?? 0,
      consecutiveErrors: json['consecutive_errors'] ?? 0,
      lastErrorMessage: json['last_error_message'],
      ebayListingUrl: json['ebay_listing_url'],
      lastSyncedAt: json['last_synced_at'] != null ? DateTime.tryParse(json['last_synced_at']) : null,
      product: json['product'] != null ? EbayListingProduct.fromJson(json['product']) : null,
    );
  }

  bool get isPublished => status == 'published';
  bool get hasError => status == 'error' || consecutiveErrors > 0;
  bool get canRetry => hasError;

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class EbayListingProduct {
  final String id;
  final String name;
  final String? sku;
  final double sellingPrice;
  final int stockQuantity;

  const EbayListingProduct({
    required this.id,
    required this.name,
    this.sku,
    required this.sellingPrice,
    required this.stockQuantity,
  });

  factory EbayListingProduct.fromJson(Map<String, dynamic> json) {
    return EbayListingProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      sku: json['sku'],
      sellingPrice: EbayListing._parseDouble(json['selling_price']),
      stockQuantity: json['stock_quantity'] ?? 0,
    );
  }
}
