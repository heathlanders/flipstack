import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../network/connectivity_service.dart';
import '../network/offline_sync_service.dart';
import '../storage/offline_scan_db.dart';
import 'shared_providers.dart';

/// Stream provider that tracks online/offline state.
final connectivityProvider = StreamProvider<bool>((ref) {
  return ConnectivityService().onConnectivityChanged;
});

/// Convenience provider that resolves to a simple bool.
final isOnlineProvider = Provider<bool>((ref) {
  final connectivity = ref.watch(connectivityProvider);
  return connectivity.when(
    data: (v) => v,
    loading: () => true,
    error: (_, __) => true,
  );
});

/// Auto-syncs pending offline scans when connectivity is restored.
final offlineSyncProvider = Provider<OfflineSyncService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final sync = OfflineSyncService(apiClient);

  ref.listen<bool>(isOnlineProvider, (prev, next) {
    if (prev == false && next == true) {
      sync.syncPendingScans();
    }
  });

  return sync;
});

/// Future provider for the count of pending offline scans.
final pendingScanCountProvider = FutureProvider<int>((ref) {
  return OfflineScanDb.pendingCount();
});
