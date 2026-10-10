import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/receiving_models.dart';
import '../../../../data/services/receiving_service.dart';

// Service provider
final receivingServiceProvider = Provider<ReceivingService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ReceivingService(apiClient);
});

// ---------------------------------------------------------------------------
// Open PO list
// ---------------------------------------------------------------------------

class ReceivingListState {
  final List<ReceivingPo> purchaseOrders;
  final ReceivingStats stats;
  final bool isLoading;
  final String? error;

  const ReceivingListState({
    this.purchaseOrders = const [],
    this.stats = const ReceivingStats(),
    this.isLoading = false,
    this.error,
  });

  ReceivingListState copyWith({
    List<ReceivingPo>? purchaseOrders,
    ReceivingStats? stats,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return ReceivingListState(
      purchaseOrders: purchaseOrders ?? this.purchaseOrders,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class ReceivingListNotifier extends StateNotifier<ReceivingListState> {
  final ReceivingService _service;

  ReceivingListNotifier(this._service) : super(const ReceivingListState()) {
    loadPurchaseOrders();
  }

  Future<void> loadPurchaseOrders() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getOpenPurchaseOrders();
      state = state.copyWith(
        purchaseOrders: result.purchaseOrders,
        stats: result.stats,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadPurchaseOrders();
}

final receivingListProvider =
    StateNotifierProvider<ReceivingListNotifier, ReceivingListState>((ref) {
  return ReceivingListNotifier(ref.watch(receivingServiceProvider));
});

// ---------------------------------------------------------------------------
// Active PO receiving
// ---------------------------------------------------------------------------

class ActiveReceivingState {
  final ReceivingPo? purchaseOrder;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const ActiveReceivingState({
    this.purchaseOrder,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  ActiveReceivingState copyWith({
    ReceivingPo? purchaseOrder,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return ActiveReceivingState(
      purchaseOrder: purchaseOrder ?? this.purchaseOrder,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class ActiveReceivingNotifier extends StateNotifier<ActiveReceivingState> {
  final ReceivingService _service;

  ActiveReceivingNotifier(this._service) : super(const ActiveReceivingState());

  Future<void> loadPurchaseOrder(String poId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final po = await _service.getPurchaseOrder(poId);
      state = state.copyWith(purchaseOrder: po, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<ReceivingScanResult> scanVerify(String itemId, String barcode) {
    final poId = state.purchaseOrder!.id;
    return _service.scanVerify(poId, itemId, barcode);
  }

  Future<bool> receiveItem(
    String itemId, {
    required int quantity,
    String? barcode,
    String? locationId,
    String condition = 'good',
  }) async {
    if (state.purchaseOrder == null) return false;
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      final po = await _service.receiveItem(
        state.purchaseOrder!.id,
        itemId,
        quantity: quantity,
        barcode: barcode,
        locationId: locationId ?? state.purchaseOrder!.location?.id,
        condition: condition,
      );
      state = state.copyWith(purchaseOrder: po, successMessage: 'Received $quantity unit(s)');
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> completePurchaseOrder({String? notes}) async {
    if (state.purchaseOrder == null) return false;
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);
    try {
      final po = await _service.completePurchaseOrder(
        state.purchaseOrder!.id,
        locationId: state.purchaseOrder!.location?.id,
        notes: notes,
      );
      state = state.copyWith(
        purchaseOrder: po,
        isLoading: false,
        successMessage: 'All remaining items received',
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

final activeReceivingProvider =
    StateNotifierProvider<ActiveReceivingNotifier, ActiveReceivingState>((ref) {
  return ActiveReceivingNotifier(ref.watch(receivingServiceProvider));
});
