import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/fulfillment_order_models.dart';

class FulfillmentOrderService {
  final ApiClient _apiClient;
  FulfillmentOrderService(this._apiClient);

  static const String _basePath = '/fulfillment/orders';

  /// Get paginated fulfillment orders with optional filters.
  Future<({List<FulfillmentOrder> orders, OrderStats stats, int total, int lastPage})>
      getOrders({
    String? status,
    String? priority,
    String? marketplace,
    String? search,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};
      if (status != null) queryParams['status'] = status;
      if (priority != null) queryParams['priority'] = priority;
      if (marketplace != null) queryParams['marketplace'] = marketplace;
      if (search != null) queryParams['search'] = search;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final orders = (response.data['data'] as List<dynamic>)
            .map((e) => FulfillmentOrder.fromJson(e as Map<String, dynamic>))
            .toList();

        final stats = response.data['stats'] != null
            ? OrderStats.fromJson(response.data['stats'])
            : const OrderStats();

        final int total = (response.data['meta']?['total'] as num?)?.toInt() ?? orders.length;
        final int lastPage = (response.data['meta']?['last_page'] as num?)?.toInt() ?? 1;

        return (orders: orders, stats: stats, total: total, lastPage: lastPage);
      }

      throw ApiException(message: 'Failed to load orders', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get a single fulfillment order by ID.
  Future<FulfillmentOrder> getOrder(String orderId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$orderId',
      );

      if (response.data['success'] == true) {
        return FulfillmentOrder.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Order not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Assign an order to a location for fulfillment.
  Future<void> assignOrder(String orderId, String locationId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/assign',
        data: {'location_id': locationId},
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to assign order',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Place an order on hold.
  Future<void> holdOrder(String orderId, String reason) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/hold',
        data: {'reason': reason},
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to hold order',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Release an order from hold.
  Future<void> releaseOrder(String orderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/release',
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to release order',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
