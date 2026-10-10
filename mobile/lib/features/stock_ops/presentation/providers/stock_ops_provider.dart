import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/inventory_ops_models.dart';
import '../../../../data/models/pick_models.dart';
import '../../../../data/services/inventory_ops_service.dart';

// Service provider
final stockOpsServiceProvider = Provider<InventoryOpsService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return InventoryOpsService(apiClient);
});

// Stock operations state
class StockOpsState {
  final List<PickLocation> locations;
  final StockLevel? selectedProduct;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const StockOpsState({
    this.locations = const [],
    this.selectedProduct,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  StockOpsState copyWith({
    List<PickLocation>? locations,
    StockLevel? selectedProduct,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearProduct = false,
  }) {
    return StockOpsState(
      locations: locations ?? this.locations,
      selectedProduct: clearProduct ? null : (selectedProduct ?? this.selectedProduct),
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class StockOpsNotifier extends StateNotifier<StockOpsState> {
  final InventoryOpsService _service;

  StockOpsNotifier(this._service) : super(const StockOpsState()) {
    loadLocations();
  }

  Future<void> loadLocations() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final locations = await _service.getLocations();
      state = state.copyWith(locations: locations, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> adjustStock({
    required String productId,
    required String locationId,
    required int quantity,
    required String reason,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);
    try {
      final result = await _service.adjustStock(
        productId: productId,
        locationId: locationId,
        quantity: quantity,
        reason: reason,
      );
      state = state.copyWith(
        isLoading: false,
        successMessage: result.message,
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> transferStock({
    required String productId,
    required String fromLocationId,
    required String toLocationId,
    required int quantity,
    String? reason,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);
    try {
      final result = await _service.transferStock(
        productId: productId,
        fromLocationId: fromLocationId,
        toLocationId: toLocationId,
        quantity: quantity,
        reason: reason,
      );
      state = state.copyWith(
        isLoading: false,
        successMessage: result.message,
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

final stockOpsProvider = StateNotifierProvider<StockOpsNotifier, StockOpsState>((ref) {
  final service = ref.watch(stockOpsServiceProvider);
  return StockOpsNotifier(service);
});
