import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/storage/secure_storage_service.dart';
import '../../../authentication/domain/usecases/auth_usecases.dart';
import '../../../authentication/data/repositories/auth_repository_impl.dart';
import '../../../authentication/domain/repositories/auth_repository.dart';
export '../../../authentication/domain/repositories/auth_repository.dart' show AuthUser;
import '../../../authentication/data/datasources/auth_remote_data_source_impl.dart';
import '../../../../core/providers/shared_providers.dart';

// providers:
final authRemoteDataSourceProvider = Provider<AuthRemoteDataSourceImpl>((ref) {
  final apiClient = ref.read(apiClientProvider); // Same shared instance
  return AuthRemoteDataSourceImpl(apiClient);
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepositoryImpl(
    remoteDataSource: ref.read(authRemoteDataSourceProvider),
    storageService: SecureStorageService.instance,
  );
});

// Use case providers
final loginUseCaseProvider = Provider<LoginUseCase>((ref) {
  return LoginUseCase(ref.read(authRepositoryProvider));
});

final logoutUseCaseProvider = Provider<LogoutUseCase>((ref) {
  return LogoutUseCase(ref.read(authRepositoryProvider));
});

final registerUseCaseProvider = Provider<RegisterUseCase>((ref) {
  return RegisterUseCase(ref.read(authRepositoryProvider));
});

final getCurrentUserUseCaseProvider = Provider<GetCurrentUserUseCase>((ref) {
  return GetCurrentUserUseCase(ref.read(authRepositoryProvider));
});

final checkAuthStatusUseCaseProvider = Provider<CheckAuthStatusUseCase>((ref) {
  return CheckAuthStatusUseCase(ref.read(authRepositoryProvider));
});

// Auth state provider
final authProvider = AsyncNotifierProvider<AuthNotifier, AuthUser?>(() {
  return AuthNotifier();
});

class AuthNotifier extends AsyncNotifier<AuthUser?> {
  @override
  Future<AuthUser?> build() async {
    // No token -> straight to login.
    final hasToken = await ref.read(checkAuthStatusUseCaseProvider).call();
    if (!hasToken) return null;

    final repo = ref.read(authRepositoryProvider);

    // Optimistic restore: if we have a cached user, land on home immediately
    // and validate the session in the background.
    final cachedUser = await repo.getCachedUser();
    if (cachedUser != null) {
      Future.microtask(_validateSession);
      return AuthUser(user: cachedUser, accessToken: '');
    }

    // Older installs: token but no cached user. Validate once.
    final result = await ref.read(getCurrentUserUseCaseProvider).call();
    return result.fold(
      (failure) {
        // Definitive 401 -> drop the dead token. Transient errors keep it
        // so a later cold start can retry rather than forcing re-login.
        if (failure is AuthFailure) {
          repo.clearAuthData();
        }
        return null;
      },
      (user) => AuthUser(user: user, accessToken: ''),
    );
  }

  /// Background session validation for the optimistic-restore path.
  /// Only a definitive 401 logs the user out; network/timeout/5xx are ignored.
  Future<void> _validateSession() async {
    final repo = ref.read(authRepositoryProvider);
    final result = await ref.read(getCurrentUserUseCaseProvider).call();
    result.fold(
      (failure) async {
        if (failure is AuthFailure) {
          await repo.clearAuthData();
          state = const AsyncData(null);
        }
        // Transient failure: keep the session as-is.
      },
      (user) {
        final current = state.value;
        state = AsyncData(
          current != null
              ? current.copyWith(user: user)
              : AuthUser(user: user, accessToken: ''),
        );
      },
    );
  }

  Future<bool> login(String email, String password) async {
    print("🔍 DEBUG: AuthNotifier.login() called");
    
    state = const AsyncLoading();
    
    final result = await ref.read(loginUseCaseProvider).call(email, password);
    
    print("🔍 DEBUG: Login use case result: $result");
    
    return result.fold(
      (failure) {
        print("🔍 DEBUG: Login failed: $failure");
        state = AsyncError(failure, StackTrace.current);
        return false;
      },
      (authUser) {
        print("🔍 DEBUG: Login success, setting state: $authUser");
        state = AsyncData(authUser);
        return true;
      },
    );
  }

  Future<bool> register(String name, String email, String password) async {
    state = const AsyncLoading();
    
    final result = await ref.read(registerUseCaseProvider).call(name, email, password);
    
    return result.fold(
      (failure) {
        state = AsyncError(failure, StackTrace.current);
        return false;
      },
      (authUser) {
        state = AsyncData(authUser);
        return true;
      },
    );
  }

  Future<void> logout() async {
    state = const AsyncLoading();
    
    final result = await ref.read(logoutUseCaseProvider).call();
    
    result.fold(
      (failure) {
        // Even if logout fails on server, clear local data
        state = const AsyncData(null);
      },
      (_) {
        state = const AsyncData(null);
      },
    );
  }

  Future<void> refreshUser() async {
    final currentState = state.value;
    if (currentState == null) return;

    final result = await ref.read(getCurrentUserUseCaseProvider).call();
    result.fold(
      (failure) {
        // Keep current state on failure
      },
      (user) {
        state = AsyncData(currentState.copyWith(user: user));
      },
    );
  }
}

// UI state providers (keeping these for backwards compatibility)
final loginFormProvider = StateNotifierProvider<LoginFormNotifier, LoginFormState>((ref) {
  return LoginFormNotifier();
});

class LoginFormState {
  final String email;
  final String password;
  final bool isObscured;
  final String? emailError;
  final String? passwordError;
  final bool isLoading;

  const LoginFormState({
    this.email = '',
    this.password = '',
    this.isObscured = true,
    this.emailError,
    this.passwordError,
    this.isLoading = false,
  });

  LoginFormState copyWith({
    String? email,
    String? password,
    bool? isObscured,
    String? emailError,
    String? passwordError,
    bool? isLoading,
  }) {
    return LoginFormState(
      email: email ?? this.email,
      password: password ?? this.password,
      isObscured: isObscured ?? this.isObscured,
      emailError: emailError,
      passwordError: passwordError,
      isLoading: isLoading ?? this.isLoading,
    );
  }

  bool get isValid => 
    email.isNotEmpty && 
    password.isNotEmpty && 
    emailError == null && 
    passwordError == null;
}

class LoginFormNotifier extends StateNotifier<LoginFormState> {
  LoginFormNotifier() : super(const LoginFormState());

  void updateEmail(String email) {
    state = state.copyWith(
      email: email,
      emailError: _validateEmail(email),
    );
  }

  void updatePassword(String password) {
    state = state.copyWith(
      password: password,
      passwordError: _validatePassword(password),
    );
  }

  void togglePasswordVisibility() {
    state = state.copyWith(isObscured: !state.isObscured);
  }

  void setLoading(bool isLoading) {
    state = state.copyWith(isLoading: isLoading);
  }

  void clearErrors() {
    state = state.copyWith(emailError: null, passwordError: null);
  }

  String? _validateEmail(String email) {
    if (email.isEmpty) return null;
    final emailRegex = RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$');
    return emailRegex.hasMatch(email) ? null : 'Please enter a valid email address';
  }

  String? _validatePassword(String password) {
    if (password.isEmpty) return null;
    return password.length >= 8 ? null : 'Password must be at least 8 characters';
  }
}

// REGISTER FORM PROVIDER - FIXED PROPERTY NAMES
final registerFormProvider = StateNotifierProvider<RegisterFormNotifier, RegisterFormState>((ref) {
  return RegisterFormNotifier();
});

class RegisterFormState {
  final String name;
  final String email;
  final String password;
  final String passwordConfirmation;
  final bool isObscured;  // FIXED: matches register page expectations
  final bool isConfirmObscured;  // FIXED: matches register page expectations
  final String? nameError;
  final String? emailError;
  final String? passwordError;
  final String? passwordConfirmationError;
  final bool isLoading;

  const RegisterFormState({
    this.name = '',
    this.email = '',
    this.password = '',
    this.passwordConfirmation = '',
    this.isObscured = true,
    this.isConfirmObscured = true,
    this.nameError,
    this.emailError,
    this.passwordError,
    this.passwordConfirmationError,
    this.isLoading = false,
  });

  RegisterFormState copyWith({
    String? name,
    String? email,
    String? password,
    String? passwordConfirmation,
    bool? isObscured,
    bool? isConfirmObscured,
    String? nameError,
    String? emailError,
    String? passwordError,
    String? passwordConfirmationError,
    bool? isLoading,
  }) {
    return RegisterFormState(
      name: name ?? this.name,
      email: email ?? this.email,
      password: password ?? this.password,
      passwordConfirmation: passwordConfirmation ?? this.passwordConfirmation,
      isObscured: isObscured ?? this.isObscured,
      isConfirmObscured: isConfirmObscured ?? this.isConfirmObscured,
      nameError: nameError,
      emailError: emailError,
      passwordError: passwordError,
      passwordConfirmationError: passwordConfirmationError,
      isLoading: isLoading ?? this.isLoading,
    );
  }

  bool get isValid => 
    name.isNotEmpty && 
    email.isNotEmpty && 
    password.isNotEmpty &&
    passwordConfirmation.isNotEmpty &&
    nameError == null && 
    emailError == null && 
    passwordError == null &&
    passwordConfirmationError == null;
}

class RegisterFormNotifier extends StateNotifier<RegisterFormState> {
  RegisterFormNotifier() : super(const RegisterFormState());

  void updateName(String name) {
    state = state.copyWith(
      name: name,
      nameError: _validateName(name),
    );
  }

  void updateEmail(String email) {
    state = state.copyWith(
      email: email,
      emailError: _validateEmail(email),
    );
  }

  void updatePassword(String password) {
    final passwordError = _validatePassword(password);
    final confirmationError = _validatePasswordConfirmation(password, state.passwordConfirmation);
    
    state = state.copyWith(
      password: password,
      passwordError: passwordError,
      passwordConfirmationError: confirmationError,
    );
  }

  void updatePasswordConfirmation(String passwordConfirmation) {
    state = state.copyWith(
      passwordConfirmation: passwordConfirmation,
      passwordConfirmationError: _validatePasswordConfirmation(state.password, passwordConfirmation),
    );
  }

  void togglePasswordVisibility() {
    state = state.copyWith(isObscured: !state.isObscured);
  }

  void toggleConfirmPasswordVisibility() {
    state = state.copyWith(isConfirmObscured: !state.isConfirmObscured);
  }

  void setLoading(bool isLoading) {
    state = state.copyWith(isLoading: isLoading);
  }

  void clearErrors() {
    state = state.copyWith(
      nameError: null,
      emailError: null,
      passwordError: null,
      passwordConfirmationError: null,
    );
  }

  String? _validateName(String name) {
    if (name.isEmpty) return null;
    return name.trim().length >= 2 ? null : 'Name must be at least 2 characters';
  }

  String? _validateEmail(String email) {
    if (email.isEmpty) return null;
    final emailRegex = RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$');
    return emailRegex.hasMatch(email) ? null : 'Please enter a valid email address';
  }

  String? _validatePassword(String password) {
    if (password.isEmpty) return null;
    return password.length >= 8 ? null : 'Password must be at least 8 characters';
  }

  String? _validatePasswordConfirmation(String password, String passwordConfirmation) {
    if (passwordConfirmation.isEmpty) return null;
    return password == passwordConfirmation ? null : 'Passwords do not match';
  }
}