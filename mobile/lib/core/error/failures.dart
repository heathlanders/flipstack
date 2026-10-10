import 'package:equatable/equatable.dart';

abstract class Failure extends Equatable {
  final String message;
  final String? code;

  const Failure(this.message, {this.code});

  @override
  List<Object?> get props => [message, code];
}

// Network failures
class NetworkFailure extends Failure {
  const NetworkFailure(super.message, {super.code});
}

class ServerFailure extends Failure {
  final int? statusCode;
  
  const ServerFailure(super.message, {this.statusCode, super.code});

  @override
  List<Object?> get props => [message, statusCode, code];
}

class AuthenticationFailure extends Failure {
  const AuthenticationFailure(super.message, {super.code});
}

class ValidationFailure extends Failure {
  final Map<String, List<String>>? errors;
  
  const ValidationFailure(super.message, {this.errors, super.code});

  @override
  List<Object?> get props => [message, errors, code];
}

class CacheFailure extends Failure {
  const CacheFailure(super.message, {super.code});
}

class UnknownFailure extends Failure {
  const UnknownFailure(super.message, {super.code});
}

// Factory methods for common failures
class Failures {
  static NetworkFailure noInternet() {
    return const NetworkFailure(
      'No internet connection. Please check your network settings.',
      code: 'NO_INTERNET',
    );
  }

  static NetworkFailure connectionTimeout() {
    return const NetworkFailure(
      'Connection timeout. Please try again.',
      code: 'CONNECTION_TIMEOUT',
    );
  }

  static ServerFailure serverError([String? message, int? statusCode]) {
    return ServerFailure(
      message ?? 'Server error occurred. Please try again later.',
      statusCode: statusCode,
      code: 'SERVER_ERROR',
    );
  }

  static AuthenticationFailure invalidCredentials() {
    return const AuthenticationFailure(
      'Invalid email or password. Please try again.',
      code: 'INVALID_CREDENTIALS',
    );
  }

  static AuthenticationFailure tokenExpired() {
    return const AuthenticationFailure(
      'Your session has expired. Please login again.',
      code: 'TOKEN_EXPIRED',
    );
  }

  static ValidationFailure invalidInput(String message, [Map<String, List<String>>? errors]) {
    return ValidationFailure(
      message,
      errors: errors,
      code: 'VALIDATION_ERROR',
    );
  }

  static UnknownFailure unexpected([String? message]) {
    return UnknownFailure(
      message ?? 'An unexpected error occurred. Please try again.',
      code: 'UNEXPECTED_ERROR',
    );
  }
}
