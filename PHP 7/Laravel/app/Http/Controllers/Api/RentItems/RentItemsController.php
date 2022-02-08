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

use App\Models\RentItem;
use App\Models\RentCar;
use App\Models\RentBike;
use App\Models\RentFurniture;
use App\Models\RentEquipment;

use App\Models\RentElectronic;
use App\Models\RentBoat;
use App\Models\RentJetski;
use App\Models\RentOther;
use App\Models\RentJewellery;
use App\Models\RentManPower;
use App\Models\RentTool;


use App\Models\RentProperty;
use App\Models\RentItemsAvailabilityByDate;
use App\Models\RentItemsAvailabilityByDay;
use App\Models\RentItemMedia;

class RentItemsController extends Controller
{

    public function index(Request $request)
    {

        if (auth('api')->user())
        {
            $uid = auth('api')->user()
                ->getId();
        }
        else
        {
            $uid = '';
        }

        $validator = Validator::make($request->all() , ['latitude' => 'required', 'longitude' => 'required', 'time_start' => 'required|date_format:H:i', 'time_end' => 'required|date_format:H:i', 'from' => 'required|date', 'to' => 'required|gte:from|date', 'driver_preference' => 'in:0,1', 'type' => 'in:' . implode(',', array_keys(\Config('app.FIELDS_GROUP'))) ,

        'price_min' => 'regex:' . SELF::$REGEX_FLOAT, 'price_max' => 'required_with:price_min|gte:price_min|regex:' . SELF::$REGEX_FLOAT,

        'floors_max' => 'required_with:floors_min|gte:floors_min',

        'capacity_max' => 'required_with:capacity_min|gte:capacity_min',

        'power_max' => 'required_with:power_min|gte:power_min',

        'seats_max' => 'required_with:seats_min|gte:seats_min',

        ], ['latitude.required' => 'Location field is required', 'longitude.required' => 'Location field is required']);

        if ($errors = SELF::getValidationErrorsToArray($validator))
        {
            return SELF::getErrorResponse($errors, 401);
        }

        $start = $end = false;
        if ($request->from && $request->time_start && $request->to && $request->time_end)
        {
            $startDateTimeString = $request->from . ' ' . $request->time_start;
            $endDateTimeString = $request->to . ' ' . $request->time_end;

            $start = SELF::getDateInDefaultTimeZone($startDateTimeString, 'Y-m-d H:i:s', '');
            $end = SELF::getDateInDefaultTimeZone($endDateTimeString, 'Y-m-d H:i:s', '');
        }

        $lat = $request->latitude;
        $lng = $request->longitude;

        $perPage = $request->get('per_page');
        $page = $request->get('page');
        $perPage = $perPage > 0 ? $perPage : 10;

        $radius = $request->get('radius');
        $radius = $radius > 0 ? $radius : 100; //miles
        //\DB::enableQueryLog();
        $itemTableName = (new RentItemsAvailabilityByDate)->getTable();
        $dateTableName = (new RentItemsAvailabilityByDate)->getTable();
        $dayTableName = (new RentItemsAvailabilityByDay)->getTable();

        $fetchWith = ['images', 'rentItemBike' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemCar' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemBoat' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemJetski' => function ($qr)
        {
            $qr->with(['brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemProperty' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        ,

        ];

        $searchObj = RentItem::with($fetchWith);
        if (!empty($uid))
        {
            $searchObj->where(function ($mainSubQuery) use ($start, $end, $request)
            {

                if ($start && $end)
                {
                    $mainSubQuery->where(function ($subQuery) use ($start, $end)
                    {
                        $subQuery->where('availabilty_type', 0);
                        $subQuery->whereHas('availabilityByDate', function ($query) use ($start, $end)
                        {
                            $query->where('start', '<=', \strtotime($start));
                            $query->where('end', '>=', \strtotime($end));
                        });
                    });

                    $mainSubQuery->orWhere(function ($subQuery) use ($start, $end)
                    {
                        $days = [\date('N', \strtotime($start)) - 1, \date('N', \strtotime($end)) - 1];
                        $days = $days ? \array_unique($days) : false;
                        //dump ( $days );
                        $subQuery->where('availabilty_type', 1);
                        $subQuery->WhereHas('availabilityByDay', function ($query) use ($days, $start, $end)
                        {
                            $query->whereIn('days', $days);
                            $query->where('start_time', '<=', \date('H:i:s', \strtotime($start)));
                            $query->where('end_time', '>=', \date('H:i:s', \strtotime($end)));
                        });
                    });
                }

            })->select(['id', 'type', 'title', 'price', 'address', 'type_of_price', 'status', \DB::raw('( 3959 * acos( cos( radians(' . $lat . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $lng . ') ) + sin( radians(' . $lat . ') ) * sin( radians( latitude ) ) ) ) AS distance') ]);
        }
        else
        {
            $searchObj->where(function ($mainSubQuery) use ($start, $end, $request)
            {

                if ($start && $end)
                {
                    $mainSubQuery->where(function ($subQuery) use ($start, $end)
                    {
                        $subQuery->where('availabilty_type', 0);
                        $subQuery->whereHas('availabilityByDate', function ($query) use ($start, $end)
                        {
                            $query->where('start', '<=', \strtotime($start));
                            $query->where('end', '>=', \strtotime($end));
                        });
                    });

                    $mainSubQuery->orWhere(function ($subQuery) use ($start, $end)
                    {
                        $days = [\date('N', \strtotime($start)) - 1, \date('N', \strtotime($end)) - 1];
                        $days = $days ? \array_unique($days) : false;
                        //dump ( $days );
                        $subQuery->where('availabilty_type', 1);
                        $subQuery->WhereHas('availabilityByDay', function ($query) use ($days, $start, $end)
                        {
                            $query->whereIn('days', $days);
                            $query->where('start_time', '<=', \date('H:i:s', \strtotime($start)));
                            $query->where('end_time', '>=', \date('H:i:s', \strtotime($end)));
                        });
                    });
                }

            })->select(['id', 'type', 'title', 'price', 'address', 'type_of_price', 'status', \DB::raw('( 3959 * acos( cos( radians(' . $lat . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $lng . ') ) + sin( radians(' . $lat . ') ) * sin( radians( latitude ) ) ) ) AS distance') , \DB::raw('0 as is_favourite') ]);
        }

        if ($uid && $uid != '')
        {
            $searchObj->withCount(['userFavourites as is_favourite' => function ($qr) use ($uid)
            {
                $qr->where('user_id', '=', $uid);
            }
            ]);
        }

        if ($request->price_min > - 1 && $request->price_max > 0)
        {
            $searchObj->whereBetween('price', [$request->price_min, $request->price_max]);
        }

        if ($request->type == RentBike::GROUP)
        {
            $searchObj->WhereHas('rentItemBike', function ($queryObj) use ($request)
            {
                if ($request->category_id)
                {
                    $queryObj->where('category_id', $request->category_id);
                }
                if ($request->brand_id)
                {
                    $queryObj->where('brand_id', $request->brand_id);
                }
               
                if ($request->capacity_min > - 1 && $request->capacity_max > 0)
                {
                    $queryObj->whereBetween('capacity', [$request->capacity_min, $request->capacity_max]);
                }
            });
        }

        if ($request->type == RentCar::GROUP)
        {
            $searchObj->WhereHas('rentItemCar', function ($queryObj) use ($request)
            {
                if ($request->category_id)
                {
                    $queryObj->where('category_id', $request->category_id);
                }
                if ($request->brand_id)
                {
                    $queryObj->where('brand_id', $request->brand_id);
                }
              
                if ($request->power_min > - 1 && $request->power_max > 0)
                {
                    $queryObj->whereBetween('power', [$request->power_min, $request->power_max]);
                }
                if ($request->seats)
                {
                    $queryObj->where('seats', $request->seats);
                }
            });
        }

        if ($request->type == RentBoat::GROUP)
        {
            $searchObj->WhereHas('rentItemBoat', function ($queryObj) use ($request)
            {
                if ($request->category_id)
                {
                    $queryObj->where('category_id', $request->category_id);
                }
                if ($request->brand_id)
                {
                    $queryObj->where('brand_id', $request->brand_id);
                }
                if ($request->seats)
                {
                    $queryObj->where('seats', $request->seats);
                }
            });
        }

        if ($request->type == RentJetski::GROUP)
        {
            $searchObj->WhereHas('rentItemJetski', function ($queryObj) use ($request)
            {
                if ($request->brand_id)
                {
                    $queryObj->where('brand_id', $request->brand_id);
                }
                
                if ($request->power_min > - 1 && $request->power_max > 0)
                {
                    $queryObj->whereBetween('power', [$request->power_min, $request->power_max]);
                }
                if ($request->seats)
                {
                    $queryObj->where('seats', $request->seats);
                }
            });
        }

        if ($request->type == RentProperty::GROUP)
        {
            $searchObj->WhereHas('rentItemProperty', function ($queryObj) use ($request)
            {
                if ($request->category_id)
                {
                    $queryObj->where('category_id', $request->category_id);
                }
                if ($request->furnished_type)
                {
                    $queryObj->where('furnished_type', $request->furnished_type);
                }
                if ($request->no_of_bedrooms)
                {
                    $queryObj->where('no_of_bedrooms', $request->no_of_bedrooms);
                }
                if ($request->no_of_bathrooms)
                {
                    $queryObj->where('no_of_bathrooms', $request->no_of_bathrooms);
                }
                if ($request->floors_min > - 1 && $request->floors_max > 0)
                {
                    $queryObj->whereBetween('floors', [$request->floors_min, $request->floors_max]);
                }
            });
        }

        if (isset($request->search) && $request->search)
        {
            $searchObj->where('title', 'LIKE', '%' . $request->search . '%');
        }
       
        if (isset($request->driver_preference) && $request->driver_preference > - 1)
        {
            $searchObj->where('driver_preference', $request->driver_preference);
        }
        if ($uid && $uid != '')
        {
            $searchObj->where('user_id', '!=', $uid);
        }
        $searchObj->where("status", 1);
        $searchObj->having("distance", "<=", $radius);

        $rentItems = $searchObj->orderBy("distance", 'ASC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->orderBy("order", 'ASc')
            ->paginate($perPage);

       
        if (!$rentItems)
        {
            return SELF::getErrorResponse(__('Rent items not found'), 404);
        }

        $allRentItems = $rentItems->toArray();
        if ($allRentItems && isset($allRentItems['data']) && !empty($allRentItems['data']))
        {
            foreach ($allRentItems['data'] as $index => $item)
            {
                $publicUrl = SELF::getStoragePublicUrl('rent_images/' . $item['type'] . '/') . '/';

                if ($item['images'])
                {
                    foreach ($item['images'] as $imIndex => $image)
                    {
                        $item['images'][$imIndex]['item_media_name'] = $publicUrl . $item['images'][$imIndex]['item_media_name'];
                    }
                }

                $item['price'] = SELF::formatPrice($item['price']);

                if ($item['rent_item_bike'])
                {
                    $item['rent_item_data'] = $item['rent_item_bike'];
                }
                unset($item['rent_item_bike']);

                if ($item['rent_item_car'])
                {
                    $item['rent_item_data'] = $item['rent_item_car'];
                }
                unset($item['rent_item_car']);

                if ($item['rent_item_boat'])
                {
                    $item['rent_item_data'] = $item['rent_item_boat'];
                }
                unset($item['rent_item_boat']);

                if ($item['rent_item_jetski'])
                {
                    $item['rent_item_data'] = $item['rent_item_jetski'];
                }
                unset($item['rent_item_jetski']);

                if ($item['rent_item_property'])
                {
                    $item['rent_item_data'] = $item['rent_item_property'];
                }
                unset($item['rent_item_property']);

                $allRentItems['data'][$index] = $item;
            }
        }

        return SELF::getSuccessResponse('200', ['rent_items' => $allRentItems]);
    }

    public function search(Request $request)
    {
        if (auth('api')->user())
        {
            $uid = auth('api')->user()
                ->getId();
        }
        else
        {
            $uid = '';
        }
        $validator = Validator::make($request->all() , ['latitude' => 'required', 'longitude' => 'required',

        ], ['latitude.required' => 'Location field is required', 'longitude.required' => 'Location field is required']);

        if ($errors = SELF::getValidationErrorsToArray($validator))
        {
            return SELF::getErrorResponse($errors, 401);
        }

        if (!isset($request->keyword) || empty($request->keyword))
        {
            return SELF::getErrorResponse(__('Invalid Request'), 401);
        }

        $lat = $request->latitude;
        $lng = $request->longitude;

        $radius = $request->get('radius');
        $radius = $radius > 0 ? $radius : 100; //miles
        $searchClass = "App\Models\RentItem";
        $searchObj = $searchClass::select(['id', 'title', \DB::raw('( 3959 * acos( cos( radians(' . $lat . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $lng . ') ) + sin( radians(' . $lat . ') ) * sin( radians( latitude ) ) ) ) AS distance') , \DB::raw('0 as is_favourite') ]);

        if (isset($request->keyword) && $request->keyword)
        {
            $searchObj->where('title', 'LIKE', '%' . $request->keyword . '%');
        }
        if ($uid && $uid != '')
        {
            
            $searchObj->withCount(['userFavourites as is_favourite' => function ($qr) use ($uid)
            {
                $qr->where('user_id', '=', $uid);
            }
            ]);
        }
        $searchObj->where("status", 1);
        $searchObj->having("distance", "<", $radius);

        $rentItems = $searchObj->orderBy("distance", 'ASC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->orderBy("order", 'ASc')
            ->take(5)
            ->get();

        if (!$rentItems)
        {
            return SELF::getErrorResponse(__('Rent items not found'), 404);
        }

        return SELF::getSuccessResponse('', ['rent_items' => $rentItems->toArray() ]);
    }

    public function detail($type = "Other", $id = 0)
    {
        if (!$id || !in_array($type, array_keys(\Config('app.FIELDS_GROUP'))))
        {
            return SELF::getErrorResponse(__('Invalid Request'), 401);
        }

        $publicUrl = SELF::getStoragePublicUrl('rent_images/' . $type . '/') . '/';

        $searchClass = "App\Models\RentItem";
        $searchClassType = "App\Models\Rent" . \ucwords($type);
        $rentItem = $searchClass::with(['images' => function ($query) use ($publicUrl)
        {
            $query->select('id', \DB::raw('CONCAT( "' . $publicUrl . '", `item_media_name` ) as media_url') , 'rent_item_id');
        }
        ])->with('rentItem' . ucfirst($type))->where(['status' => 1, 'id' => $id, 'type' => $type])->first();

        if (!$rentItem)
        {
            return SELF::getErrorResponse(__('Rent item not found'), 404);
        }

        return SELF::getSuccessResponse('', ['rent_item' => $rentItem->toArray() ]);
    }

    //search  api/
    public function newSearch(Request $request)
    {

        if (auth('api')->user())
        {
            $uid = auth('api')->user()
                ->getId();
        }
        else
        {
            $uid = '';
        }

        $validator = Validator::make($request->all() , ['latitude' => 'required', 
        'longitude' => 'required',
         'time_start' => 'required|date_format:H:i',
          'time_end' => 'date_format:H:i', 
          'from' => 'required|date',
           'to' => 'gte:from|date',
           'driver_preference' => 'in:0,1', 
           'price_min' => 'regex:' . SELF::$REGEX_FLOAT, 
           'price_max' => 'required_with:price_min|gte:price_min|regex:' . SELF::$REGEX_FLOAT,

        'floors_max' => 'required_with:floors_min|gte:floors_min',

        'capacity_max' => 'required_with:capacity_min|gte:capacity_min',

        'power_max' => 'required_with:power_min|gte:power_min',

        'seats_max' => 'required_with:seats_min|gte:seats_min',
        'country_code'=>'required',
        'search_country_code'=>'required',
        ], ['latitude.required' => 'Location field is required', 'longitude.required' => 'Location field is required','country_code.required' => 'Country Code is required','search_country_code.required' => 'Searching country code is required']);

        if ($errors = SELF::getValidationErrorsToArray($validator))
        {
            return SELF::getErrorResponse($errors, 401);
        }

        $fieldGroupKeys = array_keys(\Config('app.FIELDS_GROUP'));
        if ($request->type != 'all')
        {
            $types = explode(',', $request->type);
            if (count($types) !== count(array_intersect($fieldGroupKeys, $types)))
            {
                return SELF::getErrorResponse(__("The selected type is invalid."), 401);

            }

        }
        else
        {
            
            $types = array(
                ['car',
                'bikes'],
                ['boat',
                'jetski'],
                ['furniture',
                'electronic'],
                ['tool',
                'equipment'],
                ['other'],
                ['jewellery'],
                ['property'],
                ['manpower'],
                ['all']
            );

        }

        $start = $end = false;
        if ($request->from && $request->time_start && $request->to && $request->time_end)
        {
            $startDateTimeString = $request->from . ' ' . $request->time_start;
            $endDateTimeString = $request->to . ' ' . $request->time_end;

            $start = SELF::getDateInDefaultTimeZone($startDateTimeString, 'Y-m-d H:i:s', '');
            $end = SELF::getDateInDefaultTimeZone($endDateTimeString, 'Y-m-d H:i:s', '');
        }

        $lat = $request->latitude;
        $lng = $request->longitude;
        $userCountryCode = $request->country_code;// User Country Code for e.g BH - user is in Behrain
        $searchCountryCode = $request->search_country_code; // User is searching Item in country

        $perPage = $request->get('per_page');
        $page = $request->get('page');
        $perPage = $perPage > 0 ? $perPage : 10;

        $radius = $request->get('radius');
        $radius = $radius > 0 ? $radius : 100; //miles
       // \DB::enableQueryLog();
        $itemTableName = (new RentItemsAvailabilityByDate)->getTable();
        $dateTableName = (new RentItemsAvailabilityByDate)->getTable();
        $dayTableName = (new RentItemsAvailabilityByDay)->getTable();

        $fetchWith = ['images', 'rentItemCryptoCurrency'=>function($qr){
            $qr->select('id', 'name');
        }, 
        'rentItemUser'=>function($qr){
            $qr->select('id', 'verification_status');
        },
        'rentItemBike' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemCar' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemBoat' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemJetski' => function ($qr)
        {
            $qr->with(['brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemProperty' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , ];

        $searchObj = RentItem::with($fetchWith);
        $selectCols = ['id', 'user_id','type', 'title', 'price','crypto_currency_id' ,'address', 'type_of_price', 'item_type', 'status','world_wide','country_code', \DB::raw('( 3959 * acos( cos( radians(' . $lat . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $lng . ') ) + sin( radians(' . $lat . ') ) * sin( radians( latitude ) ) ) ) AS distance') ];
        if (empty($uid)) { 
            $selectCols[] = \DB::raw('0 as is_favourite');
        }
        $searchObj->where(function ( $mainSubQuery ) use ( $start, $end, $request ) { 
            if ( $start && $end ) { 
                $mainSubQuery->where(function ($mainChildQuery) use ($start, $end){ 

                    $mainChildQuery->where(function ($mainChildSubQuery) use ($start, $end){ 
                        $mainChildSubQuery->where('item_type', 1);
                        $mainChildSubQuery->where(function ($childQuery) use ($start, $end){ 
                            $childQuery->where(function ($subQuery) use ($start, $end)
                            {
                                $subQuery->where('availabilty_type', 0);
                                $subQuery->whereHas('availabilityByDate', function ($query) use ($start, $end)
                                {
                                    $query->where('start', '<=', \strtotime($start));
                                    $query->where('end', '>=', \strtotime($end));
                                });
                            });

                            $childQuery->orWhere(function ($subQuery) use ($start, $end)
                            {
                                $days = [\date('N', \strtotime($start)) - 1, \date('N', \strtotime($end)) - 1];
                                $days = $days ? \array_unique($days) : false;
                               
                                $subQuery->where('availabilty_type', 1);
                                $subQuery->WhereHas('availabilityByDay', function ($query) use ($days, $start, $end)
                                {
                                    $query->whereIn('days', $days);
                                    $query->where('start_time', '<=', \date('H:i:s', \strtotime($start)));
                                    $query->where('end_time', '>=', \date('H:i:s', \strtotime($end)));
                                });
                                
                            });
                        });
                        
                    });

                    $mainChildQuery->orWhere(function ($mainChildSubQuery) use ($start){ 

                        $mainChildSubQuery->where('item_type', 0);
                        $mainChildSubQuery->where(function ($childQuery) use ($start){ 
                            $childQuery->where(function ($subQuery) use ($start)
                            {
                                $subQuery->where('availabilty_type', 0);
                                $subQuery->whereHas('availabilityByDate', function ($query) use ($start)
                                {
                                    $query->where('start', '<=', \strtotime($start));
                                });
                            });
                        });
                        
                    });

                });
            }
        })->select( $selectCols );

        if(isset($request->item_type) && ($request->item_type == 1  || $request->item_type == 0) ){
            $searchObj->where('item_type',$request->item_type);
        }
        
        if($searchCountryCode != $userCountryCode){
            $searchObj->where('world_wide','1');
        }else{
            $searchObj->where('country_code',$userCountryCode);
        }


        

        if ($uid && $uid != '')
        {
            $searchObj->withCount(['userFavourites as is_favourite' => function ($qr) use ($uid)
            {
                $qr->where('user_id', '=', $uid);
            }
            ]);
        }

        if ($request->price_min > - 1 && $request->price_max > 0)
        {
            $searchObj->whereBetween('price', [$request->price_min, $request->price_max]);
        }
        
        $searchObj->where(function ($mainSubQuery) use ($types, $request)
        {
            $isOr = false;
            if (in_array(RentBike::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;
                $mainSubQuery->$funcWhereHas('rentItemBike', function ($queryObj) use ($request)
                {
                    if ($request->category_id)
                    {
                        $queryObj->where('category_id', $request->category_id);
                    }
                    if ($request->brand_id)
                    {
                        $queryObj->where('brand_id', $request->brand_id);
                    }
                    
                    if ($request->capacity_min > - 1 && $request->capacity_max > 0)
                    {
                        $queryObj->whereBetween('capacity', [$request->capacity_min, $request->capacity_max]);
                    }
                });
            }

            if (in_array(RentCar::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;
                $mainSubQuery->$funcWhereHas('rentItemCar', function ($queryObj) use ($request)
                {
                    if ($request->category_id)
                    {
                        $queryObj->where('category_id', $request->category_id);
                    }
                    if ($request->brand_id)
                    {
                        $queryObj->where('brand_id', $request->brand_id);
                    }
                    
                    if ($request->power_min > - 1 && $request->power_max > 0)
                    {
                        $queryObj->whereBetween('power', [$request->power_min, $request->power_max]);
                    }
                    if ($request->seats)
                    {
                        $queryObj->where('seats', $request->seats);
                    }
                });
            }

            if (in_array(RentBoat::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;
                $mainSubQuery->$funcWhereHas('rentItemBoat', function ($queryObj) use ($request)
                {
                    if ($request->category_id)
                    {
                        $queryObj->where('category_id', $request->category_id);
                    }
                    if ($request->brand_id)
                    {
                        $queryObj->where('brand_id', $request->brand_id);
                    }
                    if ($request->seats)
                    {
                        $queryObj->where('seats', $request->seats);
                    }
                });
            }

            if (in_array(RentJetski::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;
                $mainSubQuery->$funcWhereHas('rentItemJetski', function ($queryObj) use ($request)
                {
                    if ($request->brand_id)
                    {
                        $queryObj->where('brand_id', $request->brand_id);
                    }
                    
                    if ($request->power_min > - 1 && $request->power_max > 0)
                    {
                        $queryObj->whereBetween('power', [$request->power_min, $request->power_max]);
                    }
                    if ($request->seats)
                    {
                        $queryObj->where('seats', $request->seats);
                    }
                });
            }

            if (in_array(RentProperty::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemProperty', function ($queryObj) use ($request)
                {
                    if ($request->category_id)
                    {
                        $queryObj->where('category_id', $request->category_id);
                    }
                    if ($request->furnished_type)
                    {
                        $queryObj->where('furnished_type', $request->furnished_type);
                    }
                    if ($request->no_of_bedrooms)
                    {
                        $queryObj->where('no_of_bedrooms', $request->no_of_bedrooms);
                    }
                    if ($request->no_of_bathrooms)
                    {
                        $queryObj->where('no_of_bathrooms', $request->no_of_bathrooms);
                    }
                    if ($request->floors_min > - 1 && $request->floors_max > 0)
                    {
                        $queryObj->whereBetween('floors', [$request->floors_min, $request->floors_max]);
                    }
                });
            }

            if (in_array(RentManPower::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemManPower', function ($queryObj) use ($request)
                {
                    if ($request->category_id)
                    {
                        $queryObj->where('category_id', $request->category_id);
                    }
                  
                });
            }
            if (in_array(RentOther::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemOther');
            }
            if (in_array(RentJewellery::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemJewellery');
            }
            if (in_array(RentFurniture::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemFurniture');
            }
            if (in_array(RentFurniture::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemElectronic');
            }
            if (in_array(RentTool::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemTool');
            }
            if (in_array(RentEquipment::GROUP, $types))
            {
                $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                $isOr = true;

                $mainSubQuery->$funcWhereHas('rentItemEquipment');
            }
        });

        if (isset($request->search) && $request->search)
        {
            $searchObj->where('title', 'LIKE', '%' . $request->search . '%');
        }

        $searchObj->whereIn('type', $types);
        
        if (isset($request->driver_preference) && $request->driver_preference > - 1)
        {
            $searchObj->where('driver_preference', $request->driver_preference);
        }
        if ($uid && $uid != '')
        {
            $searchObj->where('user_id', '!=', $uid);
        }
        $searchObj->where("status", 1);
        $searchObj->where("booking_status", 0);
        $searchObj->having("distance", "<=", $radius);

        $rentItems = $searchObj->orderBy("distance", 'ASC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->orderBy("order", 'ASc')
            ->paginate($perPage);

         
        if (!$rentItems)
        {
            return SELF::getErrorResponse(__('Rent items not found'), 404);
        }

        $allRentItems = $rentItems->toArray();
       
        if ($allRentItems && isset($allRentItems['data']) && !empty($allRentItems['data']))
        {
            foreach ($allRentItems['data'] as $index => $item)
            {
                $publicUrl = SELF::getStoragePublicUrl('rent_images/' . $item['type'] . '/') . '/';

                if ($item['images'])
                {
                    foreach ($item['images'] as $imIndex => $image)
                    {
                        $item['images'][$imIndex]['item_media_name'] = $publicUrl . $item['images'][$imIndex]['item_media_name'];
                    }
                }

                $item['price'] = SELF::formatPrice($item['price']);

                if ($item['rent_item_bike'])
                {
                    $item['rent_item_data'] = $item['rent_item_bike'];
                }
                unset($item['rent_item_bike']);

                if ($item['rent_item_car'])
                {
                    $item['rent_item_data'] = $item['rent_item_car'];
                }
                unset($item['rent_item_car']);

                if ($item['rent_item_boat'])
                {
                    $item['rent_item_data'] = $item['rent_item_boat'];
                }
                unset($item['rent_item_boat']);

                if ($item['rent_item_jetski'])
                {
                    $item['rent_item_data'] = $item['rent_item_jetski'];
                }
                unset($item['rent_item_jetski']);

                if ($item['rent_item_property'])
                {
                    $item['rent_item_data'] = $item['rent_item_property'];
                }
                unset($item['rent_item_property']);

                $allRentItems['data'][$index] = $item;
            }
        }

        return SELF::getSuccessResponse('200', ['rent_items' => $allRentItems]);
    }

    //home page api//
    public function categoryList(Request $request)
    {
        $totalItems = [];
        $uid = auth('api')->user() ? auth('api')
            ->user()
            ->getId() : '';

        $user=User::find($uid);
        $userDetails = null;
        if($user){
            $userDetails = $user->toArray( );
            if( $userDetails[ 'profile_image' ] ) 
                $userDetails[ 'profile_image' ] = SELF::getStoragePublicUrl( 'avatars/' . $userDetails[ 'profile_image' ] );
            if( $userDetails[ 'driver_license_image' ] ) 
                $userDetails[ 'driver_license_image' ] = SELF::getStoragePublicUrl( 'license/' . $userDetails[ 'driver_license_image' ] );
            if( $userDetails[ 'crypto_image' ] ) 
                $userDetails[ 'crypto_image' ] = SELF::getStoragePublicUrl( 'crypto-images/' . $userDetails[ 'crypto_image' ] );
            $userDetails[ 'phoneDetails' ] = SELF::getCountryCode($userDetails[ 'phone_number' ] );
            
        }
        

        $validator = Validator::make($request->all() , ['country_code'=>'required','search_country_code'=>'required','latitude' => 'required', 'longitude' => 'required', 'time_start' => 'required|date_format:H:i', 'time_end' => 'required|date_format:H:i', 'from' => 'required|date', 'to' => 'required|gte:from|date'], ['country_code.required' => 'Country Code is required','search_country_code.required' => 'Searching country code is required','latitude.required' => 'Location field is required', 'longitude.required' => 'Location field is required']);

        if ($errors = SELF::getValidationErrorsToArray($validator))
        {
            return SELF::getErrorResponse($errors, 401);
        }

        $types_arr = array(
            ['car',
            'bike'],
            ['boat',
            'jetski'],
            ['furniture',
            'electronic'],
            ['tool',
            'equipment'],
            ['other'],
            ['jewellery'],
            ['property'],
            ['manpower'],
            ['all']
        );

        $start = $end = false;
        if ($request->from && $request->time_start && $request->to && $request->time_end)
        {
            $startDateTimeString = $request->from . ' ' . $request->time_start;
            $endDateTimeString = $request->to . ' ' . $request->time_end;

            $start = SELF::getDateInDefaultTimeZone($startDateTimeString, 'Y-m-d H:i:s', '');
            $end = SELF::getDateInDefaultTimeZone($endDateTimeString, 'Y-m-d H:i:s', '');
        }

        $lat = $request->latitude;
        $lng = $request->longitude;
        $userCountryCode = $request->country_code;// User Country Code for e.g BH - user is in Behrain
        $searchCountryCode = $request->search_country_code; // User is searching Item in country
        $radius = $request->get('radius');
        $radius = $radius > 0 ? $radius : 100; //miles
        \DB::enableQueryLog();
        $itemTableName = (new RentItemsAvailabilityByDate)->getTable();
        $dateTableName = (new RentItemsAvailabilityByDate)->getTable();
        $dayTableName = (new RentItemsAvailabilityByDay)->getTable();

        $fetchWith = ['images',
        'rentItemCryptoCurrency'=>function($qr){
            $qr->select('id', 'name');
        },
        'rentItemUser'=>function($qr){
            $qr->select('id', 'verification_status');
        }
        ,'rentItemBike' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemCar' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemBoat' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemJetski' => function ($qr)
        {
            $qr->with(['brand' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            , 'model' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemProperty' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        , 'rentItemManPower' => function ($qr)
        {
            $qr->with(['category' => function ($qr)
            {
                $qr->select('id', 'name');
            }
            ]);
        }
        
        ];

        $mainSearchObj = RentItem::with($fetchWith);
        $selectCols = ['id','user_id', 'type', 'title', 'price','crypto_currency_id' ,'address', 'type_of_price', 'item_type', 'status','world_wide','country_code', \DB::raw('( 3959 * acos( cos( radians(' . $lat . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $lng . ') ) + sin( radians(' . $lat . ') ) * sin( radians( latitude ) ) ) ) AS distance') ];
        if (empty($uid)) { 
            $selectCols[] = \DB::raw('0 as is_favourite');
        }

        $count = 0; 
        $all=[];
        foreach ($types_arr as $types)
        {
            $searchObj = clone $mainSearchObj;

            
            $searchObj->where(function ( $mainSubQuery ) use ( $start, $end, $request ) { 
                if ( $start && $end ) { 
                    $mainSubQuery->where(function ($mainChildQuery) use ($start, $end){ 

                        $mainChildQuery->where(function ($mainChildSubQuery) use ($start, $end){ 
                            $mainChildSubQuery->where('item_type', 1);
                            $mainChildSubQuery->where(function ($childQuery) use ($start, $end){ 
                                $childQuery->where(function ($subQuery) use ($start, $end)
                                {
                                    $subQuery->where('availabilty_type', 0);
                                    $subQuery->whereHas('availabilityByDate', function ($query) use ($start, $end)
                                    {
                                        $query->where('start', '<=', \strtotime($start));
                                        $query->where('end', '>=', \strtotime($end));
                                    });
                                });

                                $childQuery->orWhere(function ($subQuery) use ($start, $end)
                                {
                                    $days = [\date('N', \strtotime($start)) - 1, \date('N', \strtotime($end)) - 1];
                                    $days = $days ? \array_unique($days) : false;
                                    
                                    $subQuery->where('availabilty_type', 1);
                                    $subQuery->WhereHas('availabilityByDay', function ($query) use ($days, $start, $end)
                                    {
                                        $query->whereIn('days', $days);
                                        $query->where('start_time', '<=', \date('H:i:s', \strtotime($start)));
                                        $query->where('end_time', '>=', \date('H:i:s', \strtotime($end)));
                                    });
                                    
                                });
                            });
                            
                        });

                        $mainChildQuery->orWhere(function ($mainChildSubQuery) use ($start){ 

                            $mainChildSubQuery->where('item_type', 0);
                            $mainChildSubQuery->where(function ($childQuery) use ($start){ 
                                $childQuery->where(function ($subQuery) use ($start)
                                {
                                    $subQuery->where('availabilty_type', 0);
                                    $subQuery->whereHas('availabilityByDate', function ($query) use ($start)
                                    {
                                        $query->where('start', '<=', \strtotime($start));
                                    });
                                });
                            });
                            
                        });

                    });
                }
            })->select( $selectCols );

            if(isset($request->item_type) && ($request->item_type == 1  || $request->item_type == 0) ){
                $searchObj->where('item_type',$request->item_type);
            }
            
            if($searchCountryCode != $userCountryCode){
                $searchObj
                ->where('world_wide','1');
            }else{
                $searchObj->where('country_code',$userCountryCode);
            }

            if ($uid && $uid != '')
            {
                $searchObj->withCount(['userFavourites as is_favourite' => function ($qr) use ($uid)
                {
                    $qr->where('user_id', '=', $uid);
                }
                ]);
            }

            if ($request->price_min > - 1 && $request->price_max > 0)
            {
                $searchObj->whereBetween('price', [$request->price_min, $request->price_max]);
            }

            
            $searchObj->where(function ($mainSubQuery) use ($types, $request)
            {
                $isOr = false;
                if (in_array(RentBike::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;
                    $mainSubQuery->$funcWhereHas('rentItemBike', function ($queryObj) use ($request)
                    {
                        if ($request->category_id)
                        {
                            $queryObj->where('category_id', $request->category_id);
                        }
                        if ($request->brand_id)
                        {
                            $queryObj->where('brand_id', $request->brand_id);
                        }
                       
                        if ($request->capacity_min > - 1 && $request->capacity_max > 0)
                        {
                            $queryObj->whereBetween('capacity', [$request->capacity_min, $request->capacity_max]);
                        }
                    });
                }

                if (in_array(RentCar::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;
                    $mainSubQuery->$funcWhereHas('rentItemCar', function ($queryObj) use ($request)
                    {
                        if ($request->category_id)
                        {
                            $queryObj->where('category_id', $request->category_id);
                        }
                        if ($request->brand_id)
                        {
                            $queryObj->where('brand_id', $request->brand_id);
                        }
                       
                        if ($request->power_min > - 1 && $request->power_max > 0)
                        {
                            $queryObj->whereBetween('power', [$request->power_min, $request->power_max]);
                        }
                        if ($request->seats)
                        {
                            $queryObj->where('seats', $request->seats);
                        }
                    });
                }

                if (in_array(RentBoat::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;
                    $mainSubQuery->$funcWhereHas('rentItemBoat', function ($queryObj) use ($request)
                    {
                        if ($request->category_id)
                        {
                            $queryObj->where('category_id', $request->category_id);
                        }
                        if ($request->brand_id)
                        {
                            $queryObj->where('brand_id', $request->brand_id);
                        }
                        if ($request->seats)
                        {
                            $queryObj->where('seats', $request->seats);
                        }
                    });
                }

                if (in_array(RentJetski::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;
                    $mainSubQuery->$funcWhereHas('rentItemJetski', function ($queryObj) use ($request)
                    {
                        if ($request->brand_id)
                        {
                            $queryObj->where('brand_id', $request->brand_id);
                        }
                        /* if( $request->model_id ) {
                        $queryObj->where( 'model_id', $request->model_id );
                        } */
                        if ($request->power_min > - 1 && $request->power_max > 0)
                        {
                            $queryObj->whereBetween('power', [$request->power_min, $request->power_max]);
                        }
                        if ($request->seats)
                        {
                            $queryObj->where('seats', $request->seats);
                        }
                    });
                }

                if (in_array(RentProperty::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemProperty', function ($queryObj) use ($request)
                    {
                        if ($request->category_id)
                        {
                            $queryObj->where('category_id', $request->category_id);
                        }
                        if ($request->furnished_type)
                        {
                            $queryObj->where('furnished_type', $request->furnished_type);
                        }
                        if ($request->no_of_bedrooms)
                        {
                            $queryObj->where('no_of_bedrooms', $request->no_of_bedrooms);
                        }
                        if ($request->no_of_bathrooms)
                        {
                            $queryObj->where('no_of_bathrooms', $request->no_of_bathrooms);
                        }
                        if ($request->floors_min > - 1 && $request->floors_max > 0)
                        {
                            $queryObj->whereBetween('floors', [$request->floors_min, $request->floors_max]);
                        }
                    });
                }
                if (in_array(RentManPower::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemManPower', function ($queryObj) use ($request)
                    {
                        if ($request->category_id)
                        {
                            $queryObj->where('category_id', $request->category_id);
                        }
                    
                    });
                }
                if (in_array(RentOther::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemOther');
                }
                if (in_array(RentJewellery::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemJewellery');
                }
                if (in_array(RentFurniture::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemFurniture');
                }
                if (in_array(RentFurniture::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemElectronic');
                }
                if (in_array(RentTool::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemTool');
                }
                if (in_array(RentEquipment::GROUP, $types))
                {
                    $funcWhereHas = ($isOr) ? 'orWhereHas' : 'whereHas';
                    $isOr = true;

                    $mainSubQuery->$funcWhereHas('rentItemEquipment');
                }
            });

            $searchObj->whereIn('type', $types);

            if ($uid && $uid != '')
            {
                $searchObj->where('user_id', '!=', $uid);
            }

            $searchObj->where("status", 1);
            $searchObj->having("distance", "<=", $radius);
            $searchObj->where("booking_status", 0);
            $rentItems = $searchObj->orderBy("distance", 'ASC')
              
                ->orderBy('created_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->orderBy("order", 'ASc')
                ->take(10)
                ->get();

            $allRentItems = array();

            if ($rentItems->count() > 0)
            {
                $allRentItems = $rentItems->toArray();
            }
            
            if (count($allRentItems) > 0)
            {
                foreach ($allRentItems as $index => $item)
                {
                    $publicUrl = SELF::getStoragePublicUrl('rent_images/' . $item['type'] . '/') . '/';

                    if ($item['images'])
                    {
                        foreach ($item['images'] as $imIndex => $image)
                        {
                            $item['images'][$imIndex]['item_media_name'] = $publicUrl . $item['images'][$imIndex]['item_media_name'];
                        }
                    }

                    $item['price'] = SELF::formatPrice($item['price']);

                    if ($item['rent_item_bike'])
                    {
                        $item['rent_item_data'] = $item['rent_item_bike'];
                    }
                    unset($item['rent_item_bike']);

                    if ($item['rent_item_car'])
                    {
                        $item['rent_item_data'] = $item['rent_item_car'];
                    }
                    unset($item['rent_item_car']);

                    if ($item['rent_item_boat'])
                    {
                        $item['rent_item_data'] = $item['rent_item_boat'];
                    }
                    unset($item['rent_item_boat']);

                    if ($item['rent_item_jetski'])
                    {
                        $item['rent_item_data'] = $item['rent_item_jetski'];
                    }
                    unset($item['rent_item_jetski']);

                    if ($item['rent_item_property'])
                    {
                        $item['rent_item_data'] = $item['rent_item_property'];
                    }
                    unset($item['rent_item_property']);

                    if ($item['rent_item_man_power'])
                    {
                        $item['rent_item_man_power'] = $item['rent_item_man_power'];
                    }
                    unset($item['rent_item_man_power']);

                    $allRentItems[$index] = $item;
                    $all[$count]=$item;
                    $count++;
                }
            }

            $totalItems[implode('_', $types) ] = $allRentItems;
        }
        if(count($all) > 0){
            $newArray = array_slice($all, 0, 5, true);// get first five elements
            $totalItems['all']=$newArray;
        }

        return SELF::getSuccessResponse('200', ['rent_items' => $totalItems,'userDetails'=>$userDetails]);
    }
}