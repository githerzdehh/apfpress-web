<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->json('content_blocks')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('editorial_board_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('affiliation');
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('quote');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['new', 'in_progress', 'resolved', 'spam'])->default('new')->index();
            $table->timestamps();
        });

        Schema::create('manuscript_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('working_title');
            $table->string('genre')->nullable();
            $table->text('abstract');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->enum('status', ['new', 'reviewing', 'accepted', 'declined', 'withdrawn'])->default('new')->index();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('destination_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['revisionable_type', 'revisionable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('manuscript_submissions');
        Schema::dropIfExists('contact_inquiries');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('editorial_board_members');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('pages');
    }
};
