<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_sync_jobs', function (Blueprint $table) {
            $table->id();

            // Service identification
            $table->string('service', 50)->index();         // 'whm', 'cloudflare', 'keycloak'
            $table->string('instance', 64)->nullable();     // 'WHM-Ryder' or null
            $table->string('action', 50);                   // 'sync', 'create', 'update_password', etc.

            // Target reference
            $table->string('target_type', 100)->nullable(); // 'EmailAccount', 'DnsRecord', etc.
            $table->string('target_id', 255)->nullable();   // email or record id
            $table->string('expected_hash', 64)->nullable();// snapshot hash saat dispatch (untuk conflict detection)

            // Job state
            $table->string('status', 20)->default('queued'); // queued, running, success, failed, conflict, skipped
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Audit
            $table->unsignedBigInteger('triggered_by')->nullable(); // user_id
            $table->string('trigger_source', 50)->default('manual'); // manual, scheduled, event

            $table->timestamps();

            // Indexes
            $table->index(['service', 'status']);
            $table->index(['target_type', 'target_id']);
            $table->index('triggered_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_sync_jobs');
    }
};
