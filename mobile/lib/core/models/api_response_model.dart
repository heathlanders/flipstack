class ApiResponseModel<T> {
  final bool success;
  final String message;
  final T? data;
  final Map<String, dynamic>? errors;
  final Map<String, dynamic>? meta;

  const ApiResponseModel({
    required this.success,
    required this.message,
    this.data,
    this.errors,
    this.meta,
  });

  factory ApiResponseModel.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic) fromJsonT,
  ) {
    return ApiResponseModel<T>(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: json['data'] != null ? fromJsonT(json['data']) : null,
      errors: json['errors'] as Map<String, dynamic>?,
      meta: json['meta'] as Map<String, dynamic>?,
    );
  }

  // For responses without data transformation
  factory ApiResponseModel.fromJsonRaw(Map<String, dynamic> json) {
    return ApiResponseModel<T>(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: json['data'] as T?,
      errors: json['errors'] as Map<String, dynamic>?,
      meta: json['meta'] as Map<String, dynamic>?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'success': success,
      'message': message,
      'data': data,
      if (errors != null) 'errors': errors,
      if (meta != null) 'meta': meta,
    };
  }

  @override
  String toString() {
    return 'ApiResponseModel(success: $success, message: $message, data: $data)';
  }
}