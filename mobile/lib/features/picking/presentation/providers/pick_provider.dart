import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/pick_models.dart';
import '../../../../data/services/pick_service.dart';

// Service provider
final pickServiceProvider = Provider<PickService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PickService(apiClient);
});

// Pick list state
class PickListState {
  final List<PickOrder> orders;
  final List<PickLocation> locations;
  final PickStats stats;
  final bool isLoading;
  final String? error;
  final String? selectedLocationId;
  final bool myPicksOnly;

  const PickListState({
    this.orders = const [],
    this.locations = const [],
    this.stats = const PickStats(),
    this.isLoading = false,
    this.error,
    this.selectedLocationId,
    this.myPicksOnly = false,
  });

  PickListState copyWith({
    List<PickOrder>? orders,
    List<PickLocation>? locations,
    PickStats? stats,
    bool? isLoading,
    String? error,
    String? selectedLocationId,
    bool? myPicksOnly,
    bool clearError = false,
    bool clearLocationId = false,
  }) {
    return PickListState(
      orders: orders ?? this.orders,
      locations: locations ?? this.locations,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      selectedLocationId: clearLocationId ? null : (selectedLocationId ?? this.selectedLocationId),
      myPicksOnly: myPicksOnly ?? this.myPicksOnly,
    );
  }
}

class PickListNotifier extends StateNotifier<PickListState> {
  final PickService _pickService;

  PickListNotifier(this._pickService) : super(const PickListState()) {
    loadPickList();
  }

  Future<void> loadPickList() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _pickService.getPickList(
        locationId: state.selectedLocationId,
        myPicks: state.myPicksOnly,
      );
      state = state.copyWith(
        orders: result.orders,
        locations: result.locations,
        stats: result.stats,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadPickList();

  void setLocationFilter(String? locationId) {
    if (locationId == null) {
      state = state.copyWith(clearLocationId: true);
    } else {
      state = state.copyWith(selectedLocationId: locationId);
    }
    loadPickList();
  }

  void toggleMyPicks() {
    state = state.copyWith(myPicksOnly: !state.myPicksOnly);
    loadPickList();
  }

  Future<bool> startPicking(String orderId) async {
    try {
      final updatedOrder = await _pickService.startPicking(orderId);
      // Replace order in the list with updated version
      final updatedOrders = state.orders.map((o) {
        return o.id == updatedOrder.id ? updatedOrder : o;
      }).toList();
      state = state.copyWith(orders: updatedOrders);
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }
}

final pickListProvider = StateNotifierProvider<PickListNotifier, PickListState>((ref) {
  final pickService = ref.watch(pickServiceProvider);
  return PickListNotifier(pickService);
});

// Active picking state (single order being picked)
class ActivePickState {
  final PickOrder? order;
  final bool isLoading;
  final String? error;
  final String? successMessage;
  final bool scannerActive;

  const ActivePickState({
    this.order,
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.scannerActive = false,
  });

  ActivePickState copyWith({
    PickOrder? order,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool? scannerActive,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return ActivePickState(
      order: order ?? this.order,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
      scannerActive: scannerActive ?? this.scannerActive,
    );
  }
}

class ActivePickNotifier extends StateNotifier<ActivePickState> {
  final PickService _pickService;

  ActivePickNotifier(this._pickService) : super(const ActivePickState());

  Future<void> loadOrder(String orderId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final order = await _pickService.getOrder(orderId);
      state = state.copyWith(order: order, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> startPicking() async {
    if (state.order == null) return;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _pickService.startPicking(state.order!.id);
      state = state.copyWith(
        order: updated,
        isLoading: false,
        successMessage: 'Picking started',
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> pickItem(String itemId, {String? barcode}) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _pickService.pickItem(itemId, barcode: barcode);
      await _refreshOrder();
      state = state.copyWith(successMessage: 'Item picked');
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> shortItem(String itemId, String reason) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _pickService.shortItem(itemId, reason);
      await _refreshOrder();
      state = state.copyWith(successMessage: 'Item marked short');
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> completePicking() async {
    if (state.order == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _pickService.completePicking(state.order!.id);
      state = state.copyWith(
        isLoading: false,
        successMessage: 'Picking completed',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<ScanVerifyResult> scanVerify(String itemId, String barcode) async {
    return _pickService.scanVerify(itemId, barcode);
  }

  void toggleScanner() {
    state = state.copyWith(scannerActive: !state.scannerActive);
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }

  Future<void> _refreshOrder() async {
    if (state.order == null) return;
    try {
      final order = await _pickService.getOrder(state.order!.id);
      state = state.copyWith(order: order);
    } catch (_) {
      // Silently fail refresh - item update was successful
    }
  }
}

final activePickProvider = StateNotifierProvider<ActivePickNotifier, ActivePickState>((ref) {
  final pickService = ref.watch(pickServiceProvider);
  return ActivePickNotifier(pickService);
});
