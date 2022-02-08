<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\CommonMethods;
use App\Models\Event;
use App\Models\User;
use App\Models\UserTeam;
use App\Models\EventJoin;
use App\Models\FunFacts;
use App\Models\EventFunFactsAnswers;
use App\Models\SocketConnectUsers;
use App\Models\EventTeam;
use App\Models\Crops;
use App\Models\MmRounds;
use App\Models\Chances;
use App\Models\Production;
use App\Models\PlayerCash;
use App\Models\Team;
use App\Models\Forecasting;
use App\Models\Survey;
use App\Models\TeamTotalAssets;
use App\Models\EventLinkInvitedUsers;
use Carbon\Carbon;
use LRedis;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Auth;
// use Tymon\JWTAuth\Facades\JWTAuth;


class ApiController extends Controller
{

    use CommonMethods;

    public static $EVENT_RUNNING = 1;
    public static $PUBLISH_PRODUCTION_DATA = 2;
    public static $PUBLISH_CHANCE_RESULT = 3;
    public static $PUBLISH_ROUND_RESULT = 4;
    public static $PUBLISH_NEXT_CHANCE_DATA = 5;
    public static $PUBLISH_NEXT_ROUND_DATA = 6;
    public static $PUBLISH_GAME_RESULT = 7;
    public static $PUBLISH_FORECASTING_DATA = 8;
    public static $WAITING_FOR_TEAM_MEMBERS = 9;
    public static $EVENT_DETAILS_CALL_REQUEST = 10;
    public static $CUSTOM_RESULT_REQUEST = 11;

    public static $MIN_TEAMS_FOR_GAME = 2;
    public static $MIN_MEMBERS_REQ_TO_START_GAME = 2;
    public static $MIN_MEMBERS_REQ_FOR_ACTIVE_GAME = 1;


    /**
     * Create a new controller instance.
     *
     * @return void
     */

    private $currentTime;
    private $gameSettings;
    private $forcastingTypes = [1 => ' Weather',2 => ' Economy',3 => ' Foreign Producer'];
    private $random = [-1,0,1];
    // $randorec = $this->random[array_rand($this->random,1)];
    private $top_emojies = [1,2,3];
    
    private $lower_emojis = [4,5,6];

    private $top_string = [
                        1 => 'Awesome! Keep it up',
                        2 => 'Great job! You are killing it',
                        3 => 'No. 1! You are the best'
                    ];
    private $lower_string = [
                        1 => 'Well played! Better luck next time',
                        2 => 'Not bad! You can do better',
                        3 => 'Keep trying! You will get it'
                    ];

    public function __construct()
    {
        //$this->middleware('auth');
        $this->userTeamModelInstance = new UserTeam;
        $this->currentTime = $this->getUTCTime();
        $this->gameSettings = $this->getGameSettings();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    /** Cron Job to process all pending records of MM Game**/
    public function cronTasks(){
        //we can also add end time condition here if all round timers are fixed
        $activeEvents = MmRounds::getActiveEventRounds( $this->gameSettings->total_rounds );

        $echome = 0;

        if($activeEvents->count()){
            foreach ($activeEvents as $round) {
                //check end time for this event
                if( $this->mmGameValidations( $round->event_id, $this->currentTime ) ){

                    $echome .= 1;
                    if($round->round == $this->gameSettings->total_rounds && $round->status == MmRounds::ROUND_RESULT_ACTIVE && $round->end_time <= $this->currentTime){
                        // return game result here..
                        $this->publishGameResult($round->event_id);
                        $echome .= 2;
                    }else{
                        $echome .= 3;

                        if($round->status == MmRounds::ROUND_ACTIVE){
                            $echome .= 4;
                            //get active by time chance against this round.
                            $chanceData = Chances::where( ['round_id' => $round->id] )
                                        ->where( 'end_time', '<=', $this->currentTime )
                                        ->where( 'status', '!=', Chances::CHANCE_RESULT_COMPLETED )
                                        ->get();
                            //check chances exists against the round?
                            if( $chanceData->count() ){
                                $echome .= 5;
                                //there must be only one chance should be active against the round
                                if($chanceData->count() == 1 ){
                                    $echome .= 6;
                                    $chanceData = $chanceData->first();

                                    if( $chanceData->status == Chances::CHANCE_ACTIVE ){
                                        $echome .= 7;
                                        if($chanceData->chance == $this->gameSettings->chance_in_round){
                                            $echome .= 8;
                                            //publish complete round result here.
                                            $this->publishRoundResult ($round->event_id);
                                        }else{
                                            $echome .= 9;
                                            //publish chance result here..
                                            $this->publishChanceResult ($round->event_id);
                                        }
                                    }elseif ($chanceData->status == Chances::CHANCE_RESULT_ACTIVE ) {
                                        $echome .= '_11';
                                        //start next round
                                        if($chanceData->chance == $this->gameSettings->chance_in_round){
                                            $echome .= '_11';
                                            //start next round
                                            $this->publishNewRound ($round->id, $round->event_id);
                                        }else{
                                            $echome .= '_12';
                                            //start next chance
                                            $this->publishNextChance ($round->id, $round->event_id);
                                        }
                                    }
                                    
                                }else{
                                    $echome .= '_15';
                                    //publish complete round result here.
                                    $this->publishRoundResult ($round->event_id);
                                }
                            }else{//no active chance here
                                $chances = Chances::where( ['round_id' => $round->id, 'status' => Chances::CHANCE_RESULT_COMPLETED] )->count();
                                // if( $chances >= $this->gameSettings->chance_in_round ){
                                //     //publish Round Result here..
                                //     if($round->status != MmRounds::ROUND_RESULT_ACTIVE){
                                //         $this->publishRoundResult($round->event_id);
                                //     }
                                // }else{
                                //     $this->publishNextChance ($round->id, $round->event_id);
                                // }
                            }
                        }elseif ($round->status == MmRounds::ROUND_RESULT_ACTIVE && $round->end_time <= $this->currentTime) {
                            $echome .= '_18';
                            //start new round and chance
                            $this->publishNewRound ($round->id, $round->event_id);
                        }
                    }
                }else{//event time expired
                    /*Complete Event*/
                    $eventDetails = Event::find($round->event_id);
                    $eventDetails->status = 2;
                    $eventDetails->save();
                    /*Complete Event*/
                }
                $echome .= '_19';
            }
        }
        $echome .= '_20';
        echo $echome;
    }

    /** Validate the encription key for requested user **/
    public function validateEncKey ($encryptedId){

        $explodeMeetingId = explode("-", $encryptedId);

        if( count($explodeMeetingId) <= 0 ) return false;

        $userToken = $explodeMeetingId[0];
        $meetingToken = $explodeMeetingId[1];

        $event = Event::where(["meeting_token"=>$meetingToken])->first();
        if( !$event ) return false;

        $user = User::where(["enc_id"=>$userToken, "status"=>1])->first();

        if( !$user ) return false;

        $userteam = User::find($user->id)->userteam()->first();

        return ['event' => $event, 'user' => $user, 'userteam' => $userteam];

    }

    /** Get all active team members **/
    public function activeTeamMembersCount($eventId, $teamId){

        return $activeMembers = EventJoin::where(
            [
                "event_id"=>$eventId,
                "team_id"=>$teamId
            ]
        )->get();
    }

    public function publishData ($data, $eventId, $teamId, $type = 0){
        //Socket connection
        $redis = LRedis::connection();
        //encode data in JSON
        $json = json_encode( $data );
        if($type == 0){
            $channelId = "mm_event_".$eventId.$teamId;
            $redis->publish( $channelId, $json );
        }else{
            $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);
            //publish data for all active teams
            foreach ($activeTeams as $teams) {
                //credit initial cash to teams
                $channelId = "mm_event_".$eventId.$teams->team_id;
                $redis->publish( $channelId, $json );
            }
        }
        
    }

    /** Calculate timer minut and seconds as per the current and event time conversions **/
    public function calculateTimerDetails($event, $user){

        $startTime = Carbon::parse(date("Y-m-d H:i:s", $event->start_time))->timezone('UTC')->toDateTimeString();

        $getEventUserTimeZone = $this->getEventUserTimeZone($user->id);

        $eventStartTimeForUser = $this->getUserTimeZone($user->id, $startTime, $event->event_manager);

        $userCurrentTime = $this->getCurrentTimeofTimeZone($getEventUserTimeZone);

        $pendingTime = $this->getTimeDifference(strtotime($userCurrentTime), strtotime($eventStartTimeForUser));
    }

    /** Event details API **/
    public function eventDetails($encryptedId=NULL, REQUEST $request){
        /*Default variables*/
        $return = [];

        $userCash = $team_cash = $hasTeamProductionDone = $hasPlayerProductionDone  =  $pendingMinut = $pendingSeconds = 0;

        $status = 1;

        $event = $user = $userteam = null;

        $code = 500;

        $return = $this->validateEncKey($encryptedId);

        if($return){

            $eventId = $return['event']->id;
            $teamId = $return['userteam']->team_id;
            $userId = $return['user']->id;

            //check end time for this event
            if( $this->checkMeetingTokenValidOrNot($encryptedId) == SELF::$EVENT_RUNNING ) {

                $gameSettings = $this->getGameSettings();
                //check if eligible number of users are active with this team
                $timerDetails = $this->calculateTimerDetails($return['event'], $return['user']);

                $return['team'] = Team::find( $teamId );
                
                $return['jwttoken'] = $this->createJWTtoken($return['user'], $eventId, $teamId);

                $return['bit'] = SELF::$EVENT_DETAILS_CALL_REQUEST;

                /* check round/channel data */
                //default round and channel
                $round = 1;  $chance = 1;
                $chanceTimeMinuts = 00;
                $chanceTimeSeconds = $gameSettings->chance_time;
                $foreignProduction = 0; $weather = 0; $economy = 0;
                $team_cash = $gameSettings->team_cash;

                //check tutorial seen
                $where = array(
                            'event_id' => $eventId,
                            'user_id' => $userId,
                            'team_id' => $teamId
                        );

                $hasSeenTutorial = EventJoin::where($where)->first();
                //if not seen update it seen by this user.
                if( $hasSeenTutorial ){
                    if($hasSeenTutorial->tutorials_seen != 1){
                        EventJoin::where($where)->update(['tutorials_seen' => 1]);
                    }
                }

                $roundData =MmRounds::getLatestRound($return['event']->id);

                $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_TO_START_GAME);
                // print_r($activeTeams); die;
                if(count((array)$roundData)){
                    //check latest round for the MM and respond with time
                    if(!in_array($teamId, array_column( $activeTeams->toArray(), 'team_id') ) ){
                        $return['bit'] = SELF::$WAITING_FOR_TEAM_MEMBERS;
                        return $this->setResponse(201, 'Waiting for your team members to join the event', $return);
                    }   

                    $roundData =(array)$roundData;

                    $chanceData = Chances::getLatestChance($roundData['id']);
                    
                    $roundData['chance'] = $chanceData->chance;
                    
                    $roundData['chance_id'] = $chanceData->id;

                    $roundData['weather'] = $chanceData->weather;

                    $currentTime = $this->currentTime;
                                    
                    $pendingTime = $this->getTimeDifference($chanceData->end_time, $currentTime);

                    $chanceTimeSeconds = $pendingTime['secondsonly'];

                    if($chanceTimeSeconds < 0){
                        //Time Exausted for this round/chance
                        $chanceTimeSeconds = 0;
                    }

                    //team total cash
                    $team_cash_distribution = PlayerCash::where(['team_id' => $teamId, 'event_id' => $eventId])->get();
                    if(!count($team_cash_distribution)){

                        $userCash = $this->creditTeamCashToPlayers ($eventId, $teamId, $gameSettings->team_cash);
                    }

                    //team total cash
                    $team_cash = PlayerCash::getTeamCash ($eventId, $teamId);
                    
                    if($team_cash)
                    //fetch usercash
                    $userCash = PlayerCash::getPlayerWalletCash($userId, $eventId, $teamId);
                    
                    //check Previous Production Made bit
                    $hasDoneProductionDetails = Production::where(["chance_id"=>$roundData['chance_id'], "user_id" => $userId])->get();

                    $hasTeamProductionDone = Production::where(["chance_id"=>$roundData['chance_id'], "team_id" => $teamId])->count();

                    $hasPlayerProductionDone = Production::where(["chance_id"=>$roundData['chance_id'], "user_id" => $userId, "team_id" => $teamId])->count();

                    $status = $roundData['status'];

                }else{
                    
                    if($activeTeams->count() >= SELF::$MIN_TEAMS_FOR_GAME){

                        $data = array(); $data['bit'] = SELF::$EVENT_DETAILS_CALL_REQUEST;
                        $this->publishData($data, $eventId, $teamId, 1);

                        if(!in_array($teamId, array_column( $activeTeams->toArray(), 'team_id') ) ){
                            $return['bit'] = SELF::$WAITING_FOR_TEAM_MEMBERS;
                            return $this->setResponse(201, 'Waiting for your team members to join the event', $return);
                        }                            
                        //create record for fresh round
                        $roundDataId = $this->startNewRound ($eventId);
                        $chanceData = Chances::where(['round_id' => $roundDataId->id])->orderBy('chance', 'desc')->get()->first();
                        //fetch round chance data
                        $roundData = MmRounds::find($roundDataId->id)->first()->toArray();
                        $roundData['chance'] = $chanceData->chance;
                        $roundData['weather'] = $chanceData->weather;

                        $userCash = PlayerCash::getPlayerWalletCash ($userId, $eventId, $teamId);

                    }else{

                        $return['bit'] = SELF::$WAITING_FOR_TEAM_MEMBERS;
                        return $this->setResponse(201, 'Minimum 2 teams and 2 player in each team needs to be activce to start Market Madness game.', $return);
                    }
                }

                $crop = Crops::where(['round' => $roundData['round'] ])->first();
                /* response data management below */

                $return['crop'] = $crop;
                //static keys
                $return['round'] = [
                    'round_no' => $roundData['round'],
                    'chance' =>$roundData['chance'],
                    'market_cost' => $roundData['market_cost'],
                    'demand' => $roundData['demand'],
                    'max_profit_limit' => $roundData['max_profit_limit'],
                    'max_loss_limit' => $roundData['max_loss_limit'],
                    'hasFirstProductionMade' => $hasTeamProductionDone,
                    'hasPlayerProductionDone' => $hasPlayerProductionDone,
                    //forcasting impact
                    'weather' => $roundData['weather'],
                    'economy' => $roundData['economy'],
                    'foreign_producer' => $roundData['foreign_production'],
                    'max_min_impact' => $this->gameSettings->max_loss_profit_limit
                ];

                $return['code'] = 200;

                $return['team_cash'] = $team_cash;

                $return['userCash'] = $userCash;

                $return['game_min'] = $chanceTimeMinuts;

                $return['game_sec'] = $chanceTimeSeconds;

                $return['screen_status'] = $status;

                $return['total_rounds'] = $gameSettings->total_rounds;

                $return['total_chances'] = $gameSettings->chance_in_round;
                
                $return['forecasting_charge'] = $gameSettings->forecasting_charge;

                $return['team_count'] = $activeTeams->count();

                $return['weather_taken'] = Forecasting::checkForecastingTaken($roundData['id'], $teamId, 1, $chanceData->id);
                $return['economy_taken'] = Forecasting::checkForecastingTaken($roundData['id'], $teamId, 2);
                $return['foreign_taken'] = Forecasting::checkForecastingTaken($roundData['id'], $teamId, 3);

                $activeMembers = $this->activeTeamMembersCount($eventId, $teamId)->count();

                $return['extra_cash'] = 0;

                if( $activeMembers && $roundData['round'] != 1 ){
                    //Extra cash for each user on the start of each round.
                    $return['extra_cash'] = round($this->gameSettings->round_team_cash / $activeMembers, 2 );
                }

            }else{
                return $this->setResponse(201, 'Event time exausted.', []);
            }
        }

        return $this->setResponse(200, 'success', $return);
    }

    public function startNewRound($eventId){

        //some initial defaults for round start
        $weather = $economy = $foreignProduction = 0;
        //fetch rounds records against this event
        $lastrounds = MmRounds::where(['event_id' => $eventId])->orderBy('round', 'desc')->get();
        //get current UTC time
        $currentTime = $this->currentTime;        
        //check existing rounds for this event
        if( $lastrounds->count() ){
            //start next round this event
            $lastrounds = $lastrounds->first();

            if($currentTime < $lastrounds->end_time && $lastrounds->round >= $this->gameSettings->total_rounds)
                return $lastrounds->id;
            //add next chance to this event
            $round = $lastrounds->round + 1;
        }else{
            //create first round for this event
            $round = 1;
        }
        //calculate round end time
        $end_time = $currentTime + $this->gameSettings->chance_time + $this->gameSettings->chance_result_time + $this->gameSettings->round_results_time;

        $max_profit_limit = $this->gameSettings->max_loss_profit_limit;
        $max_loss_limit = $this->gameSettings->max_loss_profit_limit;

        //weather impact will be here..
        // $weather = $this->random[array_rand($this->random,1)];
        //economy impact will be here..
        $economy = $this->random[array_rand($this->random,1)];

        if($round > 2){
            
            $max_profit_limit = $max_loss_limit = 100;

            if($round > 5){
                //foregin producer impact will be here..
                $FPArray = explode(',', $this->gameSettings->foreign_production_amount);
                $foreignProduction = $FPArray[array_rand($FPArray)];
            }
        }
        //fetch round wise crop
        $crop = Crops::where(['round' => $round])->first();
        //if crop not found against the round
        if(!$crop){
            //fetch an random crop for this round
            $crop = Crops::all()->random();
        }
        
        //create record for fresh round
        $roundData = array(
            "event_id" => $eventId,
            "round" => $round,
            "start_time" => $currentTime,
            "end_time" => $end_time,
            "crop_id" => $crop->id,
            "weather" => $weather,
            "economy" => $economy,
            "foreign_production" => $foreignProduction,
            "market_cost" => $this->gameSettings->market_cost,
            "demand" => $this->gameSettings->market_demond,
            'max_profit_limit' => $max_profit_limit,
            'max_loss_limit' => $max_loss_limit,
        );

        $roundId = MmRounds::create($roundData);
        //create chance for this round
        $this->createChance($roundId->id);
        //credit money to activated members here..
        $this->roundMoneyToTeams($eventId);
        //return new created round Id
        return $roundId;
    }

    public function createChance ($roundId){
        //server current time UTC
        $currentTime = $this->currentTime;
        //get game setting setted by ADMIN
        $gameSettings = $this->getGameSettings();
        //get chances data if any against this round
        $chances = Chances::where(['round_id' => $roundId])->orderBy('chance', 'desc')->get();
        //calculate end time for this event.
        $end_time = $currentTime + $gameSettings->chance_time;
        //random weather
        $weather = $this->random[array_rand($this->random,1)];
        // $weather = -1;
        if( $chances->count() ){
            $chances = $chances->first();

            if( $currentTime < $chances->end_time && $chances->chance >= $gameSettings->chance_in_round )
                return $chances->id;

            $chance = $chances->chance + 1;

        }else{
            $chance = 1;
        }

        //chance detailed array
        $chanceDetails = 
                array(
                   'round_id' => $roundId,
                   'chance' => $chance,
                   'weather' => $weather,
                   'start_time' => $currentTime,
                   'end_time' => $end_time,
                   'status' => 1
                );

        return Chances::create($chanceDetails);
    }

    public function roundMoneyToTeams($eventId){

        $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_TO_START_GAME);
        //get game setting setted by ADMIN
        $gameSettings = $this->getGameSettings();

        foreach ($activeTeams as $teams) {
            //credit initial cash to teams            
            $team_cash = PlayerCash::getTeamCash ($eventId, $teams->team_id);

            if($team_cash){
                $creditMoney = $team_cash + $gameSettings->round_team_cash;
            }else{
                $creditMoney = $gameSettings->team_cash;
            }

            $userCash = $this->creditTeamCashToPlayers ($eventId, $teams->team_id, $creditMoney);
        }
    }

    public function creditTeamCashToPlayers ($eventId, $teamId, $teamCash){
        //get all active member of this team and event
        $userCash = 0;

        $activeMembers = $this->activeTeamMembersCount($eventId, $teamId);

        if(count($activeMembers) >= SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME){
            //Divide team cash eqully to all team members.
            $userCash = round($teamCash / count($activeMembers), 2 );
            //delete cash/wallet money for this team
            PlayerCash::where(['team_id' => $teamId, 'event_id' => $eventId])->delete();
            //credit cash tp player
            foreach ($activeMembers as $value) {
                //credit cash to player
                $this->creditPlayerCash ($value['user_id'], $eventId, $teamId, $userCash);
            }
        }
        return $userCash;
    }

    //credit money to player wallet
    public function creditPlayerCash ($userId, $eventId, $teamId, $cash){
        //code to credit cash to user wallet
        $playerCash = PlayerCash::where(['user_id' => $userId, 'event_id' => $eventId])->first();

        if( $playerCash ){
            
            $playerCash->cash = $cash;
            $playerCash->save();

        }else{
            
            $insert = array(
                "event_id" => $eventId,
                "user_id" => $userId,
                "team_id" => $teamId,
                "cash" => $cash
            );

            $production = PlayerCash::create($insert);
        }

        return;
    }    


//new code above only...

    public function userProduction (REQUEST $request){

        $response = [];
        
        $currentTime = $this->currentTime;

        $token= str_replace('Bearer ', "" , $request->header('token'));
        /*validate JWT token*/
        $data = $this->JWTAuthentication($token);
        if($data['code'] != 200 ){
            return $this->setResponse($data['code'], $data['message'], []);
        }

        $user = $data['data']['user'];
        $eventId = $data['data']['eventId'];
        $teamId = $data['data']['teamId'];

        $user = User::find($user->id);
        
        $response['jwtToken'] = $this->createJWTtoken($user, $eventId, $teamId);

        /*validate body parameters*/
        $validator      =   \Validator::make($request->all(), [
            'totalAmountSpentByPlayer' => 'required|numeric|min:1',
            'expectedproduction' => 'required|numeric|min:1',
        ]);
        /*return if required paramteres are missing*/
        if($validator->fails()){
            $statusCode = 400;
            return $this->setResponse($statusCode,'',$validator->errors()->all(),[]);
        }

        $roundData =MmRounds::getLatestRound($eventId);

        if(count((array)$roundData)){

            $roundData =(array)$roundData;
            //fetch latest running chance for this round
            $chanceData = Chances::getLatestChance($roundData['id']);
            //this check needs some work for chance end time comparison
            if($chanceData->status != Chances::CHANCE_ACTIVE || $chanceData->end_time < $this->currentTime)
                return $this->setResponse(404,'Production time over!');
            
            
            //check Previous Production Made bit
            $hasDoneProductionDetails = Production::where(["chance_id"=>$chanceData->id, "user_id" => $user->id])->get();

            if( count($hasDoneProductionDetails) ){

                return $this->setResponse(404,'Already Produced for this chance');
            }else{
                //fetch usercash
                $userCash = PlayerCash::getPlayerWalletCash($user->id, $eventId, $teamId);
                if($userCash >= $request->totalAmountSpentByPlayer){
                    //deduct cash from player wallet
                    $playerBalance = round( ($userCash - $request->totalAmountSpentByPlayer), 2);

                    $this->creditPlayerCash($user->id, $eventId, $teamId, $playerBalance);
                    $response['playerCash'] = $playerBalance;
                    $response['hasPlayerProductionDone'] = 1;
                    //produce crop for player
                    $production = $this->cropProduction($user->id, $chanceData->id, $teamId, $request->totalAmountSpentByPlayer, $request->expectedproduction);
                    //team cash
                    $team_cash = PlayerCash::getTeamCash ($eventId, $teamId);

                    $hasTeamProductionDone = Production::where(["chance_id"=>$chanceData->id, "team_id" => $teamId])->count();

                    $publishData = array(
                        'teamCash' => $team_cash,
                        'bit' => SELF::$PUBLISH_PRODUCTION_DATA,
                        'round_no' => $roundData['round'],
                        'chance' =>$chanceData->id,
                        'playerName' => $user->name,
                        'hasTeamProductionDone' => $hasTeamProductionDone,
                        'screen_status' => $roundData['status']
                    );
                    //publicsh data
                    $this->publishData ($publishData, $eventId, $teamId);

                }else{

                    return $this->setResponse(404,'insufficient balance');
                }
            }
            
        }else{
            //round not yet initialized for this event.
            return $this->setResponse(404,'Round not yet initialized for this event');
        }

        return $this->setResponse(200,'success',$response);
    }

    //player productions against crop
    public function cropProduction ($userId, $chanceId, $teamId, $totalAmountSpentByPlayer, $expectedproduction=0){
        // echo $mmRoundId; die;
        $chanceData = Chances::where(["id" => $chanceId, "status" => Chances::CHANCE_ACTIVE ])->first();

        if( ($chanceData) ){
            $chanceData = $chanceData->toArray();

            $roundData = MmRounds::find($chanceData['round_id'])->toArray();

            $cropDetails = Crops::find($roundData['crop_id'])->toArray();

            if( count($cropDetails) ){

                $LBS = round(($totalAmountSpentByPlayer / $cropDetails['cost']) , 2);

                $weatherPercentage = round( $chanceData['weather'] * $this->gameSettings->max_loss_profit_limit , 2 );

                $impactedLBS = round( ( $LBS * $weatherPercentage / 100 ), 2);

                $LBS = $LBS + $impactedLBS;

                $insert = array(
                    "chance_id" => $chanceId,
                    "user_id" => $userId,
                    "team_id" => $teamId,
                    "amount" => $totalAmountSpentByPlayer,
                    "expectedproduction" => $expectedproduction,
                    "production" => $LBS,
                );

                $production = Production::create($insert);

                return array('code' => 200, "data" => $production->id);

            }else{
                //invalid crop details
                return array('code' => 404, 'message' => 'invalid crop details');
            }
        }else{
            //chance time exausted
            return array('code' => 404, 'message' => 'Chance Time Exausted');
        }
    }
    
    /* Calcuate complere round result for the teams and select the winner and looser for the round*/
    public function calculateRoundResult ( $roundId , $creditTeamCash = 1){
        //Get actual production and calculate profit and loss here.
        $roundData = MmRounds::find( $roundId );
        $eventId = $roundData->event_id;
        $round = $roundData->round;
        //fetch latest chance for corosponding round
        $chanceData = Chances::getLatestChance($roundId);
        /*Total current Production against an Round*/
        $teamTotalProduction = Production::getRoundProduction ($roundId);
        // print_r($teamTotalProduction); die;
        $totalProduction = array_sum(array_map(function($teamTotalProduction) {
                    return $teamTotalProduction->production; 
                }, $teamTotalProduction));
        /*Foreign forecasting impact calculations and add if exists*/
        $totalProduction = $totalProduction + $roundData->foreign_production;
        //get impacted percentage of economy for the round if any
        $marketCostPercaentage = round($roundData->economy * $this->gameSettings->max_loss_profit_limit, 2 );
        /*Economy forecasting impact calculations*/
        $marketCost = round( $marketCostPercaentage * $roundData->demand / 100 , 2);
        //add/subtract calculated amount of cost 
        $marketCost = round( $roundData->market_cost + $marketCost, 2);
        //calculate sale price after all forecasting impacts
        if( $totalProduction ){

            $salePrice = round( $marketCost / $totalProduction , 2);
        }else{
            $salePrice = round( $roundData->market_cost / $roundData->demand, 2);
        }

        $results = [];

        foreach ( $teamTotalProduction as $key => $productions ) {
            $results[$key]['round']   = $round;
            $results[$key]['event_id']   = $eventId;
            $results[$key]['team_id'] = $productions->team_id;
            $productionReturn = round( $productions->production * $salePrice, 2);
            $results[$key]['production'] = $productions->production;
            $results[$key]['investment'] = $productions->amount;
            $percentage = round($productionReturn * 100 / $productions->amount, 2);

            $profitLossBit = 0; //For bit 1 its Profit and for 2 its loss and 0 for neutral

            if( $percentage < 100){ $profitLossBit = 2; }
                elseif ($percentage > 100) {$profitLossBit = 1;}
            //for first two rounds max profit/loss limit percentage is limited
            if( $roundData->round <= 2 ){
                if( $percentage <= (100 + $roundData->max_profit_limit) && $percentage >= (100 - $roundData->max_loss_limit) ) {
                    $percentage = $percentage;                
                }else if($percentage > (100 + $roundData->max_profit_limit) ){
                    $percentage = $roundData->max_profit_limit + 100;
                }else{
                    $percentage = (100 - $roundData->max_loss_limit);
                }

                $productionReturn = round( $percentage * $productions->amount / 100 ,2);

            }

            $results[$key]['return'] = $productionReturn;

            if($productionReturn >= $productions->amount){
                $profitLossCash = round( $productionReturn - $productions->amount , 2 );
            }else{
                $profitLossCash = round( $productions->amount - $productionReturn , 2 );
            }  

            $results[$key]['profitLossCash']    =   $profitLossCash;
            $results[$key]['profitLossBit']    =    $profitLossBit;

            $teamCash = PlayerCash::getTeamCash ($eventId, $productions->team_id);
            /*total assets after credit profit/loss amount*/
            $results[$key]['total_asset'] = $total_asset = $teamCash + $productionReturn;

            if( $creditTeamCash == 1){
                /*credit cash after round result to team/player account*/
                $this->creditTeamCashToPlayers ($eventId, $productions->team_id, $total_asset);
            }
            /*get team name*/
            $results[$key]['team'] = Team::find($productions->team_id);
            //forecasting taken or not bits
            $results[$key]['weather_taken'] = Forecasting::checkForecastingTaken($roundData->id, $productions->team_id, 1);
            $results[$key]['economy_taken'] = Forecasting::checkForecastingTaken($roundData->id, $productions->team_id, 2);
            $results[$key]['foreign_taken'] = Forecasting::checkForecastingTaken($roundData->id, $productions->team_id, 3);
            $results[$key]['salePrice'] = $salePrice;
            $results[$key]['totalProduction'] = $totalProduction;
        }

        // Team id`s who are actively joined this event.
        $activeTeamsOnly = EventJoin::getTeamsWhoJoinedEvents($eventId);

        foreach ($activeTeamsOnly as $teamsOnly) {
            if( !in_array($teamsOnly, array_column($results, 'team_id') ) ) {
                $array = array(
                    'round'   => $round,
                    'event_id'   => $eventId,
                    'team_id'               =>  $teamsOnly,
                    'production'            =>  0,
                    'investment'            =>  0,
                    'profitLossCash'        =>  0,
                    'profitLossBit'         =>  0,
                    'return'                =>  0,
                    'total_asset'           =>  PlayerCash::getTeamCash ($eventId, $teamsOnly),
                    'team'                  =>  Team::find($teamsOnly),
                    'weather_taken'         =>  0,
                    'economy_taken'         =>  0,
                    'foreign_taken'         =>  0,
                    'salePrice'             => $salePrice
                );
                $results[] = $array;
            }
        }

        if( $creditTeamCash == 1){
            //save total assets
            $this->saveTotalAssests ($results);
        }

        //fetch total assets
        $results = $this->fetchTotalAssests($results);

        $keys = array_column($results, 'total_asset');

        array_multisort($keys, SORT_DESC, $results);
        //team ordering for round result
        $i = 1; $temp = 0; $half_ranker = round( count( $activeTeamsOnly ) / 2 );

        foreach ( $results as $key => $result ) {

            if( $i <= $half_ranker ) {
                $results[$key]['emoji'] =   $this->top_emojies[array_rand($this->top_emojies,1)];
                $results[$key]['string']=   $this->top_string[array_rand($this->top_string,1)];
            }else{
                $results[$key]['emoji'] =   $this->lower_emojis[array_rand($this->lower_emojis,1)];
                $results[$key]['string']=   $this->lower_string[array_rand($this->lower_string,1)];
            }

            if( $i == 1 ){
                $results[$key]['rank']  =   $i;
                // $results[$key]['emoji'] =   $this->top_emojies[array_rand($this->top_emojies,1)];
                $results[$key]['string']=   $this->top_string[$i];
                $i++;
            }else{
                if( $result['total_asset'] == $temp ){

                    $results[$key]['rank'] = $i - 1;
                    // if( ($i - 1) == 1){
                    //     $results[$key]['emoji'] =   $this->top_emojies[array_rand($this->top_emojies,1)];
                    //     $results[$key]['string']=   $this->top_string[$i - 1];
                    // }                    

                }else{
                    $results[$key]['rank'] = $i;
                    $i++;
                }
            }

            // if($i == count( $activeTeamsOnly ) ){
            //     $results[$key]['emoji'] =   $this->lower_emojis[array_rand($this->lower_emojis,1)];
            //     $results[$key]['string']=   $this->lower_string[3];
            // }

            $temp = $result['total_asset'];            
        }

        return $results;
    }

    public function createJWTtoken ($userData, $eventId, $teamId){

        $userData->setMMAttribute( [ 'eventId' => $eventId, 'teamId' => $teamId ] );

        return JWTAuth::fromUser($userData);
    }

    public function JWTAuthentication($token){

        $return = [
            "code" => 200,
            "message" => "authenticated",
            "data" => []
        ];

        try { 

            JWTAuth::setToken($token); //<-- set token and check

            if (! $claim = JWTAuth::getPayload()) {
                $return['code'] = 404; $return['message'] = 'user_not_found';
                return $return;
            }

            $return['data'] = $claim;

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            $return['code'] = 404; $return['message'] = 'token_expired';
            return $return;

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            $return['code'] = 404; $return['message'] = 'token_invalid';
            return $return;

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            $return['code'] = 404; $return['message'] = 'token_absent';
            return $return;
        }

        $user = JWTAuth::toUser($token);
        $data['user'] = $user;
        $data['userId'] = $user->id;
        $data['name'] = $user->name;
        $data['eventId'] = $claim->get('eventId');
        $data['teamId'] = $claim->get('teamId');
        

        $return['data'] = $data;
        // the token is valid and we have exposed the contents
        return $return;
    }


    public function updateChance ($chanceId, $status, $startTime, $endTime = 0){

        $chanceData = Chances::find($chanceId);
        if($chanceData){
            $chanceData->status = $status;
            $chanceData->start_time = $startTime;
            if($endTime !=0){
                $chanceData->end_time = $endTime;
            }
            $chanceData->save();
        }
    }

    public function updateMMRound ($mmRoundId, $status, $startTime, $endTime=0){

        $mmRoundData = MmRounds::find($mmRoundId);
        if($mmRoundData){
            $mmRoundData->status = $status;
            $mmRoundData->start_time = $startTime;

            if($endTime != 0){
                $mmRoundData->end_time = $endTime;
            }

            $mmRoundData->save();
        }
    }
    
    private function getResponsedata( $roundId ){

        $gameSettings       = $this->getGameSettings();
        $currentTime        = $this->currentTime;
        $roundData          = MmRounds::find( $roundId )->toArray();
        $chanceData         = Chances::getLatestChance($roundData[ 'id' ]);
        $chance             = $chanceData->chance;
        
        $pendingTime        = $this->getTimeDifference( $currentTime, $chanceData->start_time );
        $chanceTimeSeconds  = $gameSettings->chance_time - $pendingTime[ 'secondsonly' ];
        if( $chanceTimeSeconds < 0 ) { 
            //Time Exausted for this round/chance
            $chanceTimeSeconds = 0;
        }

        $return = [
                'crop'  => Crops::find( $roundData[ 'crop_id' ] ),
                'round' => [
                        'round_no'                => $roundData[ 'round' ],
                        'chance'                  => $chance,
                        'market_cost'             => $roundData[ 'market_cost' ],
                        'demand'                  => $roundData[ 'demand' ],
                        'max_profit_limit'        => $roundData[ 'max_profit_limit' ],
                        'max_loss_limit'          => $roundData[ 'max_loss_limit' ],
                        'hasFirstProductionMade'  => 0,
                        'hasPlayerProductionDone' => 0,
                        //forcasting impact
                        'weather'                 => $chanceData->weather,
                        'economy'                 => $roundData[ 'economy' ],
                        'foreign_producer'        => $roundData[ 'foreign_production' ],
                        'max_min_impact' => $this->gameSettings->max_loss_profit_limit
                    ],
                'user'          => [],
                'code'          => 200,
                'team_cash'     => -1,
                'userCash'      => -1,
                'jwttoken'      => 0,
                'game_min'      => 0,
                'game_sec'      => $chanceTimeSeconds,
                'screen_status' => $roundData[ 'status' ],
                'total_rounds'  => $gameSettings->total_rounds,
                'total_chances' => $gameSettings->chance_in_round,
                'team_count'  => EventJoin::getActiveMMTeamsWithMembers($roundData['event_id'],SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME)->count()
            ];

        return $return;
    }

    //Complete data for a chance result
    public function calculateChanceResult ( $mmRoundId, $teamId , $chance=0) {

        $response   =   [];     $chanceTimeMinuts   =   0;

        $roundData  =   MmRounds::find($mmRoundId)->toArray();
        //get chance data against latest round.
        if($chance == 0){
            $chanceData = Chances::getLatestChance($roundData['id']);
        }else{
            $chanceData = Chances::where(['round_id' => $mmRoundId, 'chance' => $chance])->get()->first();
        }

        $where = [
                    'chance_id' =>  $chanceData->id, 
                    'team_id'   =>  $teamId
                ];

        $production = Production::where($where)->get()->toArray();
        
        $leftUsersObj = EventJoin::getLeftJoinUsersWithourProduction( $mmRoundId, $chanceData->id, $teamId );
        $leftUsers = [];
        if( $leftUsersObj->count( ) > 0 ) {
            $leftUsers = $leftUsersObj->pluck( 'user_id' )->toArray(); 
            foreach( $leftUsers as $leftUser ) { 
                $userDetail = User::find( $leftUser );
                //fetch forecasting taken by this user
                $forecasted = Forecasting::where([
                                    'round_id'  => $chanceData->round_id,
                                    'user_id'   => $leftUser,
                                    'team_id'   => $teamId,
                                    'chance_id' => $chanceData->id
                                ])->get();
                // print_r($forecasted); die;
                
                $fTaken = '';

                if( $forecasted->count() ){
                    $forcasting_taken = [];
                    foreach ($forecasted as $forecasting) {
                        $forcasting_taken[] = $this->forcastingTypes[$forecasting->type];
                    }

                    $fTaken = implode(',', $forcasting_taken);
                }
                
                $production[] = [ 
                                "id"                => 0,
                                "user_id"           => $leftUser,
                                "team_id"           => $teamId,
                                "chance_id"         => $chanceData->id,
                                "amount"            => 0.00,
                                "production"        => 0.00,
                                "expectedproduction"=> 0.00,
                                "created_at"        => '',
                                "updated_at"        => '',
                                "name"              => $userDetail->name,
                                "avatar"            => $userDetail->avatar,
                                "f_taken"           => $fTaken
                            ];
            }
        }
 
        if($production){
            foreach ($production as $key => $record) {
                if( $leftUsers && in_array( $record['user_id'], $leftUsers ) ) continue;

                $userDetail = User::find($record['user_id']);
                $production[$key]['name'] = $userDetail->name;
                $production[$key]['avatar'] = $userDetail->avatar;

                $forecasted = Forecasting::where([
                                    'round_id'  => $chanceData->round_id,
                                    'user_id'   => $record['user_id'],
                                    'team_id'   => $teamId,
                                    'chance_id' => $chanceData->id
                                ])->get();
                
                $fTaken = NULL;

                if( $forecasted->count() ){
                    $forcasting_taken = [];
                    foreach ($forecasted as $forecasting) {
                        $forcasting_taken[] = $this->forcastingTypes[$forecasting->type];
                    }

                    $forcasting_taken = array_unique($forcasting_taken);
                    
                    $fTaken = implode(' ,', $forcasting_taken);
                }

                $production[$key]['f_taken'] = $fTaken;
            }            

            $response['production'] = $production;

            $response['toalInvestMent'] = array_sum(array_map(function($production) { 
                    return $production['amount']; 
                }, $production));

            $response['totalProduction'] = array_sum(array_map(function($production) { 
                    return $production['production']; 
                }, $production));

            $response['expectedproduction'] = array_sum(array_map(function($production) { 
                    return $production['expectedproduction']; 
                }, $production));

            //team cash
            $response['team_cash'] = PlayerCash::getTeamCash ($roundData['event_id'], $teamId);
        }

        $response['crop'] = Crops::find($roundData['crop_id']);
        $response['weather'] = $chanceData->weather;
        $response['round'] = $roundData['round'];
        $response['chance'] = $chanceData->chance;
        $response['screen_status'] = $roundData['status'];
        $response['team_name'] = Team::find($teamId)->name;
        //single chance result calculated
        return $response;
    }
//updated code above



    /**  AUTO PUBLISH DATA CALLS STARTS HERE..  **/

    //publish latest chance result to all members of this event
    private function publishChanceResult ($eventId){

        $roundData =MmRounds::getLatestRound($eventId);
        //get active teams first
        $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);
        //publish data for all active teams
        foreach ($activeTeams as $teams) {
            $teamId = $teams->team_id;
            //create chanel
            $channelId = "mm_event_".$eventId.$teamId;

            if( count((array)$roundData) ){

                $roundData =(array)$roundData;

                $chanceData = chances::getLatestChance($roundData['id']);

                $response = $this->calculateChanceResult($roundData['id'], $teamId);

                $gameSettings = $this->getGameSettings();
                $chanceTimeSeconds = $gameSettings->chance_result_time;

                $currentTime = $this->currentTime;

                $endTime = $currentTime + $gameSettings->chance_result_time;

                $this->updateChance($chanceData->id, 2, $currentTime, $endTime);
                
                $response['screen_status'] = 2;

                $response['game_min'] = 0;

                $response['game_sec'] = $chanceTimeSeconds;

                $response['bit'] = SELF::$PUBLISH_CHANCE_RESULT;

                $chanceData = Chances::getLatestChance($roundData['id']);
                
                $response['weather_taken'] = Forecasting::checkForecastingTaken($roundData['id'], $teamId, 1, $chanceData->id);
                $response['economy_taken'] = Forecasting::checkForecastingTaken($roundData['id'], $teamId, 2);
                $response['foreign_taken'] = Forecasting::checkForecastingTaken($roundData['id'], $teamId, 3);

                $this->publishData ($response, $eventId, $teamId);
            }
        }
        
    }
    

    private function publishRoundResult ($eventId){

        $roundData =MmRounds::getLatestRound($eventId);

        if(count((array)$roundData) ){

            $roundData =(array)$roundData;

            $chancesData = Chances::where(['round_id' => $roundData['id']])
                            ->orderBy( 'chance', 'DESC' )
                            ->first();

            $weather_percentage = round($chancesData->weather * $this->gameSettings->max_loss_profit_limit, 2 );

            $marketCostPercaentage = round($roundData['economy'] * $this->gameSettings->max_loss_profit_limit, 2 );

            $return['production'] = $this->calculateRoundResult($roundData['id']);

            $gameSettings = $this->getGameSettings();

            // $chanceTimeSeconds = $gameSettings->chance_result_time;
            $chanceTimeSeconds = $gameSettings->round_results_time;

            $currentTime = $this->currentTime;

            $endTime = $currentTime + $gameSettings->round_results_time;

            Chances::updateAllChanceStatus($roundData['id'],3);

            $this->updateMMRound ($roundData['id'], 2, $currentTime, $endTime);

            $return['game_min'] = 0;

            $return['game_sec'] = $chanceTimeSeconds;

            $return['bit'] = SELF::$PUBLISH_ROUND_RESULT;

            $return['round'] = $roundData['round'];
            $return['crop'] = Crops::find($roundData['crop_id']);

            $return['weather_percentage']    =   $weather_percentage;
            $return['economy_percentage']    =   $marketCostPercaentage;
            $return['foreign_percentage']    =   $roundData['foreign_production'];

            $return['demand'] = $roundData['demand'];
            //code to correct result economy starts here
            /*Economy forecasting impact calculations*/
            
            $marketCost = round( $marketCostPercaentage * $roundData['demand'] / 100 , 2);
            //add/subtract calculated amount of cost 
            $marketCost = round( $roundData['market_cost'] + $marketCost, 2);

            $return['market_cost'] = $marketCost;
            //code to correct result economy ends here
            // $return['market_cost'] = $roundData['market_cost'];

            $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);

            $chancesData = Chances::where(['round_id' => $roundData['id']])->get();

            //publish data for all active teams
            if($activeTeams->count()){
                foreach ($activeTeams as $teams) {
                    $channelId = "mm_event_".$eventId.$teams->team_id;
                    $chance = [];
                    if($chancesData->count()){
                        foreach ($chancesData as $chancesRecord) {
                            $chance[$chancesRecord->chance] = $this->calculateChanceResult ( $chancesRecord->round_id, $teams->team_id, $chancesRecord->chance );
                        }
                    }                    
                    $return['chance'] = $chance;

                    $this->publishData ($return, $eventId, $teams->team_id);
                } 
            }
        }
    }

    /*Start new chance and publish this to all active teams*/
    private function publishNextChance ($roundId, $eventId){

        Chances::updateAllChanceStatus($roundId, 3);
        
        $chanceId = $this->createChance ($roundId);

        $return = $this->getResponsedata( $roundId);

        $return['bit'] = SELF::$PUBLISH_NEXT_CHANCE_DATA;

        $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);

        foreach ($activeTeams as $teams) {

            $channelId = "mm_event_".$eventId.$teams->team_id;            
            
            $return['team_cash'] = PlayerCash::getTeamCash ($eventId, $teams->team_id);

            $return['weather_taken'] = Forecasting::checkForecastingTaken($roundId, $teams->team_id, 1, $chanceId->id);
            $return['economy_taken'] = Forecasting::checkForecastingTaken($roundId, $teams->team_id, 2);
            $return['foreign_taken'] = Forecasting::checkForecastingTaken($roundId, $teams->team_id, 3);

            $this->publishData ($return, $eventId, $teams->team_id);
        }

    }
    /*Start a new round*/
    private function publishNewRound ($roundId, $eventId){

        Chances::updateAllChanceStatus($roundId, 3);

        MmRounds::updateAllRoundsStatus($eventId, 3);

        $roundId = $this->startNewRound($eventId);

        $return = $this->getResponsedata($roundId->id);

        $return['bit'] = SELF::$PUBLISH_NEXT_ROUND_DATA;

        $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);

        foreach ($activeTeams as $teams) {

            $channelId = "mm_event_".$eventId.$teams->team_id;            
            
            $return['team_cash'] = PlayerCash::getTeamCash ($eventId, $teams->team_id);

            $return['userCash'] = PlayerCash::where(['event_id' => $eventId, 'team_id' => $teams->team_id])->first()->cash;

            $return['weather_taken'] = Forecasting::checkForecastingTaken($roundId->id, $teams->team_id, 1);
            $return['economy_taken'] = Forecasting::checkForecastingTaken($roundId->id, $teams->team_id, 2);
            $return['foreign_taken'] = Forecasting::checkForecastingTaken($roundId->id, $teams->team_id, 3);

            $activeMembers = $this->activeTeamMembersCount($eventId, $teams->team_id)->count();

            $return['extra_cash'] = 0;

            if($activeMembers){
                //Extra cash for each user on the start of each round.
                $return['extra_cash'] = round($this->gameSettings->round_team_cash / $activeMembers, 2 );
            }

            $this->publishData ($return, $eventId, $teams->team_id);
        }
    }
    /*just share all teams cash for once*/
    private function publishGameResult($eventId){

        MmRounds::updateAllRoundsStatus($eventId, 3);

        $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);

        if($activeTeams->count()){
            $winningTeam = PlayerCash::winningTeam($eventId);

            if( $winningTeam->team_id ){
                $members = EventJoin::getOptions ($eventId, $winningTeam->team_id);
            }
        }

        $return['bit'] = SELF::$PUBLISH_GAME_RESULT;

        $return['team'] = Team::find($winningTeam->team_id);
        
        $return['team_members'] = $members;
        
        /*Complete Event*/
        $eventDetails = Event::find($eventId);
        $eventDetails->status = 2;
        $eventDetails->save();
        /*Complete Event*/

        $return['event_date'] = date('m/d/Y', strtotime($eventDetails->start_date));
        
        $this->publishData ( $return, $eventId, 1, 1 );
    }
    /**  AUTO PUBLISH DATA CALLS ENDS HERE..  **/


    /*Forecasting API*/

    public function getForecastingDetails (REQUEST $request){
        // print_r($this->forcastingTypes); die;
        $token= str_replace('Bearer ', "" , $request->header('token'));
        /*validate JWT token*/
        $data = $this->JWTAuthentication( $token );
        if( $data[ 'code' ] != 200 ) { 
            return $this->setResponse( $data[ 'code' ], $data[ 'message' ], [ ] );
        }

        $user       = $data[ 'data' ][ 'user' ];
        $eventId    = $data[ 'data' ][ 'eventId' ];
        $teamId     = $data[ 'data' ][ 'teamId' ];

        $user       = User::find( $user->id );

        $jwtToken   = $this->createJWTtoken( $user, $eventId, $teamId );

        /*validate body parameters*/
        $validator      =   \Validator::make($request->all(), [
            'type' => 'required|numeric|min:1'
        ]);
        /*return if required paramteres are missing*/
        if($validator->fails()){
            $statusCode = 400;
            return $this->setResponse($statusCode,'',$validator->errors()->all(),[]);
        }
 
        if( !array_key_exists($request->type, $this->forcastingTypes) ) {
            return $this->setResponse( 404, 'Invalid forecasting type');
        }
        //get latest active round for this event
        $roundData =MmRounds::getLatestRound($eventId);

        if(!count((array)$roundData)){
            return array( 'code' => 404, 'message' => 'Invalid Request!!' );
        }

        $roundData =(array)$roundData;

        if( $roundData[ 'status' ] == MmRounds::ROUND_ACTIVE ) {

            $gameSettings = $this->gameSettings;

            $userCash = PlayerCash::getPlayerWalletCash($user->id, $eventId, $teamId);

            $chanceData = chances::getLatestChance($roundData['id']);

            $where = array(
                        'team_id' => $teamId,
                        'type' => $request->type,
                        'round_id' => $chanceData->round_id
                    );

            $forecasting_charge = $gameSettings->forecasting_charge;

            if($request->type == 1){
                $where['chance_id'] = $chanceData->id;

                if( $roundData['round'] < 2 ){
                    return $this->setResponse(402,'For first two round its default forecasted.');
                }
            }elseif ( $request->type == 2 ) {
                if( $roundData['round'] == 1 ){
                    return $this->setResponse(402,'For first round its default forecasted.');
                }
            }elseif ( $request->type == 3 ) {
                if( $roundData['round'] < 6 ){
                    return $this->setResponse(402,'This forecasting only avalavble in last three rounds only.');
                }elseif($roundData['round'] == 6){
                    return $this->setResponse(402,'For 5th round its default forecasted.');   
                }
            }

            $forecasting = Forecasting::where($where)->get();

            if( $forecasting->count() ) 
                return $this->setResponse(402,'Forecasting already done for this type.');

            if( $userCash >= $forecasting_charge ){
                //deduct cash from player wallet
                $playerBalance = round( ($userCash - $forecasting_charge), 2);

                $this->creditPlayerCash($user->id, $eventId, $teamId, $playerBalance);
                
                $publish['hasForcastingDone'] = 1;
                $publish['type'] = $request->type;
                $publish['max_min_impact'] = $this->gameSettings->max_loss_profit_limit;
                
                if($request->type == 1){
                    $publish['f_impact'] = $chanceData->weather;
                }elseif ($request->type == 2) {
                    $publish['f_impact'] = $roundData['economy'];
                }else{
                    $publish['f_impact'] = $roundData['foreign_production'];
                }

                $forcastingDetails = array(
                                        'round_id'  => $chanceData->round_id,
                                        'chance_id'  => $chanceData->id,
                                        'user_id'   => $user->id,
                                        'team_id'   => $teamId,
                                        'type'      => $request->type,
                                        'amount'    => $forecasting_charge,
                                        'impact'    => $publish['f_impact']
                                    );

                Forecasting::create($forcastingDetails);                
                //team cash
                $publish['team_cash'] = PlayerCash::getTeamCash ($eventId, $teamId);
            }else{

                return $this->setResponse(404,'insufficient balance');
            }

            $publish['bit'] = SELF::$PUBLISH_FORECASTING_DATA;

            $this->publishData ($publish, $eventId, $teamId);

            return $this->setResponse( 200,'success', ['userCash' => $playerBalance] );

        }else{

            return $this->setResponse(404,'In active round');
        }
    }
    /*Forecating API ends here*/  


    /**
    * Submit Player survey
    * Post method
    * (int)Rating and (str)survey params
    **/
    public function saveSurvey (REQUEST $request){

        $token= str_replace('Bearer ', "" , $request->header('token'));
        /*validate JWT token*/
        $data = $this->JWTAuthentication( $token );
        if( $data[ 'code' ] != 200 ) { 
            return $this->setResponse( $data[ 'code' ], $data[ 'message' ], [ ] );
        }

        $user       = $data[ 'data' ][ 'user' ];
        $eventId    = $data[ 'data' ][ 'eventId' ];
        $teamId     = $data[ 'data' ][ 'teamId' ];

        $user       = User::find( $user->id );

        $jwtToken   = $this->createJWTtoken( $user, $eventId, $teamId );

        /*validate body parameters*/
        $validator      =   \Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5',
            'survey' => 'required'
        ]);
        /*return if required paramteres are missing*/
        if($validator->fails()){
            $statusCode = 400;
            return $this->setResponse($statusCode,'',$validator->errors()->all(),[]);
        }
        $surveyDetails = array(
                    'event_id'  =>  $eventId,
                    'user_id'   =>  $user->id,
                    'team_id'   =>  $teamId
                );

        $data = Survey::where($surveyDetails)->count();

        if($data)
            return $this->setResponse(402,'Already submitted by this user');

        $surveyDetails['rating'] = $request->rating;
        $surveyDetails['survey'] = trim( $request->survey );

        $survey = Survey::create($surveyDetails);

        if($survey){
            return $this->setResponse(200,'success');
        }else{
            return $this->setResponse(405,'something went wrong');
        }
    }

    /**
    * Custom RoundResult API
    * method POST
    * round and event based result
    */
    public function getRoundResult (REQUEST $request)
    {
        $return = [];

        $token= str_replace('Bearer ', "" , $request->header('token'));
        /*validate JWT token*/
        $data = $this->JWTAuthentication($token);

        if($data['code'] != 200 ){
            return $this->setResponse($data['code'], $data['message'], []);
        }

        /*validate body parameters*/
        $validator      =   \Validator::make($request->all(), [
            'round' => 'required|numeric|min:1',
        ]);
        /*return if required paramteres are missing*/
        if($validator->fails()){
            $statusCode = 400;
            return $this->setResponse($statusCode,'',$validator->errors()->all(),[]);
        }

        $user = $data['data']['user'];
        $eventId = $data['data']['eventId'];
        $teamId = $data['data']['teamId'];
        $round  =   $request->round;

        $user = User::find($user->id);
        
        $return['jwtToken'] = $this->createJWTtoken($user, $eventId, $teamId);

        // $eventId = 401; $teamId = 5; $request->round = 8;

        $roundData=MmRounds::where(['round' => $round, 'event_id' => $eventId])->first()->toArray();

        if($roundData){
            $chancesData = Chances::where(['round_id' => $roundData['id']])
                            ->orderBy( 'chance', 'DESC' )
                            ->first();

            $return['production'] = $this->calculateRoundResult($roundData['id'], 2);
            // print_r($return['production']); die;
            $weather_percentage = round($chancesData->weather * $this->gameSettings->max_loss_profit_limit, 2 );

            $marketCostPercaentage = round($roundData['economy'] * $this->gameSettings->max_loss_profit_limit, 2 );

            $return['bit'] = SELF::$CUSTOM_RESULT_REQUEST;

            $return['round'] = $roundData['round'];
            $return['crop'] = Crops::find($roundData['crop_id']);

            $return['weather_percentage']    =   $weather_percentage;
            $return['economy_percentage']    =   $marketCostPercaentage;
            $return['foreign_percentage']    =   $roundData['foreign_production'];

            $return['demand'] = $roundData['demand'];
            
            $marketCost = round( $marketCostPercaentage * $roundData['demand'] / 100 , 2);
            //add/subtract calculated amount of cost 
            $marketCost = round( $roundData['market_cost'] + $marketCost, 2);

            $return['market_cost'] = $marketCost;

            // $activeTeams = EventJoin::getActiveMMTeamsWithMembers($eventId,SELF::$MIN_MEMBERS_REQ_FOR_ACTIVE_GAME);

            $chancesData = Chances::where(['round_id' => $roundData['id']])->get();

            foreach ($chancesData as $chancesRecord) {
                $chance[$chancesRecord->chance] = $this->calculateChanceResult ( $chancesRecord->round_id, $teamId, $chancesRecord->chance );
            }

            $return['chance'] = $chance;

            return $this->setResponse(200,'success',$return);

        }else{
            //if round not found
            return "inlavid details";
        }
    }

    /*Common methods*/


    public function removeFunFactsCron (){

        $date = date('Y-m-d',strtotime("-2 days")). " 00:00:00";
        //remove history records older than two days
        //Remove fun facts
        FunFacts::whereDate('created_at', '<', $date)->delete();
        //Remove fun fact answers
        EventFunFactsAnswers::whereDate('created_at', '<', $date)->delete();
        //Remove event invites
        EventLinkInvitedUsers::whereDate('created_at', '<', $date)->delete();
        //Remove event join detail records if any
        EventJoin::whereDate('created_at', '<', $date)->delete();
        //remove forcasting records
        Forecasting::whereDate('created_at', '<', $date)->delete();
        //Remove rounds history
        MmRounds::whereDate('created_at', '<', $date)->delete();
        //Remove chances data
        Chances::whereDate('created_at', '<', $date)->delete();
        //distributed player cash records
        PlayerCash::whereDate('created_at', '<', $date)->delete();
        //player productions
        Production::whereDate('created_at', '<', $date)->delete();
        //team assets i.e team total cash records
        TeamTotalAssets::whereDate('created_at', '<', $date)->delete();
    }


    public function saveTotalAssests ($data){
        if( $data ){
            foreach ($data as $record) {
                $details = [
                        'round'  =>  $record['round'],
                        'event_id'  =>  $record['event_id'],
                        'team_id'  =>  $record['team_id']
                    ];
                $get = TeamTotalAssets::where($details)->first();
                if( !$get ){
                    $details['total_asset']  =  $record['total_asset'];
                    TeamTotalAssets::create($details);
                }
            }
        }

    }
    
    public function fetchTotalAssests ( $data ){
        foreach ($data as $key => $record) {
            $details = [
                    'round'  =>  $record['round'],
                    'event_id'  =>  $record['event_id'],
                    'team_id'  =>  $record['team_id']
                ];
            $get = TeamTotalAssets::where($details)->first();
            if( $get ){
                $data[$key]['total_asset']  =  $get->total_asset;
            }
        }
        return $data;
    }
}//class ends..