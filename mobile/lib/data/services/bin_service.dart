import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/bin_models.dart';

class BinService {
  final ApiClient _apiClient;
  BinService(this._apiClient);

  static const String _basePath = '/bins';

  /// Resolve a scanned bin barcode to the bin + its contents.
  Future<WarehouseBin> scan(String barcode) async {
    try {
      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath/scan',
        queryParameters: {'barcode': barcode},
      );

      if (response.data['success'] == true) {
        return WarehouseBin.fromJson(response.data['data']);
      }

      throw ApiException(message: response.data['message'] ?? 'Bin not found', statusCode: 404);
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['message'] ?? 'No bin matches that barcode.',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// List bins, optionally filtered by location/zone or a code/barcode search.
  Future<List<WarehouseBin>> list({String? locationId, String? zoneId, String? search}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (locationId != null) queryParams['location_id'] = locationId;
      if (zoneId != null) queryParams['zone_id'] = zoneId;
      if (search != null && search.isNotEmpty) queryParams['search'] = search;

      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath', queryParameters: queryParams);

      return (response.data['data'] as List<dynamic>? ?? [])
          .map((e) => WarehouseBin.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Place a product (by scanned barcode) into a bin. Returns the refreshed bin.
  Future<WarehouseBin> assignProduct(String binId, {String? barcode, String? productId}) async {
    try {
      final data = <String, dynamic>{};
      if (barcode != null) data['barcode'] = barcode;
      if (productId != null) data['product_id'] = productId;

      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$binId/assign',
        data: data,
      );

      if (response.data['success'] == true) {
        return WarehouseBin.fromJson(response.data['data']);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to assign product',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['message'] ?? 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
