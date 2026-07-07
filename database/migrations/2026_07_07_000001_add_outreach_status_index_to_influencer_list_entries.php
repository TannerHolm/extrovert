<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influencer_list_entries', function (Blueprint $table) {
            // Speeds up the per-status filtering used by the dashboard, list filter, and kanban board.
            $table->index(['influencer_list_id', 'outreach_status']);
        });
    }

    public function down(): void
    {
        Schema::table('influencer_list_entries', function (Blueprint $table) {
            $table->dropIndex(['influencer_list_id', 'outreach_status']);
        });
    }
};
