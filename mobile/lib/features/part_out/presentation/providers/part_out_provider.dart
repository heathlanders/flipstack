import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/part_out_models.dart';
import '../../../../data/services/part_out_service.dart';

// Service provider
final partOutServiceProvider = Provider<PartOutService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PartOutService(apiClient);
});

// Part-out list state
class PartOutListState {
  final List<PartOutWorkOrder> workOrders;
  final List<PartOutLocation> locations;
  final PartOutStats stats;
  final bool isLoading;
  final String? error;
  final String? selectedLocationId;
  final bool myOrdersOnly;
  final String? statusFilter;

  const PartOutListState({
    this.workOrders = const [],
    this.locations = const [],
    this.stats = const PartOutStats(),
    this.isLoading = false,
    this.error,
    this.selectedLocationId,
    this.myOrdersOnly = false,
    this.statusFilter,
  });

  PartOutListState copyWith({
    List<PartOutWorkOrder>? workOrders,
    List<PartOutLocation>? locations,
    PartOutStats? stats,
    bool? isLoading,
    String? error,
    String? selectedLocationId,
    bool? myOrdersOnly,
    String? statusFilter,
    bool clearError = false,
    bool clearLocationId = false,
    bool clearStatusFilter = false,
  }) {
    return PartOutListState(
      workOrders: workOrders ?? this.workOrders,
      locations: locations ?? this.locations,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      selectedLocationId: clearLocationId ? null : (selectedLocationId ?? this.selectedLocationId),
      myOrdersOnly: myOrdersOnly ?? this.myOrdersOnly,
      statusFilter: clearStatusFilter ? null : (statusFilter ?? this.statusFilter),
    );
  }
}

class PartOutListNotifier extends StateNotifier<PartOutListState> {
  final PartOutService _partOutService;

  PartOutListNotifier(this._partOutService) : super(const PartOutListState()) {
    loadWorkOrders();
  }

  Future<void> loadWorkOrders() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _partOutService.getWorkOrderList(
        locationId: state.selectedLocationId,
        myOrders: state.myOrdersOnly,
        status: state.statusFilter,
      );
      state = state.copyWith(
        workOrders: result.workOrders,
        locations: result.locations,
        stats: result.stats,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadWorkOrders();

  void setLocationFilter(String? locationId) {
    if (locationId == null) {
      state = state.copyWith(clearLocationId: true);
    } else {
      state = state.copyWith(selectedLocationId: locationId);
    }
    loadWorkOrders();
  }

  void toggleMyOrders() {
    state = state.copyWith(myOrdersOnly: !state.myOrdersOnly);
    loadWorkOrders();
  }

  void setStatusFilter(String? status) {
    if (status == null) {
      state = state.copyWith(clearStatusFilter: true);
    } else {
      state = state.copyWith(statusFilter: status);
    }
    loadWorkOrders();
  }

  Future<bool> startWorkOrder(String workOrderId) async {
    try {
      final updated = await _partOutService.startWorkOrder(workOrderId);
      final updatedOrders = state.workOrders.map((wo) {
        return wo.id == updated.id ? updated : wo;
      }).toList();
      state = state.copyWith(workOrders: updatedOrders);
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }
}

final partOutListProvider = StateNotifierProvider<PartOutListNotifier, PartOutListState>((ref) {
  final partOutService = ref.watch(partOutServiceProvider);
  return PartOutListNotifier(partOutService);
});

// Active part-out state (single work order being executed)
class ActivePartOutState {
  final PartOutWorkOrder? workOrder;
  final bool isLoading;
  final String? error;
  final String? successMessage;
  final bool scannerActive;
  final String? currentScanItemId;

  const ActivePartOutState({
    this.workOrder,
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.scannerActive = false,
    this.currentScanItemId,
  });

  ActivePartOutState copyWith({
    PartOutWorkOrder? workOrder,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool? scannerActive,
    String? currentScanItemId,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearCurrentScanItem = false,
  }) {
    return ActivePartOutState(
      workOrder: workOrder ?? this.workOrder,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
      scannerActive: scannerActive ?? this.scannerActive,
      currentScanItemId: clearCurrentScanItem ? null : (currentScanItemId ?? this.currentScanItemId),
    );
  }
}

class ActivePartOutNotifier extends StateNotifier<ActivePartOutState> {
  final PartOutService _partOutService;

  ActivePartOutNotifier(this._partOutService) : super(const ActivePartOutState());

  Future<void> loadWorkOrder(String workOrderId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final workOrder = await _partOutService.getWorkOrder(workOrderId);
      state = state.copyWith(workOrder: workOrder, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> startWorkOrder() async {
    if (state.workOrder == null) return;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _partOutService.startWorkOrder(state.workOrder!.id);
      state = state.copyWith(
        workOrder: updated,
        isLoading: false,
        successMessage: 'Work order started',
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> extractItem(String itemId, {String? barcode, String? condition, String? notes}) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _partOutService.extractItem(itemId, barcode: barcode, condition: condition, notes: notes);
      await _refreshWorkOrder();
      state = state.copyWith(successMessage: 'Component extracted');
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> skipItem(String itemId, String reason) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _partOutService.skipItem(itemId, reason);
      await _refreshWorkOrder();
      state = state.copyWith(successMessage: 'Component skipped');
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> updateItemNotes(String itemId, String notes) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _partOutService.updateItemNotes(itemId, notes);
      await _refreshWorkOrder();
      state = state.copyWith(successMessage: 'Notes updated');
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<PartOutScanVerifyResult> scanVerify(String itemId, String barcode) async {
    return _partOutService.scanVerify(itemId, barcode);
  }

  Future<bool> completeWorkOrder() async {
    if (state.workOrder == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _partOutService.completeWorkOrder(state.workOrder!.id);
      state = state.copyWith(
        isLoading: false,
        successMessage: 'Work order completed - component products created',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> cancelWorkOrder() async {
    if (state.workOrder == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _partOutService.cancelWorkOrder(state.workOrder!.id);
      state = state.copyWith(
        isLoading: false,
        successMessage: 'Work order cancelled',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  void toggleScanner() {
    state = state.copyWith(scannerActive: !state.scannerActive);
  }

  void setCurrentScanItem(String? itemId) {
    if (itemId == null) {
      state = state.copyWith(clearCurrentScanItem: true);
    } else {
      state = state.copyWith(currentScanItemId: itemId);
    }
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }

  Future<void> _refreshWorkOrder() async {
    if (state.workOrder == null) return;
    try {
      final workOrder = await _partOutService.getWorkOrder(state.workOrder!.id);
      state = state.copyWith(workOrder: workOrder);
    } catch (_) {
      // Silently fail refresh - item action was successful
    }
  }
}

final activePartOutProvider = StateNotifierProvider<ActivePartOutNotifier, ActivePartOutState>((ref) {
  final partOutService = ref.watch(partOutServiceProvider);
  return ActivePartOutNotifier(partOutService);
});
