import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/analytics_models.dart';
import '../../../../data/services/analytics_service.dart';

// Service provider
final analyticsServiceProvider = Provider<AnalyticsService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AnalyticsService(apiClient);
});

// Analytics page state
class AnalyticsState {
  final InventoryAnalytics? analytics;
  final InventoryValuation? valuation;
  final StockHealth? stockHealth;
  final ABCAnalysis? abcAnalysis;
  final TurnoverAnalysis? turnoverAnalysis;
  final List<TopPerformer> topPerformers;
  final Map<String, dynamic>? kpis;
  final bool isLoading;
  final bool isLoadingAbc;
  final bool isLoadingTurnover;
  final String? error;
  final String? abcError;
  final String? turnoverError;

  const AnalyticsState({
    this.analytics,
    this.valuation,
    this.stockHealth,
    this.abcAnalysis,
    this.turnoverAnalysis,
    this.topPerformers = const [],
    this.kpis,
    this.isLoading = false,
    this.isLoadingAbc = false,
    this.isLoadingTurnover = false,
    this.error,
    this.abcError,
    this.turnoverError,
  });

  AnalyticsState copyWith({
    InventoryAnalytics? analytics,
    InventoryValuation? valuation,
    StockHealth? stockHealth,
    ABCAnalysis? abcAnalysis,
    TurnoverAnalysis? turnoverAnalysis,
    List<TopPerformer>? topPerformers,
    Map<String, dynamic>? kpis,
    bool? isLoading,
    bool? isLoadingAbc,
    bool? isLoadingTurnover,
    String? error,
    String? abcError,
    String? turnoverError,
    bool clearError = false,
    bool clearAbcError = false,
    bool clearTurnoverError = false,
  }) {
    return AnalyticsState(
      analytics: analytics ?? this.analytics,
      valuation: valuation ?? this.valuation,
      stockHealth: stockHealth ?? this.stockHealth,
      abcAnalysis: abcAnalysis ?? this.abcAnalysis,
      turnoverAnalysis: turnoverAnalysis ?? this.turnoverAnalysis,
      topPerformers: topPerformers ?? this.topPerformers,
      kpis: kpis ?? this.kpis,
      isLoading: isLoading ?? this.isLoading,
      isLoadingAbc: isLoadingAbc ?? this.isLoadingAbc,
      isLoadingTurnover: isLoadingTurnover ?? this.isLoadingTurnover,
      error: clearError ? null : (error ?? this.error),
      abcError: clearAbcError ? null : (abcError ?? this.abcError),
      turnoverError: clearTurnoverError ? null : (turnoverError ?? this.turnoverError),
    );
  }
}

class AnalyticsNotifier extends StateNotifier<AnalyticsState> {
  final AnalyticsService _analyticsService;

  AnalyticsNotifier(this._analyticsService) : super(const AnalyticsState()) {
    loadAll();
  }

  Future<void> loadAll() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final results = await Future.wait([
        _analyticsService.getAnalytics(),
        _analyticsService.getValuation(),
      ]);
      state = state.copyWith(
        analytics: results[0] as InventoryAnalytics,
        valuation: results[1] as InventoryValuation,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }

    // Load new analytics data in parallel (non-blocking)
    _loadOverviewData();
  }

  Future<void> _loadOverviewData() async {
    try {
      final results = await Future.wait([
        _analyticsService.getStockHealth(),
        _analyticsService.getTopPerformers(limit: 5),
        _analyticsService.getKPIs(),
      ]);
      state = state.copyWith(
        stockHealth: results[0] as StockHealth,
        topPerformers: results[1] as List<TopPerformer>,
        kpis: results[2] as Map<String, dynamic>,
      );
    } catch (_) {
      // Non-critical; overview tabs still show legacy data
    }
  }

  Future<void> loadABCAnalysis({String metric = 'revenue'}) async {
    state = state.copyWith(isLoadingAbc: true, clearAbcError: true);
    try {
      final abc = await _analyticsService.getABCAnalysis(metric: metric);
      state = state.copyWith(abcAnalysis: abc, isLoadingAbc: false);
    } catch (e) {
      state = state.copyWith(isLoadingAbc: false, abcError: e.toString());
    }
  }

  Future<void> loadTurnoverAnalysis({int period = 365}) async {
    state = state.copyWith(isLoadingTurnover: true, clearTurnoverError: true);
    try {
      final turnover = await _analyticsService.getTurnoverAnalysis(period: period);
      state = state.copyWith(turnoverAnalysis: turnover, isLoadingTurnover: false);
    } catch (e) {
      state = state.copyWith(isLoadingTurnover: false, turnoverError: e.toString());
    }
  }

  Future<void> refresh() => loadAll();
}

final analyticsProvider =
    StateNotifierProvider<AnalyticsNotifier, AnalyticsState>((ref) {
  final service = ref.watch(analyticsServiceProvider);
  return AnalyticsNotifier(service);
});
