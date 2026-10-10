---
name: communication-debug
description: Quick health checks for marketplace messages and email campaigns
user-invocable: true
---

Diagnose communication system health. Run ALL of these checks sequentially and report a summary at the end.

**IMPORTANT:** All PHP commands must run inside Docker: `docker exec ecommerce_app`

## Check 1: Unread Message Count by Marketplace

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$unread = DB::table('marketplace_messages')
    ->where('status', 'unread')
    ->where('direction', 'inbound')
    ->selectRaw(\"marketplace, count(*) as count\")
    ->groupBy('marketplace')
    ->orderBy('count', 'desc')
    ->get();
  \$total = \$unread->sum('count');
  echo 'Total unread messages: ' . \$total . PHP_EOL;
  if (\$unread->isNotEmpty()) {
    foreach (\$unread as \$u) {
      echo \"  \$u->marketplace: \$u->count\" . PHP_EOL;
    }
  }

  echo PHP_EOL . '--- Oldest Unread Messages ---' . PHP_EOL;
  \$oldest = DB::table('marketplace_messages')
    ->where('status', 'unread')
    ->where('direction', 'inbound')
    ->select('id', 'marketplace', 'sender_name', 'subject', 'received_at')
    ->orderBy('received_at')
    ->limit(5)
    ->get();
  if (\$oldest->isNotEmpty()) echo \$oldest->toJson(JSON_PRETTY_PRINT);
"
```

## Check 2: Failed Reply Count

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$failed = DB::table('marketplace_message_replies')
    ->where('status', 'failed')
    ->join('marketplace_messages', 'marketplace_message_replies.message_id', '=', 'marketplace_messages.id')
    ->selectRaw(\"marketplace_messages.marketplace, count(*) as count\")
    ->groupBy('marketplace_messages.marketplace')
    ->get();
  \$totalFailed = \$failed->sum('count');
  echo 'Total failed replies: ' . \$totalFailed . PHP_EOL;
  if (\$failed->isNotEmpty()) {
    foreach (\$failed as \$f) {
      echo \"  \$f->marketplace: \$f->count\" . PHP_EOL;
    }
  }

  echo PHP_EOL . '--- Recent Failed Replies ---' . PHP_EOL;
  \$recentFailed = DB::table('marketplace_message_replies')
    ->where('marketplace_message_replies.status', 'failed')
    ->join('marketplace_messages', 'marketplace_message_replies.message_id', '=', 'marketplace_messages.id')
    ->select('marketplace_message_replies.id', 'marketplace_messages.marketplace', 'marketplace_messages.subject', 'marketplace_message_replies.created_at')
    ->orderByDesc('marketplace_message_replies.created_at')
    ->limit(5)
    ->get();
  if (\$recentFailed->isNotEmpty()) echo \$recentFailed->toJson(JSON_PRETTY_PRINT);
"
```

## Check 3: Last Message Sync Timestamps

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Last Inbound Message per Marketplace ---' . PHP_EOL;
  \$lastSync = DB::table('marketplace_messages')
    ->where('direction', 'inbound')
    ->selectRaw(\"marketplace, max(received_at) as last_received, max(created_at) as last_synced, count(*) as total_messages\")
    ->groupBy('marketplace')
    ->get();
  foreach (\$lastSync as \$s) {
    \$hoursAgo = \$s->last_synced ? round(now()->diffInHours(\$s->last_synced), 1) : 'N/A';
    echo \"  \$s->marketplace: last received \$s->last_received, last synced \$s->last_synced (\$hoursAgo hours ago), total: \$s->total_messages\" . PHP_EOL;
  }
  if (\$lastSync->isEmpty()) echo '  No marketplace messages found.' . PHP_EOL;

  echo PHP_EOL . '--- Failed Message Sync Jobs (24h) ---' . PHP_EOL;
  \$failedJobs = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where(function(\$q) {
      \$q->where('payload', 'like', '%SyncMarketplaceMessages%')
        ->orWhere('payload', 'like', '%MessageSync%');
    })
    ->orderByDesc('failed_at')
    ->get();
  echo 'Failed sync jobs: ' . \$failedJobs->count() . PHP_EOL;
  foreach (\$failedJobs->take(3) as \$j) {
    \$p = json_decode(\$j->payload);
    echo '  ' . \$p->displayName . ' at ' . \$j->failed_at . PHP_EOL;
    echo '  ' . substr(\$j->exception, 0, 150) . PHP_EOL . PHP_EOL;
  }
"
```

## Check 4: Pending Email Campaign Sends

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Active Email Campaigns ---' . PHP_EOL;
  \$campaigns = DB::table('email_campaigns')
    ->where('is_active', true)
    ->select('id', 'name', 'type', 'trigger_event', 'sent_count', 'open_count', 'click_count')
    ->get();
  echo 'Active campaigns: ' . \$campaigns->count() . PHP_EOL;
  foreach (\$campaigns as \$c) {
    \$openRate = \$c->sent_count > 0 ? round((\$c->open_count / \$c->sent_count) * 100, 1) : 0;
    echo \"  \$c->name [\$c->type] - sent: \$c->sent_count, open rate: \$openRate%\" . PHP_EOL;
  }

  echo PHP_EOL . '--- Pending Campaign Sends ---' . PHP_EOL;
  \$pending = DB::table('email_campaign_sends')
    ->where('status', 'pending')
    ->join('email_campaigns', 'email_campaign_sends.campaign_id', '=', 'email_campaigns.id')
    ->selectRaw(\"email_campaigns.name, email_campaigns.type, count(*) as pending_count\")
    ->groupBy('email_campaigns.name', 'email_campaigns.type')
    ->get();
  \$totalPending = \$pending->sum('pending_count');
  echo 'Total pending sends: ' . \$totalPending . PHP_EOL;
  foreach (\$pending as \$p) {
    echo \"  \$p->name [\$p->type]: \$p->pending_count pending\" . PHP_EOL;
  }

  echo PHP_EOL . '--- Bounced/Failed Sends (Last 7 Days) ---' . PHP_EOL;
  \$bounced = DB::table('email_campaign_sends')
    ->where('status', 'bounced')
    ->where('created_at', '>', now()->subDays(7))
    ->count();
  echo 'Bounced sends (7d): ' . \$bounced . PHP_EOL;
"
```

## Summary

After running all checks, provide a summary:

1. **Health Status**: healthy / degraded / critical
   - Critical: failed replies > 10, or message sync > 24 hours stale, or pending sends > 50
   - Degraded: unread messages > 20, any failed replies, bounced emails > 5, or sync > 6 hours stale
   - Healthy: all checks pass

2. **Issues Found**: list each issue with severity

3. **Recommended Actions**:
   - Unread messages: suggest reviewing buyer messages, especially oldest ones
   - Failed replies: suggest checking marketplace API credentials and retrying
   - Stale sync: suggest checking message sync job schedule and queue health
   - Pending sends: suggest checking email queue worker and campaign trigger configuration
   - Bounced emails: suggest reviewing customer email validity and sender reputation
