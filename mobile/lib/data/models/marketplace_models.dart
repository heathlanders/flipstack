/// Models for marketplace integration status.

class MarketplaceStatus {
  final String id;
  final String platform;
  final String name;
  final bool isActive;
  final DateTime? connectedAt;
  final DateTime? lastSyncAt;

  const MarketplaceStatus({
    required this.id,
    required this.platform,
    required this.name,
    required this.isActive,
    this.connectedAt,
    this.lastSyncAt,
  });

  factory MarketplaceStatus.fromJson(Map<String, dynamic> json) {
    return MarketplaceStatus(
      id: json['id']?.toString() ?? '',
      platform: json['platform'] ?? '',
      name: json['name'] ?? '',
      isActive: json['is_active'] ?? false,
      connectedAt: _parseDateTime(json['connected_at']),
      lastSyncAt: _parseDateTime(json['last_sync_at']),
    );
  }

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    if (value is String) return DateTime.tryParse(value);
    return null;
  }
}

/// Extended marketplace connection model used by the UI.
class MarketplaceConnection {
  final String id;
  final String platform;
  final String name;
  final bool isActive;
  final DateTime? connectedAt;
  final DateTime? lastSyncAt;
  final String? storeName;
  final int? listingCount;
  final int? recentErrorCount;

  const MarketplaceConnection({
    required this.id,
    required this.platform,
    required this.name,
    required this.isActive,
    this.connectedAt,
    this.lastSyncAt,
    this.storeName,
    this.listingCount,
    this.recentErrorCount,
  });

  factory MarketplaceConnection.fromJson(Map<String, dynamic> json) {
    return MarketplaceConnection(
      id: json['id']?.toString() ?? '',
      platform: json['platform'] ?? '',
      name: json['name'] ?? '',
      isActive: json['is_active'] ?? false,
      connectedAt: _parseDateTime(json['connected_at']),
      lastSyncAt: _parseDateTime(json['last_sync_at']),
      storeName: json['store_name'] as String?,
      listingCount: json['listing_count'] as int?,
      recentErrorCount: json['recent_error_count'] as int?,
    );
  }

  factory MarketplaceConnection.fromStatus(MarketplaceStatus status) {
    return MarketplaceConnection(
      id: status.id,
      platform: status.platform,
      name: status.name,
      isActive: status.isActive,
      connectedAt: status.connectedAt,
      lastSyncAt: status.lastSyncAt,
    );
  }

  MarketplaceConnection copyWith({
    int? listingCount,
    int? recentErrorCount,
    DateTime? lastSyncAt,
    String? storeName,
  }) {
    return MarketplaceConnection(
      id: id,
      platform: platform,
      name: name,
      isActive: isActive,
      connectedAt: connectedAt,
      lastSyncAt: lastSyncAt ?? this.lastSyncAt,
      storeName: storeName ?? this.storeName,
      listingCount: listingCount ?? this.listingCount,
      recentErrorCount: recentErrorCount ?? this.recentErrorCount,
    );
  }

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    if (value is String) return DateTime.tryParse(value);
    return null;
  }
}

/// eBay-specific status information.
class EbayStatusDetail {
  final bool isConnected;
  final String? storeName;
  final DateTime? tokenExpiresAt;
  final Map<String, dynamic>? rateLimit;

  const EbayStatusDetail({
    this.isConnected = false,
    this.storeName,
    this.tokenExpiresAt,
    this.rateLimit,
  });

  factory EbayStatusDetail.fromJson(Map<String, dynamic> json) {
    return EbayStatusDetail(
      isConnected: json['is_connected'] ?? json['connected'] ?? false,
      storeName: json['store_name'] as String?,
      tokenExpiresAt: MarketplaceConnection._parseDateTime(json['token_expires_at']),
      rateLimit: json['rate_limit'] as Map<String, dynamic>?,
    );
  }
}

/// eBay stats (listing counts, etc.).
class EbayStats {
  final int totalListings;
  final int activeListings;
  final int draftListings;
  final int errorListings;
  final int recentSyncErrors;

  const EbayStats({
    this.totalListings = 0,
    this.activeListings = 0,
    this.draftListings = 0,
    this.errorListings = 0,
    this.recentSyncErrors = 0,
  });

  factory EbayStats.fromJson(Map<String, dynamic> json) {
    return EbayStats(
      totalListings: (json['total_listings'] as num?)?.toInt() ??
          (json['total'] as num?)?.toInt() ?? 0,
      activeListings: (json['active_listings'] as num?)?.toInt() ??
          (json['active'] as num?)?.toInt() ?? 0,
      draftListings: (json['draft_listings'] as num?)?.toInt() ??
          (json['draft'] as num?)?.toInt() ?? 0,
      errorListings: (json['error_listings'] as num?)?.toInt() ??
          (json['error'] as num?)?.toInt() ?? 0,
      recentSyncErrors: (json['recent_sync_errors'] as num?)?.toInt() ?? 0,
    );
  }
}

/// A single eBay sync log entry.
class EbaySyncLog {
  final String id;
  final String action;
  final String status;
  final String? message;
  final DateTime createdAt;

  const EbaySyncLog({
    required this.id,
    required this.action,
    required this.status,
    this.message,
    required this.createdAt,
  });

  factory EbaySyncLog.fromJson(Map<String, dynamic> json) {
    return EbaySyncLog(
      id: json['id']?.toString() ?? '',
      action: json['action'] as String? ?? json['type'] as String? ?? '',
      status: json['status'] as String? ?? '',
      message: json['message'] as String? ?? json['details'] as String?,
      createdAt: MarketplaceConnection._parseDateTime(json['created_at']) ?? DateTime.now(),
    );
  }
}
