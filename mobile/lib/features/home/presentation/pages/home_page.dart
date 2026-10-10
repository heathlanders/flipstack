import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../authentication/presentation/providers/auth_provider.dart';
import '../../../authentication/domain/repositories/auth_repository.dart';
import '../../../inventory/presentation/pages/add_product_page.dart';
import '../../../authentication/presentation/pages/login_page.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('Home'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) {
                Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (context) => const LoginPage()),
                );
              }
            },
          ),
        ],
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              '🎉 LOGIN SUCCESSFUL!',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: Colors.green,
              ),
            ),
            const SizedBox(height: 16),
            
            authState.when(
              data: (authUser) {
                if (authUser != null) {
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Welcome, ${authUser.user.name}!',
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 8),
                          Text('Email: ${authUser.user.email}'),
                          Text('User ID: ${authUser.user.id}'),
                          Text('Email Verified: ${authUser.user.isEmailVerified ? "Yes" : "No"}'),
                          if (authUser.user.createdAt != null)
                            Text('Member since: ${authUser.user.createdAt}'),
                        ],
                      ),
                    ),
                  );
                }
                return const Text('No user data');
              },
              loading: () => const CircularProgressIndicator(),
              error: (error, stackTrace) => Text('Error: $error'),
            ),

            const SizedBox(height: 32),
            
            const Text(
              'Navigation Options:',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            
            ElevatedButton.icon(
              onPressed: () {
                // TODO: Navigate to inventory page when it's ready
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Inventory page coming soon!')),
                );
              },
              icon: const Icon(Icons.inventory),
              label: const Text('Inventory'),
            ),
            const SizedBox(height: 8),
            
            ElevatedButton.icon(
              onPressed: () {
                // FIXED: Navigate to your existing advanced Add Product page
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (context) => const AddProductPage()),
                );
              },
              icon: const Icon(Icons.add),
              label: const Text('Add Product'),
            ),
          ],
        ),
      ),
    );
  }
}