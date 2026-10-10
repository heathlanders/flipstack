import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/labeling_models.dart';
import '../../../../data/services/labeling_service.dart';

// Service provider
final labelingServiceProvider = Provider<LabelingService>((ref) {
  return LabelingService(ref.watch(apiClientProvider));
});

// ---------------------------------------------------------------------------
// Work order list
// ---------------------------------------------------------------------------

class LabelingListState {
  final List<LabelingWorkOrder> workOrders;
  final LabelingStats stats;
  final bool isLoading;
  final String? error;

  const LabelingListState({
    this.workOrders = const [],
    this.stats = const LabelingStats(),
    this.isLoading = false,
    this.error,
  });

  LabelingListState copyWith({
    List<LabelingWorkOrder>? workOrders,
    LabelingStats? stats,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return LabelingListState(
      workOrders: workOrders ?? this.workOrders,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class LabelingListNotifier extends StateNotifier<LabelingListState> {
  final LabelingService _service;

  LabelingListNotifier(this._service) : super(const LabelingListState()) {
    load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getWorkOrderList();
      state = state.copyWith(workOrders: result.workOrders, stats: result.stats, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => load();
}

final labelingListProvider =
    StateNotifierProvider<LabelingListNotifier, LabelingListState>((ref) {
  return LabelingListNotifier(ref.watch(labelingServiceProvider));
});

// ---------------------------------------------------------------------------
// Active work order
// ---------------------------------------------------------------------------

class ActiveLabelingState {
  final LabelingWorkOrder? workOrder;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const ActiveLabelingState({
    this.workOrder,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  ActiveLabelingState copyWith({
    LabelingWorkOrder? workOrder,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return ActiveLabelingState(
      workOrder: workOrder ?? this.workOrder,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class ActiveLabelingNotifier extends StateNotifier<ActiveLabelingState> {
  final LabelingService _service;

  ActiveLabelingNotifier(this._service) : super(const ActiveLabelingState());

  Future<void> load(String workOrderId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final wo = await _service.getWorkOrder(workOrderId);
      state = state.copyWith(workOrder: wo, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> start() => _action(() => _service.startWorkOrder(state.workOrder!.id), 'Work order started');

  Future<bool> markItemLabeled(String itemId) =>
      _action(() => _service.markItemLabeled(itemId), 'Label printed');

  Future<bool> scanBin(String itemId, String barcode) =>
      _action(() => _service.scanBin(itemId, barcode), 'Item shelved');

  Future<bool> skipItem(String itemId, String reason) =>
      _action(() => _service.skipItem(itemId, reason), 'Item skipped');

  Future<bool> markAllLabeled() =>
      _action(() => _service.markAllLabeled(state.workOrder!.id), 'All items labeled');

  Future<LabelingVerifyResult> verifyShelf(String itemId, String barcode) {
    return _service.verifyShelf(itemId, barcode);
  }

  Future<bool> complete() async {
    if (state.workOrder == null) return false;
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);
    try {
      await _service.completeWorkOrder(state.workOrder!.id);
      await _refresh();
      state = state.copyWith(isLoading: false, successMessage: 'Work order completed');
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> _action(Future<void> Function() op, String successMessage) async {
    if (state.workOrder == null) return false;
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await op();
      await _refresh();
      state = state.copyWith(successMessage: successMessage);
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<void> _refresh() async {
    if (state.workOrder == null) return;
    try {
      final wo = await _service.getWorkOrder(state.workOrder!.id);
      state = state.copyWith(workOrder: wo);
    } catch (_) {
      // keep prior state on refresh failure
    }
  }

  void clearMessages() => state = state.copyWith(clearError: true, clearSuccess: true);
}

final activeLabelingProvider =
    StateNotifierProvider<ActiveLabelingNotifier, ActiveLabelingState>((ref) {
  return ActiveLabelingNotifier(ref.watch(labelingServiceProvider));
});
