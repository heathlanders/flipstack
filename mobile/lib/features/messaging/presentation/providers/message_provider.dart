import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/providers/shared_providers.dart';
import '../../../../data/models/message_models.dart';
import '../../../../data/services/message_service.dart';

// Service provider
final messageServiceProvider = Provider<MessageService>((ref) {
  return MessageService(ref.watch(apiClientProvider));
});

// ---------------------------------------------------------------------------
// Inbox
// ---------------------------------------------------------------------------

class InboxState {
  final List<MarketplaceMessage> messages;
  final MessageStats stats;
  final bool isLoading;
  final String? error;
  final String? statusFilter;
  final String? marketplaceFilter;

  const InboxState({
    this.messages = const [],
    this.stats = const MessageStats(),
    this.isLoading = false,
    this.error,
    this.statusFilter,
    this.marketplaceFilter,
  });

  InboxState copyWith({
    List<MarketplaceMessage>? messages,
    MessageStats? stats,
    bool? isLoading,
    String? error,
    String? statusFilter,
    String? marketplaceFilter,
    bool clearError = false,
    bool clearStatus = false,
    bool clearMarketplace = false,
  }) {
    return InboxState(
      messages: messages ?? this.messages,
      stats: stats ?? this.stats,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      statusFilter: clearStatus ? null : (statusFilter ?? this.statusFilter),
      marketplaceFilter: clearMarketplace ? null : (marketplaceFilter ?? this.marketplaceFilter),
    );
  }
}

class InboxNotifier extends StateNotifier<InboxState> {
  final MessageService _service;

  InboxNotifier(this._service) : super(const InboxState()) {
    load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getInbox(
        status: state.statusFilter,
        marketplace: state.marketplaceFilter,
      );
      state = state.copyWith(messages: result.messages, stats: result.stats, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> refresh() => load();

  void setStatusFilter(String? status) {
    state = status == null ? state.copyWith(clearStatus: true) : state.copyWith(statusFilter: status);
    load();
  }

  void setMarketplaceFilter(String? marketplace) {
    state = marketplace == null ? state.copyWith(clearMarketplace: true) : state.copyWith(marketplaceFilter: marketplace);
    load();
  }
}

final inboxProvider = StateNotifierProvider<InboxNotifier, InboxState>((ref) {
  return InboxNotifier(ref.watch(messageServiceProvider));
});

// ---------------------------------------------------------------------------
// Thread
// ---------------------------------------------------------------------------

class ThreadState {
  final MarketplaceMessage? message;
  final List<MarketplaceMessage> thread;
  final bool isLoading;
  final bool isSending;
  final String? error;
  final String? successMessage;

  const ThreadState({
    this.message,
    this.thread = const [],
    this.isLoading = false,
    this.isSending = false,
    this.error,
    this.successMessage,
  });

  ThreadState copyWith({
    MarketplaceMessage? message,
    List<MarketplaceMessage>? thread,
    bool? isLoading,
    bool? isSending,
    String? error,
    String? successMessage,
    bool clearError = false,
    bool clearSuccess = false,
  }) {
    return ThreadState(
      message: message ?? this.message,
      thread: thread ?? this.thread,
      isLoading: isLoading ?? this.isLoading,
      isSending: isSending ?? this.isSending,
      error: clearError ? null : (error ?? this.error),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

class ThreadNotifier extends StateNotifier<ThreadState> {
  final MessageService _service;

  ThreadNotifier(this._service) : super(const ThreadState());

  Future<void> load(String messageId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _service.getThread(messageId);
      state = state.copyWith(message: result.message, thread: result.thread, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> sendReply(String body) async {
    if (state.message == null) return false;
    state = state.copyWith(isSending: true, clearError: true, clearSuccess: true);
    try {
      await _service.reply(state.message!.id, body);
      await load(state.message!.id);
      state = state.copyWith(isSending: false, successMessage: 'Reply sent');
      return true;
    } catch (e) {
      state = state.copyWith(isSending: false, error: e.toString());
      return false;
    }
  }

  Future<void> archive() async {
    if (state.message == null) return;
    try {
      await _service.archive(state.message!.id);
      state = state.copyWith(successMessage: 'Message archived');
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  void clearMessages() => state = state.copyWith(clearError: true, clearSuccess: true);
}

final threadProvider = StateNotifierProvider<ThreadNotifier, ThreadState>((ref) {
  return ThreadNotifier(ref.watch(messageServiceProvider));
});
