import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/ebay_listing_models.dart';
import '../../../../data/services/ebay_listing_service.dart';

final ebayListingServiceProvider = Provider<EbayListingService>((ref) {
  return EbayListingService(ref.watch(apiClientProvider));
});

class EbayListingsState {
  final List<EbayListing> listings;
  final bool isLoading;
  final String? error;
  final String? successMessage;
  final String? statusFilter;

  const EbayListingsState({
    this.listings = const [],
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.statusFilter,
  });

  EbayListingsState copyWith({
    List<EbayListing>? listings,
    bool? isLoading,
    String? error,
    String? successMessage,
    String? statusFilter,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearStatus = false,
  }) {
    return EbayListingsState(
      listings: listings ?? this.listings,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
      statusFilter: clearStatus ? null : (statusFilter ?? this.statusFilter),
    );
  }
}

class EbayListingsNotifier extends StateNotifier<EbayListingsState> {
  final EbayListingService _service;

  EbayListingsNotifier(this._service) : super(const EbayListingsState()) {
    load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getListings(status: state.statusFilter);
      state = state.copyWith(listings: result.listings, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => load();

  void setStatusFilter(String? status) {
    state = status == null ? state.copyWith(clearStatus: true) : state.copyWith(statusFilter: status);
    load();
  }

  Future<void> updateListing(String listingId, String updateType) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      final message = await _service.updateListing(listingId, updateType);
      state = state.copyWith(successMessage: message);
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  Future<void> retryListing(String listingId) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      final message = await _service.retryListing(listingId);
      state = state.copyWith(successMessage: message);
      await load();
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  void clearMessages() => state = state.copyWith(clearError: true, clearSuccess: true);
}

final ebayListingsProvider =
    StateNotifierProvider<EbayListingsNotifier, EbayListingsState>((ref) {
  return EbayListingsNotifier(ref.watch(ebayListingServiceProvider));
});
