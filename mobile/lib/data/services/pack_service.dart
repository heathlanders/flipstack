import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/pack_models.dart';

class PackService {
  final ApiClient _apiClient;
  PackService(this._apiClient);

  static const String _basePath = '/fulfillment/pack';

  /// Get the pack queue with orders ready for packing.
  Future<({List<PackOrder> orders, PackStats stats})> getPackQueue() async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
      );

      if (response.data['success'] == true) {
        final orders = (response.data['data'] as List<dynamic>)
            .map((e) => PackOrder.fromJson(e as Map<String, dynamic>))
            .toList();

        final stats = response.data['stats'] != null
            ? PackStats.fromJson(response.data['stats'])
            : const PackStats();

        return (orders: orders, stats: stats);
      }

      throw ApiException(message: 'Failed to load pack queue', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get a single order for packing.
  Future<PackOrder> getOrder(String orderId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$orderId',
      );

      if (response.data['success'] == true) {
        return PackOrder.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Order not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Start packing an order.
  Future<PackOrder> startPacking(String orderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/start',
      );

      if (response.data['success'] == true) {
        return PackOrder.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to start packing',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Get shipping rates for an order.
  Future<List<ShippingRate>> getRates(
    String orderId,
    PackageDimensions dimensions,
  ) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/rates',
        data: dimensions.toJson(),
      );

      if (response.data['success'] == true) {
        return (response.data['data'] as List<dynamic>)
            .map((e) => ShippingRate.fromJson(e as Map<String, dynamic>))
            .toList();
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to get shipping rates',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Purchase a shipping label for an order.
  Future<PackShipment> purchaseLabel(
    String orderId,
    String rateId,
    PackageDimensions dimensions,
  ) async {
    try {
      final data = <String, dynamic>{
        'rate_id': rateId,
        ...dimensions.toJson(),
      };

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/purchase-label',
        data: data,
      );

      if (response.data['success'] == true) {
        return PackShipment.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to purchase label',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Complete packing for an order.
  Future<void> completePacking(
    String orderId,
    PackageDimensions dimensions,
  ) async {
    try {
      final data = dimensions.toJson();

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/complete',
        data: data.isNotEmpty ? data : null,
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to complete packing',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
