import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/marketplace_models.dart';
import '../../../../data/services/marketplace_service.dart';

// Service provider
final marketplaceServiceProvider = Provider<MarketplaceService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return MarketplaceService(apiClient);
});

// Marketplace state
class MarketplaceState {
  final List<MarketplaceConnection> marketplaces;
  final EbayStatusDetail? ebayStatus;
  final EbayStats? ebayStats;
  final List<EbaySyncLog> ebayLogs;
  final bool isLoading;
  final String? error;

  const MarketplaceState({
    this.marketplaces = const [],
    this.ebayStatus,
    this.ebayStats,
    this.ebayLogs = const [],
    this.isLoading = false,
    this.error,
  });

  MarketplaceState copyWith({
    List<MarketplaceConnection>? marketplaces,
    EbayStatusDetail? ebayStatus,
    EbayStats? ebayStats,
    List<EbaySyncLog>? ebayLogs,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return MarketplaceState(
      marketplaces: marketplaces ?? this.marketplaces,
      ebayStatus: ebayStatus ?? this.ebayStatus,
      ebayStats: ebayStats ?? this.ebayStats,
      ebayLogs: ebayLogs ?? this.ebayLogs,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class MarketplaceNotifier extends StateNotifier<MarketplaceState> {
  final MarketplaceService _marketplaceService;

  MarketplaceNotifier(this._marketplaceService) : super(const MarketplaceState()) {
    loadStatus();
  }

  Future<void> loadStatus() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final marketplaces = await _marketplaceService.getMarketplaces();
      state = state.copyWith(marketplaces: marketplaces, isLoading: false);

      // If eBay is connected, load detail data
      final hasEbay = marketplaces.any((m) => m.platform.toLowerCase() == 'ebay');
      if (hasEbay) {
        _loadEbayDetails();
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> _loadEbayDetails() async {
    try {
      final results = await Future.wait([
        _marketplaceService.getEbayStatus(),
        _marketplaceService.getEbayStats(),
        _marketplaceService.getEbayLogs(limit: 10),
      ]);

      final ebayStatus = results[0] as EbayStatusDetail;
      final ebayStats = results[1] as EbayStats;
      final ebayLogs = results[2] as List<EbaySyncLog>;

      // Enrich the eBay marketplace entry with listing counts
      final enriched = state.marketplaces.map((m) {
        if (m.platform.toLowerCase() == 'ebay') {
          return m.copyWith(
            listingCount: ebayStats.activeListings,
            recentErrorCount: ebayStats.recentSyncErrors,
            storeName: ebayStatus.storeName,
          );
        }
        return m;
      }).toList();

      state = state.copyWith(
        marketplaces: enriched,
        ebayStatus: ebayStatus,
        ebayStats: ebayStats,
        ebayLogs: ebayLogs,
      );
    } catch (_) {
      // Non-critical; marketplace cards still show basic info
    }
  }

  Future<void> refresh() => loadStatus();
}

final marketplaceProvider = StateNotifierProvider<MarketplaceNotifier, MarketplaceState>((ref) {
  final marketplaceService = ref.watch(marketplaceServiceProvider);
  return MarketplaceNotifier(marketplaceService);
});
