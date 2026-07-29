<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One approval queue backs every AI assist: the AI drafts, a human
        // approves, and the existing send/update actions fire.
        Schema::create('suggested_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject'); // list entry (outreach) or deal (recap)
            $table->string('type'); // first_touch | follow_up | reply_triage | deal_recap
            $table->json('payload'); // drafts, classification, suggested status, recap text
            $table->string('status')->default('pending'); // pending | approved | dismissed
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggested_actions');
    }
};
