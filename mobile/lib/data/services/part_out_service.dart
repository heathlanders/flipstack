import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/part_out_models.dart';

class PartOutService {
  final ApiClient _apiClient;
  PartOutService(this._apiClient);

  static const String _basePath = '/part-out';

  /// Get work order list with optional filters.
  Future<({List<PartOutWorkOrder> workOrders, List<PartOutLocation> locations, PartOutStats stats})>
      getWorkOrderList({String? locationId, bool myOrders = false, String? status}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (locationId != null) queryParams['location_id'] = locationId;
      if (myOrders) queryParams['my_orders'] = '1';
      if (status != null) queryParams['status'] = status;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final workOrders = (response.data['data'] as List<dynamic>)
            .map((e) => PartOutWorkOrder.fromJson(e as Map<String, dynamic>))
            .toList();

        final locations = (response.data['locations'] as List<dynamic>?)
                ?.map((e) => PartOutLocation.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [];

        final stats = response.data['stats'] != null
            ? PartOutStats.fromJson(response.data['stats'])
            : const PartOutStats();

        return (workOrders: workOrders, locations: locations, stats: stats);
      }

      throw ApiException(message: 'Failed to load work orders', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get single work order details.
  Future<PartOutWorkOrder> getWorkOrder(String workOrderId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$workOrderId',
      );

      if (response.data['success'] == true) {
        return PartOutWorkOrder.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Work order not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Start a work order (transition from draft to in_progress).
  Future<PartOutWorkOrder> startWorkOrder(String workOrderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$workOrderId/start',
      );

      if (response.data['success'] == true) {
        return PartOutWorkOrder.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to start work order',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Mark an item as extracted (disassembled from source product).
  Future<PartOutItem> extractItem(String itemId, {String? barcode, String? condition, String? notes}) async {
    try {
      final data = <String, dynamic>{};
      if (barcode != null) data['barcode'] = barcode;
      if (condition != null) data['condition'] = condition;
      if (notes != null) data['notes'] = notes;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/extract',
        data: data,
      );

      if (response.data['success'] == true) {
        return PartOutItem.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to extract item',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Skip an item (will not be extracted).
  Future<PartOutItem> skipItem(String itemId, String reason) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/skip',
        data: {'reason': reason},
      );

      if (response.data['success'] == true) {
        return PartOutItem.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to skip item',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Update extraction notes for an item.
  Future<PartOutItem> updateItemNotes(String itemId, String notes) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/notes',
        data: {'notes': notes},
      );

      if (response.data['success'] == true) {
        return PartOutItem.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to update notes',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Verify a scanned barcode matches an item without extracting it.
  Future<PartOutScanVerifyResult> scanVerify(String itemId, String barcode) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/scan-verify',
        data: {'barcode': barcode},
      );

      if (response.data['success'] == true) {
        return PartOutScanVerifyResult.fromJson(response.data);
      }

      throw ApiException(message: 'Scan verification failed', statusCode: 422);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Complete a work order (all items must be processed).
  Future<void> completeWorkOrder(String workOrderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$workOrderId/complete',
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to complete work order',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Cancel a work order.
  Future<void> cancelWorkOrder(String workOrderId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$workOrderId/cancel',
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to cancel work order',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
