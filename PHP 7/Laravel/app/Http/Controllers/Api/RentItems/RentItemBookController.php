<?php 
namespace App\Http\Controllers\API\RentItems;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;
use Carbon\Carbon;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Transaction;
use App\Models\RentCar;
use App\Models\RentBike; 
use App\Models\RentBoat;
use App\Models\RentJetski;
use App\Models\RentOther;
use App\Models\RentJewellery;
use App\Models\RentProperty;
use App\Models\RentItemsAvailabilityByDate;
use App\Models\RentItemsAvailabilityByDay;
use App\Models\RentItemMedia; 
use App\Models\RentItemBooking; 
use App\Models\RentItem; 
use DB;


class RentItemBookController extends Controller { 

      public function topRentItems( Request $request ) {

        $validator = Validator::make($request->all(), [ 
          'time_start' => 'required|date_format:H:i',
          'time_end'   => 'required|date_format:H:i',
          'from'       => 'required|date',
          'to'         => 'required|gte:from|date',
          ]);

        $start = $end = false;
        if( $request->from && $request->time_start && $request->to && $request->time_end ) { 
          $startDateTimeString = $request->from.' '.$request->time_start;
          $endDateTimeString = $request->to.' '.$request->time_end;

          $start = SELF::getDateInDefaultTimeZone( $startDateTimeString, 'Y-m-d H:i:s', '');
          $end = SELF::getDateInDefaultTimeZone( $endDateTimeString, 'Y-m-d H:i:s', '');
        }

        if (auth('api')->user()) {
          $uid = auth('api')->user()->getId();
        } else {
          $uid = '';
        }
        if( isset( $request->type ) && $request->type ) { 
          if(!in_array( $request->type, array_keys( \Config( 'app.FIELDS_GROUP' ) ) ) ) { 
            return SELF::getErrorResponse( __('Invalid Request'), 401 );
          } 
        } 


        $perPage = $request->get( 'per_page' );
        $page = $request->get( 'page' );
        $perPage = $perPage > 0?$perPage:10;

        $searchClass = "App\Models\RentItemBooking";
        
        $fetchWith = [ 
            'rentItems' => function ($qr) use($request,$uid){ 
              if( ( isset( $request->latitude ) && $request->latitude ) && ( isset( $request->longitude ) && $request->longitude ) ) { 
                if( $uid ) { 
                  $qr->select('id', 'user_id', 'type', 'type_of_price','item_type', 'title', 'detail', 'price', 'address', 'latitude', 'longitude',  'type', \DB::raw( '( 3959 * acos( cos( radians(' . $request->latitude . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $request->longitude . ') ) + sin( radians(' . $request->latitude . ') ) * sin( radians( latitude ) ) ) ) AS distance' ));
                } else { 
                  $qr->select('id', 'user_id', 'type', 'type_of_price', 'item_type', 'title', 'detail', 'price', 'address', 'latitude', 'longitude',  'type', \DB::raw( '( 3959 * acos( cos( radians(' . $request->latitude . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $request->longitude . ') ) + sin( radians(' . $request->latitude . ') ) * sin( radians( latitude ) ) ) ) AS distance',\DB::raw( '0 as is_favourite' ) ));
                }
                 
              } else {
                if(!empty($uid)){
                  $qr->select('id', 'user_id', 'type', 'type_of_price', 'title','item_type', 'detail', 'price', 'address', 'latitude', 'longitude',  'type');
                }else{
                  $qr->select('id', 'user_id', 'type', 'type_of_price', 'title','item_type',  'detail', 'price', 'address', 'latitude', 'longitude',  'type',\DB::raw( '0 as is_favourite' ));
                }
                  
              }   
              if($uid && $uid !='') {          
                $qr->withCount(['userFavourites as is_favourite'=>function ($qr) use($uid){ 
                    $qr->where('user_id','=',$uid);
                }]);
              }
            }, 
            'images',
            'rentItemBike' => function( $qr ){  
                                $qr->with( [ 
                                  'category' => function( $qr ){
                                    $qr->select( 'id', 'name' );
                                  },
                                  'brand' => function( $qr ){
                                    $qr->select( 'id', 'name' );
                                  },
                                  'model' => function( $qr ){
                                    $qr->select( 'id', 'name' );
                                  }
                                ] );
                            },
            'rentItemCar' => function( $qr ){  
                              $qr->with( [ 
                                'category' => function( $qr ){
                                  $qr->select( 'id', 'name' );
                                },
                                'brand' => function( $qr ){
                                  $qr->select( 'id', 'name' );
                                },
                                'model' => function( $qr ){
                                  $qr->select( 'id', 'name' );
                                }
                              ] );
                          },
            'rentItemBoat' => function( $qr ){  
                                $qr->with( [ 
                                  'category' => function( $qr ){
                                    $qr->select( 'id', 'name' );
                                  },
                                  'brand' => function( $qr ){
                                    $qr->select( 'id', 'name' );
                                  }
                                ] );
                            },
            'rentItemJetski' => function( $qr ){  
                              $qr->with( [ 
                                'brand' => function( $qr ){
                                  $qr->select( 'id', 'name' );
                                },
                                'model' => function( $qr ){
                                  $qr->select( 'id', 'name' );
                                }
                              ] );
                          },
            'rentItemProperty' => function( $qr ){  
                              $qr->with( [ 
                                'category' => function( $qr ){
                                  $qr->select( 'id', 'name' );
                                }
                              ] );
                          },
          ];
          $searchObj = RentItem::with($fetchWith);


          $searchObj->where( function( $mainSubQuery ) use( $start, $end, $request ) { 
                              
            if( $start && $end ) { 
                $mainSubQuery->where( function( $subQuery ) use( $start, $end ) { 
                 
                  $subQuery->where( 'availabilty_type', 0 );
                  $subQuery->whereHas( 'availabilityByDate', function ( $query ) use( $start, $end ) { 
                    $query->where( 'start', '<=', $start );
                    $query->where( 'end', '>=', $end  );
                  });
                });
  
                $mainSubQuery->orWhere( function( $subQuery ) use( $start, $end ) { 
                  $days = [ \date( 'N', \strtotime( $start ) ) - 1, \date( 'N', \strtotime( $end ) ) - 1 ];
                  $days = $days ?  \array_unique( $days ) : false;
                  //dump ( $days );
                  
                  $subQuery->where( 'availabilty_type', 1 );
                  $subQuery->WhereHas( 'availabilityByDay', function ( $query ) use( $days, $start, $end ) { 
                    $query->whereIn( 'days', $days );
                    $query->where( 'start_time', '<=', \date( 'H:i:s', \strtotime( $start ) ) );
                    $query->where( 'end_time', '>=', \date( 'H:i:s', \strtotime( $end ) ) );
                  });
                });
            }
  
          })
          ->select([ 
            'id',
            'type',
            'title',
            'price',
            'address',
            'item_type',
            'type_of_price',
            'status',
            \DB::raw( '( 3959 * acos( cos( radians(' . $request->latitude . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $request->longitude . ') ) + sin( radians(' . $request->latitude . ') ) * sin( radians( latitude ) ) ) ) AS distance' )
          ]);





         $tops = $searchClass::with($fetchWith);
         if( $start && $end ) {
          $tops = $tops->where( 'booking_start', '<=', $start  );
          $tops = $tops->where( 'booking_end', '>=', $end);
        }
        $tops = $tops->whereHas(
            'rentItems', function( $qr ) use( $request, $uid) { 
              $radius = $request->radius > 0 ? $request->radius : 100;
              if( isset( $request->type ) && $request->type ) { 
                  $qr->where('type', $request->type);
              }
              if($uid && $uid !='') {
                $qr->where('user_id', '!=', $uid);
              }
              $qr->whereHas( 'user' );
            }
        )
        
        ->select('rent_item_id', DB::raw('COUNT(id) as tops'))
        ->groupBy('rent_item_id')
        ->orderBy(DB::raw('COUNT(id)'), 'DESC')
        ->paginate($perPage);
        
        if( !$tops ) { 
          return SELF::getErrorResponse( __('Rent items not found'), 404);
        }
        $allRentItems = $tops->toArray( );
        
        if( $allRentItems && isset( $allRentItems['data'] ) && !empty( $allRentItems['data'] ) ) { 

        foreach( $allRentItems['data'] as $index => $item ) {

          $publicUrl = SELF::getStoragePublicUrl( 'rent_images/' . $item['rent_items'][ 'type' ] . '/' ) . '/';

          if( $item[ 'images' ] ) { 
            foreach( $item[ 'images' ] as $imIndex => $image ) { 
              $item[ 'images' ][ $imIndex ]['item_media_name'] = $publicUrl . $item[ 'images' ][ $imIndex ]['item_media_name'];
            }
          }

          $item[ 'rent_items' ][ 'price' ] = SELF::formatPrice( $item['rent_items'][ 'price' ] );

          if( $item[ 'rent_item_bike' ] ){
            $item[ 'rent_item_data' ] = $item[ 'rent_item_bike' ];
          }
          unset( $item[ 'rent_item_bike' ] );

          if( $item[ 'rent_item_car' ] ){
            $item[ 'rent_item_data' ] = $item[ 'rent_item_car' ];
          }
          unset( $item[ 'rent_item_car' ] );

          if( $item[ 'rent_item_boat' ] ){
            $item[ 'rent_item_data' ] = $item[ 'rent_item_boat' ];
          }
          unset( $item[ 'rent_item_boat' ] );

          if( $item[ 'rent_item_jetski' ] ){
            $item[ 'rent_item_data' ] = $item[ 'rent_item_jetski' ];
          }
          unset( $item[ 'rent_item_jetski' ] );

          if( $item[ 'rent_item_property' ] ){
            $item[ 'rent_item_data' ] = $item[ 'rent_item_property' ];
          }
          unset( $item[ 'rent_item_property' ] );
          
          $allRentItems['data'][ $index ] = $item;
        }
      }
      return SELF::getSuccessResponse( '', [ 'rent_items' => $allRentItems ] );
    }

    public function topBrands() { 
      
      $searchClass = "App\Models\RentItemBooking";
        $tops = $searchClass::with(['rentBrand' => function( $qr ){  
                                  $qr->select('id', 'name', 'image');
                                }])
        ->select('brand_id', DB::raw('COUNT(id) as tops'))
        ->groupBy('brand_id')
        ->orderBy(DB::raw('COUNT(id)'), 'DESC')
        ->take(10)
        ->get();

        $topBrands = $tops->toArray( );
        
        if( $topBrands && isset( $topBrands ) && !empty( $topBrands) )
        { 
          

          foreach ($topBrands as $index => $item) {

            if(!empty($item['rent_brand'])) {
              $publicImageUrl = $item['rent_brand']['image'] !='' ? SELF::getStoragePublicUrl( 'brand/' . $item['rent_brand']['image']) :  '';
              $item['rent_brand']['image'] =  $publicImageUrl;
              $topBrands[ $index ] = $item;
            }
          }
          
        }
        return SELF::getSuccessResponse( '', [ 'top_brands' => $topBrands ] );
    }


    public function bookedDate( $id ) { 

        if(!is_numeric($id)) { 
          return SELF::getErrorResponse( __('Invalid Request'), 401 );
        } 
         

        $objClass = "App\Models\RentItemBooking";
        $booked = $objClass::with(['rentItems'])
        ->where('rent_item_id', $id)
        ->orderBy('id', 'DESC')
        ->get();
        $bookedDate = $booked->toArray( );
        
        $data = array();
        if( $bookedDate && isset( $bookedDate ) && !empty( $bookedDate) )
        { 
          
          
          foreach ($bookedDate as $index => $item) {
            if(!empty($item['rent_items'])) {
              $data[ $index ][ 'booking_start' ] = $item['booking_start'];
              $data[ $index ][ 'booking_end' ] = $item['booking_end'];
              $data[ $index ][ 'Item' ] = $item['rent_items'];
            }
          }
          
        }
        return SELF::getSuccessResponse( '', [ 'rent_items' => $data ] );
       
    }


    public function addBooking(Request $request){

      $validator = Validator::make($request->all(), [
            'rent_item_id'     => 'required',
            'booking_start'  => 'required|date_format:Y-m-d H:i:s',
            'booking_end' =>  'required|date_format:Y-m-d H:i:s'
        ]);

        if ($errors = SELF::getValidationErrorsToArray($validator)) {
            return SELF::getErrorResponse($errors, 401);
        }

        /*******************Save Booking Data****/
        $checkbookedItem = RentItem::where('id', $request->rent_item_id)->where('booking_status', 1)->first();
        if(!empty($checkbookedItem)) {
          return SELF::getErrorResponse(__('Item already booked'), 401);
        }
        $ranString = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'),1,7);
        $rentItemOj = new RentItemBooking;

        $rentItemOj->user_id = Auth::user()->id;
        $rentItemOj->rent_item_id = $request->rent_item_id;
        $rentItemOj->brand_id = isset($request->brand_id) ? $request->brand_id : '';
        $rentItemOj->booking_id = $ranString;
        $rentItemOj->booking_start = $request->booking_start;
        $rentItemOj->booking_end = $request->booking_end;
        $rentItemOj->status = 0;
        $rentItemOj->pay_type = 0;


        
        if (!$rentItemOj->save())
            return SELF::getErrorResponse(__('Something went wrong, please try again later'), 401);
        RentItem::where('id', $request->rent_item_id)->update(['booking_status' => 1, 'booking_status_modified' => Carbon::now()]);

        return SELF::getSuccessResponse(__('Your details successfully saved'), [], $this->successStatus);
        
    }
}