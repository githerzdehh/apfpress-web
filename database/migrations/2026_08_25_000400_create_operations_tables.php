<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->enum('environment', ['sandbox', 'live'])->default('sandbox');
            $table->boolean('enabled')->default(false);
            $table->longText('credentials')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->enum('health_status', ['untested', 'healthy', 'failed'])->default('untested');
            $table->text('health_message')->nullable();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('file_name')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->enum('status', ['previewed', 'processing', 'completed', 'failed'])->default('previewed')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('source_id')->nullable();
            $table->enum('action', ['create', 'update', 'merge', 'skip', 'error']);
            $table->json('source_data');
            $table->json('mapped_data')->nullable();
            $table->json('messages')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('integration_settings');
    }
};
