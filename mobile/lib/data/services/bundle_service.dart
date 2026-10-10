import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/bundle_models.dart';

class BundleService {
  final ApiClient _apiClient;
  BundleService(this._apiClient);

  static const String _basePath = '/bundles';

  Future<({List<ProductBundle> bundles, int total, int active})> getBundles({String? search}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (search != null && search.isNotEmpty) queryParams['search'] = search;

      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath', queryParameters: queryParams);

      final bundles = (response.data['data'] as List<dynamic>? ?? [])
          .map((e) => ProductBundle.fromJson(e as Map<String, dynamic>))
          .toList();

      final stats = response.data['stats'] ?? {};
      return (
        bundles: bundles,
        total: (stats['total'] as int?) ?? bundles.length,
        active: (stats['active'] as int?) ?? 0,
      );
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  Future<ProductBundle> getBundle(String bundleId) async {
    try {
      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath/$bundleId');

      if (response.data['success'] == true) {
        return ProductBundle.fromJson(response.data['data']);
      }

      throw ApiException(message: 'Bundle not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
