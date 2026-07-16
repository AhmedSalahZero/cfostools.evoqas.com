<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportSalesData extends Model
{
    protected $table = 'export_sales_data';

    protected $fillable = [
        'portfolio_company_id', 'upload_id', 'date',
        'purchase_order_number', 'purchase_order_date', 'business_unit',
        'customer_name', 'consignee', 'loading_country', 'destination_country',
        'broker', 'product_category', 'product_item', 'origin',
        'packing_unit_of_measurement', 'packing_quantity', 'packing_type',
        'full_container_load_count', 'full_container_load_type',
        'quantity_unit_of_measurement', 'quantity', 'currency',
        'price_per_unit', 'purchase_order_value', 'purchase_order_net_value',
        'incoterms', 'freight_value', 'payment_terms', 'shipping_line',
        'booking_number', 'port_of_loading', 'cut_off_date',
        'estimated_time_of_sailing', 'estimated_time_of_arrival',
        'port_of_destination', 'inspection_company', 'clearance_agent',
        'export_bank', 'documents_sending_type', 'purchase_order_status',
        'revenue_stream',
    ];
}