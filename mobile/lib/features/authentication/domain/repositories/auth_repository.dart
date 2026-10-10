import 'package:dartz/dartz.dart';
import '../../../../core/error/failures.dart';
import '../entities/user.dart';

abstract class AuthRepository {
  Future<Either<Failure, AuthUser>> login(String email, String password);
  Future<Either<Failure, AuthUser>> register(String name, String email, String password);
  Future<Either<Failure, Unit>> logout();
  Future<Either<Failure, User>> getCurrentUser();
  Future<bool> checkAuthStatus();

  /// Locally cached user from the last successful auth, for optimistic restore.
  /// Returns null if nothing is cached or the cache is unreadable.
  Future<User?> getCachedUser();
  
  // Additional methods that are being called
  Future<Either<Failure, AuthUser>> refreshToken();
  Future<bool> isLoggedIn();
  Future<Either<Failure, Unit>> clearAuthData();
}

class AuthUser {
  final User user;
  final String accessToken;
  final String? refreshToken;

  AuthUser({
    required this.user,
    required this.accessToken,
    this.refreshToken,
  });

  // Add getters that are being accessed in UI
  String get name => user.name;
  String get email => user.email;
  int get id => user.id;
  bool get isEmailVerified => user.isEmailVerified;
  DateTime? get createdAt => user.createdAt;
  String? get avatar => user.avatar;

  AuthUser copyWith({
    User? user,
    String? accessToken,
    String? refreshToken,
  }) {
    return AuthUser(
      user: user ?? this.user,
      accessToken: accessToken ?? this.accessToken,
      refreshToken: refreshToken ?? this.refreshToken,
    );
  }
}
