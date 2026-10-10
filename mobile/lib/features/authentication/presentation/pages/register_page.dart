import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/routes/app_router.dart';
import '../../../../shared/widgets/custom_button.dart';
import '../../../../shared/widgets/custom_text_field.dart';
import '../providers/auth_provider.dart';

class RegisterPage extends ConsumerStatefulWidget {
  const RegisterPage({super.key});

  @override
  ConsumerState<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends ConsumerState<RegisterPage> {
  final _formKey = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) {
    final registerForm = ref.watch(registerFormProvider);
    final authState = ref.watch(authProvider);

    // Listen to authentication state changes
    ref.listen<AsyncValue<AuthUser?>>(authProvider, (previous, next) {
      next.whenOrNull(
        data: (authUser) {
          if (authUser != null) {
            context.go(AppRoute.home.path);
          }
        },
        error: (error, stackTrace) {
          _showErrorMessage(error.toString());
        },
      );
    });

    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      body: SafeArea(
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 40),
                _buildHeader(),
                const SizedBox(height: 32),
                _buildRegisterForm(),
                const SizedBox(height: 24),
                _buildRegisterButton(),
                const SizedBox(height: 32),
                _buildDivider(),
                const SizedBox(height: 32),
                _buildLoginButton(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Column(
      children: [
        Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primary,
            borderRadius: BorderRadius.circular(20),
          ),
          child: const Icon(
            Icons.store,
            size: 40,
            color: Colors.white,
          ),
        ),
        const SizedBox(height: 24),
        Text(
          'Create Account',
          style: Theme.of(context).textTheme.displaySmall?.copyWith(
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'Sign up to get started with your e-commerce journey',
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
            color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildRegisterForm() {
    final registerForm = ref.watch(registerFormProvider);

    return Form(
      key: _formKey,
      child: Column(
        children: [
          CustomTextField(
            label: 'Full Name',
            hintText: 'Enter your full name',
            prefixIcon: Icons.person_outlined,
            initialValue: registerForm.name,
            errorText: registerForm.nameError,
            onChanged: (value) {
              ref.read(registerFormProvider.notifier).updateName(value);
            },
            validator: (value) {
              if (value == null || value.isEmpty) {
                return AppStrings.fieldRequired;
              }
              return registerForm.nameError;
            },
          ),
          const SizedBox(height: 16),
          CustomTextField(
            label: AppStrings.email,
            hintText: 'Enter your email address',
            keyboardType: TextInputType.emailAddress,
            prefixIcon: Icons.email_outlined,
            initialValue: registerForm.email,
            errorText: registerForm.emailError,
            onChanged: (value) {
              ref.read(registerFormProvider.notifier).updateEmail(value);
            },
            validator: (value) {
              if (value == null || value.isEmpty) {
                return AppStrings.fieldRequired;
              }
              return registerForm.emailError;
            },
          ),
          const SizedBox(height: 16),
          CustomTextField(
            label: AppStrings.password,
            hintText: 'Enter your password',
            obscureText: registerForm.isObscured,
            prefixIcon: Icons.lock_outlined,
            suffixIcon: registerForm.isObscured 
              ? Icons.visibility_outlined
              : Icons.visibility_off_outlined,
            onSuffixIconTap: () {
              ref.read(registerFormProvider.notifier).togglePasswordVisibility();
            },
            initialValue: registerForm.password,
            errorText: registerForm.passwordError,
            onChanged: (value) {
              ref.read(registerFormProvider.notifier).updatePassword(value);
            },
            validator: (value) {
              if (value == null || value.isEmpty) {
                return AppStrings.fieldRequired;
              }
              return registerForm.passwordError;
            },
          ),
          const SizedBox(height: 16),
          CustomTextField(
            label: AppStrings.confirmPassword,
            hintText: 'Confirm your password',
            obscureText: registerForm.isConfirmObscured,
            prefixIcon: Icons.lock_outlined,
            suffixIcon: registerForm.isConfirmObscured 
              ? Icons.visibility_outlined
              : Icons.visibility_off_outlined,
            onSuffixIconTap: () {
              ref.read(registerFormProvider.notifier).toggleConfirmPasswordVisibility();
            },
            initialValue: registerForm.passwordConfirmation,
            errorText: registerForm.passwordConfirmationError,
            onChanged: (value) {
              ref.read(registerFormProvider.notifier).updatePasswordConfirmation(value);
            },
            validator: (value) {
              if (value == null || value.isEmpty) {
                return AppStrings.fieldRequired;
              }
              return registerForm.passwordConfirmationError;
            },
          ),
          const SizedBox(height: 16),
          _buildTermsAndConditions(),
        ],
      ),
    );
  }

  Widget _buildTermsAndConditions() {
    return Text.rich(
      TextSpan(
        text: 'By creating an account, you agree to our ',
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
        ),
        children: [
          TextSpan(
            text: 'Terms of Service',
            style: TextStyle(
              color: Theme.of(context).colorScheme.primary,
              fontWeight: FontWeight.w500,
            ),
          ),
          const TextSpan(text: ' and '),
          TextSpan(
            text: 'Privacy Policy',
            style: TextStyle(
              color: Theme.of(context).colorScheme.primary,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }

  Widget _buildRegisterButton() {
    final registerForm = ref.watch(registerFormProvider);
    final authState = ref.watch(authProvider);

    return CustomButton(
      text: AppStrings.register,
      isLoading: authState.isLoading,
      onPressed: registerForm.isValid && !authState.isLoading 
        ? _handleRegister 
        : null,
    );
  }

  Widget _buildDivider() {
    return Row(
      children: [
        const Expanded(child: Divider()),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Text(
            'or',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
            ),
          ),
        ),
        const Expanded(child: Divider()),
      ],
    );
  }

  Widget _buildLoginButton() {
    return OutlinedButton(
      onPressed: () {
        context.go(AppRoute.login.path);
      },
      child: const Text('Already have an account? Sign In'),
    );
  }

  Future<void> _handleRegister() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final registerForm = ref.read(registerFormProvider);
    final success = await ref.read(authProvider.notifier).register(
      registerForm.name,
      registerForm.email,
      registerForm.password,
    );

    if (success) {
      _showSuccessMessage('Registration successful! Welcome to our platform.');
    }
    // Error handling is done in the auth provider listener
  }

  void _showErrorMessage(String message) {
    if (!mounted) return;
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Theme.of(context).colorScheme.error,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _showSuccessMessage(String message) {
    if (!mounted) return;
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}
