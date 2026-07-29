<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            // team_id is denormalized for cheap scoping, matching influencer_lists.
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_list_entry_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft | agreed | live | completed | cancelled
            $table->string('compensation_type'); // gifted | flat_fee | commission | hybrid
            $table->unsignedBigInteger('flat_fee_cents')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable(); // percent of attributed revenue
            $table->unsignedBigInteger('product_value_cents')->nullable(); // retail value of seeded product
            $table->json('deliverables')->nullable(); // [{type, platform, due_date, posted_url, posted_at}]
            $table->text('usage_rights')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
