<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['book', 'product', 'service'])->index();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('source_system')->nullable();
            $table->string('source_id')->nullable();
            $table->text('source_url')->nullable();
            $table->json('metadata_flags')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'source_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('catalog_items', fn (Blueprint $table) => $table->fullText(['title', 'subtitle', 'summary', 'description']));
        }

        Schema::create('contributors', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_item_contributors', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['author', 'editor', 'translator', 'illustrator', 'foreword', 'contributor'])->default('author');
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['catalog_item_id', 'contributor_id', 'role']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_item_category', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['catalog_item_id', 'category_id']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_item_collection', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['catalog_item_id', 'collection_id']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('credit')->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->text('source_url')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_item_media', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['cover', 'gallery', 'social'])->default('gallery');
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['catalog_item_id', 'media_asset_id', 'role']);
        });

        Schema::create('book_details', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('publisher')->default('APF Press');
            $table->string('imprint')->nullable();
            $table->string('original_language', 10)->default('en');
            $table->timestamps();
        });

        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['print_book', 'ebook', 'physical_product', 'digital_product', 'service']);
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->char('currency', 3)->default('CAD');
            $table->enum('purchase_mode', ['online', 'inquiry', 'unavailable'])->default('inquiry')->index();
            $table->string('tax_class')->default('standard');
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('access_duration_days')->nullable();
            $table->timestamps();
        });

        Schema::create('book_editions', function (Blueprint $table) {
            $table->foreignId('offering_id')->primary()->constrained()->cascadeOnDelete();
            $table->enum('format', ['paperback', 'hardcover', 'pdf', 'epub', 'other']);
            $table->string('edition_label')->nullable();
            $table->string('isbn_10', 10)->nullable()->unique();
            $table->string('isbn_13', 13)->nullable()->unique();
            $table->date('publication_date')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('language', 10)->default('en');
            $table->decimal('weight_grams', 10, 2)->nullable();
            $table->decimal('width_mm', 10, 2)->nullable();
            $table->decimal('height_mm', 10, 2)->nullable();
            $table->decimal('depth_mm', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->foreignId('offering_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('variant_label')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_details', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('deliverables')->nullable();
            $table->string('typical_duration')->nullable();
            $table->enum('fulfillment_type', ['inquiry', 'fixed_package'])->default('inquiry');
            $table->timestamps();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->foreignId('offering_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(2);
            $table->boolean('track_inventory')->default(false);
            $table->boolean('allow_backorder')->default(false);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_delta');
            $table->enum('reason', ['import', 'adjustment', 'reservation', 'release', 'sale', 'refund']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('service_details');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('book_editions');
        Schema::dropIfExists('offerings');
        Schema::dropIfExists('book_details');
        Schema::dropIfExists('catalog_item_media');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('catalog_item_collection');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('catalog_item_category');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('catalog_item_contributors');
        Schema::dropIfExists('contributors');
        Schema::dropIfExists('catalog_items');
    }
};
