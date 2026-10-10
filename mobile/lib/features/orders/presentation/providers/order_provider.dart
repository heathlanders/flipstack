import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/fulfillment_order_models.dart';
import '../../../../data/services/fulfillment_order_service.dart';

// Service provider
final orderServiceProvider = Provider<FulfillmentOrderService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return FulfillmentOrderService(apiClient);
});

// Order list state
class OrderListState {
  final List<FulfillmentOrder> orders;
  final OrderStats stats;
  final bool isLoading;
  final String? error;
  final String? successMessage;
  final String? statusFilter;
  final int currentPage;
  final int lastPage;

  const OrderListState({
    this.orders = const [],
    this.stats = const OrderStats(),
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.statusFilter,
    this.currentPage = 1,
    this.lastPage = 1,
  });

  OrderListState copyWith({
    List<FulfillmentOrder>? orders,
    OrderStats? stats,
    bool? isLoading,
    String? error,
    String? successMessage,
    String? statusFilter,
    int? currentPage,
    int? lastPage,
    bool clearError = false,
    bool clearSuccess = false,
    bool clearStatusFilter = false,
  }) {
    return OrderListState(
      orders: orders ?? this.orders,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
      statusFilter: clearStatusFilter ? null : (statusFilter ?? this.statusFilter),
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
    );
  }

  bool get hasNextPage => currentPage < lastPage;
}

class OrderListNotifier extends StateNotifier<OrderListState> {
  final FulfillmentOrderService _service;

  OrderListNotifier(this._service) : super(const OrderListState()) {
    loadOrders();
  }

  Future<void> loadOrders({int page = 1}) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getOrders(
        status: state.statusFilter,
        page: page,
      );
      state = state.copyWith(
        orders: page == 1 ? result.orders : [...state.orders, ...result.orders],
        stats: result.stats,
        currentPage: page,
        lastPage: result.lastPage,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => loadOrders(page: 1);

  void setStatusFilter(String? status) {
    if (status == null) {
      state = state.copyWith(clearStatusFilter: true, currentPage: 1);
    } else {
      state = state.copyWith(statusFilter: status, currentPage: 1);
    }
    loadOrders(page: 1);
  }

  Future<void> nextPage() async {
    if (!state.hasNextPage || state.isLoading) return;
    await loadOrders(page: state.currentPage + 1);
  }

  Future<bool> assignOrder(String orderId, String locationId) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _service.assignOrder(orderId, locationId);
      state = state.copyWith(successMessage: 'Order assigned');
      await refresh();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> holdOrder(String orderId, String reason) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _service.holdOrder(orderId, reason);
      state = state.copyWith(successMessage: 'Order placed on hold');
      await refresh();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> releaseOrder(String orderId) async {
    state = state.copyWith(clearError: true, clearSuccess: true);
    try {
      await _service.releaseOrder(orderId);
      state = state.copyWith(successMessage: 'Order released from hold');
      await refresh();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }
}

final orderListProvider = StateNotifierProvider<OrderListNotifier, OrderListState>((ref) {
  final service = ref.watch(orderServiceProvider);
  return OrderListNotifier(service);
});

// Order detail state
class OrderDetailState {
  final FulfillmentOrder? order;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const OrderDetailState({
    this.order,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  OrderDetailState copyWith({
    FulfillmentOrder? order,
    bool? isLoading,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return OrderDetailState(
      order: order ?? this.order,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class OrderDetailNotifier extends StateNotifier<OrderDetailState> {
  final FulfillmentOrderService _service;

  OrderDetailNotifier(this._service) : super(const OrderDetailState());

  Future<void> loadOrder(String orderId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final order = await _service.getOrder(orderId);
      state = state.copyWith(order: order, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }
}

final orderDetailProvider = StateNotifierProvider<OrderDetailNotifier, OrderDetailState>((ref) {
  final service = ref.watch(orderServiceProvider);
  return OrderDetailNotifier(service);
});
