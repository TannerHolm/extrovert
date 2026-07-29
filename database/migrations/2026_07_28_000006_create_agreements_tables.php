<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('body_markdown');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft | sent | viewed | signed | declined | voided
            $table->text('body_markdown'); // editable while draft
            $table->string('pdf_path')->nullable(); // immutable snapshot created at send
            $table->string('signed_pdf_path')->nullable(); // executed copy with signature + audit block
            $table->string('content_hash')->nullable(); // sha256 of the sent PDF — tamper evidence
            $table->string('sign_token', 64)->unique(); // the only credential the signer needs
            $table->string('signer_name')->nullable();
            $table->string('signer_email')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('signature_payload')->nullable(); // typed name (and consent) captured at signing
            $table->string('signed_ip')->nullable();
            $table->string('signed_user_agent')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });

        Schema::create('agreement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // created | sent | viewed | signed | declined | voided | resent
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_events');
        Schema::dropIfExists('agreements');
        Schema::dropIfExists('agreement_templates');
    }
};
