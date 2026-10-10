import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/inventory_ops_models.dart';
import '../models/pick_models.dart';

class InventoryOpsService {
  final ApiClient _apiClient;
  InventoryOpsService(this._apiClient);

  static const String _basePath = '/inventory';

  /// Adjust stock for a product at a specific location.
  Future<AdjustmentResult> adjustStock({
    required String productId,
    required String locationId,
    required int quantity,
    required String reason,
  }) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/adjust',
        data: {
          'product_id': productId,
          'location_id': locationId,
          'quantity': quantity,
          'reason': reason,
        },
      );

      if (response.data['success'] == true) {
        return AdjustmentResult.fromJson(response.data);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to adjust stock',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Transfer stock between two locations.
  Future<TransferResult> transferStock({
    required String productId,
    required String fromLocationId,
    required String toLocationId,
    required int quantity,
    String? reason,
  }) async {
    try {
      final data = <String, dynamic>{
        'product_id': productId,
        'from_location_id': fromLocationId,
        'to_location_id': toLocationId,
        'quantity': quantity,
      };
      if (reason != null) data['reason'] = reason;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/transfer',
        data: data,
      );

      if (response.data['success'] == true) {
        return TransferResult.fromJson(response.data);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to transfer stock',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Get inventory levels, optionally filtered.
  Future<List<StockLevel>> getLevels({
    String? productId,
    String? locationId,
    bool? lowStock,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (productId != null) queryParams['product_id'] = productId;
      if (locationId != null) queryParams['location_id'] = locationId;
      if (lowStock == true) queryParams['low_stock'] = '1';

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/levels',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        return (response.data['data'] as List<dynamic>)
            .map((e) => StockLevel.fromJson(e as Map<String, dynamic>))
            .toList();
      }

      throw ApiException(message: 'Failed to load inventory levels', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get inventory movement history.
  Future<List<InventoryMovement>> getMovements({
    String? productId,
    String? locationId,
    String? type,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (productId != null) queryParams['product_id'] = productId;
      if (locationId != null) queryParams['location_id'] = locationId;
      if (type != null) queryParams['type'] = type;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/movements',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        return (response.data['data'] as List<dynamic>)
            .map((e) => InventoryMovement.fromJson(e as Map<String, dynamic>))
            .toList();
      }

      throw ApiException(message: 'Failed to load movements', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get all inventory locations.
  Future<List<PickLocation>> getLocations() async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}/products/locations',
      );

      if (response.data['success'] == true) {
        return (response.data['data'] as List<dynamic>)
            .map((e) => PickLocation.fromJson(e as Map<String, dynamic>))
            .toList();
      }

      throw ApiException(message: 'Failed to load locations', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
