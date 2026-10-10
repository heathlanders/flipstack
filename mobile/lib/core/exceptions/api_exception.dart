class ApiException implements Exception {
  final int statusCode;
  final String message;
  final String? details;

  const ApiException({
    required this.statusCode,
    required this.message,
    this.details,
  });

  // Named constructors for common error types
  factory ApiException.serverError(int statusCode, String message) {
    return ApiException(
      statusCode: statusCode,
      message: message,
    );
  }

  factory ApiException.networkError() {
    return const ApiException(
      statusCode: -1,
      message: 'Network connection failed',
    );
  }

  factory ApiException.timeout() {
    return const ApiException(
      statusCode: -2,
      message: 'Request timeout',
    );
  }

  factory ApiException.unauthorized() {
    return const ApiException(
      statusCode: 401,
      message: 'Unauthorized access',
    );
  }

  @override
  String toString() {
    return 'ApiException: $message (Status: $statusCode)';
  }
}