import 'package:dio/dio.dart';
import 'package:cookie_jar/cookie_jar.dart';
import 'package:dio_cookie_manager/dio_cookie_manager.dart';
import 'package:flutter/foundation.dart';
import '../config/app_config.dart';
import '../exceptions/api_exception.dart';
export '../exceptions/api_exception.dart';
import '../storage/secure_storage_service.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;
  ApiClient._internal();

  late Dio _dio;
  late CookieJar _cookieJar;

  bool _initialized = false;

  void initialize() {
    if (_initialized) return;

    _cookieJar = CookieJar();

    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(milliseconds: 30000),
      receiveTimeout: const Duration(milliseconds: 30000),
      sendTimeout: const Duration(milliseconds: 30000),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    ));

    // Add interceptors in correct order
    _dio.interceptors.addAll([
      // 1. AUTH INTERCEPTOR (FIRST - most important)
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          try {
            print('🔐 AUTH INTERCEPTOR: Processing ${options.path}');
            
            final token = await SecureStorageService().getAccessToken();
            
            if (token != null && token.isNotEmpty) {
              options.headers['Authorization'] = 'Bearer $token';
              print('✅ AUTH INTERCEPTOR: Added token');
            } else {
              print('❌ AUTH INTERCEPTOR: No token available');
            }
          } catch (e) {
            print('❌ AUTH INTERCEPTOR ERROR: $e');
          }
          
          handler.next(options);
        },
        onResponse: (response, handler) {
          print('✅ AUTH INTERCEPTOR: Response ${response.statusCode} for ${response.requestOptions.path}');
          handler.next(response);
        },
        onError: (error, handler) async {
          print('❌ AUTH INTERCEPTOR: Error ${error.response?.statusCode} for ${error.requestOptions.path}');

          // Do NOT clear the token here. A single transient 401 on any endpoint
          // must not destroy the session. Logout is owned by the auth layer,
          // which clears tokens only on a definitive 401 from /auth/user.

          handler.next(error);
        },
      ),
      
      // 2. LOGGING INTERCEPTOR
      LogInterceptor(
        requestBody: true,
        responseBody: false,
        logPrint: (obj) => print('[API] $obj'),
      ),
      
      // 3. COOKIE MANAGER (only on non-web platforms)
      if (!kIsWeb) CookieManager(_cookieJar),
    ]);

    _initialized = true;
    print('🔥 ApiClient initialized with ${_dio.interceptors.length} interceptors');
    for (int i = 0; i < _dio.interceptors.length; i++) {
      print('  $i: ${_dio.interceptors[i].runtimeType}');
    }
  }

  // Token management using SecureStorageService
  Future<String?> getToken() async {
    return await SecureStorageService().getAccessToken();
  }

  Future<void> setToken(String token) async {
    await SecureStorageService().storeAccessToken(token);
  }

  Future<void> clearToken() async {
    await SecureStorageService().deleteAccessToken();
  }

  Future<bool> hasToken() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  // HTTP Methods
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    if (!_initialized) initialize();
    return await _dio.get<T>(
      path,
      queryParameters: queryParameters,
      options: options,
      cancelToken: cancelToken,
    );
  }

  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
    ProgressCallback? onSendProgress,
    ProgressCallback? onReceiveProgress,
  }) async {
    if (!_initialized) initialize();
    return await _dio.post<T>(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
      cancelToken: cancelToken,
      onSendProgress: onSendProgress,
      onReceiveProgress: onReceiveProgress,
    );
  }

  Future<Response<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
    ProgressCallback? onSendProgress,
    ProgressCallback? onReceiveProgress,
  }) async {
    if (!_initialized) initialize();
    return await _dio.put<T>(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
      cancelToken: cancelToken,
      onSendProgress: onSendProgress,
      onReceiveProgress: onReceiveProgress,
    );
  }

  Future<Response<T>> patch<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
    ProgressCallback? onSendProgress,
    ProgressCallback? onReceiveProgress,
  }) async {
    if (!_initialized) initialize();
    return await _dio.patch<T>(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
      cancelToken: cancelToken,
      onSendProgress: onSendProgress,
      onReceiveProgress: onReceiveProgress,
    );
  }

  Future<Response<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    if (!_initialized) initialize();
    return await _dio.delete<T>(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
      cancelToken: cancelToken,
    );
  }

  // File upload
  Future<Response<T>> uploadFile<T>(
    String path,
    String filePath, {
    String? fileName,
    Map<String, dynamic>? data,
    ProgressCallback? onSendProgress,
    CancelToken? cancelToken,
  }) async {
    if (!_initialized) initialize();
    
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: fileName),
      if (data != null) ...data,
    });

    return await _dio.post<T>(
      path,
      data: formData,
      onSendProgress: onSendProgress,
      cancelToken: cancelToken,
    );
  }

  // Download file
  Future<Response> downloadFile(
    String path,
    String savePath, {
    ProgressCallback? onReceiveProgress,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
  }) async {
    if (!_initialized) initialize();
    return await _dio.download(
      path,
      savePath,
      onReceiveProgress: onReceiveProgress,
      queryParameters: queryParameters,
      cancelToken: cancelToken,
    );
  }

  // Update timeouts
  void updateTimeouts({
    Duration? connectTimeout,
    Duration? receiveTimeout,
    Duration? sendTimeout,
  }) {
    if (connectTimeout != null) {
      _dio.options.connectTimeout = connectTimeout;
    }
    if (receiveTimeout != null) {
      _dio.options.receiveTimeout = receiveTimeout;
    }
    if (sendTimeout != null) {
      _dio.options.sendTimeout = sendTimeout;
    }
  }

  // Update base URL
  void updateBaseUrl(String baseUrl) {
    _dio.options.baseUrl = baseUrl;
  }

  // Add interceptor
  void addInterceptor(Interceptor interceptor) {
    _dio.interceptors.add(interceptor);
  }

  // Remove interceptor
  void removeInterceptor(Interceptor interceptor) {
    _dio.interceptors.remove(interceptor);
  }

  // Clear interceptors
  void clearInterceptors() {
    _dio.interceptors.clear();
  }

  // Get Dio instance for advanced usage
  Dio get dio {
    if (!_initialized) {
      initialize();
    }
    return _dio;
  }

  // Dispose
  void dispose() {
    _dio.close();
    _initialized = false;
  }

  // Helper methods for common API responses
  Future<T> handleResponse<T>(
    Future<Response> apiCall,
    T Function(dynamic data) parser,
  ) async {
    try {
      final response = await apiCall;
      
      if (response.statusCode != null &&
          response.statusCode! >= 200 &&
          response.statusCode! < 300) {
        return parser(response.data);
      } else {
        throw ApiException(
          message: 'HTTP ${response.statusCode}: ${response.statusMessage}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } on DioException catch (e) {
      throw ApiException(
        message: e.message ?? 'Network error occurred',
        statusCode: e.response?.statusCode ?? 500,
      );
    }
  }

  // Common error handling
  ApiException handleDioError(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
        return ApiException.timeout();
      case DioExceptionType.sendTimeout:
        return ApiException.timeout();
      case DioExceptionType.receiveTimeout:
        return ApiException.timeout();
      case DioExceptionType.badResponse:
        return ApiException.serverError(
          error.response?.statusCode ?? 500,
          'Server error: ${error.response?.statusCode}',
        );
      case DioExceptionType.cancel:
        return ApiException(message: 'Request cancelled', statusCode: -3);
      case DioExceptionType.connectionError:
        return ApiException.networkError();
      case DioExceptionType.badCertificate:
        return ApiException(message: 'Certificate error', statusCode: -4);
      default:
        return ApiException(message: 'Unknown error occurred', statusCode: -5);
    }
  }
}