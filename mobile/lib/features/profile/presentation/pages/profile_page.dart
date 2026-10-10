import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../shared/routes/app_router.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../authentication/presentation/providers/auth_provider.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Theme.of(context).colorScheme.onPrimary,
      ),
      body: authState.when(
        data: (user) {
          if (user == null) {
            return const Center(
              child: Text('No user data available'),
            );
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Profile Header
                Center(
                  child: Column(
                    children: [
                      CircleAvatar(
                        radius: 50,
                        backgroundColor: Theme.of(context).colorScheme.primary,
                        child: Text(
                          user.name.isNotEmpty 
                              ? user.name[0].toUpperCase()
                              : 'U',
                          style: TextStyle(
                            fontSize: 36,
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.onPrimary,
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        user.name,
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        user.email,
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                          color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 32),

                // Account Information Section
                _buildSection(
                  context,
                  title: 'Account Information',
                  children: [
                    _buildInfoTile(
                      context,
                      icon: Icons.person,
                      title: 'Full Name',
                      subtitle: user.name,
                    ),
                    _buildInfoTile(
                      context,
                      icon: Icons.email,
                      title: 'Email Address',
                      subtitle: user.email,
                    ),
                    _buildInfoTile(
                      context,
                      icon: Icons.verified_user,
                      title: 'Account Status',
                      subtitle: user.isEmailVerified ? 'Verified' : 'Not Verified',
                      trailing: user.isEmailVerified 
                          ? Icon(Icons.check_circle, color: Colors.green)
                          : Icon(Icons.warning, color: Colors.orange),
                    ),
                    _buildInfoTile(
                      context,
                      icon: Icons.calendar_today,
                      title: 'Member Since',
                      subtitle: user.createdAt != null ? _formatDate(user.createdAt!) : 'Not available',
                    ),
                  ],
                ),

                const SizedBox(height: 24),

                // Settings Section
                _buildSection(
                  context,
                  title: 'Settings',
                  children: [
                    _buildActionTile(
                      context,
                      icon: Icons.edit,
                      title: 'Edit Profile',
                      subtitle: 'Update your personal information',
                      onTap: () => _showEditProfileDialog(context),
                    ),
                    _buildActionTile(
                      context,
                      icon: Icons.lock,
                      title: 'Change Password',
                      subtitle: 'Update your password',
                      onTap: () => _showChangePasswordDialog(context),
                    ),
                    _buildActionTile(
                      context,
                      icon: Icons.notifications,
                      title: 'Notifications',
                      subtitle: 'Manage notification preferences',
                      onTap: () => _showNotificationsDialog(context),
                    ),
                  ],
                ),

                const SizedBox(height: 24),

                // Security Section
                _buildSection(
                  context,
                  title: 'Security',
                  children: [
                    _buildActionTile(
                      context,
                      icon: Icons.devices,
                      title: 'Active Sessions',
                      subtitle: 'Manage your login sessions',
                      onTap: () => context.push('/profile/sessions'),
                    ),
                    _buildActionTile(
                      context,
                      icon: Icons.security,
                      title: 'Two-Factor Authentication',
                      subtitle: 'Manage 2FA settings',
                      onTap: () => _show2FADialog(context),
                    ),
                    _buildActionTile(
                      context,
                      icon: Icons.delete_forever,
                      title: 'Delete Account',
                      subtitle: 'Permanently delete your account',
                      onTap: () => _showDeleteAccountDialog(context, ref),
                    ),
                  ],
                ),

                const SizedBox(height: 24),

                // Support Section
                _buildSection(
                  context,
                  title: 'Support',
                  children: [
                    _buildActionTile(
                      context,
                      icon: Icons.help,
                      title: 'Help & FAQ',
                      subtitle: 'Get answers to common questions',
                      onTap: () => _showHelpDialog(context),
                    ),
                    _buildActionTile(
                      context,
                      icon: Icons.contact_support,
                      title: 'Contact Support',
                      subtitle: 'Reach out to our support team',
                      onTap: () => _showContactSupportDialog(context),
                    ),
                    _buildActionTile(
                      context,
                      icon: Icons.info,
                      title: 'About',
                      subtitle: 'App version and information',
                      onTap: () => _showAboutDialog(context),
                    ),
                  ],
                ),

                const SizedBox(height: 32),

                // Logout Button
                Container(
                  width: double.infinity,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                  child: TextButton.icon(
                    onPressed: () => _showLogoutDialog(context, ref),
                    icon: Icon(
                      Icons.logout,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    label: Text(
                      'Logout',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 32),
              ],
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.error_outline,
                size: 64,
                color: Theme.of(context).colorScheme.error,
              ),
              const SizedBox(height: 16),
              Text(
                'Error loading profile',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              Text(
                error.toString(),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSection(
    BuildContext context, {
    required String title,
    required List<Widget> children,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.bold,
            color: Theme.of(context).colorScheme.primary,
          ),
        ),
        const SizedBox(height: 12),
        Container(
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: Theme.of(context).colorScheme.outline.withOpacity(0.3),
            ),
          ),
          child: Column(
            children: children,
          ),
        ),
      ],
    );
  }

  Widget _buildInfoTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    Widget? trailing,
  }) {
    return ListTile(
      leading: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primary.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(
          icon,
          color: Theme.of(context).colorScheme.primary,
        ),
      ),
      title: Text(
        title,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          fontWeight: FontWeight.w500,
        ),
      ),
      subtitle: Text(
        subtitle,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
        ),
      ),
      trailing: trailing,
    );
  }

  Widget _buildActionTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return ListTile(
      leading: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primary.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(
          icon,
          color: Theme.of(context).colorScheme.primary,
        ),
      ),
      title: Text(
        title,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          fontWeight: FontWeight.w500,
        ),
      ),
      subtitle: Text(
        subtitle,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
        ),
      ),
      trailing: const Icon(Icons.arrow_forward_ios, size: 16),
      onTap: onTap,
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }

  void _showEditProfileDialog(BuildContext context) {
    context.push(AppRoute.editProfile.path);
  }

  void _showChangePasswordDialog(BuildContext context) {
    context.push(AppRoute.changePassword.path);
  }

  void _showNotificationsDialog(BuildContext context) {
    context.push(AppRoute.notifications.path);
  }

  void _showHelpDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Help & FAQ'),
        content: const Text('Help center coming soon!'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showContactSupportDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Contact Support'),
        content: const Text('Support contact feature coming soon!'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showAboutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('About'),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('E-commerce Mobile App'),
            SizedBox(height: 8),
            Text('Version: 1.0.0'),
            SizedBox(height: 8),
            Text('Built with Flutter & Laravel'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _show2FADialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Two-Factor Authentication'),
        content: const Text(
          'Two-factor authentication setup and management is available from the web dashboard. '
          'Please visit your account settings on the web to enable or disable 2FA.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showDeleteAccountDialog(BuildContext context, WidgetRef ref) {
    final controller = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            title: const Text('Delete Account'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'This action is permanent and cannot be undone. '
                  'All your data will be permanently deleted.',
                ),
                const SizedBox(height: 16),
                const Text(
                  'Type DELETE to confirm:',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: controller,
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    hintText: 'DELETE',
                  ),
                  onChanged: (_) => setDialogState(() {}),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () {
                  controller.dispose();
                  Navigator.pop(context);
                },
                child: const Text('Cancel'),
              ),
              ElevatedButton(
                onPressed: controller.text == 'DELETE'
                    ? () async {
                        Navigator.pop(context);
                        await _performDeleteAccount(context, ref);
                        controller.dispose();
                      }
                    : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red,
                  foregroundColor: Colors.white,
                ),
                child: const Text('Delete Account'),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _performDeleteAccount(BuildContext context, WidgetRef ref) async {
    try {
      final apiService = ApiService();
      await apiService.initialize();
      final response = await apiService.delete(ApiEndpoints.deleteAccount);

      if (response.data['success'] == true || response.statusCode == 200) {
        ref.read(authProvider.notifier).logout();
      } else {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.data['message'] ?? 'Failed to delete account'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error deleting account: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _showLogoutDialog(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Logout'),
        content: const Text('Are you sure you want to logout?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              ref.read(authProvider.notifier).logout();
            },
            style: TextButton.styleFrom(
              foregroundColor: Theme.of(context).colorScheme.error,
            ),
            child: const Text('Logout'),
          ),
        ],
      ),
    );
  }
}
