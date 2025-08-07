<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryProduct extends Model
{
	protected $guarded=[];

  
    public function Product($company_id)
    {
        return $this->hasMany(Product::class)->where('company_id',$company_id)->get();
    }
    public function products()
    {
        return  $this->hasMany(Product::class);
    }
    public function allProducts()
    {
        return $this->hasMany(Product::class);
    }
    public function revenueStreamType()
    {
         return $this->belongsTo(RevenueStreamType::class);
    }


}
