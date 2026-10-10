import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/print_models.dart';

class PrintService {
  final ApiClient _apiClient;
  PrintService(this._apiClient);

  static final String _basePath = '${AppConstants.baseUrl}/api/v1/printing';

  /// Print a single product label.
  Future<PrintResult> printProductLabel(String productId, {int quantity = 1}) async {
    try {
      final response = await _apiClient.post(
        '$_basePath/print/single/$productId',
        data: {'quantity': quantity},
      );

      if (response.data['success'] == true) {
        return PrintResult(
          success: true,
          message: response.data['message'] ?? 'Label printed',
        );
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to print label',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Print labels for a batch of products.
  Future<PrintResult> printBatch(List<String> productIds, {int quantity = 1}) async {
    try {
      final response = await _apiClient.post(
        '$_basePath/print/batch',
        data: {'product_ids': productIds, 'quantity': quantity},
      );

      if (response.data['success'] == true) {
        return PrintResult.fromJson(response.data);
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to print batch',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Discover available printers on the network.
  Future<List<DiscoveredPrinter>> discoverPrinters() async {
    try {
      final response = await _apiClient.post('$_basePath/printers/discover');

      if (response.data['success'] == true || response.data['printers'] != null) {
        final printers = response.data['printers'] as List? ?? [];
        return printers.map((p) => DiscoveredPrinter.fromJson(p)).toList();
      }

      throw ApiException(
        message: response.data['message'] ?? 'Failed to discover printers',
        statusCode: response.statusCode ?? 422,
      );
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Test printer connectivity.
  Future<PrinterTestResult> testPrinter(String printerId) async {
    try {
      final response = await _apiClient.post('$_basePath/printers/$printerId/test');

      return PrinterTestResult.fromJson(response.data);
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Register a discovered printer.
  Future<bool> addPrinter(Map<String, dynamic> data) async {
    try {
      final response = await _apiClient.post(
        '$_basePath/printers/add-discovered',
        data: data,
      );

      return response.data['success'] == true;
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }

  /// Create default label templates for the organization.
  Future<bool> createDefaultTemplates() async {
    try {
      final response = await _apiClient.post('$_basePath/templates/create-defaults');

      return response.data['success'] == true;
    } on DioException catch (e) {
      final message = e.response?.data?['message'] ?? 'Network error: ${e.message}';
      throw ApiException(message: message, statusCode: e.response?.statusCode ?? 500);
    }
  }
}
