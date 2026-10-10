import '../storage/offline_scan_db.dart';
import '../constants/app_constants.dart';
import 'api_client.dart';

/// Syncs pending offline barcode scans to the server when connectivity returns.
class OfflineSyncService {
  final ApiClient _apiClient;
  OfflineSyncService(this._apiClient);

  /// Attempt to sync all pending scans. Returns the number successfully synced.
  Future<int> syncPendingScans() async {
    final pending = await OfflineScanDb.getPendingScans();
    int synced = 0;
    for (final scan in pending) {
      try {
        await _apiClient.post(
          '${ApiEndpoints.api}/mobile/intake',
          data: {
            'identifiers': [
              {
                'type': scan['barcode_type'] ?? 'unknown',
                'value': scan['barcode'],
                'is_primary': true,
              }
            ],
            'user_notes': scan['user_notes'] ?? 'Offline scan',
            'quick_quantity': scan['quantity'],
            'quick_location': scan['location'],
          },
        );
        await OfflineScanDb.markSynced(scan['id'] as int);
        synced++;
      } catch (_) {
        // Stop on first failure -- likely still offline
        break;
      }
    }
    await OfflineScanDb.deleteOldSynced();
    return synced;
  }
}
