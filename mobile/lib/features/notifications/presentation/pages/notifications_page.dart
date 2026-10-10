import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';

// Notification model
class AppNotification {
  final String id;
  final String title;
  final String message;
  final String? type;
  final bool isRead;
  final DateTime createdAt;

  AppNotification({
    required this.id,
    required this.title,
    required this.message,
    this.type,
    required this.isRead,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'].toString(),
      title: json['title'] ?? json['data']?['title'] ?? 'Notification',
      message: json['message'] ?? json['data']?['message'] ?? '',
      type: json['type'],
      isRead: json['read_at'] != null,
      createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
    );
  }
}

// Notifications state
class NotificationsState {
  final List<AppNotification> notifications;
  final bool isLoading;
  final String? error;
  final String filter;

  const NotificationsState({
    this.notifications = const [],
    this.isLoading = false,
    this.error,
    this.filter = 'all',
  });

  NotificationsState copyWith({
    List<AppNotification>? notifications,
    bool? isLoading,
    String? error,
    String? filter,
  }) {
    return NotificationsState(
      notifications: notifications ?? this.notifications,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      filter: filter ?? this.filter,
    );
  }

  List<AppNotification> get filteredNotifications {
    if (filter == 'unread') {
      return notifications.where((n) => !n.isRead).toList();
    }
    return notifications;
  }

  int get unreadCount => notifications.where((n) => !n.isRead).length;
}

// Notifications notifier
class NotificationsNotifier extends StateNotifier<NotificationsState> {
  final ApiService _apiService;

  NotificationsNotifier(this._apiService) : super(const NotificationsState()) {
    loadNotifications();
  }

  Future<void> loadNotifications() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      await _apiService.initialize();
      final response = await _apiService.get('${ApiEndpoints.api}/notifications/recent');

      if (response.data['success'] == true) {
        final List<dynamic> notificationsJson = response.data['data'] ?? [];
        final notifications = notificationsJson
            .map((json) => AppNotification.fromJson(json))
            .toList();

        state = state.copyWith(
          notifications: notifications,
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Failed to load notifications',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to load notifications',
      );
    }
  }

  Future<void> markAsRead(String notificationId) async {
    try {
      await _apiService.post('${ApiEndpoints.api}/notifications/$notificationId/read');

      final updatedNotifications = state.notifications.map((n) {
        if (n.id == notificationId) {
          return AppNotification(
            id: n.id,
            title: n.title,
            message: n.message,
            type: n.type,
            isRead: true,
            createdAt: n.createdAt,
          );
        }
        return n;
      }).toList();

      state = state.copyWith(notifications: updatedNotifications);
    } catch (e) {
      // Silent fail
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await _apiService.post('${ApiEndpoints.api}/notifications/mark-all-read');

      final updatedNotifications = state.notifications.map((n) {
        return AppNotification(
          id: n.id,
          title: n.title,
          message: n.message,
          type: n.type,
          isRead: true,
          createdAt: n.createdAt,
        );
      }).toList();

      state = state.copyWith(notifications: updatedNotifications);
    } catch (e) {
      // Silent fail
    }
  }

  void setFilter(String filter) {
    state = state.copyWith(filter: filter);
  }

  Future<void> deleteNotification(String notificationId) async {
    try {
      await _apiService.delete('${ApiEndpoints.api}/notifications/$notificationId');
      final updated = state.notifications.where((n) => n.id != notificationId).toList();
      state = state.copyWith(notifications: updated);
    } catch (e) {
      // Silent fail
    }
  }
}

// Provider
final notificationsProvider = StateNotifierProvider<NotificationsNotifier, NotificationsState>((ref) {
  return NotificationsNotifier(ApiService());
});

class NotificationsPage extends ConsumerWidget {
  const NotificationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notificationsState = ref.watch(notificationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (notificationsState.unreadCount > 0)
            TextButton(
              onPressed: () {
                ref.read(notificationsProvider.notifier).markAllAsRead();
              },
              child: const Text('Mark all read'),
            ),
          IconButton(
            icon: const Icon(Icons.settings),
            tooltip: 'Notification Preferences',
            onPressed: () => context.push('/notifications/preferences'),
          ),
        ],
      ),
      body: Column(
        children: [
          // Filter tabs
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                _buildFilterChip(
                  context,
                  ref,
                  'All',
                  'all',
                  notificationsState.filter == 'all',
                ),
                const SizedBox(width: 8),
                _buildFilterChip(
                  context,
                  ref,
                  'Unread (${notificationsState.unreadCount})',
                  'unread',
                  notificationsState.filter == 'unread',
                ),
              ],
            ),
          ),

          const Divider(height: 1),

          // Notifications list
          Expanded(
            child: _buildNotificationsList(context, ref, notificationsState),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(
    BuildContext context,
    WidgetRef ref,
    String label,
    String value,
    bool isSelected,
  ) {
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) {
        ref.read(notificationsProvider.notifier).setFilter(value);
      },
    );
  }

  Widget _buildNotificationsList(
    BuildContext context,
    WidgetRef ref,
    NotificationsState state,
  ) {
    if (state.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.error!),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () {
                ref.read(notificationsProvider.notifier).loadNotifications();
              },
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final notifications = state.filteredNotifications;

    if (notifications.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.notifications_none,
              size: 64,
              color: Colors.grey[400],
            ),
            const SizedBox(height: 16),
            Text(
              state.filter == 'unread'
                  ? 'No unread notifications'
                  : 'No notifications yet',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(notificationsProvider.notifier).loadNotifications(),
      child: ListView.builder(
        itemCount: notifications.length,
        itemBuilder: (context, index) {
          final notification = notifications[index];
          return _buildNotificationTile(context, ref, notification);
        },
      ),
    );
  }

  Widget _buildNotificationTile(
    BuildContext context,
    WidgetRef ref,
    AppNotification notification,
  ) {
    return Dismissible(
      key: Key(notification.id),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 16),
        color: Colors.red,
        child: const Icon(Icons.delete, color: Colors.white),
      ),
      onDismissed: (_) {
        ref.read(notificationsProvider.notifier).deleteNotification(notification.id);
      },
      child: ListTile(
      leading: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: _getNotificationColor(notification.type).withOpacity(0.1),
          shape: BoxShape.circle,
        ),
        child: Icon(
          _getNotificationIcon(notification.type),
          color: _getNotificationColor(notification.type),
          size: 20,
        ),
      ),
      title: Text(
        notification.title,
        style: TextStyle(
          fontWeight: notification.isRead ? FontWeight.normal : FontWeight.bold,
        ),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            notification.message,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 4),
          Text(
            _formatTime(notification.createdAt),
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey[500],
            ),
          ),
        ],
      ),
      trailing: notification.isRead
          ? null
          : Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(
                color: Colors.blue,
                shape: BoxShape.circle,
              ),
            ),
      onTap: () {
        if (!notification.isRead) {
          ref.read(notificationsProvider.notifier).markAsRead(notification.id);
        }
      },
    ),
    );
  }

  IconData _getNotificationIcon(String? type) {
    switch (type) {
      case 'inventory':
        return Icons.inventory_2;
      case 'order':
        return Icons.shopping_bag;
      case 'alert':
        return Icons.warning;
      case 'success':
        return Icons.check_circle;
      default:
        return Icons.notifications;
    }
  }

  Color _getNotificationColor(String? type) {
    switch (type) {
      case 'inventory':
        return Colors.blue;
      case 'order':
        return Colors.green;
      case 'alert':
        return Colors.orange;
      case 'success':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'Just now';
    } else if (difference.inMinutes < 60) {
      return '${difference.inMinutes}m ago';
    } else if (difference.inHours < 24) {
      return '${difference.inHours}h ago';
    } else if (difference.inDays < 7) {
      return '${difference.inDays}d ago';
    } else {
      return '${dateTime.month}/${dateTime.day}/${dateTime.year}';
    }
  }
}
