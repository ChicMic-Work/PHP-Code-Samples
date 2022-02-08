<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RentItemBooking;
use App\Models\RentItem;
use Illuminate\Support\Facades\Log;

class ReleaseRentItemCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update RentItem booking if item is locked state.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today=date('Y-m-d');
        $result=RentItemBooking::whereDate('booking_end','<',$today)->where('status', '=', 0)->get();
        if($result->count( ) > 0 ){
            
            $rentItemBookingIds = [];
            foreach ($result as $key => $value) {
                RentItemBooking::where('id',$value->id)->update(['status'=>'1']);
                RentItem::where('id',$value->rent_item_id)->update(['booking_status'=>'0']);
                $rentItemBookingIds[$key] = $value->id;
            }
            $serialize_data = serialize($rentItemBookingIds);
            Log::info("Cron is working fine executed on ". date('Y-m-d H:i:s')." \n Serialzed Data:- ".$serialize_data);
            echo "Cron is working fine!"." \n";
        }
    }
}
