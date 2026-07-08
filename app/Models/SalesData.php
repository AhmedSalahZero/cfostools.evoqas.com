<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesData extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'upload_id',
        'date',
        'branch',
        'document_number',
        'business_unit',
        'business_sector',
        'sales_channel',
        'country',
        'document_type',
        'sales_person',
        'service_provider_name',
        'service_provider_type',
        'service_provider_birth_year',
        'principle',
        'product_category',
        'product_sub_category',
        'product_item',
        'measurement_unit',
        'price_per_unit',
        'customer_name',
        'zone',
        'quantity',
        'sales_value',
        'cash_discount',
        'quantity_discount',
        'special_discount',
        'other_discounts',
        'net_sales_value',
    ];

    protected $casts = [
        'date'                    => 'date',
        'price_per_unit'          => 'decimal:4',
        'quantity'                => 'decimal:4',
        'sales_value'             => 'decimal:2',
        'cash_discount'           => 'decimal:2',
        'quantity_discount'       => 'decimal:2',
        'special_discount'        => 'decimal:2',
        'other_discounts'         => 'decimal:2',
        'net_sales_value'         => 'decimal:2',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function upload()
    {
        return $this->belongsTo(SalesUpload::class, 'upload_id');
    }
}