<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentItem extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'title',
        'detail',
        'price',
        'crypto_currency_id',
        'type_of_price',
        'type',
        'item_type',
        'availabilty_type',
        'world_wide',
        'country_code',
        'driver_preference',
        'latitude',
        'longitude',
        'address',
        'order',
        'status',
        
    ];
    
    public $hidden = [
        'deleted_at'
    ];

    public $timestamps = true;
    
    public function user( ) { 
        return $this->belongsTo( 'App\Models\User' );
    }

    public function images( ) { 
        return $this->hasMany( 'App\Models\RentItemMedia' );
    }

    public function rentItemCar() { 
        return $this->hasOne( 'App\Models\RentCar' );
    }

    public function rentItemBike() { 
        return $this->hasOne( 'App\Models\RentBike' );
    }

    public function rentItemBoat() { 
        return $this->hasOne( 'App\Models\RentBoat' );
    }

    public function rentItemJetski() { 
        return $this->hasOne( 'App\Models\RentJetski' );
    }

    public function rentItemUser() { 
        return $this->belongsTo( 'App\Models\User', 'user_id', 'id' );
    }

    public function rentItemOther() { 
        return $this->hasOne( 'App\Models\RentOther' );
    }

    public function rentItemJewellery() { 
        return $this->hasOne( 'App\Models\RentJewellery');
    }

    public function rentItemFurniture() { 
        return $this->hasOne( 'App\Models\RentFurniture' );
    }
    public function rentItemElectronic() { 
        return $this->hasOne( 'App\Models\RentElectronic' );
    }

    public function rentItemTool() { 
        return $this->hasOne( 'App\Models\RentTool' );
    }
    public function rentItemEquipment() { 
        return $this->hasOne( 'App\Models\RentEquipment' );
    }

    
    public function rentItemProperty() { 
        return $this->hasOne( 'App\Models\RentProperty' );
    }
    public function rentItemManPower() { 
        return $this->hasOne( 'App\Models\RentManPower' );
    }

    public function availabilityByDay( ) { 
        return $this->hasMany( 'App\Models\RentItemsAvailabilityByDay' );
    }

    public function availabilityByDate() { 
        return $this->hasOne( 'App\Models\RentItemsAvailabilityByDate' );
    }

    public function availabilityByDays( ) { 
        return $this->belongsTo( 'App\Models\RentItemsAvailabilityByDay' ,'rent_item_id' )->select(['id','days']);
    }

    public function availabilityByDates() { 
        return $this->belongsTo( 'App\Models\RentItemsAvailabilityByDate')->select(array('start', 'end'))->first();
    }

    public function brand() { 
        return $this->hasOne( 'App\Models\Brand' );
    }
    public function model() { 
        return $this->hasOne( 'App\Models\Model' );
    }
   
    public function userFavourites(){
        return $this->hasMany('App\Models\UserFavouriteItem');
    }
    public function ItemReviews(){
        return $this->hasMany('App\Models\RentItemRatingReview');
    }
    
    //images//
    public function itemImages( ) { 
        return $this->belongsTo( 'App\Models\RentItemMedia', 'id', 'rent_item_id' );
    }
    public function bookingStatus( ) { 
        return $this->belongsTo( 'App\Models\RentItemBooking', 'id', 'rent_item_id' );
    }

    public function rentItemCryptoCurrency() { 
        return $this->belongsTo( 'App\Models\CryptoCurrencyCoins', 'crypto_currency_id', 'id' );
    }
}
