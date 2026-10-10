import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/auth_provider.dart';
import '../../../home/presentation/pages/home_page.dart';

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final loginForm = ref.watch(loginFormProvider);
    
    // FIXED: Add debug logging for navigation
    ref.listen<AsyncValue<AuthUser?>>(authProvider, (previous, next) {
      print('🔍 AUTH STATE CHANGED: $next'); // Debug log
      
      next.whenData((authUser) {
        print('🔍 AUTH USER: $authUser'); // Debug log
        if (authUser != null) {
          print('🔍 NAVIGATING TO HOME...'); // Debug log
          
          // SIMPLE NAVIGATION - Replace current page instead of push
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (context) => const HomePage()),
          );
        }
      });
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Login'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Logo/Title
            Text(
              'E-commerce App',
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                fontWeight: FontWeight.bold,
                color: Theme.of(context).primaryColor,
              ),
            ),
            const SizedBox(height: 32),

            // Email Field
            TextFormField(
              decoration: const InputDecoration(
                labelText: 'Email',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.email),
              ),
              keyboardType: TextInputType.emailAddress,
              onChanged: (value) {
                ref.read(loginFormProvider.notifier).updateEmail(value);
              },
            ),
            if (loginForm.emailError != null) ...[
              const SizedBox(height: 4),
              Text(
                loginForm.emailError!,
                style: const TextStyle(color: Colors.red, fontSize: 12),
              ),
            ],
            const SizedBox(height: 16),

            // Password Field
            TextFormField(
              obscureText: loginForm.isObscured,
              decoration: InputDecoration(
                labelText: 'Password',
                border: const OutlineInputBorder(),
                prefixIcon: const Icon(Icons.lock),
                suffixIcon: IconButton(
                  icon: Icon(
                    loginForm.isObscured ? Icons.visibility : Icons.visibility_off,
                  ),
                  onPressed: () {
                    ref.read(loginFormProvider.notifier).togglePasswordVisibility();
                  },
                ),
              ),
              onChanged: (value) {
                ref.read(loginFormProvider.notifier).updatePassword(value);
              },
            ),
            if (loginForm.passwordError != null) ...[
              const SizedBox(height: 4),
              Text(
                loginForm.passwordError!,
                style: const TextStyle(color: Colors.red, fontSize: 12),
              ),
            ],
            const SizedBox(height: 24),

            // Login Button
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: authState.isLoading || !loginForm.isValid ? null : _login,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Theme.of(context).primaryColor,
                  foregroundColor: Colors.white,
                ),
                child: authState.isLoading
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('Login'),
              ),
            ),

            const SizedBox(height: 16),

            // Error Display
            if (authState.hasError) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red[100],
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Login failed: ${authState.error}',
                  style: const TextStyle(color: Colors.red),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _login() async {
    final loginForm = ref.read(loginFormProvider);
    
    print('🔍 STARTING LOGIN PROCESS...'); // Debug log
    
    final success = await ref.read(authProvider.notifier).login(
      loginForm.email,
      loginForm.password,
    );

    print('🔍 LOGIN RESULT: $success'); // Debug log

    if (!success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Login failed. Please check your credentials.'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
}