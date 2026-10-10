import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/bin_models.dart';
import '../../../../data/services/bin_service.dart';

// Service provider
final binServiceProvider = Provider<BinService>((ref) {
  return BinService(ref.watch(apiClientProvider));
});

class BinLookupState {
  final WarehouseBin? bin;
  final bool isLoading;
  final bool isAssigning;
  final String? error;
  final String? successMessage;

  const BinLookupState({
    this.bin,
    this.isLoading = false,
    this.isAssigning = false,
    this.error,
    this.successMessage,
  });

  BinLookupState copyWith({
    WarehouseBin? bin,
    bool? isLoading,
    bool? isAssigning,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearBin = false,
  }) {
    return BinLookupState(
      bin: clearBin ? null : (bin ?? this.bin),
      isLoading: isLoading ?? this.isLoading,
      isAssigning: isAssigning ?? this.isAssigning,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class BinLookupNotifier extends StateNotifier<BinLookupState> {
  final BinService _service;

  BinLookupNotifier(this._service) : super(const BinLookupState());

  Future<void> scanBin(String barcode) async {
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);
    try {
      final bin = await _service.scan(barcode);
      state = state.copyWith(bin: bin, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString(), clearBin: true);
    }
  }

  Future<bool> assignProduct(String productBarcode) async {
    if (state.bin == null) return false;
    state = state.copyWith(isAssigning: true, clearError: true, clearSuccess: true);
    try {
      final bin = await _service.assignProduct(state.bin!.id, barcode: productBarcode);
      state = state.copyWith(bin: bin, isAssigning: false, successMessage: 'Product placed in bin');
      return true;
    } catch (e) {
      state = state.copyWith(isAssigning: false, error: e.toString());
      return false;
    }
  }

  void clear() => state = const BinLookupState();

  void clearMessages() => state = state.copyWith(clearError: true, clearSuccess: true);
}

final binLookupProvider = StateNotifierProvider<BinLookupNotifier, BinLookupState>((ref) {
  return BinLookupNotifier(ref.watch(binServiceProvider));
});
