<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name'); // Kilogram, Metre, Litre, Piece, Metric Ton
            $table->string('symbol'); // KG, M, L, PCS, MT
            $table->string('type')->default('weight'); // weight, volume, length, count, area
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'symbol']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('sku')->index();
            $table->string('name');
            $table->string('type')->default('raw_material'); // raw_material, work_in_progress, finished_good, consumable
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->string('hsn_code')->nullable()->index(); // Indian HSN/SAC code
            $table->decimal('min_stock_level', 15, 4)->default(0);
            $table->decimal('max_stock_level', 15, 4)->nullable();
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->integer('lead_time_days')->default(7);
            $table->boolean('is_batch_tracked')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->index();
            $table->string('name');
            $table->json('attributes')->nullable(); // e.g. {"grade": "EN24", "diameter_mm": 50, "finish": "Polished"}
            $table->decimal('additional_cost', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'sku']);
        });

        Schema::create('bills_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->decimal('batch_size', 15, 4)->default(1.0);
            $table->decimal('output_quantity', 15, 4)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_materials_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('component_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('scrap_percentage', 6, 2)->default(0.00);
            $table->integer('sequence')->default(1);
            $table->boolean('is_sub_assembly')->default(false);
            $table->foreignId('sub_bom_id')->nullable()->constrained('bills_of_materials')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bills_of_materials');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('units');
    }
};

