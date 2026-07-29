<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Review queue for inbound mail that couldn't be matched to a thread —
        // kept rather than dropped so nothing from a creator silently vanishes.
        Schema::create('unmatched_inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->string('from_email')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('raw_payload');
            $table->timestamp('received_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmatched_inbound_emails');
    }
};
