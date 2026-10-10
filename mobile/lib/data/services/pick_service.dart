import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/pick_models.dart';

class PickService {
  final ApiClient _apiClient;
  PickService(this._apiClient);

  static const String _basePath = '/fulfillment/pick';

  /// Get pick list with orders ready for picking.
  Future<({List<PickOrder> orders, List<PickLocation> locations, PickStats stats})>
      getPickList({String? locationId, bool myPicks = false}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (locationId != null) queryParams['location_id'] = locationId;
      if (myPicks) queryParams['my_picks'] = '1';

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final orders = (response.data['data'] as List<dynamic>)
            .map((e) => PickOrder.fromJson(e as Map<String, dynamic>))
            .toList();

        final locations = (response.data['locations'] as List<dynamic>?)
                ?.map((e) => PickLocation.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [];

        final stats = response.data['stats'] != null
            ? PickStats.fromJson(response.data['stats'])
            : const PickStats();

        return (orders: orders, locations: locations, stats: stats);
      }

      throw ApiException(message: 'Failed to load pick list', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get single order details for picking.
  Future<PickOrder> getOrder(String orderId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$orderId',
      );

      if (response.data['success'] == true) {
        return PickOrder.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Order not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Start picking an order.
  Future<PickOrder> startPicking(String orderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/start',
      );

      if (response.data['success'] == true) {
        return PickOrder.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to start picking',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Mark an item as picked. Optionally verify barcode.
  Future<PickItem> pickItem(String itemId, {int? quantity, String? barcode}) async {
    try {
      final data = <String, dynamic>{};
      if (quantity != null) data['quantity'] = quantity;
      if (barcode != null) data['barcode'] = barcode;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/pick',
        data: data,
      );

      if (response.data['success'] == true) {
        return PickItem.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to pick item',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Mark an item as short (unavailable).
  Future<PickItem> shortItem(String itemId, String reason) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/short',
        data: {'reason': reason},
      );

      if (response.data['success'] == true) {
        return PickItem.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to short item',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Complete picking for an order.
  Future<void> completePicking(String orderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$orderId/complete',
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to complete picking',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Verify a scanned barcode matches an item without picking it.
  Future<ScanVerifyResult> scanVerify(String itemId, String barcode) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/scan-verify',
        data: {'barcode': barcode},
      );

      if (response.data['success'] == true) {
        return ScanVerifyResult.fromJson(response.data);
      }

      throw ApiException(message: 'Scan verification failed', statusCode: 422);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
