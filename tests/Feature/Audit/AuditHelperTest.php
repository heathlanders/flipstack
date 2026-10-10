<?php

namespace Tests\Feature\Audit;

use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

class AuditHelperTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;

    protected Organization $org;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        AuditContext::reset();

        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_audit_event_writes_row_with_actor_org_and_metadata(): void
    {
        $row = audit_event(
            action: 'test.audit_event',
            description: 'helper test',
            metadata: ['source' => 'web', 'extra' => 'data'],
            severity: 'info',
            category: 'general',
            actor: $this->user,
        );

        $this->assertNotNull($row);
        $this->assertSame($this->user->id, $row->user_id);
        $this->assertSame($this->org->id, $row->organization_id);
        $this->assertSame('test.audit_event', $row->action);
        $this->assertSame('info', $row->severity);
        $this->assertSame('general', $row->category);
        $this->assertSame('web', $row->metadata['source']);
    }

    public function test_audit_event_returns_null_when_no_actor_and_no_organization(): void
    {
        $row = audit_event(action: 'test.no_context', description: 'orphan');
        $this->assertNull($row, 'Helper must refuse to persist rows without an organization.');
    }

    public function test_audit_security_writes_warning_severity_security_category(): void
    {
        $this->actingAs($this->user);

        $row = audit_security('test.security', 'security probe', ['ip' => '1.2.3.4']);

        $this->assertSame('warning', $row->severity);
        $this->assertSame('security', $row->category);
        $this->assertSame('1.2.3.4', $row->metadata['ip']);
    }

    public function test_with_audit_actor_propagates_actor_and_metadata_inside_callback(): void
    {
        $captured = null;

        withAuditActor($this->user, function () use (&$captured) {
            $captured = audit_event('test.with_actor', 'inside ctx');
        }, ['source' => 'job', 'job_class' => 'TestJob']);

        $this->assertNotNull($captured);
        $this->assertSame($this->user->id, $captured->user_id);
        $this->assertSame('job', $captured->metadata['source']);
        $this->assertSame('TestJob', $captured->metadata['job_class']);
    }

    public function test_with_audit_actor_pops_context_after_callback(): void
    {
        withAuditActor($this->user, fn () => null, ['source' => 'webhook']);

        $this->assertNull(AuditContext::actor(), 'Context must pop after the callback.');
        $this->assertSame([], AuditContext::metadata());
    }

    public function test_audit_event_records_subject_polymorphic_and_entity_label(): void
    {
        $row = audit_event(
            action: 'test.subject',
            description: 'with subject',
            subject: $this->user,
            actor: $this->user,
        );

        $this->assertSame(User::class, $row->model_type);
        $this->assertSame((string) $this->user->id, $row->model_id);
        $this->assertNotEmpty($row->metadata['entity_label']);
    }

    public function test_asserts_audits_trait_round_trips(): void
    {
        audit_event(
            action: 'test.round_trip',
            description: 'round trip',
            metadata: ['key' => 'value'],
            actor: $this->user,
        );

        $this->assertAudited('test.round_trip', $this->user, ['key' => 'value']);
        $this->assertNotAudited('test.not_emitted');
    }
}
