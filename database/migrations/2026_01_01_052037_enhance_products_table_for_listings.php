<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Additional Identifiers (after barcode)
            $table->string('upc', 14)->nullable()->after('barcode');
            $table->string('ean', 14)->nullable()->after('upc');
            $table->string('isbn', 13)->nullable()->after('ean');
            $table->string('mpn', 100)->nullable()->after('isbn');
            $table->string('asin', 10)->nullable()->after('mpn');
            $table->string('epid')->nullable()->after('asin');
            $table->json('gtin_array')->nullable()->after('epid');

            // Model info (after brand_id)
            $table->string('model_number')->nullable()->after('brand_id');
            $table->string('series')->nullable()->after('model_number');

            // Package dimensions (after height)
            $table->decimal('weight_lbs', 10, 3)->nullable()->after('height');
            $table->decimal('package_weight_lbs', 10, 3)->nullable()->after('weight_lbs');
            $table->decimal('package_length', 10, 2)->nullable()->after('package_weight_lbs');
            $table->decimal('package_width', 10, 2)->nullable()->after('package_length');
            $table->decimal('package_height', 10, 2)->nullable()->after('package_width');

            // Rich content (after short_description)
            $table->json('features_bullets')->nullable()->after('short_description');
            $table->text('whats_in_box')->nullable()->after('features_bullets');
            $table->json('specifications_table')->nullable()->after('whats_in_box');

            // Condition (after status)
            $table->string('item_condition', 50)->nullable()->after('status');
            $table->text('condition_details')->nullable()->after('item_condition');

            // Warranty (at end)
            $table->string('warranty_type', 50)->nullable();
            $table->string('warranty_duration')->nullable();
            $table->text('warranty_details')->nullable();

            // Compliance
            $table->boolean('requires_age_verification')->default(false);
            $table->json('compliance_certifications')->nullable();
            $table->json('safety_warnings')->nullable();

            // Multi-pack
            $table->boolean('is_multipack')->default(false);
            $table->integer('multipack_quantity')->nullable();

            // Listing readiness
            $table->boolean('ready_for_listing')->default(false);
            $table->json('listing_checklist')->nullable();

            // Indexes
            $table->index('upc');
            $table->index('ean');
            $table->index('mpn');
            $table->index('asin');
            $table->index('ready_for_listing');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['upc']);
            $table->dropIndex(['ean']);
            $table->dropIndex(['mpn']);
            $table->dropIndex(['asin']);
            $table->dropIndex(['ready_for_listing']);

            $table->dropColumn([
                'upc', 'ean', 'isbn', 'mpn', 'asin', 'epid', 'gtin_array',
                'model_number', 'series',
                'weight_lbs', 'package_weight_lbs', 'package_length', 'package_width', 'package_height',
                'features_bullets', 'whats_in_box', 'specifications_table',
                'item_condition', 'condition_details',
                'warranty_type', 'warranty_duration', 'warranty_details',
                'requires_age_verification', 'compliance_certifications', 'safety_warnings',
                'is_multipack', 'multipack_quantity',
                'ready_for_listing', 'listing_checklist',
            ]);
        });
    }
};
