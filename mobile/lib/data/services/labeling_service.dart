import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/labeling_models.dart';

class LabelingService {
  final ApiClient _apiClient;
  LabelingService(this._apiClient);

  static const String _basePath = '/labeling';

  /// Actionable labeling work orders with summary stats.
  Future<({List<LabelingWorkOrder> workOrders, LabelingStats stats})> getWorkOrderList({String? locationId}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (locationId != null) queryParams['location_id'] = locationId;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
        queryParameters: queryParams,
      );

      final workOrders = (response.data['work_orders'] as List<dynamic>? ?? [])
          .map((e) => LabelingWorkOrder.fromJson(e as Map<String, dynamic>))
          .toList();

      final stats = response.data['stats'] != null
          ? LabelingStats.fromJson(response.data['stats'])
          : const LabelingStats();

      return (workOrders: workOrders, stats: stats);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Single work order with items + progress.
  Future<LabelingWorkOrder> getWorkOrder(String workOrderId) async {
    try {
      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath/$workOrderId');

      final wo = response.data['work_order'] as Map<String, dynamic>;
      if (response.data['progress'] != null) {
        wo['progress'] = response.data['progress'];
      }
      return LabelingWorkOrder.fromJson(wo);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  Future<void> startWorkOrder(String workOrderId) => _simplePost('$_basePath/$workOrderId/start');

  Future<void> markAllLabeled(String workOrderId) => _simplePost('$_basePath/$workOrderId/mark-all-labeled');

  Future<void> completeWorkOrder(String workOrderId) => _simplePost('$_basePath/$workOrderId/complete');

  Future<void> markItemLabeled(String itemId) => _simplePost('$_basePath/items/$itemId/mark-labeled');

  /// Assign + shelve an item by scanning a bin barcode.
  Future<void> scanBin(String itemId, String barcode) =>
      _simplePost('$_basePath/items/$itemId/scan-bin', data: {'barcode': barcode});

  Future<void> skipItem(String itemId, String reason) =>
      _simplePost('$_basePath/items/$itemId/skip', data: {'reason': reason});

  /// Verify a scanned bin barcode matches the item's assigned bin.
  Future<LabelingVerifyResult> verifyShelf(String itemId, String barcode) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/items/$itemId/verify',
        data: {'barcode': barcode},
      );
      return LabelingVerifyResult.fromJson(response.data);
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['message'] ?? 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  Future<void> _simplePost(String path, {Map<String, dynamic>? data}) async {
    try {
      final response = await _apiClient.post('${ApiEndpoints.api}$path', data: data);
      if (response.data is Map && response.data['success'] == false) {
        throw ApiException(
          message: response.data['message'] ?? 'Request failed',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['message'] ?? 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
