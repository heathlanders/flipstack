import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/storage/secure_storage_service.dart';
import '../providers/auth_provider.dart';

class TwoFactorChallengePage extends ConsumerStatefulWidget {
  final String email;

  const TwoFactorChallengePage({super.key, required this.email});

  @override
  ConsumerState<TwoFactorChallengePage> createState() => _TwoFactorChallengePageState();
}

class _TwoFactorChallengePageState extends ConsumerState<TwoFactorChallengePage> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();
  final _recoveryCodeController = TextEditingController();
  bool _isLoading = false;
  bool _useRecoveryCode = false;
  String? _error;

  @override
  void dispose() {
    _codeController.dispose();
    _recoveryCodeController.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final apiService = ApiService();
      await apiService.initialize();

      final data = {
        'email': widget.email,
        'code': _useRecoveryCode ? '' : _codeController.text.trim(),
        'recovery_code': _useRecoveryCode ? _recoveryCodeController.text.trim() : null,
      };

      final response = await apiService.post(
        '${ApiEndpoints.api}/auth/two-factor-challenge',
        data: data,
      );

      if (response.data['success'] == true) {
        // Store the token
        final token = response.data['data']['access_token'];
        await apiService.setAuthToken(token);
        await SecureStorageService.instance.saveAccessToken(token);

        // Refresh auth state
        ref.invalidate(authProvider);

        if (mounted) {
          context.go('/home');
        }
      } else {
        setState(() => _error = response.data['message'] ?? 'Invalid verification code');
      }
    } catch (e) {
      setState(() => _error = 'Verification failed. Please try again.');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Two-Factor Authentication'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.pop(),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 32),

                // Icon
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Theme.of(context).primaryColor.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.security,
                    size: 64,
                    color: Theme.of(context).primaryColor,
                  ),
                ),

                const SizedBox(height: 32),

                // Title
                Text(
                  'Verify Your Identity',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 16),

                // Description
                Text(
                  _useRecoveryCode
                      ? 'Enter one of your recovery codes to access your account.'
                      : 'Enter the 6-digit code from your authenticator app.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Colors.grey[600],
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 32),

                // Error message
                if (_error != null)
                  Container(
                    padding: const EdgeInsets.all(12),
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: Colors.red.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.withOpacity(0.3)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.error_outline, color: Colors.red, size: 20),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _error!,
                            style: const TextStyle(color: Colors.red),
                          ),
                        ),
                      ],
                    ),
                  ),

                // Code field
                if (!_useRecoveryCode)
                  TextFormField(
                    controller: _codeController,
                    decoration: const InputDecoration(
                      labelText: 'Verification Code',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.pin),
                      hintText: '000000',
                    ),
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 24,
                      letterSpacing: 8,
                      fontWeight: FontWeight.bold,
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter the verification code';
                      }
                      if (value.length != 6) {
                        return 'Code must be 6 digits';
                      }
                      return null;
                    },
                  )
                else
                  TextFormField(
                    controller: _recoveryCodeController,
                    decoration: const InputDecoration(
                      labelText: 'Recovery Code',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.vpn_key),
                      hintText: 'XXXXX-XXXXX',
                    ),
                    textCapitalization: TextCapitalization.characters,
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return 'Please enter a recovery code';
                      }
                      return null;
                    },
                  ),

                const SizedBox(height: 24),

                // Verify button
                ElevatedButton(
                  onPressed: _isLoading ? null : _verify,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: _isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Verify'),
                ),

                const SizedBox(height: 16),

                // Toggle recovery code
                TextButton(
                  onPressed: () {
                    setState(() {
                      _useRecoveryCode = !_useRecoveryCode;
                      _error = null;
                    });
                  },
                  child: Text(
                    _useRecoveryCode
                        ? 'Use authenticator code instead'
                        : 'Use a recovery code instead',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
