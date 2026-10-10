import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/ebay_listing_models.dart';

class EbayListingService {
  final ApiClient _apiClient;
  EbayListingService(this._apiClient);

  /// Paginated eBay listings (endpoint returns a raw Laravel paginator).
  Future<({List<EbayListing> listings, int currentPage, int lastPage})> getListings({
    String? status,
    String? search,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};
      if (status != null) queryParams['status'] = status;
      if (search != null && search.isNotEmpty) queryParams['search'] = search;

      final response = await _apiClient.get(ApiEndpoints.ebayListings, queryParameters: queryParams);
      final data = response.data;

      final listings = (data['data'] as List<dynamic>? ?? [])
          .map((e) => EbayListing.fromJson(e as Map<String, dynamic>))
          .toList();

      return (
        listings: listings,
        currentPage: (data['current_page'] as int?) ?? 1,
        lastPage: (data['last_page'] as int?) ?? 1,
      );
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['error'] ?? 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Queue an inventory/price update. updateType: quantity | price | both.
  Future<String> updateListing(String listingId, String updateType) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.ebayListings}/$listingId/update',
        data: {'update_type': updateType},
      );
      return response.data['message'] ?? 'Update job queued';
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['error'] ?? 'Failed to queue update: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Re-queue a failed listing for sync.
  Future<String> retryListing(String listingId) async {
    try {
      final response = await _apiClient.post('${ApiEndpoints.ebayListings}/$listingId/retry');
      return response.data['message'] ?? 'Retry job queued';
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['error'] ?? 'Failed to queue retry: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
