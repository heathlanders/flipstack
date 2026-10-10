import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/marketplace_models.dart';

class MarketplaceService {
  final ApiClient _apiClient;
  MarketplaceService(this._apiClient);

  static const String _basePath = '/marketplace';

  /// Get status of all connected marketplaces (returns MarketplaceStatus list).
  Future<List<MarketplaceStatus>> getStatus() async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/status',
      );

      if (response.data['success'] == true) {
        return (response.data['data'] as List<dynamic>)
            .map((e) => MarketplaceStatus.fromJson(e as Map<String, dynamic>))
            .toList();
      }

      throw ApiException(message: 'Failed to load marketplace status', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get all connected marketplaces as MarketplaceConnection objects.
  Future<List<MarketplaceConnection>> getMarketplaces() async {
    final statuses = await getStatus();
    return statuses.map((s) => MarketplaceConnection.fromStatus(s)).toList();
  }

  /// Get eBay connection status details.
  Future<EbayStatusDetail> getEbayStatus() async {
    try {
      final response = await _apiClient.get(ApiEndpoints.ebayStatus);
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      // Handle wrapped response
      final inner = data['data'] as Map<String, dynamic>? ?? data;
      return EbayStatusDetail.fromJson(inner);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get eBay listing stats.
  Future<EbayStats> getEbayStats() async {
    try {
      final response = await _apiClient.get(ApiEndpoints.ebayStats);
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      final inner = data['data'] as Map<String, dynamic>? ?? data;
      return EbayStats.fromJson(inner);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get eBay sync logs.
  Future<List<EbaySyncLog>> getEbayLogs({int limit = 10}) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.ebayLogs,
        queryParameters: {'limit': limit},
      );
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      final list = data['data'] as List<dynamic>? ?? (response.data is List ? response.data as List : []);
      return list
          .map((e) => EbaySyncLog.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
