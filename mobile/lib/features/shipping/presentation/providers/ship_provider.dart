import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/ship_models.dart';
import '../../../../data/services/ship_service.dart';

// Service provider
final shipServiceProvider = Provider<ShipService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ShipService(apiClient);
});

// Ship list state
class ShipListState {
  final List<ShipmentDetail> shipments;
  final ShipStats stats;
  final bool isLoading;
  final String? error;

  const ShipListState({
    this.shipments = const [],
    this.stats = const ShipStats(),
    this.isLoading = false,
    this.error,
  });

  ShipListState copyWith({
    List<ShipmentDetail>? shipments,
    ShipStats? stats,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return ShipListState(
      shipments: shipments ?? this.shipments,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class ShipListNotifier extends StateNotifier<ShipListState> {
  final ShipService _shipService;

  ShipListNotifier(this._shipService) : super(const ShipListState()) {
    loadShipQueue();
  }

  Future<void> loadShipQueue() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _shipService.getShipQueue();
      state = state.copyWith(
        shipments: result.shipments,
        stats: result.stats,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadShipQueue();
}

final shipListProvider = StateNotifierProvider<ShipListNotifier, ShipListState>((ref) {
  final shipService = ref.watch(shipServiceProvider);
  return ShipListNotifier(shipService);
});

// Ship detail state
class ShipDetailState {
  final ShipmentDetail? shipment;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const ShipDetailState({
    this.shipment,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  ShipDetailState copyWith({
    ShipmentDetail? shipment,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return ShipDetailState(
      shipment: shipment ?? this.shipment,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class ShipDetailNotifier extends StateNotifier<ShipDetailState> {
  final ShipService _shipService;

  ShipDetailNotifier(this._shipService) : super(const ShipDetailState());

  Future<void> loadShipment(String shipmentId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final shipment = await _shipService.getShipment(shipmentId);
      state = state.copyWith(shipment: shipment, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> confirmShipment() async {
    if (state.shipment == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _shipService.confirmShipment(state.shipment!.id);
      state = state.copyWith(
        shipment: updated,
        isLoading: false,
        successMessage: 'Shipment confirmed',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> voidLabel() async {
    if (state.shipment == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _shipService.voidLabel(state.shipment!.id);
      state = state.copyWith(
        shipment: updated,
        isLoading: false,
        successMessage: 'Label voided',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }
}

final shipDetailProvider = StateNotifierProvider<ShipDetailNotifier, ShipDetailState>((ref) {
  final shipService = ref.watch(shipServiceProvider);
  return ShipDetailNotifier(shipService);
});
