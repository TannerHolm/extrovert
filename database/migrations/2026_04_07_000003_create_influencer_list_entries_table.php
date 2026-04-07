<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_list_id')->constrained('influencer_lists')->cascadeOnDelete();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();
            $table->string('outreach_status')->default('none');
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['influencer_list_id', 'influencer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_list_entries');
    }
};
