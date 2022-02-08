<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class RentItemBooking extends Model
{
    use HasFactory;

    

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id','rent_item_id', 'user_id','brand_id', 'model_id', 'booking_start', 'booking_end', 'pay_type'
    ];
    
    

    public $timestamps = true;

    public function rentItems()
    {
        return $this->belongsTo( 'App\Models\RentItem', 'rent_item_id', 'id' );
        
    }
    public function images()
    {
        return $this->hasMany('App\Models\RentItemMedia','rent_item_id','rent_item_id');
        //->select(array('title', 'type'));
    }


    public function rentItemCar() { 
        return $this->hasOne( 'App\Models\RentCar','rent_item_id','rent_item_id');
    }

    public function rentItemBike() { 
        return $this->hasOne( 'App\Models\RentBike','rent_item_id','rent_item_id');
    }

    public function rentItemBoat() { 
        return $this->hasOne( 'App\Models\RentBoat' ,'rent_item_id','rent_item_id');
    }

    public function rentItemJetski() { 
        return $this->hasOne( 'App\Models\RentJetski' ,'rent_item_id','rent_item_id');
    }

    public function rentItemOther() { 
        return $this->hasOne( 'App\Models\RentOther' ,'rent_item_id','rent_item_id');
    }

    public function rentItemJewellery() { 
        return $this->hasOne( 'App\Models\RentJewellery' ,'rent_item_id','rent_item_id');
    }

    public function rentItemProperty() { 
        return $this->hasOne( 'App\Models\RentProperty' ,'rent_item_id','rent_item_id');
    }
    public function rentBrand()
    {
        return $this->hasOne('App\Models\Brand','id','brand_id');
    }
    public function Brand()
    {
        return $this->hasOne('App\Models\Brand','id','brand_id')->select(['id', 'name']);
    }
    public function Model()
    {
        return $this->hasOne('App\Models\Model','id','model_id')->select(['id', 'name']);
    }
    public function BookbyUser()
    {
        return $this->hasOne('App\Models\User','id','user_id')->select(['name', 'profile_image']);
    }
    
}
