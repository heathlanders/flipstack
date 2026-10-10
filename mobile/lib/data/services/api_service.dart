import 'package:dio/dio.dart';
import 'package:cookie_jar/cookie_jar.dart';
import 'package:dio_cookie_manager/dio_cookie_manager.dart';
import 'package:logger/logger.dart';
import '../../core/constants/app_constants.dart';
import '../../core/storage/secure_storage_service.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  late Dio _dio;
  late CookieJar _cookieJar;
  late Logger _logger;
  late SecureStorageService _storageService;
  String? _authToken;
  String? _refreshToken;

  static const Duration _connectTimeout = Duration(milliseconds: AppConstants.connectionTimeout);
  static const Duration _receiveTimeout = Duration(milliseconds: AppConstants.receiveTimeout);
  static const Duration _sendTimeout = Duration(milliseconds: AppConstants.sendTimeout);

  // Initialize the API service
  Future<void> initialize() async {
    _logger = Logger(
      printer: PrettyPrinter(
        methodCount: 0,
        errorMethodCount: 8,
        lineLength: 120,
        colors: true,
        printEmojis: true,
        printTime: false,
      ),
    );

    _cookieJar = CookieJar();
    _storageService = SecureStorageService.instance; // Use SecureStorageService

    _dio = Dio(BaseOptions(
      baseUrl: AppConstants.apiBaseUrl,
      connectTimeout: _connectTimeout,
      receiveTimeout: _receiveTimeout,
      sendTimeout: _sendTimeout,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    ));

    // Add cookie manager
    _dio.interceptors.add(CookieManager(_cookieJar));

    // Add logging interceptor
    _dio.interceptors.add(
      LogInterceptor(
        requestBody: true,
        responseBody: true,
        logPrint: (object) => _logger.d(object.toString()),
      ),
    );

    // Add auth interceptor
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          if (_authToken != null) {
            options.headers['Authorization'] = 'Bearer $_authToken';
          }
          handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            // Token expired, try to refresh
            if (await _refreshAuthToken()) {
              // Retry the original request
              final opts = error.requestOptions;
              if (_authToken != null) {
                opts.headers['Authorization'] = 'Bearer $_authToken';
              }
              try {
                final response = await _dio.fetch(opts);
                handler.resolve(response);
                return;
              } catch (e) {
                // Refresh failed, clear tokens
                await clearAuthToken();
              }
            }
          }
          handler.next(error);
        },
      ),
    );

    // Load stored auth token
    await loadAuthToken();
  }

  // Load auth token from secure storage
  Future<void> loadAuthToken() async {
    try {
      _authToken = await _storageService.getAccessToken();
      _refreshToken = await _storageService.getRefreshToken();
      
      if (_authToken != null) {
        _logger.i('Auth token loaded successfully from secure storage');
      }
    } catch (e) {
      _logger.e('Error loading auth token from secure storage: $e');
    }
  }

  // Set auth token in secure storage
  Future<void> setAuthToken(String token, {String? refreshToken}) async {
    try {
      _authToken = token;
      _refreshToken = refreshToken;
      
      // Save to secure storage
      await _storageService.saveAccessToken(token);
      
      if (refreshToken != null) {
        await _storageService.saveRefreshToken(refreshToken);
      }
      
      _logger.i('Auth token saved successfully to secure storage');
    } catch (e) {
      _logger.e('Error saving auth token to secure storage: $e');
    }
  }

  // Clear auth token from secure storage
  Future<void> clearAuthToken() async {
    try {
      _authToken = null;
      _refreshToken = null;
      
      // Clear from secure storage
      await _storageService.clearAuthData();
      
      _logger.i('Auth token cleared successfully from secure storage');
    } catch (e) {
      _logger.e('Error clearing auth token from secure storage: $e');
    }
  }

  // Check if token exists
  bool get hasToken => _authToken != null && _authToken!.isNotEmpty;

  // Get current auth token
  String? get authToken => _authToken;

  // Refresh auth token
  Future<bool> _refreshAuthToken() async {
    if (_refreshToken == null) return false;
    
    try {
      final response = await _dio.post(
        ApiEndpoints.refresh,
        data: {'refresh_token': _refreshToken},
      );
      
      if (response.statusCode == 200 && response.data['success'] == true) {
        final newToken = response.data['data']['access_token'];
        final newRefreshToken = response.data['data']['refresh_token'];
        await setAuthToken(newToken, refreshToken: newRefreshToken);
        return true;
      }
    } catch (e) {
      _logger.e('Error refreshing token: $e');
    }
    return false;
  }

  // Generic GET request
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await _dio.get<T>(
        path,
        queryParameters: queryParameters,
        options: options,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Generic POST request
  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await _dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Generic PUT request
  Future<Response<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await _dio.put<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Generic PATCH request
  Future<Response<T>> patch<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await _dio.patch<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Generic DELETE request
  Future<Response<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await _dio.delete<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Upload file
  Future<Response<T>> uploadFile<T>(
    String path,
    String filePath, {
    String? fileName,
    Map<String, dynamic>? data,
    ProgressCallback? onSendProgress,
    CancelToken? cancelToken,
  }) async {
    try {
      final formData = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath, filename: fileName),
        if (data != null) ...data,
      });

      final response = await _dio.post<T>(
        path,
        data: formData,
        onSendProgress: onSendProgress,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Download file
  Future<Response> downloadFile(
    String path,
    String savePath, {
    ProgressCallback? onReceiveProgress,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await _dio.download(
        path,
        savePath,
        onReceiveProgress: onReceiveProgress,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Multipart form upload
  Future<Response<T>> uploadMultipartForm<T>(
    String path,
    Map<String, dynamic> fields, {
    List<String>? filePaths,
    List<String>? fileFields,
    ProgressCallback? onSendProgress,
    CancelToken? cancelToken,
  }) async {
    try {
      final formData = FormData();

      // Add regular fields
      fields.forEach((key, value) {
        formData.fields.add(MapEntry(key, value.toString()));
      });

      // Add files
      if (filePaths != null && fileFields != null) {
        for (int i = 0; i < filePaths.length && i < fileFields.length; i++) {
          formData.files.add(MapEntry(
            fileFields[i],
            await MultipartFile.fromFile(filePaths[i]),
          ));
        }
      }

      final response = await _dio.post<T>(
        path,
        data: formData,
        onSendProgress: onSendProgress,
        cancelToken: cancelToken,
      );
      _logResponse(response);
      return response;
    } on DioException catch (e) {
      _logError(e);
      rethrow;
    }
  }

  // Get Dio instance for custom requests
  Dio get dio => _dio;

  // Update base URL
  void updateBaseUrl(String newBaseUrl) {
    _dio.options.baseUrl = newBaseUrl;
    _logger.i('Base URL updated to: $newBaseUrl');
  }

  // Add custom header
  void addHeader(String key, String value) {
    _dio.options.headers[key] = value;
    _logger.i('Header added: $key = $value');
  }

  // Remove header
  void removeHeader(String key) {
    _dio.options.headers.remove(key);
    _logger.i('Header removed: $key');
  }

  // Clear all custom headers
  void clearHeaders() {
    _dio.options.headers.clear();
    _dio.options.headers.addAll({
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    });
    _logger.i('All headers cleared and defaults restored');
  }

  // Set timeout
  void setTimeout({
    Duration? connectTimeout,
    Duration? receiveTimeout,
    Duration? sendTimeout,
  }) {
    if (connectTimeout != null) _dio.options.connectTimeout = connectTimeout;
    if (receiveTimeout != null) _dio.options.receiveTimeout = receiveTimeout;
    if (sendTimeout != null) _dio.options.sendTimeout = sendTimeout;
    _logger.i('Timeouts updated');
  }

  // Logging helpers
  void _logResponse(Response response) {
    if (response.statusCode != null && response.statusCode! >= 200 && response.statusCode! < 300) {
      _logger.i('✅ ${response.requestOptions.method} ${response.requestOptions.path} - ${response.statusCode}');
    } else {
      _logger.w('⚠️ ${response.requestOptions.method} ${response.requestOptions.path} - ${response.statusCode}');
    }
  }

  void _logError(DioException error) {
    _logger.e('❌ ${error.requestOptions.method} ${error.requestOptions.path} - ${error.message}');
    if (error.response != null) {
      _logger.e('Response: ${error.response?.statusCode} - ${error.response?.data}');
    }
  }

  // Close the Dio instance
  void close() {
    _dio.close();
    _logger.i('API Service closed');
  }
}