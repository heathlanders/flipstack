import 'dart:convert';

import 'package:dartz/dartz.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/exceptions/api_exception.dart' as exceptions;
import '../../../../core/storage/secure_storage_service.dart';
import '../../domain/entities/user.dart' as domain_entities;
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_remote_data_source.dart';
import '../../../../data/models/user_model.dart' as data_models;

class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteDataSource _remoteDataSource;
  final SecureStorageService _storageService;

  AuthRepositoryImpl({
    required AuthRemoteDataSource remoteDataSource,
    required SecureStorageService storageService,
  })  : _remoteDataSource = remoteDataSource,
        _storageService = storageService;

  @override
  Future<Either<Failure, AuthUser>> login(String email, String password) async {
    try {
      final authResponse = await _remoteDataSource.login(email, password);
      
      // Store tokens
      await _storageService.storeAccessToken(authResponse.accessToken);
      if (authResponse.refreshToken != null) {
        await _storageService.storeRefreshToken(authResponse.refreshToken!);
      }

      // Cache the user for optimistic restore on the next cold start.
      await _storageService.storeUserData(jsonEncode(authResponse.user.toJson()));

      // Convert response user to domain user
      final domainUser = domain_entities.User(
        id: authResponse.user.id,
        name: authResponse.user.name,
        email: authResponse.user.email,
        emailVerifiedAt: authResponse.user.emailVerifiedAt,
        avatar: authResponse.user.avatar,
        createdAt: authResponse.user.createdAt,
        updatedAt: authResponse.user.updatedAt,
        roles: authResponse.user.roles ?? [],
        metadata: authResponse.user.metadata,
      );

      final authUser = AuthUser(
        user: domainUser,
        accessToken: authResponse.accessToken,
        refreshToken: authResponse.refreshToken,
      );

      return Right(authUser);
    } catch (e) {
      return Left(ServerFailure('Login failed: ${e.toString()}'));
    }
  }

  @override
  Future<Either<Failure, AuthUser>> register(String name, String email, String password) async {
    try {
      final authResponse = await _remoteDataSource.register(name, email, password);
      
      // Store tokens
      await _storageService.storeAccessToken(authResponse.accessToken);
      if (authResponse.refreshToken != null) {
        await _storageService.storeRefreshToken(authResponse.refreshToken!);
      }

      // Cache the user for optimistic restore on the next cold start.
      await _storageService.storeUserData(jsonEncode(authResponse.user.toJson()));

      // Convert response user to domain user
      final domainUser = domain_entities.User(
        id: authResponse.user.id,
        name: authResponse.user.name,
        email: authResponse.user.email,
        emailVerifiedAt: authResponse.user.emailVerifiedAt,
        avatar: authResponse.user.avatar,
        createdAt: authResponse.user.createdAt,
        updatedAt: authResponse.user.updatedAt,
        roles: authResponse.user.roles ?? [],
        metadata: authResponse.user.metadata,
      );

      final authUser = AuthUser(
        user: domainUser,
        accessToken: authResponse.accessToken,
        refreshToken: authResponse.refreshToken,
      );

      return Right(authUser);
    } catch (e) {
      return Left(ServerFailure('Registration failed: ${e.toString()}'));
    }
  }

  @override
  Future<Either<Failure, Unit>> logout() async {
    try {
      await _remoteDataSource.logout();
      await _clearLocalData();
      return const Right(unit);
    } catch (e) {
      // Clear local data even if server logout fails
      await _clearLocalData();
      return const Right(unit);
    }
  }

  @override
  Future<Either<Failure, domain_entities.User>> getCurrentUser() async {
    try {
      final userModel = await _remoteDataSource.getCurrentUser();

      // Refresh the cached user so optimistic restore stays current.
      await _storageService.storeUserData(jsonEncode(userModel.toJson()));

      // Convert data model to domain entity
      final domainUser = domain_entities.User(
        id: userModel.id,
        name: userModel.name,
        email: userModel.email,
        emailVerifiedAt: userModel.emailVerifiedAt,
        avatar: userModel.avatar,
        createdAt: userModel.createdAt,
        updatedAt: userModel.updatedAt,
        roles: [], // Add roles if available in your UserModel
        metadata: const [] // Add metadata if available in your UserModel
      );

      return Right(domainUser);
    } on exceptions.ApiException catch (e) {
      // A real 401 means the token is dead -> caller should log out.
      // Anything else (timeout, no connection, 5xx) is transient -> keep session.
      if (e.statusCode == 401) {
        return Left(AuthFailure('Unauthorized'));
      }
      return Left(ServerFailure('Failed to get current user: ${e.message}'));
    } catch (e) {
      return Left(ServerFailure('Failed to get current user: ${e.toString()}'));
    }
  }

  @override
  Future<domain_entities.User?> getCachedUser() async {
    try {
      final raw = await _storageService.getUserData();
      if (raw == null || raw.isEmpty) return null;
      final map = jsonDecode(raw) as Map<String, dynamic>;
      return data_models.User.fromJson(map).toEntity();
    } catch (e) {
      return null;
    }
  }

  @override
  Future<bool> checkAuthStatus() async {
    try {
      final token = await _storageService.getAccessToken();
      return token != null && token.isNotEmpty;
    } catch (e) {
      return false;
    }
  }

  @override
  Future<Either<Failure, AuthUser>> refreshToken() async {
    try {
      final refreshToken = await _storageService.getRefreshToken();
      if (refreshToken == null) {
        return Left(AuthFailure('No refresh token available'));
      }

      // You'll need to implement refresh token in your remote data source
      // For now, just return current user
      final userResult = await getCurrentUser();
      return userResult.fold(
        (failure) => Left(failure),
        (user) {
          final authUser = AuthUser(
            user: user,
            accessToken: '', // This should be the new token from server
            refreshToken: refreshToken,
          );
          return Right(authUser);
        },
      );
    } catch (e) {
      return Left(ServerFailure('Token refresh failed: ${e.toString()}'));
    }
  }

  @override
  Future<bool> isLoggedIn() async {
    return await checkAuthStatus();
  }

  @override
  Future<Either<Failure, Unit>> clearAuthData() async {
    try {
      await _clearLocalData();
      return const Right(unit);
    } catch (e) {
      return Left(CacheFailure('Failed to clear auth data: ${e.toString()}'));
    }
  }

  Future<void> _clearLocalData() async {
    await _storageService.deleteAccessToken();
    await _storageService.deleteRefreshToken();
    await _storageService.deleteUserData();
  }
}

// Define missing Failure classes if they don't exist
class ServerFailure extends Failure {
  ServerFailure(String message) : super(message);
}

class AuthFailure extends Failure {
  AuthFailure(String message) : super(message);
}

class CacheFailure extends Failure {
  CacheFailure(String message) : super(message);
}
