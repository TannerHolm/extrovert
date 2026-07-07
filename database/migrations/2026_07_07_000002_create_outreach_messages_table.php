<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_list_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction'); // outbound | inbound
            $table->string('from_email');
            $table->string('to_email');
            $table->string('subject');
            $table->text('body');
            $table->string('reply_token')->nullable()->index(); // matches inbound replies back to this thread
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('status')->default('sent'); // sent | failed | received
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['influencer_list_entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
    }
};
