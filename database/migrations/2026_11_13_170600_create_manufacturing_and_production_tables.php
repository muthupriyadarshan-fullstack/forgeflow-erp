<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->string('type'); // CNC Lathe, Hydraulic Press, Furnace, Milling, Welding Robot
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('capacity_per_hour', 10, 2)->default(0);
            $table->string('location')->nullable();
            $table->enum('status', ['operational', 'under_maintenance', 'breakdown', 'idle'])->default('operational')->index();
            $table->decimal('hourly_rate', 15, 2)->default(0); // Cost per machine hour in INR
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_due')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('designation');
            $table->string('department')->default('production'); // production, quality_assurance, warehouse, maintenance, engineering
            $table->string('shift')->default('morning'); // general, morning, evening, night
            $table->decimal('hourly_rate', 15, 2)->default(0); // Labour rate in INR
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active'); // active, on_leave, terminated
            $table->timestamps();

            $table->unique(['company_id', 'employee_code']);
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('order_number')->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('bill_of_materials_id')->constrained('bills_of_materials')->restrictOnDelete();
            $table->foreignId('target_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('planned_quantity', 15, 4);
            $table->decimal('produced_quantity', 15, 4)->default(0);
            $table->decimal('rejected_quantity', 15, 4)->default(0);
            $table->decimal('scrapped_quantity', 15, 4)->default(0);
            $table->date('start_date');
            $table->date('due_date');
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_completed_at')->nullable();
            $table->enum('status', [
                'draft',
                'planned',
                'material_reserved',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('draft')->index();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->decimal('actual_material_cost', 15, 2)->default(0);
            $table->decimal('actual_labour_cost', 15, 2)->default(0);
            $table->decimal('actual_machine_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'order_number']);
        });

        Schema::create('production_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('stage_name'); // Forging, Rough Machining, Heat Treatment, Finish Grinding, Quality Inspection, Assembly
            $table->integer('sequence')->default(1);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending')->index();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->integer('planned_duration_minutes')->default(60);
            $table->integer('actual_duration_minutes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('material_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('quantity_reserved', 15, 4);
            $table->decimal('quantity_issued', 15, 4)->default(0);
            $table->enum('status', ['reserved', 'partially_issued', 'fully_issued', 'cancelled'])->default('reserved')->index();
            $table->timestamps();
        });

        Schema::create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('issue_number')->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('quantity_issued', 15, 4);
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('batch_number')->nullable()->index();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('output_number')->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('batch_number')->index();
            $table->decimal('quantity_produced', 15, 4);
            $table->decimal('quantity_rejected', 15, 4)->default(0);
            $table->decimal('quantity_scrapped', 15, 4)->default(0);
            $table->decimal('unit_production_cost', 15, 2)->default(0);
            $table->decimal('total_production_cost', 15, 2)->default(0);
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_outputs');
        Schema::dropIfExists('material_issues');
        Schema::dropIfExists('material_reservations');
        Schema::dropIfExists('production_stages');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('machines');
    }
};

