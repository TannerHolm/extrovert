<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributed_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_order_id');
            $table->string('order_number')->nullable();
            $table->bigInteger('total_cents');
            $table->string('currency', 3);
            $table->string('customer_hash')->nullable(); // new-vs-returning without storing PII
            $table->boolean('is_new_customer')->default(false);
            $table->string('matched_via'); // discount_code | ref_link | utm
            $table->timestamp('placed_at')->nullable();
            $table->json('raw_payload'); // kept so orders can be re-attributed when matching improves
            $table->timestamps();

            $table->unique(['team_id', 'shopify_order_id']);
            $table->index(['deal_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributed_orders');
    }
};
