import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/ship_models.dart';

class ShipService {
  final ApiClient _apiClient;
  ShipService(this._apiClient);

  static const String _basePath = '/fulfillment/ship';

  /// Get the ship queue with shipments ready for dispatch.
  Future<({List<ShipmentDetail> shipments, ShipStats stats})> getShipQueue() async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
      );

      if (response.data['success'] == true) {
        final shipments = (response.data['data'] as List<dynamic>)
            .map((e) => ShipmentDetail.fromJson(e as Map<String, dynamic>))
            .toList();

        final stats = response.data['stats'] != null
            ? ShipStats.fromJson(response.data['stats'])
            : const ShipStats();

        return (shipments: shipments, stats: stats);
      }

      throw ApiException(message: 'Failed to load ship queue', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get a single shipment by ID.
  Future<ShipmentDetail> getShipment(String shipmentId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$shipmentId',
      );

      if (response.data['success'] == true) {
        return ShipmentDetail.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Shipment not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Confirm a shipment as dispatched.
  Future<ShipmentDetail> confirmShipment(String shipmentId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$shipmentId/confirm',
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return ShipmentDetail.fromJson(response.data['data']);
      } else if (response.data['success'] == true) {
        return await getShipment(shipmentId);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to confirm shipment',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Void a shipping label.
  Future<ShipmentDetail> voidLabel(String shipmentId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$shipmentId/void',
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return ShipmentDetail.fromJson(response.data['data']);
      } else if (response.data['success'] == true) {
        return await getShipment(shipmentId);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to void label',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Get the label URL for a shipment.
  Future<String> getLabelUrl(String shipmentId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$shipmentId/label',
      );

      if (response.data['success'] == true) {
        return response.data['data']['url'] as String;
      }

      throw ApiException(message: 'Failed to get label URL', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
