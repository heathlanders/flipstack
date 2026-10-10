/// Environment-based configuration for the mobile app.
///
/// Build-time switches:
///   --dart-define=API_BASE_URL=https://develop.flipstack.app
///   --dart-define=APP_FLAVOR=develop   // or `prod` (default)
class AppConfig {
  static const String _defaultBaseUrl = 'http://localhost:8090';

  /// The base URL for the API (no trailing slash, no /api suffix).
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _defaultBaseUrl,
  );

  /// The full API base URL with /api prefix.
  static const String apiBaseUrl = '$baseUrl/api';

  /// Build flavor: `prod` or `develop`. Matches Android product flavors.
  static const String appFlavor = String.fromEnvironment(
    'APP_FLAVOR',
    defaultValue: 'prod',
  );

  /// Pointing at develop.flipstack.app staging server.
  static bool get isStaging => baseUrl.contains('develop.flipstack.app');

  /// Pointing at production flipstack.app (and NOT develop).
  static bool get isProduction =>
      baseUrl.contains('flipstack.app') && !isStaging;

  /// Local dev server (LAN IP or localhost).
  static bool get isDevelopment =>
      baseUrl.contains('192.168.') || baseUrl.contains('localhost');

  /// Short label suitable for a debug banner or About screen.
  static String get environmentLabel {
    if (isProduction) return 'Production';
    if (isStaging) return 'Develop';
    return 'Local';
  }
}
