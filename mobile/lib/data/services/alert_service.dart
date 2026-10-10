import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/alert_models.dart';

class AlertService {
  final ApiClient _apiClient;
  AlertService(this._apiClient);

  static final String _basePath = '${AppConstants.baseUrl}/api/v1/inventory/alerts';

  /// Get inventory alerts, optionally filtered by type and location.
  Future<List<InventoryAlert>> getAlerts({String? type, String? locationId}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (type != null) queryParams['type'] = type;
      if (locationId != null) queryParams['location_id'] = locationId;

      final response = await _apiClient.get(
        _basePath,
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        return (response.data['data'] as List<dynamic>)
            .map((e) => InventoryAlert.fromJson(e as Map<String, dynamic>))
            .toList();
      }

      throw ApiException(message: 'Failed to load alerts', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get alert summary counts.
  Future<AlertSummary> getSummary() async {
    try {
      final response = await _apiClient.get('$_basePath/summary');

      if (response.data['success'] == true) {
        return AlertSummary.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Failed to load alert summary', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get alert configuration.
  Future<AlertConfig> getConfig() async {
    try {
      final response = await _apiClient.get('$_basePath/config');

      if (response.data['success'] == true) {
        return AlertConfig.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Failed to load alert config', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Update alert configuration.
  Future<void> updateConfig(AlertConfig config) async {
    try {
      final response = await _apiClient.put(
        '$_basePath/config',
        data: config.toJson(),
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to update alert config',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Dismiss a single alert.
  Future<void> dismissAlert(String alertId) async {
    try {
      final response = await _apiClient.post('$_basePath/$alertId/dismiss');

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to dismiss alert',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Dismiss all alerts.
  Future<void> dismissAll() async {
    try {
      final response = await _apiClient.post('$_basePath/dismiss-all');

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to dismiss all alerts',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Restore a previously dismissed alert.
  Future<void> restoreAlert(String alertId) async {
    try {
      final response = await _apiClient.post('$_basePath/$alertId/restore');

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to restore alert',
          statusCode: response.statusCode ?? 422,
        );
      }
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
