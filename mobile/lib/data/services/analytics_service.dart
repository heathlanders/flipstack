import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/analytics_models.dart';

/// Service for fetching inventory analytics and valuation data.
class AnalyticsService {
  final ApiClient _apiClient;
  AnalyticsService(this._apiClient);

  /// Fetch inventory analytics summary (legacy endpoint).
  Future<InventoryAnalytics> getAnalytics({
    String? startDate,
    String? endDate,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (startDate != null) queryParams['start_date'] = startDate;
      if (endDate != null) queryParams['end_date'] = endDate;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}/inventory/analytics',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return InventoryAnalytics.fromJson(
          response.data['data'] as Map<String, dynamic>,
        );
      }

      throw ApiException(
        message: 'Failed to load analytics',
        statusCode: response.statusCode ?? 500,
      );
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch inventory valuation, optionally filtered by location.
  Future<InventoryValuation> getValuation({String? locationId}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (locationId != null) queryParams['location_id'] = locationId;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}/inventory/valuation',
        queryParameters: queryParams,
      );

      if (response.data['success'] == true && response.data['data'] != null) {
        return InventoryValuation.fromJson(
          response.data['data'] as Map<String, dynamic>,
        );
      }

      throw ApiException(
        message: 'Failed to load valuation',
        statusCode: response.statusCode ?? 500,
      );
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch KPI data from the analytics API.
  Future<Map<String, dynamic>> getKPIs() async {
    try {
      final response = await _apiClient.get(ApiEndpoints.analyticsKpis);
      if (response.data is Map<String, dynamic>) {
        return response.data as Map<String, dynamic>;
      }
      throw ApiException(message: 'Invalid KPI response', statusCode: 500);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch stock health metrics.
  Future<StockHealth> getStockHealth() async {
    try {
      final response = await _apiClient.get(ApiEndpoints.analyticsStockHealth);
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      return StockHealth.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch ABC analysis.
  Future<ABCAnalysis> getABCAnalysis({String metric = 'revenue'}) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.analyticsAbc,
        queryParameters: {'metric': metric},
      );
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      return ABCAnalysis.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch turnover analysis.
  Future<TurnoverAnalysis> getTurnoverAnalysis({int period = 365}) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.analyticsTurnover,
        queryParameters: {'period': period},
      );
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      return TurnoverAnalysis.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch top performers.
  Future<List<TopPerformer>> getTopPerformers({int limit = 10}) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.analyticsTopPerformers,
        queryParameters: {'limit': limit},
      );
      if (response.data is List) {
        return (response.data as List<dynamic>)
            .map((e) => TopPerformer.fromJson(e as Map<String, dynamic>))
            .toList();
      }
      // Handle wrapped response
      final data = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      if (data['data'] is List) {
        return (data['data'] as List<dynamic>)
            .map((e) => TopPerformer.fromJson(e as Map<String, dynamic>))
            .toList();
      }
      return [];
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Fetch movement trends.
  Future<Map<String, dynamic>> getMovementTrends({
    String interval = 'day',
    int periods = 30,
  }) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.analyticsMovementTrends,
        queryParameters: {'interval': interval, 'periods': periods},
      );
      if (response.data is Map<String, dynamic>) {
        return response.data as Map<String, dynamic>;
      }
      return {};
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
