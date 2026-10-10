// lib/core/providers/shared_providers.dart

import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../network/api_client.dart';
import '../../data/services/product_intake_service.dart';
import '../../features/authentication/data/datasources/auth_remote_data_source_impl.dart';

// ============================================
// SHARED API CLIENT (Single source of truth)
// ============================================
final apiClientProvider = Provider<ApiClient>((ref) {
  print('🏭 CREATING API CLIENT INSTANCE');
  
  final apiClient = ApiClient();
  apiClient.initialize(); // This now includes auth interceptor
  
  print('✅ API CLIENT READY');
  return apiClient;
});

// ============================================
// AUTHENTICATION PROVIDERS
// ============================================
final authRemoteDataSourceProvider = Provider<AuthRemoteDataSourceImpl>((ref) {
  print('🔐 CREATING AUTH DATA SOURCE');
  final apiClient = ref.read(apiClientProvider); // Use shared instance
  return AuthRemoteDataSourceImpl(apiClient);
});

// ============================================
// PRODUCT INTAKE PROVIDERS  
// ============================================
final productIntakeServiceProvider = Provider<ProductIntakeService>((ref) {
  print('📦 CREATING PRODUCT INTAKE SERVICE');
  final apiClient = ref.read(apiClientProvider); // Use shared instance
  return ProductIntakeService(apiClient);
});