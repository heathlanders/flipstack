import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import '../config/app_config.dart';
import '../network/api_client.dart';

/// Holds the latest FCM message tap so navigators can pick it up after the
/// app has been brought to the foreground.
///
/// A widget high in the tree listens, reads `data['action_url']` (set by
/// the backend `BaseNotification::toFcm()` payload), and navigates via
/// GoRouter.
class FcmActionNotifier extends ValueNotifier<RemoteMessage?> {
  FcmActionNotifier() : super(null);
  static final FcmActionNotifier instance = FcmActionNotifier();
}

/// Service for Firebase Cloud Messaging push notifications.
class PushNotificationService {
  static final PushNotificationService _instance =
      PushNotificationService._();
  factory PushNotificationService() => _instance;
  PushNotificationService._();

  String? _fcmToken;
  ApiClient? _apiClient;

  String? get fcmToken => _fcmToken;

  /// Initialize FCM. Call from main.dart after Firebase.initializeApp().
  Future<void> initialize() async {
    try {
      final messaging = FirebaseMessaging.instance;

      final settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      if (settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional) {
        _fcmToken = await messaging.getToken();
        if (_apiClient != null && _fcmToken != null) {
          // Re-register on cold start in case the token rotated while signed out.
          await _registerToken(_apiClient!, _fcmToken!);
        }

        messaging.onTokenRefresh.listen((token) async {
          _fcmToken = token;
          if (_apiClient != null) {
            await _registerToken(_apiClient!, token);
          }
        });

        FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
        FirebaseMessaging.onMessageOpenedApp.listen(_handleMessageTap);

        // Cold start from a notification tap.
        final initial = await messaging.getInitialMessage();
        if (initial != null) {
          _handleMessageTap(initial);
        }
      }
    } catch (e) {
      debugPrint('PushNotificationService: Firebase init skipped ($e)');
    }
  }

  void _handleForegroundMessage(RemoteMessage message) {
    debugPrint('FCM foreground message: ${message.messageId} ${message.data}');
    // Surface to UI listeners (e.g. notification bell, snackbar) by setting
    // the notifier; consumers can read message.notification + message.data.
    FcmActionNotifier.instance.value = message;
  }

  void _handleMessageTap(RemoteMessage message) {
    debugPrint('FCM message tap: ${message.data}');
    FcmActionNotifier.instance.value = message;
  }

  /// Register FCM token with backend (call after login).
  Future<void> registerTokenWithBackend(ApiClient apiClient) async {
    _apiClient = apiClient;
    if (_fcmToken == null) return;
    await _registerToken(apiClient, _fcmToken!);
  }

  /// Revoke the current token from the backend (call on logout).
  Future<void> revokeTokenFromBackend(ApiClient apiClient) async {
    if (_fcmToken == null) return;
    try {
      await apiClient.delete(
        '${AppConfig.apiBaseUrl}/auth/fcm-token',
        data: {'token': _fcmToken},
      );
    } catch (_) {
      // best-effort
    }
  }

  Future<void> _registerToken(ApiClient apiClient, String token) async {
    try {
      await apiClient.post(
        '${AppConfig.apiBaseUrl}/auth/fcm-token',
        data: {
          'token': token,
          'platform': defaultTargetPlatform == TargetPlatform.iOS
              ? 'ios'
              : 'android',
        },
      );
    } catch (e) {
      debugPrint('FCM token registration failed: $e');
    }
  }
}
