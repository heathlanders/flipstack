<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('category_id');
            $table->string('name', 100);
            $table->string('label');
            $table->enum('type', ['text', 'number', 'boolean', 'date', 'select', 'multiselect']);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->integer('sort_order')->default(0);
            $table->jsonb('options')->default('[]');
            $table->jsonb('validation_rules')->default('{}');
            $table->timestamps();

            $table->unique(['category_id', 'name']);
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('category_attributes');
    }
};
