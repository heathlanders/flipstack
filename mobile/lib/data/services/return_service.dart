import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/return_models.dart';

class ReturnMobileService {
  final ApiClient _apiClient;
  ReturnMobileService(this._apiClient);

  static const String _basePath = '/fulfillment/returns';

  /// Get returns list with optional filters.
  Future<({List<ReturnRequest> returns, ReturnStats stats})> getReturns({
    String? status,
    String? search,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (status != null) queryParams['status'] = status;
      if (search != null) queryParams['search'] = search;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final returns = (response.data['data'] as List<dynamic>)
            .map((e) => ReturnRequest.fromJson(e as Map<String, dynamic>))
            .toList();

        final stats = response.data['stats'] != null
            ? ReturnStats.fromJson(response.data['stats'])
            : const ReturnStats();

        return (returns: returns, stats: stats);
      }

      throw ApiException(message: 'Failed to load returns', statusCode: response.statusCode ?? 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Get a single return by ID.
  Future<ReturnRequest> getReturn(String returnId) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/$returnId',
      );

      if (response.data['success'] == true) {
        return ReturnRequest.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Return not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Approve a return request.
  Future<ReturnRequest> approve(String returnId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$returnId/approve',
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return ReturnRequest.fromJson(response.data['data']);
      } else if (response.data['success'] == true) {
        return await getReturn(returnId);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to approve return',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Mark a return as received.
  Future<ReturnRequest> receive(String returnId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$returnId/receive',
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return ReturnRequest.fromJson(response.data['data']);
      } else if (response.data['success'] == true) {
        return await getReturn(returnId);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to receive return',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Inspect a returned item.
  Future<ReturnRequest> inspect(
    String returnId, {
    required String condition,
    String? notes,
    required bool restockable,
  }) async {
    try {
      final data = <String, dynamic>{
        'condition': condition,
        'restockable': restockable,
      };
      if (notes != null) data['notes'] = notes;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$returnId/inspect',
        data: data,
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return ReturnRequest.fromJson(response.data['data']);
      } else if (response.data['success'] == true) {
        return await getReturn(returnId);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to inspect return',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Restock a returned item to a location.
  Future<ReturnRequest> restock(String returnId, String locationId) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$returnId/restock',
        data: {'location_id': locationId},
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return ReturnRequest.fromJson(response.data['data']);
      } else if (response.data['success'] == true) {
        return await getReturn(returnId);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to restock return',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
