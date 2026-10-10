import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/receiving_models.dart';

class ReceivingService {
  final ApiClient _apiClient;
  ReceivingService(this._apiClient);

  static const String _basePath = '/receiving';

  /// Open purchase orders ready to receive, with summary stats.
  Future<({List<ReceivingPo> purchaseOrders, ReceivingStats stats})>
      getOpenPurchaseOrders() async {
    try {
      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath');

      if (response.data['success'] == true) {
        final pos = (response.data['data'] as List<dynamic>)
            .map((e) => ReceivingPo.fromJson(e as Map<String, dynamic>))
            .toList();

        final stats = response.data['stats'] != null
            ? ReceivingStats.fromJson(response.data['stats'])
            : const ReceivingStats();

        return (purchaseOrders: pos, stats: stats);
      }

      throw ApiException(
        message: 'Failed to load purchase orders',
        statusCode: response.statusCode ?? 500,
      );
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Single purchase order with per-line received/remaining quantities.
  Future<ReceivingPo> getPurchaseOrder(String poId) async {
    try {
      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath/$poId');

      if (response.data['success'] == true) {
        return ReceivingPo.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Purchase order not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Verify a scanned barcode matches a PO line without receiving it.
  Future<ReceivingScanResult> scanVerify(String poId, String itemId, String barcode) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$poId/items/$itemId/scan-verify',
        data: {'barcode': barcode},
      );

      if (response.data['success'] == true) {
        return ReceivingScanResult.fromJson(response.data);
      }

      throw ApiException(message: 'Scan verification failed', statusCode: 422);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Receive a quantity against a single PO line. Returns the refreshed PO.
  Future<ReceivingPo> receiveItem(
    String poId,
    String itemId, {
    required int quantity,
    String? barcode,
    String? locationId,
    String condition = 'good',
    String? conditionNotes,
  }) async {
    try {
      final data = <String, dynamic>{
        'quantity': quantity,
        'condition': condition,
      };
      if (barcode != null) data['barcode'] = barcode;
      if (locationId != null) data['location_id'] = locationId;
      if (conditionNotes != null) data['condition_notes'] = conditionNotes;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$poId/items/$itemId/receive',
        data: data,
      );

      if (response.data['success'] == true) {
        return ReceivingPo.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to receive item',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Receive all remaining quantity across every line in good condition.
  Future<ReceivingPo> completePurchaseOrder(String poId, {String? locationId, String? notes}) async {
    try {
      final data = <String, dynamic>{};
      if (locationId != null) data['location_id'] = locationId;
      if (notes != null) data['notes'] = notes;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$poId/complete',
        data: data,
      );

      if (response.data['success'] == true) {
        return ReceivingPo.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to complete receiving',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
