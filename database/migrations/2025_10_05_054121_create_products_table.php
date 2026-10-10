<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id'); // Changed from uuid to bigint
            $table->uuid('category_id')->nullable();

            // Basic info
            $table->string('name', 500);
            $table->string('slug', 500);
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('sku');
            $table->string('barcode')->nullable();

            // Type and status
            $table->enum('type', ['simple', 'variable', 'bundle', 'digital'])->default('simple');
            $table->enum('status', ['active', 'inactive', 'discontinued', 'draft'])->default('active');

            // Pricing
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('msrp', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            // Physical attributes
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // Inventory settings
            $table->boolean('track_inventory')->default(true);
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(10);
            $table->boolean('allow_backorders')->default(false);

            // Tax and shipping
            $table->string('tax_class', 100)->nullable();
            $table->boolean('requires_shipping')->default(true);
            $table->string('shipping_class', 100)->nullable();

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('search_keywords')->nullable();

            // Flexible attributes
            $table->jsonb('attributes')->default('{}');

            // Media
            $table->string('featured_image', 500)->nullable();
            $table->jsonb('gallery_images')->default('[]');

            // Ratings
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);

            // Analytics
            $table->integer('total_sales')->default(0);
            $table->integer('view_count')->default(0);

            // Timestamps
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // Changed from uuid to bigint
            $table->timestamps();
            $table->integer('version')->default(1);

            $table->unique(['organization_id', 'sku']);
            $table->unique(['organization_id', 'slug']);

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Add check constraints using raw SQL
        DB::statement('ALTER TABLE products ADD CONSTRAINT positive_prices CHECK (cost_price >= 0 AND selling_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT positive_dimensions CHECK (weight >= 0 AND length >= 0 AND width >= 0 AND height >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT valid_rating CHECK (average_rating >= 0 AND average_rating <= 5)');

        // Create indexes for performance
        DB::statement('CREATE INDEX idx_products_organization_category ON products (organization_id, category_id)');
        DB::statement('CREATE INDEX idx_products_sku ON products (organization_id, sku)');
        DB::statement('CREATE INDEX idx_products_status ON products (organization_id, status)');
        DB::statement("CREATE INDEX idx_products_search ON products USING GIN (to_tsvector('english', name || ' ' || COALESCE(description, '') || ' ' || COALESCE(search_keywords, '')))");
        DB::statement('CREATE INDEX idx_products_attributes ON products USING GIN (attributes)');
        DB::statement('CREATE INDEX idx_products_created_at ON products (organization_id, created_at DESC)');
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
