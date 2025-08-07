<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
	protected $guarded=[];


    public function categoryProducts($company_id)
    {
        return $this->belongsTo(CategoryProduct::class,'category_product_id','id')->where('company_id',$company_id)->first();
    }


    public function Product_volume_measurement()
    {
        return $this->hasmany(Product_volume_measurement::class,'product_id','id');
    }
    public function getproductUnitsAttribute ()
    {
        $main_units = [];
        if($this->revenue_stream_type_id == 1 || $this->revenue_stream_type_id == 5){
            $main_units['productSelling'] = Product_volume_measurement::where('product_id',$this->id)->where('type',"Selling")->where('is_main',1)->first();
            $main_units['productStocking'] = Product_volume_measurement::where('product_id',$this->id)->where('type',"Stocking")->where('is_main',1)->first();

            if ($this->revenue_stream_type_id == 1){
                $main_units['productPurchasing'] = Product_volume_measurement::where('product_id',$this->id)->where('type',"Purchasing")->where('is_main',1)->first();
            }if($this->revenue_stream_type_id == 5) {
                $main_units['productmanufacturingBatch'] = Product_volume_measurement::where('product_id', $this->id)->where('type', "Manufacturing Batch")->where('is_main', 1)->first();
            }
        }
        return $main_units;
    }
    public function manufacturingFormula($company_id,$financial_id)
    {
        return $this->hasOne(ManufacturingFormula::class,'product_id','id')->where('company_id',$company_id)->where('financial_plan_id',$financial_id)->first();
    }
    public function tradablePurchasinPlan()
    {
        return $this->hasOne(TradingPurchasingPlan::class,'foreign_id','id')->where('type','tradable_product');

    }
}
