import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/message_provider.dart';

class ThreadPage extends ConsumerStatefulWidget {
  final String messageId;

  const ThreadPage({super.key, required this.messageId});

  @override
  ConsumerState<ThreadPage> createState() => _ThreadPageState();
}

class _ThreadPageState extends ConsumerState<ThreadPage> {
  final _replyController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(threadProvider.notifier).load(widget.messageId);
    });
  }

  @override
  void dispose() {
    _replyController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(threadProvider);
    final notifier = ref.read(threadProvider.notifier);

    ref.listen<ThreadState>(threadProvider, (prev, next) {
      if (next.successMessage != null && next.successMessage != prev?.successMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.successMessage!), backgroundColor: Colors.green),
        );
        _replyController.clear();
        notifier.clearMessages();
      }
      if (next.error != null && next.error != prev?.error) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(next.error!), backgroundColor: Colors.red),
        );
        notifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: Text(state.message?.subject ?? 'Conversation', overflow: TextOverflow.ellipsis),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/messages')),
        actions: [
          if (state.message != null)
            IconButton(
              icon: const Icon(Icons.archive_outlined),
              tooltip: 'Archive',
              onPressed: () async {
                await notifier.archive();
                if (context.mounted) context.go('/messages');
              },
            ),
        ],
      ),
      body: state.isLoading && state.message == null
          ? const Center(child: CircularProgressIndicator())
          : state.message == null
              ? _buildError(state, notifier)
              : Column(
                  children: [
                    Expanded(child: _buildTimeline(state)),
                    _buildComposer(state, notifier),
                  ],
                ),
    );
  }

  Widget _buildError(ThreadState state, ThreadNotifier notifier) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(state.error ?? 'Message not found'),
          const SizedBox(height: 16),
          ElevatedButton(onPressed: () => notifier.load(widget.messageId), child: const Text('Retry')),
        ],
      ),
    );
  }

  Widget _buildTimeline(ThreadState state) {
    final bubbles = <Widget>[];
    final messages = state.thread.isNotEmpty ? state.thread : [state.message!];

    for (final msg in messages) {
      bubbles.add(_bubble(
        author: msg.senderName ?? (msg.isInbound ? 'Buyer' : 'You'),
        body: msg.body ?? '',
        time: msg.receivedAt ?? msg.createdAt,
        inbound: msg.isInbound,
      ));
      for (final reply in msg.replies) {
        bubbles.add(_bubble(
          author: reply.senderName ?? 'You',
          body: reply.body,
          time: reply.sentAt,
          inbound: false,
          failed: reply.isFailed,
        ));
      }
    }

    return ListView(
      padding: const EdgeInsets.all(12),
      children: bubbles,
    );
  }

  Widget _bubble({required String author, required String body, DateTime? time, required bool inbound, bool failed = false}) {
    final align = inbound ? CrossAxisAlignment.start : CrossAxisAlignment.end;
    final color = inbound ? Colors.grey.shade100 : Colors.indigo.shade50;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: align,
        children: [
          Text(author, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
          const SizedBox(height: 2),
          Container(
            constraints: const BoxConstraints(maxWidth: 280),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(10),
              border: failed ? Border.all(color: Colors.red.shade200) : null,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(body),
                if (failed)
                  const Padding(
                    padding: EdgeInsets.only(top: 4),
                    child: Text('Not delivered', style: TextStyle(color: Colors.red, fontSize: 11)),
                  ),
              ],
            ),
          ),
          if (time != null)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(_formatTime(time), style: TextStyle(fontSize: 10, color: Colors.grey[500])),
            ),
        ],
      ),
    );
  }

  Widget _buildComposer(ThreadState state, ThreadNotifier notifier) {
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: Colors.grey.shade200)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: _replyController,
                minLines: 1,
                maxLines: 4,
                maxLength: 5000,
                decoration: const InputDecoration(
                  hintText: 'Type a reply...',
                  border: OutlineInputBorder(),
                  counterText: '',
                  isDense: true,
                ),
              ),
            ),
            const SizedBox(width: 8),
            state.isSending
                ? const Padding(
                    padding: EdgeInsets.all(8),
                    child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2)),
                  )
                : IconButton.filled(
                    icon: const Icon(Icons.send),
                    style: IconButton.styleFrom(backgroundColor: Colors.indigo),
                    onPressed: () {
                      final body = _replyController.text.trim();
                      if (body.isEmpty) return;
                      notifier.sendReply(body);
                    },
                  ),
          ],
        ),
      ),
    );
  }

  String _formatTime(DateTime time) {
    final local = time.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${local.year}-${two(local.month)}-${two(local.day)} ${two(local.hour)}:${two(local.minute)}';
  }
}
