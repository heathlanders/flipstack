import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/alert_models.dart';
import '../../../../data/services/alert_service.dart';

// Service provider
final alertServiceProvider = Provider<AlertService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AlertService(apiClient);
});

// Alert state
class AlertState {
  final List<InventoryAlert> alerts;
  final AlertSummary summary;
  final bool isLoading;
  final String? error;
  final String? successMessage;
  final String? typeFilter;

  const AlertState({
    this.alerts = const [],
    this.summary = const AlertSummary(),
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.typeFilter,
  });

  AlertState copyWith({
    List<InventoryAlert>? alerts,
    AlertSummary? summary,
    bool? isLoading,
    String? error,
    String? successMessage,
    String? typeFilter,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearTypeFilter = false,
  }) {
    return AlertState(
      alerts: alerts ?? this.alerts,
      summary: summary ?? this.summary,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
      typeFilter: clearTypeFilter ? null : (typeFilter ?? this.typeFilter),
    );
  }
}

class AlertNotifier extends StateNotifier<AlertState> {
  final AlertService _service;

  AlertNotifier(this._service) : super(const AlertState()) {
    loadAlerts();
    loadSummary();
  }

  Future<void> loadAlerts() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final alerts = await _service.getAlerts(type: state.typeFilter);
      state = state.copyWith(alerts: alerts, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> loadSummary() async {
    try {
      final summary = await _service.getSummary();
      state = state.copyWith(summary: summary);
    } catch (_) {
      // Summary load failure is non-critical
    }
  }

  Future<void> refresh() async {
    await Future.wait([loadAlerts(), loadSummary()]);
  }

  void setTypeFilter(String? type) {
    if (type == null) {
      state = state.copyWith(clearTypeFilter: true);
    } else {
      state = state.copyWith(typeFilter: type);
    }
    loadAlerts();
  }

  Future<bool> dismissAlert(String alertId) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _service.dismissAlert(alertId);
      final updatedAlerts = state.alerts.where((a) => a.id != alertId).toList();
      state = state.copyWith(
        alerts: updatedAlerts,
        successMessage: 'Alert dismissed',
      );
      loadSummary();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> restoreAlert(String alertId) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _service.restoreAlert(alertId);
      state = state.copyWith(successMessage: 'Alert restored');
      await refresh();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> dismissAll() async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _service.dismissAll();
      state = state.copyWith(
        alerts: [],
        summary: const AlertSummary(),
        successMessage: 'All alerts dismissed',
      );
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }
}

final alertProvider = StateNotifierProvider<AlertNotifier, AlertState>((ref) {
  final service = ref.watch(alertServiceProvider);
  return AlertNotifier(service);
});
