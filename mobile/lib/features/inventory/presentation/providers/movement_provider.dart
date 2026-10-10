import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/inventory_ops_models.dart';
import '../../../../data/services/inventory_ops_service.dart';

// Service provider
final inventoryOpsServiceProvider = Provider<InventoryOpsService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return InventoryOpsService(apiClient);
});

/// State for the movement history page.
class MovementHistoryState {
  final List<InventoryMovement> movements;
  final bool isLoading;
  final String? error;
  final String? typeFilter;
  final int currentPage;
  final bool hasMore;

  const MovementHistoryState({
    this.movements = const [],
    this.isLoading = false,
    this.error,
    this.typeFilter,
    this.currentPage = 1,
    this.hasMore = true,
  });

  MovementHistoryState copyWith({
    List<InventoryMovement>? movements,
    bool? isLoading,
    String? error,
    String? typeFilter,
    int? currentPage,
    bool? hasMore,
    bool clearError = false,
    bool clearTypeFilter = false,
  }) {
    return MovementHistoryState(
      movements: movements ?? this.movements,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      typeFilter: clearTypeFilter ? null : (typeFilter ?? this.typeFilter),
      currentPage: currentPage ?? this.currentPage,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

class MovementHistoryNotifier extends StateNotifier<MovementHistoryState> {
  final InventoryOpsService _service;

  MovementHistoryNotifier(this._service) : super(const MovementHistoryState()) {
    loadMovements();
  }

  Future<void> loadMovements() async {
    state = state.copyWith(isLoading: true, clearError: true, currentPage: 1);
    try {
      final movements = await _service.getMovements(
        type: state.typeFilter,
      );
      state = state.copyWith(
        movements: movements,
        isLoading: false,
        hasMore: movements.length >= 20,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> setTypeFilter(String? type) async {
    if (type == state.typeFilter) return;
    if (type == null || type == 'all') {
      state = state.copyWith(clearTypeFilter: true);
    } else {
      state = state.copyWith(typeFilter: type);
    }
    await loadMovements();
  }

  Future<void> loadMore() async {
    if (state.isLoading || !state.hasMore) return;
    state = state.copyWith(isLoading: true);
    try {
      final nextPage = state.currentPage + 1;
      final movements = await _service.getMovements(
        type: state.typeFilter,
      );
      if (movements.isEmpty) {
        state = state.copyWith(isLoading: false, hasMore: false);
      } else {
        state = state.copyWith(
          movements: [...state.movements, ...movements],
          isLoading: false,
          currentPage: nextPage,
          hasMore: movements.length >= 20,
        );
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadMovements();
}

final movementHistoryProvider =
    StateNotifierProvider<MovementHistoryNotifier, MovementHistoryState>((ref) {
  final service = ref.watch(inventoryOpsServiceProvider);
  return MovementHistoryNotifier(service);
});
