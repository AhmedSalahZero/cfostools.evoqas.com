<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Field Mappings ─────────────────────────────────────────────────
        Schema::create('export_sales_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('field_key');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['portfolio_company_id', 'field_key'], 'es_field_mappings_company_field_unique');
            $table->foreign('portfolio_company_id')
                  ->references('id')->on('portfolio_companies')->onDelete('cascade');
        });

        // ── Uploads ────────────────────────────────────────────────────────
        Schema::create('export_sales_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('file_path');
            $table->string('original_filename');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('date_format')->default('DD/MM/YYYY');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->integer('row_count')->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('portfolio_company_id')
                  ->references('id')->on('portfolio_companies')->onDelete('cascade');
        });

        // ── Data rows ──────────────────────────────────────────────────────
        Schema::create('export_sales_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('upload_id');
            $table->date('date')->nullable()->index();

            // Core trade fields
            $table->string('purchase_order_number')->nullable();
            $table->date('purchase_order_date')->nullable();
            $table->string('business_unit')->nullable()->index();
            $table->string('customer_name')->nullable()->index();
            $table->string('consignee')->nullable();
            $table->string('loading_country')->nullable()->index();
            $table->string('destination_country')->nullable()->index();
            $table->string('broker')->nullable();
            $table->string('product_category')->nullable()->index();
            $table->string('product_item')->nullable()->index();
            $table->string('origin')->nullable();
            $table->string('packing_unit_of_measurement')->nullable();
            $table->decimal('packing_quantity', 18, 4)->nullable();
            $table->string('packing_type')->nullable();
            $table->integer('full_container_load_count')->nullable();
            $table->string('full_container_load_type')->nullable();
            $table->string('quantity_unit_of_measurement')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('price_per_unit', 18, 4)->nullable();
            $table->decimal('purchase_order_value', 18, 4)->nullable();
            $table->decimal('purchase_order_net_value', 18, 4)->nullable();
            $table->string('incoterms')->nullable();
            $table->decimal('freight_value', 18, 4)->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('shipping_line')->nullable();
            $table->string('booking_number')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('cut_off_date')->nullable();
            $table->date('estimated_time_of_sailing')->nullable();
            $table->date('estimated_time_of_arrival')->nullable();
            $table->string('port_of_destination')->nullable();
            $table->string('inspection_company')->nullable();
            $table->string('clearance_agent')->nullable();
            $table->string('export_bank')->nullable();
            $table->string('documents_sending_type')->nullable();
            $table->string('purchase_order_status')->nullable()->index();
            $table->string('revenue_stream')->nullable()->index();

            $table->timestamps();

            $table->index('portfolio_company_id');
            $table->index('upload_id');
        });

        // ── Dashboard Notes ────────────────────────────────────────────────
        Schema::create('export_sales_dashboard_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('color')->default('blue');
            $table->timestamps();

            $table->foreign('portfolio_company_id')
                  ->references('id')->on('portfolio_companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_sales_dashboard_notes');
        Schema::dropIfExists('export_sales_data');
        Schema::dropIfExists('export_sales_uploads');
        Schema::dropIfExists('export_sales_field_mappings');
    }
};