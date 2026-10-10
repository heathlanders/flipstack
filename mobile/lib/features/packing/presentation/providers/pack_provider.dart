import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/pack_models.dart';
import '../../../../data/services/pack_service.dart';

// Service provider
final packServiceProvider = Provider<PackService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PackService(apiClient);
});

// Pack list state
class PackListState {
  final List<PackOrder> orders;
  final PackStats stats;
  final bool isLoading;
  final String? error;

  const PackListState({
    this.orders = const [],
    this.stats = const PackStats(),
    this.isLoading = false,
    this.error,
  });

  PackListState copyWith({
    List<PackOrder>? orders,
    PackStats? stats,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return PackListState(
      orders: orders ?? this.orders,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class PackListNotifier extends StateNotifier<PackListState> {
  final PackService _packService;

  PackListNotifier(this._packService) : super(const PackListState()) {
    loadPackQueue();
  }

  Future<void> loadPackQueue() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _packService.getPackQueue();
      state = state.copyWith(
        orders: result.orders,
        stats: result.stats,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadPackQueue();
}

final packListProvider = StateNotifierProvider<PackListNotifier, PackListState>((ref) {
  final packService = ref.watch(packServiceProvider);
  return PackListNotifier(packService);
});

// Active packing state (single order being packed)
class ActivePackState {
  final PackOrder? order;
  final List<ShippingRate> rates;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const ActivePackState({
    this.order,
    this.rates = const [],
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  ActivePackState copyWith({
    PackOrder? order,
    List<ShippingRate>? rates,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearRates = false,
  }) {
    return ActivePackState(
      order: order ?? this.order,
      rates: clearRates ? const [] : (rates ?? this.rates),
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class ActivePackNotifier extends StateNotifier<ActivePackState> {
  final PackService _packService;

  ActivePackNotifier(this._packService) : super(const ActivePackState());

  Future<void> loadOrder(String orderId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final order = await _packService.getOrder(orderId);
      state = state.copyWith(order: order, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> startPacking() async {
    if (state.order == null) return;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _packService.startPacking(state.order!.id);
      state = state.copyWith(
        order: updated,
        isLoading: false,
        successMessage: 'Packing started',
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> getRates(PackageDimensions dimensions) async {
    if (state.order == null) return;
    state = state.copyWith(isLoading: true, clearError: true, clearRates: true);
    try {
      final rates = await _packService.getRates(state.order!.id, dimensions);
      state = state.copyWith(rates: rates, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> purchaseLabel(String rateId, PackageDimensions dimensions) async {
    if (state.order == null) return;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final shipment = await _packService.purchaseLabel(
        state.order!.id,
        rateId,
        dimensions,
      );
      // Reload the full order to get updated state
      final order = await _packService.getOrder(state.order!.id);
      state = state.copyWith(
        order: order,
        isLoading: false,
        successMessage: 'Label purchased - Tracking: ${shipment.trackingNumber ?? "pending"}',
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> completePacking(PackageDimensions dimensions) async {
    if (state.order == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _packService.completePacking(state.order!.id, dimensions);
      state = state.copyWith(
        isLoading: false,
        successMessage: 'Packing completed',
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

final activePackProvider = StateNotifierProvider<ActivePackNotifier, ActivePackState>((ref) {
  final packService = ref.watch(packServiceProvider);
  return ActivePackNotifier(packService);
});
