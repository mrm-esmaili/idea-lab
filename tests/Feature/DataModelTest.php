<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Experiment;
use App\Models\Idea;
use App\Models\Lead;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => AdminRole::Manager]);
    }

    public function test_legacy_amount_column_is_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('preorders', 'amount'));
        $this->assertTrue(Schema::hasColumn('preorders', 'amount_rial'));
    }

    public function test_campaign_with_expenses_or_experiments_cannot_be_deleted(): void
    {
        $idea = Idea::create(['name' => 'a', 'slug' => 'a', 'status' => 'testing']);
        $page = $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't']);
        $used = Campaign::create(['landing_page_id' => $page->id, 'name' => 'used', 'slug' => 'used', 'status' => 'running']);
        $inExperiment = Campaign::create(['landing_page_id' => $page->id, 'name' => 'exp', 'slug' => 'exp', 'status' => 'running']);
        $free = Campaign::create(['landing_page_id' => $page->id, 'name' => 'free', 'slug' => 'free', 'status' => 'planned']);

        Expense::create(['idea_id' => $idea->id, 'campaign_id' => $used->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 10, 'incurred_on' => now(), 'description' => 'x']);
        $experiment = Experiment::create(['idea_id' => $idea->id, 'title' => 't', 'hypothesis' => 'h', 'goal' => 'g', 'starts_on' => now(), 'ends_on' => now(), 'criteria' => []]);
        $experiment->campaigns()->attach($inExperiment->id);

        $manager = $this->manager();
        $this->actingAs($manager)->delete(route('admin.campaigns.destroy', $used))->assertSessionHasErrors('campaign');
        $this->actingAs($manager)->delete(route('admin.campaigns.destroy', $inExperiment))->assertSessionHasErrors('campaign');
        $this->actingAs($manager)->delete(route('admin.campaigns.destroy', $free))->assertSessionHasNoErrors();

        $this->assertSame(['exp', 'used'], Campaign::orderBy('slug')->pluck('slug')->all());
        $this->assertSame([$inExperiment->id], $experiment->campaigns()->pluck('campaigns.id')->all());

        // پایگاه داده هم حذف را رد می‌کند
        $this->expectException(QueryException::class);
        $used->delete();
    }

    public function test_idea_with_experiments_or_expenses_cannot_be_deleted(): void
    {
        $withExperiment = Idea::create(['name' => 'a', 'slug' => 'a', 'status' => 'draft']);
        Experiment::create(['idea_id' => $withExperiment->id, 'title' => 't', 'hypothesis' => 'h', 'goal' => 'g', 'starts_on' => now(), 'ends_on' => now(), 'criteria' => []]);
        $withExpense = Idea::create(['name' => 'b', 'slug' => 'b', 'status' => 'draft']);
        Expense::create(['idea_id' => $withExpense->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 10, 'incurred_on' => now(), 'description' => 'x']);
        $empty = Idea::create(['name' => 'c', 'slug' => 'c', 'status' => 'draft']);

        $manager = $this->manager();
        $this->actingAs($manager)->delete(route('ideas.destroy', $withExperiment))->assertSessionHasErrors('idea');
        $this->actingAs($manager)->delete(route('ideas.destroy', $withExpense))->assertSessionHasErrors('idea');
        $this->actingAs($manager)->delete(route('ideas.destroy', $empty))->assertSessionHasNoErrors();

        $this->assertSame(['a', 'b'], Idea::orderBy('slug')->pluck('slug')->all());
    }

    public function test_landing_page_with_leads_cannot_be_deleted_at_database_level(): void
    {
        $idea = Idea::create(['name' => 'a', 'slug' => 'a', 'status' => 'testing']);
        $page = $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't']);
        Lead::create(['landing_page_id' => $page->id, 'name' => 'x', 'phone' => '09120000000']);

        try {
            $page->delete();
            $this->fail('Deleting a landing page with leads must be rejected.');
        } catch (QueryException) {
            $this->assertSame(1, Lead::count());
        }
    }

    public function test_refund_reference_is_unique_per_payment_in_database(): void
    {
        $idea = Idea::create(['name' => 'a', 'slug' => 'a', 'status' => 'testing']);
        $page = $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't']);
        $preorder = Preorder::create(['landing_page_id' => $page->id, 'name' => 'x', 'phone' => '09120000000', 'amount_rial' => 100, 'payment_status' => 'paid', 'public_token' => (string) Str::uuid()]);
        $payment = $preorder->payments()->create(['amount_rial' => 100, 'status' => 'paid', 'authority' => 'A1']);
        $request = RefundRequest::create(['preorder_id' => $preorder->id, 'payment_id' => $payment->id, 'requested_by_type' => 'admin', 'requested_amount_rial' => 100, 'reason' => 'r', 'status' => 'approved', 'approved_amount_rial' => 100]);

        $row = ['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'amount_rial' => 10, 'method' => 'bank_transfer', 'reference' => 'R1', 'refunded_on' => now()];
        Refund::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        Refund::create($row);
    }
}
