<?php

namespace Tests\Unit\Traits;

use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use App\Support\AuditContext;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogsAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        AuditContext::reset();

        Schema::create('audit_test_widgets', function ($table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->integer('count')->default(0);
            $table->string('secret_token')->nullable();
            $table->timestamps();
        });

        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_test_widgets');
        parent::tearDown();
    }

    public function test_trait_emits_audit_on_create(): void
    {
        AuditTestWidget::create([
            'organization_id' => $this->org->id,
            'name' => 'Widget A',
            'count' => 3,
        ]);

        $row = UserActivityLog::where('action', 'audit_test_widget.created')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame($this->user->id, $row->user_id);
        $this->assertSame($this->org->id, $row->organization_id);
        $this->assertSame('product', $row->category);
        $this->assertSame('info', $row->severity);
        $this->assertSame('Widget A', $row->metadata['attributes']['name']);
    }

    public function test_trait_diffs_changes_on_update(): void
    {
        $widget = AuditTestWidget::create([
            'organization_id' => $this->org->id,
            'name' => 'Widget B',
            'count' => 1,
        ]);

        $widget->update(['count' => 5, 'name' => 'Widget B (renamed)']);

        $updated = UserActivityLog::where('action', 'audit_test_widget.updated')->latest('id')->first();
        $this->assertNotNull($updated);
        $this->assertSame(1, $updated->metadata['changes']['count']['old']);
        $this->assertSame(5, $updated->metadata['changes']['count']['new']);
        $this->assertSame('Widget B', $updated->metadata['changes']['name']['old']);
    }

    public function test_trait_excludes_audit_exclude_fields_from_diff(): void
    {
        $widget = AuditTestWidget::create([
            'organization_id' => $this->org->id,
            'name' => 'Widget C',
            'secret_token' => 'original',
        ]);

        $widget->update(['secret_token' => 'rotated']);

        $updated = UserActivityLog::where('action', 'audit_test_widget.updated')->latest('id')->first();
        $this->assertNull($updated, 'No audit row should fire when only excluded fields changed.');
    }

    public function test_trait_bumps_severity_to_warning_on_delete(): void
    {
        $widget = AuditTestWidget::create([
            'organization_id' => $this->org->id,
            'name' => 'Widget D',
        ]);
        $widget->delete();

        $deleted = UserActivityLog::where('action', 'audit_test_widget.deleted')->latest('id')->first();
        $this->assertNotNull($deleted);
        $this->assertSame('warning', $deleted->severity);
    }

    public function test_without_audit_flag_skips_emission(): void
    {
        $widget = new AuditTestWidget;
        $widget->organization_id = $this->org->id;
        $widget->name = 'Silent';
        $widget->withoutAudit = true;
        $widget->save();

        $this->assertSame(0, UserActivityLog::where('action', 'audit_test_widget.created')->count());
    }

    public function test_trait_resolves_actor_from_audit_context_when_no_auth(): void
    {
        auth()->logout();

        withAuditActor($this->user, function () {
            AuditTestWidget::create([
                'organization_id' => $this->org->id,
                'name' => 'Widget E',
            ]);
        }, ['source' => 'job']);

        $row = UserActivityLog::where('action', 'audit_test_widget.created')->latest('id')->first();
        $this->assertSame($this->user->id, $row->user_id);
        $this->assertSame('job', $row->metadata['source']);
    }
}

/**
 * Stub model used only by these tests.
 */
class AuditTestWidget extends Model
{
    use LogsAudit;

    protected $table = 'audit_test_widgets';

    protected $guarded = [];

    protected $auditCategory = 'product';

    protected array $auditExclude = ['secret_token'];
}
