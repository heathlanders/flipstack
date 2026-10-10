import 'package:dio/dio.dart';
import '../../../../core/network/api_client.dart';
import '../../../../core/exceptions/api_exception.dart' as exceptions;
import '../../../../data/models/auth_response_model.dart';
import '../../../../data/models/user_model.dart' as user_models;
import 'auth_remote_data_source.dart';

class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
  final ApiClient _apiClient;

  AuthRemoteDataSourceImpl(this._apiClient);

  @override
  Future<AuthResponseModel> login(String email, String password) async {
    try {
      final response = await _apiClient.post(
        '/auth/login',
        data: {
          'email': email,
          'password': password,
        },
      );

      print('🔍 RAW RESPONSE DATA: ${response.data}'); // Debug log
      print('🔍 RESPONSE TYPE: ${response.data.runtimeType}'); // Debug log

      if (response.statusCode == 200) {
        // HANDLE DIFFERENT RESPONSE FORMATS
        dynamic responseData;
        
        if (response.data is Map<String, dynamic>) {
          // Response is an object: {"data": {...}, "message": "..."}
          responseData = response.data['data'] ?? response.data;
        } else if (response.data is List) {
          // Response is an array: [{...}]
          responseData = (response.data as List).isNotEmpty ? response.data[0] : null;
        } else {
          // Response is direct object: {...}
          responseData = response.data;
        }

        if (responseData == null) {
          throw exceptions.ApiException(
            message: 'Invalid response format from server',
            statusCode: 500,
          );
        }

        print('🔍 PARSED RESPONSE DATA: $responseData'); // Debug log
        
        return AuthResponseModel.fromJson(responseData as Map<String, dynamic>);
      } else {
        throw exceptions.ApiException(
          message: 'Login failed with status: ${response.statusCode}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      print('🔍 LOGIN ERROR: $e'); // Debug log
      throw exceptions.ApiException(
        message: 'Login failed: ${e.toString()}',
        statusCode: 500,
      );
    }
  }

  @override
  Future<AuthResponseModel> register(String name, String email, String password) async {
    try {
      final response = await _apiClient.post(
        '/auth/register',
        data: {
          'name': name,
          'email': email,
          'password': password,
        },
      );

      if (response.statusCode == 201 || response.statusCode == 200) {
        // Handle different response formats
        dynamic responseData;
        
        if (response.data is Map<String, dynamic>) {
          responseData = response.data['data'] ?? response.data;
        } else if (response.data is List) {
          responseData = (response.data as List).isNotEmpty ? response.data[0] : null;
        } else {
          responseData = response.data;
        }

        return AuthResponseModel.fromJson(responseData as Map<String, dynamic>);
      } else {
        throw exceptions.ApiException(
          message: 'Registration failed with status: ${response.statusCode}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw exceptions.ApiException(
        message: 'Registration failed: ${e.toString()}',
        statusCode: 500,
      );
    }
  }

  @override
  Future<void> logout() async {
    try {
      final response = await _apiClient.post('/auth/logout');

      if (response.statusCode != 200) {
        throw exceptions.ApiException(
          message: 'Logout failed with status: ${response.statusCode}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw exceptions.ApiException(
        message: 'Logout failed: ${e.toString()}',
        statusCode: 500,
      );
    }
  }

  @override
  Future<user_models.User> getCurrentUser() async {
    try {
      final response = await _apiClient.get('/auth/user');

      if (response.statusCode == 200) {
        // Handle different response formats
        dynamic responseData;
        
        if (response.data is Map<String, dynamic>) {
          responseData = response.data['data'] ?? response.data;
        } else if (response.data is List) {
          responseData = (response.data as List).isNotEmpty ? response.data[0] : null;
        } else {
          responseData = response.data;
        }

        return user_models.User.fromJson(responseData as Map<String, dynamic>);
      } else {
        throw exceptions.ApiException(
          message: 'Get user failed with status: ${response.statusCode}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } on exceptions.ApiException {
      rethrow;
    } on DioException catch (e) {
      // Preserve the real status so callers can tell a 401 (token dead)
      // apart from a transient network/timeout/5xx failure. No response =>
      // network/timeout, signalled with -1 (never collapse to 401 or 500).
      throw exceptions.ApiException(
        message: 'Get current user failed: ${e.message}',
        statusCode: e.response?.statusCode ?? -1,
      );
    } catch (e) {
      throw exceptions.ApiException(
        message: 'Get current user failed: ${e.toString()}',
        statusCode: -1,
      );
    }
  }

  @override
  Future<AuthResponseModel> refreshToken() async {
    try {
      final response = await _apiClient.post('/auth/refresh');

      if (response.statusCode == 200) {
        // Handle different response formats
        dynamic responseData;
        
        if (response.data is Map<String, dynamic>) {
          responseData = response.data['data'] ?? response.data;
        } else if (response.data is List) {
          responseData = (response.data as List).isNotEmpty ? response.data[0] : null;
        } else {
          responseData = response.data;
        }

        return AuthResponseModel.fromJson(responseData as Map<String, dynamic>);
      } else {
        throw exceptions.ApiException(
          message: 'Token refresh failed with status: ${response.statusCode}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw exceptions.ApiException(
        message: 'Token refresh failed: ${e.toString()}',
        statusCode: 500,
      );
    }
  }
}