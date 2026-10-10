import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/bundle_models.dart';
import '../../../../data/services/bundle_service.dart';

final bundleServiceProvider = Provider<BundleService>((ref) {
  return BundleService(ref.watch(apiClientProvider));
});

class BundleListState {
  final List<ProductBundle> bundles;
  final int total;
  final int active;
  final bool isLoading;
  final String? error;

  const BundleListState({
    this.bundles = const [],
    this.total = 0,
    this.active = 0,
    this.isLoading = false,
    this.error,
  });

  BundleListState copyWith({
    List<ProductBundle>? bundles,
    int? total,
    int? active,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return BundleListState(
      bundles: bundles ?? this.bundles,
      total: total ?? this.total,
      active: active ?? this.active,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class BundleListNotifier extends StateNotifier<BundleListState> {
  final BundleService _service;

  BundleListNotifier(this._service) : super(const BundleListState()) {
    load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getBundles();
      state = state.copyWith(
        bundles: result.bundles,
        total: result.total,
        active: result.active,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => load();
}

final bundleListProvider = StateNotifierProvider<BundleListNotifier, BundleListState>((ref) {
  return BundleListNotifier(ref.watch(bundleServiceProvider));
});
