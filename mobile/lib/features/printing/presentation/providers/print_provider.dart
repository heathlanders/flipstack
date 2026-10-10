import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/print_models.dart';
import '../../../../data/services/print_service.dart';

final printServiceProvider = Provider<PrintService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PrintService(apiClient);
});

class PrintState {
  final bool isLoading;
  final String? error;
  final String? successMessage;
  final List<DiscoveredPrinter> discoveredPrinters;
  final bool isDiscovering;

  const PrintState({
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.discoveredPrinters = const [],
    this.isDiscovering = false,
  });

  PrintState copyWith({
    bool? isLoading,
    String? error,
    String? successMessage,
    List<DiscoveredPrinter>? discoveredPrinters,
    bool? isDiscovering,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return PrintState(
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
      discoveredPrinters: discoveredPrinters ?? this.discoveredPrinters,
      isDiscovering: isDiscovering ?? this.isDiscovering,
    );
  }
}

class PrintNotifier extends StateNotifier<PrintState> {
  final PrintService _service;

  PrintNotifier(this._service) : super(const PrintState());

  Future<void> printLabel(String productId, {int quantity = 1}) async {
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);

    try {
      final result = await _service.printProductLabel(productId, quantity: quantity);
      state = state.copyWith(
        isLoading: false,
        successMessage: result.message,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> printBatch(List<String> productIds, {int quantity = 1}) async {
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);

    try {
      final result = await _service.printBatch(productIds, quantity: quantity);
      final msg = result.successfulJobs != null
          ? 'Printed ${result.successfulJobs}/${result.totalJobs} labels'
          : result.message;
      state = state.copyWith(
        isLoading: false,
        successMessage: msg,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> discoverPrinters() async {
    state = state.copyWith(isDiscovering: true, clearError: true, clearSuccess: true);

    try {
      final printers = await _service.discoverPrinters();
      state = state.copyWith(
        isDiscovering: false,
        discoveredPrinters: printers,
        successMessage: 'Found ${printers.length} printer(s)',
      );
    } catch (e) {
      state = state.copyWith(
        isDiscovering: false,
        error: e.toString(),
      );
    }
  }

  Future<void> testPrinter(String printerId) async {
    state = state.copyWith(clearError: true, clearSuccess: true);

    try {
      final result = await _service.testPrinter(printerId);
      state = state.copyWith(
        successMessage: result.success ? 'Printer test passed' : 'Printer test failed: ${result.message}',
      );
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  Future<void> addPrinter(Map<String, dynamic> data) async {
    state = state.copyWith(isLoading: true, clearError: true, clearSuccess: true);

    try {
      await _service.addPrinter(data);
      state = state.copyWith(
        isLoading: false,
        successMessage: 'Printer added',
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  void clearMessages() {
    state = state.copyWith(clearError: true, clearSuccess: true);
  }
}

final printProvider = StateNotifierProvider<PrintNotifier, PrintState>((ref) {
  final service = ref.watch(printServiceProvider);
  return PrintNotifier(service);
});
