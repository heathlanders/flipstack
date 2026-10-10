import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';

class SessionsPage extends ConsumerStatefulWidget {
  const SessionsPage({super.key});

  @override
  ConsumerState<SessionsPage> createState() => _SessionsPageState();
}

class _SessionsPageState extends ConsumerState<SessionsPage> {
  List<Map<String, dynamic>> _sessions = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadSessions();
  }

  Future<void> _loadSessions() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final apiService = ApiService();
      await apiService.initialize();
      final response = await apiService.get('${ApiEndpoints.api}/profile/sessions');

      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'] ?? [];
        setState(() {
          _sessions = data.cast<Map<String, dynamic>>();
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.data['message'] ?? 'Failed to load sessions';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Failed to load sessions: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _revokeSession(String sessionId) async {
    try {
      final apiService = ApiService();
      await apiService.initialize();
      final response = await apiService.delete(
        '${ApiEndpoints.api}/profile/sessions/$sessionId',
      );

      if (response.data['success'] == true || response.statusCode == 200) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Session revoked'),
              backgroundColor: Colors.green,
            ),
          );
        }
        _loadSessions();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.data['message'] ?? 'Failed to revoke session'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error revoking session: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _revokeAllOtherSessions() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Revoke All Other Sessions'),
        content: const Text(
          'This will log you out of all other devices. Continue?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Revoke All'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      final apiService = ApiService();
      await apiService.initialize();
      final response = await apiService.delete(
        '${ApiEndpoints.api}/profile/sessions',
      );

      if (response.data['success'] == true || response.statusCode == 200) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('All other sessions revoked'),
              backgroundColor: Colors.green,
            ),
          );
        }
        _loadSessions();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.data['message'] ?? 'Failed to revoke sessions'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error revoking sessions: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Active Sessions'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Theme.of(context).colorScheme.onPrimary,
        actions: [
          if (_sessions.length > 1)
            TextButton(
              onPressed: _revokeAllOtherSessions,
              child: Text(
                'Revoke All',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onPrimary,
                ),
              ),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError()
              : _buildSessionsList(),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(_error ?? 'An error occurred'),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: _loadSessions,
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildSessionsList() {
    if (_sessions.isEmpty) {
      return const Center(
        child: Text('No active sessions found'),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadSessions,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _sessions.length,
        itemBuilder: (context, index) {
          final session = _sessions[index];
          return _buildSessionTile(session);
        },
      ),
    );
  }

  Widget _buildSessionTile(Map<String, dynamic> session) {
    final isCurrent = session['is_current'] == true;
    final agent = session['agent'] ?? {};
    final browser = agent['browser'] ?? 'Unknown browser';
    final platform = agent['platform'] ?? 'Unknown device';
    final ipAddress = session['ip_address'] ?? 'Unknown IP';
    final lastActive = session['last_active'] ?? '';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: isCurrent
                    ? Colors.green.withOpacity(0.1)
                    : Theme.of(context).colorScheme.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                _getDeviceIcon(platform),
                color: isCurrent
                    ? Colors.green
                    : Theme.of(context).colorScheme.primary,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '$browser on $platform',
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 14,
                          ),
                        ),
                      ),
                      if (isCurrent)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.green.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Text(
                            'Current',
                            style: TextStyle(
                              color: Colors.green,
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'IP: $ipAddress',
                    style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                  ),
                  if (lastActive.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      'Last active: $lastActive',
                      style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                    ),
                  ],
                  if (!isCurrent) ...[
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 32,
                      child: OutlinedButton(
                        onPressed: () {
                          final sessionId = session['id']?.toString();
                          if (sessionId != null) {
                            _revokeSession(sessionId);
                          }
                        },
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.red,
                          side: const BorderSide(color: Colors.red),
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                        ),
                        child: const Text(
                          'Revoke',
                          style: TextStyle(fontSize: 12),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  IconData _getDeviceIcon(String platform) {
    final lower = platform.toLowerCase();
    if (lower.contains('android') || lower.contains('ios') || lower.contains('mobile')) {
      return Icons.phone_android;
    }
    if (lower.contains('mac') || lower.contains('windows') || lower.contains('linux')) {
      return Icons.laptop;
    }
    return Icons.devices;
  }
}
