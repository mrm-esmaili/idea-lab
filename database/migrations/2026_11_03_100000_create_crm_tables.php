<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // علاقه اعلام‌شده مخاطب به ایده (چندبه‌چند). بازدید به‌تنهایی علاقه نیست؛
        // فقط ثبت فرم علاقه‌مندی، پیش‌خرید یا افزودن دستی مدیر.
        Schema::create('contact_idea_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            // lead_form | preorder | admin
            $table->string('source', 20);
            $table->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('campaign')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['contact_id', 'idea_id']);
            $table->index(['idea_id', 'created_at']);
        });

        // منبع جذب مخاطب (اولین تماس ثبت‌شده)
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('first_landing_page_id')->nullable()->after('email')
                ->constrained('landing_pages')->nullOnDelete();
            $table->string('first_source')->nullable()->after('first_landing_page_id');
            $table->string('first_medium')->nullable()->after('first_source');
            $table->string('first_campaign')->nullable()->after('first_medium');

            $table->index('first_campaign');
        });

        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // manual: اعضای ثبت‌شده؛ dynamic: قواعد که هنگام خواندن اجرا می‌شوند
            $table->string('type', 20);
            $table->json('rules')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contact_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['contact_group_id', 'contact_id']);
        });

        // ثبت هر خروجی داده شخصی: چه کسی، چه چیزی، چند ردیف
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 50);
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $records = DB::table('leads')
            ->join('landing_pages', 'landing_pages.id', '=', 'leads.landing_page_id')
            ->whereNotNull('leads.contact_id')
            ->select(['leads.contact_id', 'landing_pages.idea_id', 'leads.landing_page_id', 'leads.source', 'leads.medium', 'leads.campaign', 'leads.created_at'])
            ->selectRaw("'lead_form' AS kind")
            ->unionAll(
                DB::table('preorders')
                    ->join('landing_pages', 'landing_pages.id', '=', 'preorders.landing_page_id')
                    ->whereNotNull('preorders.contact_id')
                    ->select(['preorders.contact_id', 'landing_pages.idea_id', 'preorders.landing_page_id', 'preorders.source', 'preorders.medium', 'preorders.campaign', 'preorders.created_at'])
                    ->selectRaw("'preorder' AS kind")
            )
            ->get()
            ->sortBy('created_at');

        foreach ($records as $record) {
            DB::table('contact_idea_interests')->insertOrIgnore([
                'contact_id' => $record->contact_id,
                'idea_id' => $record->idea_id,
                'source' => $record->kind,
                'landing_page_id' => $record->landing_page_id,
                'campaign' => $record->campaign,
                'created_at' => $record->created_at,
            ]);

            DB::table('contacts')
                ->where('id', $record->contact_id)
                ->whereNull('first_landing_page_id')
                ->update([
                    'first_landing_page_id' => $record->landing_page_id,
                    'first_source' => $record->source,
                    'first_medium' => $record->medium,
                    'first_campaign' => $record->campaign,
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('contact_group_members');
        Schema::dropIfExists('contact_groups');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['first_campaign']);
            $table->dropConstrainedForeignId('first_landing_page_id');
            $table->dropColumn(['first_source', 'first_medium', 'first_campaign']);
        });

        Schema::dropIfExists('contact_idea_interests');
    }
};
