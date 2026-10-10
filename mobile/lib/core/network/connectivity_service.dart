import 'package:connectivity_plus/connectivity_plus.dart';

/// Singleton service for checking and monitoring network connectivity.
class ConnectivityService {
  static final ConnectivityService _instance = ConnectivityService._();
  factory ConnectivityService() => _instance;
  ConnectivityService._();

  final _connectivity = Connectivity();
  bool _isOnline = true;

  bool get isOnline => _isOnline;

  /// Stream that emits true/false when connectivity changes.
  Stream<bool> get onConnectivityChanged =>
      _connectivity.onConnectivityChanged.map((results) {
        _isOnline = results.any((r) => r != ConnectivityResult.none);
        return _isOnline;
      });

  /// Check current connectivity status.
  Future<bool> checkConnectivity() async {
    final results = await _connectivity.checkConnectivity();
    _isOnline = results.any((r) => r != ConnectivityResult.none);
    return _isOnline;
  }
}
