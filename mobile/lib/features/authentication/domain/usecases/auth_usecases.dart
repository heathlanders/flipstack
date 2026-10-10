import 'package:dartz/dartz.dart';
import '../../../../core/error/failures.dart';
import '../entities/user.dart';
import '../repositories/auth_repository.dart';

class LoginUseCase {
  final AuthRepository _repository;

  LoginUseCase(this._repository);

  Future<Either<Failure, AuthUser>> call(String email, String password) async {
    return await _repository.login(email, password);
  }
}

class LogoutUseCase {
  final AuthRepository _repository;

  LogoutUseCase(this._repository);

  Future<Either<Failure, void>> call() async {
    return await _repository.logout();
  }
}

class RegisterUseCase {
  final AuthRepository _repository;

  RegisterUseCase(this._repository);

  Future<Either<Failure, AuthUser>> call(String name, String email, String password) async {
    return await _repository.register(name, email, password);
  }
}

class GetCurrentUserUseCase {
  final AuthRepository _repository;

  GetCurrentUserUseCase(this._repository);

  Future<Either<Failure, User>> call() async {
    return await _repository.getCurrentUser();
  }
}

class RefreshTokenUseCase {
  final AuthRepository _repository;

  RefreshTokenUseCase(this._repository);

  Future<Either<Failure, AuthUser>> call() async {
    return await _repository.refreshToken();
  }
}

class CheckAuthStatusUseCase {
  final AuthRepository _repository;

  CheckAuthStatusUseCase(this._repository);

  Future<bool> call() async {
    return await _repository.isLoggedIn();
  }
}

class ClearAuthDataUseCase {
  final AuthRepository _repository;

  ClearAuthDataUseCase(this._repository);

  Future<void> call() async {  // CHANGED return type to void
    final result = await _repository.clearAuthData();
    // Handle the Either result but don't return it since method should return void
    result.fold(
      (failure) {
        // Log error but don't throw since usecase should be void
        print('Clear auth data failed: $failure');
      },
      (_) {
        // Success - do nothing
      },
    );
  }
}

// Validation Use Cases
class ValidateEmailUseCase {
  Either<Failure, String> call(String email) {
    if (email.isEmpty) {
      return Left(Failures.invalidInput('Email is required'));
    }
    
    final emailRegex = RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$');
    if (!emailRegex.hasMatch(email)) {
      return Left(Failures.invalidInput('Please enter a valid email address'));
    }
    
    return Right(email);
  }
}

class ValidatePasswordUseCase {
  Either<Failure, String> call(String password) {
    if (password.isEmpty) {
      return Left(Failures.invalidInput('Password is required'));
    }
    
    if (password.length < 8) {
      return Left(Failures.invalidInput('Password must be at least 8 characters long'));
    }
    
    return Right(password);
  }
}

class ValidateNameUseCase {
  Either<Failure, String> call(String name) {
    if (name.isEmpty) {
      return Left(Failures.invalidInput('Name is required'));
    }
    
    if (name.length < 2) {
      return Left(Failures.invalidInput('Name must be at least 2 characters long'));
    }
    
    return Right(name);
  }
}

class ValidatePasswordConfirmationUseCase {
  Either<Failure, String> call(String password, String confirmation) {
    if (confirmation.isEmpty) {
      return Left(Failures.invalidInput('Password confirmation is required'));
    }
    
    if (password != confirmation) {
      return Left(Failures.invalidInput('Passwords do not match'));
    }
    
    return Right(confirmation);
  }
}
