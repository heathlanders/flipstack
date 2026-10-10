import 'package:dio/dio.dart';
import '../../core/constants/app_constants.dart';
import '../../core/network/api_client.dart';
import '../models/message_models.dart';

class MessageService {
  final ApiClient _apiClient;
  MessageService(this._apiClient);

  static const String _basePath = '/messages';

  /// Inbox list with stats. Optional status/marketplace/search filters.
  Future<({List<MarketplaceMessage> messages, MessageStats stats})> getInbox({
    String? status,
    String? marketplace,
    String? search,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (status != null) queryParams['status'] = status;
      if (marketplace != null) queryParams['marketplace'] = marketplace;
      if (search != null && search.isNotEmpty) queryParams['search'] = search;

      final response = await _apiClient.get(
        '${ApiEndpoints.api}$_basePath',
        queryParameters: queryParams,
      );

      final messages = (response.data['data'] as List<dynamic>? ?? [])
          .map((e) => MarketplaceMessage.fromJson(e as Map<String, dynamic>))
          .toList();

      final stats = response.data['stats'] != null
          ? MessageStats.fromJson(Map<String, dynamic>.from(response.data['stats']))
          : const MessageStats();

      return (messages: messages, stats: stats);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  /// Thread detail (message + conversation + replies). Marks the message read.
  Future<({MarketplaceMessage message, List<MarketplaceMessage> thread})> getThread(String messageId) async {
    try {
      final response = await _apiClient.get('${ApiEndpoints.api}$_basePath/$messageId');

      final message = MarketplaceMessage.fromJson(response.data['message']);
      final thread = (response.data['thread'] as List<dynamic>? ?? [])
          .map((e) => MarketplaceMessage.fromJson(e as Map<String, dynamic>))
          .toList();

      return (message: message, thread: thread);
    } on DioException catch (e) {
      throw ApiException(
        message: 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  Future<MarketplaceMessage> reply(String messageId, String body) async {
    try {
      final response = await _apiClient.post(
        '${ApiEndpoints.api}$_basePath/$messageId/reply',
        data: {'body': body},
      );
      return MarketplaceMessage.fromJson(response.data['message']);
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['message'] ?? 'Failed to send reply: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  Future<void> markRead(String messageId) => _simplePost('$_basePath/$messageId/mark-read');

  Future<void> archive(String messageId) => _simplePost('$_basePath/$messageId/archive');

  Future<void> _simplePost(String path) async {
    try {
      await _apiClient.post('${ApiEndpoints.api}$path');
    } on DioException catch (e) {
      throw ApiException(
        message: e.response?.data?['message'] ?? 'Network error: ${e.message}',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }
}
