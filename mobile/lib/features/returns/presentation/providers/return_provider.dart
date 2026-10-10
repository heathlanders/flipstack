import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/return_models.dart';
import '../../../../data/services/return_service.dart';

// Service provider
final returnServiceProvider = Provider<ReturnMobileService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ReturnMobileService(apiClient);
});

// Return list state
class ReturnListState {
  final List<ReturnRequest> returns;
  final ReturnStats stats;
  final bool isLoading;
  final String? error;
  final String? statusFilter;

  const ReturnListState({
    this.returns = const [],
    this.stats = const ReturnStats(),
    this.isLoading = false,
    this.error,
    this.statusFilter,
  });

  ReturnListState copyWith({
    List<ReturnRequest>? returns,
    ReturnStats? stats,
    bool? isLoading,
    String? error,
    String? statusFilter,
    bool clearError = false,
    bool clearStatusFilter = false,
  }) {
    return ReturnListState(
      returns: returns ?? this.returns,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      statusFilter: clearStatusFilter ? null : (statusFilter ?? this.statusFilter),
    );
  }
}

class ReturnListNotifier extends StateNotifier<ReturnListState> {
  final ReturnMobileService _returnService;

  ReturnListNotifier(this._returnService) : super(const ReturnListState()) {
    loadReturns();
  }

  Future<void> loadReturns() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _returnService.getReturns(status: state.statusFilter);
      state = state.copyWith(
        returns: result.returns,
        stats: result.stats,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  void setStatusFilter(String? status) {
    if (status == null) {
      state = state.copyWith(clearStatusFilter: true);
    } else {
      state = state.copyWith(statusFilter: status);
    }
    loadReturns();
  }

  Future<void> refresh() => loadReturns();
}

final returnListProvider = StateNotifierProvider<ReturnListNotifier, ReturnListState>((ref) {
  final returnService = ref.watch(returnServiceProvider);
  return ReturnListNotifier(returnService);
});

// Return detail state
class ReturnDetailState {
  final ReturnRequest? returnRequest;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const ReturnDetailState({
    this.returnRequest,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  ReturnDetailState copyWith({
    ReturnRequest? returnRequest,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return ReturnDetailState(
      returnRequest: returnRequest ?? this.returnRequest,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class ReturnDetailNotifier extends StateNotifier<ReturnDetailState> {
  final ReturnMobileService _returnService;

  ReturnDetailNotifier(this._returnService) : super(const ReturnDetailState());

  Future<void> loadReturn(String returnId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final returnRequest = await _returnService.getReturn(returnId);
      state = state.copyWith(returnRequest: returnRequest, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> approve() async {
    if (state.returnRequest == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _returnService.approve(state.returnRequest!.id);
      state = state.copyWith(
        returnRequest: updated,
        isLoading: false,
        successMessage: 'Return approved',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> receive() async {
    if (state.returnRequest == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _returnService.receive(state.returnRequest!.id);
      state = state.copyWith(
        returnRequest: updated,
        isLoading: false,
        successMessage: 'Return received',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> inspect(String condition, String notes, bool restockable) async {
    if (state.returnRequest == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _returnService.inspect(
        state.returnRequest!.id,
        condition: condition,
        notes: notes,
        restockable: restockable,
      );
      state = state.copyWith(
        returnRequest: updated,
        isLoading: false,
        successMessage: 'Inspection completed',
      );
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> restock(String locationId) async {
    if (state.returnRequest == null) return false;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final updated = await _returnService.restock(state.returnRequest!.id, locationId);
      state = state.copyWith(
        returnRequest: updated,
        isLoading: false,
        successMessage: 'Item restocked',
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

final returnDetailProvider = StateNotifierProvider<ReturnDetailNotifier, ReturnDetailState>((ref) {
  final returnService = ref.watch(returnServiceProvider);
  return ReturnDetailNotifier(returnService);
});
