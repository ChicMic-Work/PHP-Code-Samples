<?php

namespace App\Http\Controllers\CrimeInvestigation;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Traits\CommonMethods;
use App\Models\Event;
use App\Models\User;
use App\Models\UserTeam;
use App\Models\EventJoin;
use App\Models\SocketConnectUsers;
use App\Models\EventTeam;
use App\Models\Game;
use App\Models\CiQuestions;
use App\Models\CiGuests;
use App\Models\CiSuspectInterviews;
use App\Models\CiAnswers;
use App\Models\CiLifes;
use App\Models\CiSeenItems;
use App\Models\MainGameStatus;
use App\Models\Team;
use Carbon\Carbon;
use LRedis;
use \Config;

class CrimeInvestigationController extends Controller
{

    use CommonMethods;

    private $encId = 0;
    private $eventId;
    private $teamId;
    private $userId;
    private $gameKey;
    private $currentUTC;
    private $gameMinutes    =   0;
    private $gameSeconds    =   0;
    private $hintMinutes    =   0;
    private $hintSeconds    =   0;
    private $activeQues     =   1;

    private $defaulthintMinutes    =   15;
    private $defaulthintSeconds     =   0;

    // some static variables
    public static $HINTMINUTS = 02;
    public static $HINTSECONDS = 00;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
        $this->userTeamModelInstance = new UserTeam;
        
        $gameSettings   =   $this->getGameSettings( );
        $this->defaulthintMinutes = $gameSettings->ci_hint_timer;
        $this->defaulthintSeconds =   0;
        $this->currentUTC = $this->getUTCTimeStamp();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    /**
     * Check Event status with game timer calculations
     * Input Encription Id (String).
     */
    private function eventStatus ( $encId )
    {
        $status = 0;
        // meeting token check starts here
        $status = $this->checkMeetingTokenValidOrNot( $encId );
        // echo "here"; die;
        // if( !in_array( $status, [ SELF::$MEETING_RUNNING, SELF::$MEETING_GOING_TO_START ] ) ) { 
        //     return redirect()->route('invalid')->with(['error'=>$this->getInvalidMeetingRequestErrorMsgs()[ $status ], 'error_title'=>$this->getInvalidMeetingRequestErrorMsgsTitle()[ $status ]])->send();
        // }
        
        $this->encId = $encId;
        $event =$this->getEvent();
        $user = $this->getUser();
        $userteam = $user->userteam()->first();
        $this->teamId = Event::getTeamforEventAndUser($event->id, $user->id)->team_id;
        $this->userId = $user->id;
        $this->eventId = $event->id;
        // meeting token check ends here

        $eventDetails = Event::find( $this->eventId )->game;

        $this->gameKey = $eventDetails->key;

        $where = [
            'team_id'   =>  $this->teamId,
            'event_id'  =>  $this->eventId,
            'game_key'  =>  $this->gameKey
        ];

        // echo "got it"; die;
        if($eventDetails->key == 'escape_room'){
            //timer calculations
            $this->timerCalculations();
            $this->QueshintTimer( $this->eventId, $this->teamId );
            $timerDetails = MainGameStatus::where($where)->get();
            $gameSettings = $this->getGameSettings( );
            if($timerDetails && $timerDetails->first()->status == 2 ){
                return redirect()->route('thankyou', [$encId])->send();
            }

        }elseif($eventDetails->key == 'market_madness'){
            // move to market madness
            redirect()->route('front.mmrulesscreen',['encryptedId'=>$encId])->send();
        }else{
            //invalid link
            return redirect()->route('invalid')->with(['error'=>'Invalid event link.', 'error_title'=>'Invalid event link.' ])->send();
        }
    }
    /**
     * Game timer calculations with declared event variables.
     */
    private function timerCalculations ( ){
        //get game timer details and save time in DB
        $gameSettings   =   $this->getGameSettings( );
        $gameMinutes    =   $gameSettings->ci_timer;
        $gameSeconds    =   0;
        // $serverTime = $this->getServerTime( $this->eventId );
        $serverTime = $starttime = $this->currentUTC;
        
        $where = [
            'team_id'   =>  $this->teamId,
            'event_id'  =>  $this->eventId,
            'game_key'  =>  $this->gameKey
        ];

        $timerDetails = MainGameStatus::where($where)->get();
        // echo "sadvsd ".$timerDetails->count(); die;
        if( !$timerDetails->count() ){

            $otherTeamtimerDetails = MainGameStatus::where(
                                    [
                                        'event_id'  =>  $this->eventId,
                                        'game_key'  =>  $this->gameKey
                                    ]);

            if( $otherTeamtimerDetails->count() ){
                $starttime  =   $otherTeamtimerDetails->first()->start_time;
                $endTime    =   $otherTeamtimerDetails->first()->end_time;
            }else{
                $eventDetails = Event::find($this->eventId);
                $countdowntime = '+'.$gameMinutes.' minutes';
                $endTime = strtotime($countdowntime, $starttime);
                // if($endTime > $eventDetails->end_time){
                //     $endTime = $eventDetails->end_time;
                // }
            }

            $insert = $where;
            $insert['start_time']  =   $starttime;
            $insert['end_time']  =   $endTime;
            $insert['status']  =   1;

            MainGameStatus::create( $insert );

        }
        
        $timerDetails = MainGameStatus::where($where)->get();
        
        if( $timerDetails->count() ){
            $endTime  =   $timerDetails->first()->end_time;
            if( $endTime > $serverTime){
                $screenRunningTime = $this->getTimeDifference($serverTime, $endTime);
                $this->gameMinutes = $screenRunningTime['minutes'];
                $this->gameseconds = $screenRunningTime['seconds'];
            }else{
                $this->gameMinutes = 0;
                $this->gameseconds = 1;
            }
        }else{
            $this->gameMinutes = $gameMinutes;
            $this->gameSeconds = $gameSeconds;
        }
        
    }

    private function publishData ($data, $eventId, $teamId, $type = 1){
        //Socket connection
        $redis = LRedis::connection();
        $data['game']   =   'crime_investigations';
        $username   =   'one of your team member';

        if( $this->userId ){
            $username = User::where( 'id' , $this->userId )->first()->name;
        }

        $data['user_id'] =  $this->encodeId( $this->userId );
        $data['user_name'] =  $username;
        //encode data in JSON
        $data = json_encode( $data );
        if($type == 1){
            $channelId = "event_".$eventId.$teamId;
            $redis->publish( $channelId, $data );
        }else{
            $activeTeams = EventJoin::getActiveTeamsForChat($eventId);
            //publish data for all active teams
            foreach ($activeTeams as $teams) {
                //credit initial cash to teams
                $channelId = "event_".$eventId.$teams->team_id;
                $redis->publish( $channelId, $data );
            }
        }
    }
    // Welcome page
    public function splash($encId){
        $title = 'Welcome to Crime Investigation';
        $enbleCIGame = $redirectTogame = 0;

        $status = 0;
        // meeting token check starts here
        $status = $this->checkMeetingTokenValidOrNot( $encId );

        if( !in_array( $status, [ SELF::$MEETING_RUNNING, SELF::$MEETING_GOING_TO_START ] ) ) { 
            return redirect()->route('invalid')->with(['error'=>$this->getInvalidMeetingRequestErrorMsgs()[ $status ], 'error_title'=>$this->getInvalidMeetingRequestErrorMsgsTitle()[ $status ]])->send();
        }
        $this->encId = $encId;
        $event =$this->getEvent();
        $user = $this->getUser();
        $userteam = $user->userteam()->first();
        $this->teamId = Event::getTeamforEventAndUser($event->id, $user->id)->team_id;
        $this->userId = $user->id;
        $this->eventId = $event->id;
        $eventDetails = Event::find( $this->eventId )->game;
        $this->gameKey = $eventDetails->key;
        // echo $this->teamId; die;
        $where = [
            'team_id'   =>  $this->teamId,
            'event_id'  =>  $this->eventId,
            'game_key'  =>  $this->gameKey
        ];
        
        EventJoin::where(['event_id' => $this->eventId, 'team_id' => $this->teamId, 'user_id' => $this->userId])->update(['tutorials_seen' => 1]);

        $timerDetails = MainGameStatus::where($where)->get();
        // echo "<pre>"; print_r($timerDetails); die;
        if( $timerDetails->count() ){
            $starttime  =   $timerDetails->first()->start_time;
            $starttime = strtotime('+7 seconds', $starttime);
            // $serverTime = $this->getServerTime( $this->eventId );
            $serverTime = $this->currentUTC;

            
            if( $serverTime > $starttime){
                //redirect to next
                $redirectTogame = 1;
            }
        }else{
            //check game min team requirements
            // echo $this->eventId; die;
            sleep(1);
            $joindedTeams = EventJoin::getActiveMMTeamsWithMembers( $this->eventId, 1);
            // echo "<pre>"; print_r($joindedTeams); die;
            $gameSettings   =   $this->getGameSettings();
            $min_teams_for_ci = $gameSettings->min_teams_for_ci;
            // echo "outer ".$joindedTeams->count() ." | ".$min_teams_for_ci; die;
            if($joindedTeams->count() >= $min_teams_for_ci ){
                // echo $joindedTeams->count() ." | ".$min_teams_for_ci; die;
                //start the event and publish the data for other teams too.
                $this->eventStatus( $this->encId );
                $data = [
                    'teamCount' => $joindedTeams,
                    'action'    =>  'enbleCIGame',
                    'user_id'   =>  $this->encodeId( $this->userId )
                ];
                $enbleCIGame = 1;
                $this->publishData($data, $this->eventId, $this->teamId, 2 );   
            }
        }

        return view('crimeinvestigation.splash', [
            'title'         =>  $title,
            'encId'         =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameSeconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds,
            'redirectTogame'=>  $redirectTogame,
            'enbleCIGame'   =>  $enbleCIGame
        ]);
    }

    public function overview( $encId )
    {
        $answers = [];  $ans1 = $ans2 = $ans3 = null; $question = [];
        $gameSettings = $this->getGameSettings( );
        $title = 'Game Overview';
        //check this event status
        $eStatus    =   $this->eventStatus( $encId );
        
        $answered   =  CiAnswers::where(["team_id" => $this->teamId, "event_id" => $this->eventId])->get();

        $usedlifes  =  CiLifes::where(["team_id" => $this->teamId, "event_id" => $this->eventId])->count();
        
        $hintUnlocked = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'action' => 8])->groupBy('team_id', 'item_id')->get();
        
        $hintarr = [];
        if( $hintUnlocked->count() ){
            foreach ( $hintUnlocked as $hints){
                $hintarr[] = $hints->item_id;
            }
        }

        $lifes = $gameSettings->ci_lifes - $usedlifes;
        if( $answered->count() ){
            foreach ($answered as $answ) {
                $answers['ans'.$answ['question']] = $answ['answer'];
            }
        }

        extract($answers);
        $unlock = $answered->count() + 1;
        //fetch all questions for CI game
        $questions  =   CiQuestions::all();
        foreach ($questions as $key => $value) {
            $question[$value['serial']] = $value['question'];
        }

        $guests     =   CiGuests::where(['type' => 1])->get();
        $helpers    =   CiGuests::where(['type' => 2])->get();
        //need to make dynamic when we found actual id's

        return view('crimeinvestigation.overview', [
            'title'     =>  $title,
            'team_id'   =>  $this->encodeId( $this->teamId ),
            'teamname'  =>  Team::where(['id' => $this->teamId])->first()->name,
            'event_id'  =>  $this->encodeId( $this->eventId ),
            'user_id'   =>  $this->encodeId( $this->userId ),
            'question'  =>  $question,
            'guests'    =>  $guests,
            'helpers'   =>  $helpers,
            'ans1'      =>  $ans1,
            'ans2'      =>  $ans2,
            'ans3'      =>  $ans3,
            'unlock'    =>  $unlock,
            'lifes'     =>  $lifes,
            'hintarr'   =>  $hintarr,
            'encId'     =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function file_closed($encId){
        $eStatus    =   $this->eventStatus( $encId );
        $title = 'Case to resolve';

        // echo " page ".$this->gameMinutes. " | ". $this->gameSeconds; die;
        return view('crimeinvestigation.file_closed', [
            'title' => $title,
            'encId' =>  $encId,
            'gameMinutes'     =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameSeconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function evidence($encId){
        $securityPhotosStatusSeen = $partyPhotosSeen = $policeReportSeen = 0;
        $thiefSeen  =   $mansionSeen    =   null;
        
        $eStatus    =   $this->eventStatus( $encId );

        $securityPhotosStatusSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 5])->count();
        $partyPhotosSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 6])->count();
        $policeReportSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 7])->count();
        $lampSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 10])->count();

        $thiefSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 8])->count();
        $mansionSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 9])->count();
        
        $title = 'Evidence';

        return view('crimeinvestigation.evidence', [
            'title' => $title,
            'securityPhotosStatusSeen' => $securityPhotosStatusSeen,
            'partyPhotosSeen'   => $partyPhotosSeen,
            'policeReportSeen'  => $policeReportSeen,
            'team_id'   =>  $this->encodeId( $this->teamId ),
            'event_id'  =>  $this->encodeId( $this->eventId ),
            'user_id'   =>  $this->encodeId( $this->userId ),
            'thiefSeen' =>  $thiefSeen,
            'mansionSeen'   =>  $mansionSeen,
            'lampSeen'      =>  $lampSeen,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function newspapper($encId){

        $eStatus    =   $this->eventStatus( $encId );
        
        $title = 'Clips of Newspapper';
        return view('crimeinvestigation.newspapper', [
            'title' => $title,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function partyphotos($encId){
        $eStatus    =   $this->eventStatus( $encId );
        $title = 'Party Photos';
        return view('crimeinvestigation.partyphotos', [
            'title' => $title,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function mansion($encId){
        $title = 'Mansion Layout';
        $mansionSeen = $bit = null;
        // meeting token check starts here
        $eStatus    =   $this->eventStatus( $encId );
        // meeting token check ends here
        $mansionSeen = CiSeenItems::where(['team_id' => $this->teamId, 'event_id' => $this->eventId, 'item_id' => 9])->count();
        $accessSecurityCamraSeen = CiSeenItems::where(['team_id'   =>  $this->teamId, 'event_id'  =>  $this->eventId, 'item_id' => 5] )->count();
        if( $accessSecurityCamraSeen )
            $bit = 1;
        return view('crimeinvestigation.mansion', [
            'title' => $title,
            'mansionSeen'   =>  $mansionSeen,
            'bit'   =>  $bit,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function dmv($encId){
        $eStatus    =   $this->eventStatus( $encId );
        $title = 'DMV';
        return view('crimeinvestigation.dmv', [
            'title' => $title,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function dmv_detail($encId, Request $request){
        $eStatus    =   $this->eventStatus( $encId );
        if($request->vehicle_number){
            $vehicle  = $request->vehicle_number;
            $guest    =   CiGuests::where(['vehicle_no' => $vehicle])->get();
        }else{
            return redirect()->route('crimeinvestigation.dmv', $encId)->with('popup', 'open');
        }

        if(!$guest->count()){
            return redirect()->route('crimeinvestigation.dmv', $encId)->with('popup', 'open');
        }

        $title = 'DMV Detail';
        return view('crimeinvestigation.dmv_detail', [
            'title' => $title,
            'guest' =>  $guest->first(),
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function suspects($encId){
        $eStatus    =   $this->eventStatus( $encId );

        $title = 'Suspects';
        $suspects   =   CiGuests::all();
        $htm = ""; 
        foreach( $suspects as $suspect ){

            $htm .= '
                <a class="profile-items" href="'.route('crimeinvestigation.suspects_detail', [$this->encodeId($suspect->id), $encId] ).'">
                    <img class="img-fluid" src="'. asset( 'assets/crime_investigation/images/profile_img/'.str_replace("jpg","png",$suspect->image) ) .'" alt="7:30PM"/>
                    <div class="profile_det">
                        <h6>'.$suspect->role.'</h6>
                        <p>'.$suspect->name.'</p>                        
                    </div>                  
                </a>';
        }

        return view('crimeinvestigation.suspects', [
            'title'     =>  $title,
            'suspects'  =>  $suspects,
            'htm'       =>  $htm,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function suspects_detail( $suspect_id, $encId )
    {
        $eStatus    =   $this->eventStatus( $encId );
        $fingerprintsSeen = $interviewSeen = $imagePath = null;

        $interview = 'INSPECTOR:  Mr. Pembroke, thank you for coming in to the station.
        Mr. PEMBROKE:  Well, I guess I didn’t have much choice
        INSPECTOR:  Can we get you some coffee? Water?
        Mr. PEMBROKE:  No, no let’s make this quick; I have other matters to attend to today....';
        $houseSearched = 1;
        
        $suspectId  = $this->decodeId($suspect_id);
        $interviewTaken = CiSeenItems::where(['team_id'   =>  $this->teamId,'event_id'  =>  $this->eventId,'suspect_id'=>  $suspectId, 'action' => 1] )->count();
        $fingerprintsSeen = CiSeenItems::where(['team_id'   =>  $this->teamId,'event_id'  =>  $this->eventId,'suspect_id'=>  $suspectId, 'item_id' => 3] )->count();
        $suspectDetails = CiGuests::find($suspectId);

        if( $fingerprintsSeen ){
            $imagePath = $suspectDetails->fingerprints_img;
        }

        if($interviewTaken){
            $interviewData  =   CiSuspectInterviews::where(['suspect_id' => $suspectId, 'status' => 1])->first();
            if( $interviewData->count() ){
                $interview = $interviewData->interview;
                $interviewSeen = 1;
            }   
            
            $houseSearched = CiSeenItems::where(['team_id'   =>  $this->teamId,'event_id'  =>  $this->eventId, 'action'   =>  4, 'suspect_id'=>  $suspectId] );
            
            if( $houseSearched->count() ){
                $houseSearched = 3;
                if( $suspectId == 3 ){
                    $houseSearched = 4;
                    $painterHouseSearched = CiSeenItems::where(['team_id'   =>  $this->teamId,'event_id'  =>  $this->eventId, 'action'   =>  4, 'suspect_id'=>  10] );
                    // print_r($painterHouseSearched->count()); die;
                    if( $painterHouseSearched->count() )
                        $houseSearched = 6;
                }elseif ( $suspectId == 10 ) {
                    $houseSearched = 7;
                    $professorHouseSearched = CiSeenItems::where(['team_id'   =>  $this->teamId,'event_id'  =>  $this->eventId, 'action'   =>  4, 'suspect_id'=>  3] );
                    if( $professorHouseSearched->count() )
                        $houseSearched = 8;
                }elseif ( $suspectId == 6 ) {
                    $houseSearched = 5;
                }
            }else{
                $houseSearched = 2;
            }
        }

        $suspect    =   CiGuests::find($suspectId);
        $title = 'Suspects Detail';

        return view('crimeinvestigation.suspects_detail', [
            'title'     =>  $title,
            'suspect'   =>  $suspect,
            'interview' =>  $interview,
            'suspect_id'=>  $suspect_id,
            'team_id'   =>  $this->encodeId( $this->teamId ),
            'event_id'  =>  $this->encodeId( $this->eventId ),
            'user_id'   =>  $this->encodeId( $this->userId ),
            'houseSearched'     =>  $houseSearched,
            'fingerprintsSeen'  =>  $fingerprintsSeen,
            'imagePath'         =>  $imagePath,
            'interviewSeen'     =>  $interviewSeen,
            'glove_img'         =>  $suspectDetails->search_house_img,
            'encId'             =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function security_photos($encId){
        $eStatus    =   $this->eventStatus( $encId );
        $title = 'Security Camra Pics';
        return view('crimeinvestigation.security_photos', [
            'title' => $title,
            'encId' =>  $encId,
            'gameMinutes'   =>  $this->gameMinutes,
            'gameSeconds'   =>  $this->gameseconds,
            'hintMinutes'   =>  $this->hintMinutes,
            'hintSeconds'   =>  $this->hintSeconds
        ]);
    }

    public function ci_submit(Request $request){

        $gameSettings   =   $this->getGameSettings();
        $usedlifes      =   CiLifes::where(["team_id" => $request->team_id, "event_id" => $request->event_id] )->count();
        $data['unlock'] = $request->unlock;
        $ques   =   0;
        $this->userId = $user_id = $this->decodeId($request->user_id);
        $team_id = $this->decodeId($request->team_id);
        $event_id = $this->decodeId($request->event_id);

        if($usedlifes < $gameSettings->ci_lifes){
            $ans1 = $request->ans1;
            $ans2 = $request->ans2;
            $ans3 = $request->ans3;
            
            if( $ans1 ){
                $ques   =   1;
                $data = $this->submitQuestion ($ques, $team_id, $event_id, $user_id, $ans1);
                if( $data['record'] )
                    $data['unlock'] = 2;
            }
            if( $ans2 ){
                $ques   =   2;
                $ans2 = implode(',', $ans2);
                $data = $this->submitQuestion ($ques, $team_id, $event_id, $user_id, $ans2);
                if( $data['record'] )
                    $data['unlock'] = 3;
            }
            if( $ans3 ){
                $ques   =   3;
                $ans3 = implode(',', $ans3);
                $data = $this->submitQuestion ($ques, $team_id, $event_id, $user_id, $ans3);
                if( $data['record'] )
                    $data['unlock'] = 4;     
            }

        }else{
            $data['ansbit'] = 4;
            $data['msg'] = 'You have used all your attempts!';
        }

        $latestLifes    =   CiLifes::where(["team_id" => $team_id, "event_id" => $event_id])->count();
        $data['lifes']  =   $gameSettings->ci_lifes - $latestLifes;
        $data['action'] =   'question';
        $data['ques']   =   $ques;
        $data['hintMinutes']   =  $this->hintMinutes;
        $data['hintSeconds']   =  $this->hintSeconds;
        
        $data['teamname']      =    Team::where(['id' => $team_id])->first()->name;
        $data['team_id']       =  $this->encodeId( $team_id );
        $teamMembers = EventJoin::getOptions( $event_id, $team_id);
        $members = "";
        
        foreach( $teamMembers as $member ){
            $members .= '<li class="col-3 col-md-2"><div class="player_img"><img src="'. asset( 'assets/front/images/icons/avtar'.$member->avatar.'.png' ) .'" alt="" class="img-fluid" /><h5 class="text-center">'.$member->name.'</h5></div></li>';
        }
        
        $data['html'] = $members;
        
        if( $data['lifes'] == 0 ){
            $this->updateGameStatus ( $event_id, $team_id );
            $this->updateEventStatus( $event_id );
        }

        if ( $data['ansbit'] == 5 ){
            $data['teamrank'] = $this->getTeamRank( $team_id, $event_id);
            $this->publishData($data, $event_id, $team_id, 2 );
        }else{
            $this->publishData($data, $event_id, $team_id);
        }

        return $data;
    }

    public function submitQuestion ($ques, $team_id, $event_id, $user_id, $answer){
        $return = $msg = $record = null; $ansbit = 0;
        // $answer_at = $this->getUTCTime();
        $answer_at = $this->currentUTC;
        //check if already answered.
        $check = CiAnswers::where(['team_id'=> $team_id, 'event_id'=>$event_id, 'question' => $ques])->count();

        if(empty( $check) ){
            $questions  =   CiQuestions::all();

            foreach ($questions as $question) {
                $ans[$question['serial']] = $question['answer'];
            }

            $insert = [
                        'user_id'   =>  $user_id,
                        'team_id'   =>  $team_id,
                        'event_id'  =>  $event_id,
                        'question'  =>  $ques
                    ];
            if($answer == $ans[$ques])
            {
                $ansbit = 1;
                $insert['answer']    =  $answer;
                $insert['status']    =  1;
                $insert['answered_at']    =  $answer_at;

                $record   =   CiAnswers::create( $insert );
                $msg    =   'Good job, detective! You have answered correctly.';
                //enable police report in case of 2nd question answered correctly.
                if( $ques == 2 ){
                    $this->enablePoliceReport($user_id, $team_id, $event_id);
                    $msg .= ' An old police report has been unlocked. See the evidence page for details.';
                }elseif( $ques == 3 ){
                    //game over bit
                    $ansbit = 5;
                    $this->updateGameStatus ( $event_id, $team_id, true );
                    $this->updateEventStatus( $event_id );
                }

                $this->hintMinutes = $this->defaulthintMinutes;
                $this->hintSeconds = $this->defaulthintSeconds;
                
            }else{
                $ansbit = 2;
                //deduct one life from the team
                CiLifes::create( $insert );

                $msg = "Incorrect Answer! Deducted one team life.";            
                
            }
        }else{
            $ansbit = 3;
            //team has already answered for this question
            $msg = "Team has already answered for this question";
        }

        return $return = ['record' => $record, 'msg' => $msg, 'ansbit' => $ansbit];
    }

    public function takeInterview (Request $request){
        $suspectId = $request->s_id; 
        $msg = "Nothing useful found for the case.";
        $interviewContent = $data = null; $status = 0;
        $suspectName = '';
        $this->userId = $user_id = $this->decodeId($request->user_id);
        $team_id = $this->decodeId($request->team_id);
        $event_id = $this->decodeId($request->event_id);

        if( $suspectId ){
            $suspectId = $this->decodeId($suspectId);
            $suspectsDetail     =   CiGuests::find($suspectId);
            $suspectName = $suspectsDetail->name;
            $interviewData  =   CiSuspectInterviews::where(['suspect_id' => $suspectId, 'status' => 1])->first();
            if( $interviewData->count() ){
                $status = 1;
                $interviewContent = $interviewData->interview;
                $msg = "Success";

                $interviewTaken = CiSeenItems::where(['team_id'   =>  $team_id,'event_id'  =>  $event_id,'suspect_id'=>  $suspectId, 'item_id'   =>  1] )->count();

                if( !$interviewTaken ){
                    $insert = [
                            'user_id'   =>  $user_id,
                            'team_id'   =>  $team_id,
                            'event_id'  =>  $event_id,
                            'item_id'   =>  1,
                            'item_name' =>  'Interview',
                            'action'    =>  1,
                            'suspect_id'=>  $suspectId
                        ];

                    CiSeenItems::create( $insert );
                }
            }

        }else{
            $msg = "Insufficient details";
        }

        $data = [
            'msg'   =>  $msg, 
            'data'  =>  $interviewContent, 
            'status'=>  $status,
            'action'=>  'take_interview',
            'suspectName'   =>  $suspectName
        ];
        
        $this->publishData($data, $event_id, $team_id);

        return $data;
    }

    public function searchHouse ( Request $request )
    {
        $suspectId = $request->s_id; $compareBtn = 0; $encId = $request->encId;
        $suspectName = '';
        $eStatus    =   $this->eventStatus( $encId );
        $user_id = $this->userId;
        $team_id = $this->teamId;
        $event_id = $this->eventId;

        $msg = "Nothing useful was found for the case.";
        $link = "#"; $imgpath = $data = null; $status = $bit = 0;

        if( $suspectId ){
            $suspectId = $this->decodeId($request->s_id);

            $searchHouseSeen = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'suspect_id'   =>  $suspectId, 'action' => 4] )->count();
            $suspectsDetail     =   CiGuests::find($suspectId);
            $data = $suspectsDetail->search_house_img;
            if(!empty($suspectsDetail->search_house_link) && $suspectsDetail->search_house_link != '#' ){
                if( $suspectsDetail->search_house_link == 'crimeinvestigation.partyphotos' ){
                    $link = route($suspectsDetail->search_house_link, $encId);
                }else{
                    $link = route($suspectsDetail->search_house_link);
                }
                
            }

            if(!empty($suspectsDetail->search_house_img) ){
                $imgpath = asset( 'assets/crime_investigation/images/photos/'.$suspectsDetail->search_house_img);
            }

            if(  !$searchHouseSeen ){
                $insert = [
                        'user_id'   =>  $user_id,
                        'team_id'   =>  $team_id,
                        'event_id'  =>  $event_id,
                        'suspect_id'=>  $suspectId,
                        'action'    =>  4,
                        'item_name' =>  'Search House'
                    ];

                $bit = $status = 1;

                if( $suspectId == 6){
                    $insert['item_id'] = 6;
                    $msg = "Lucky you! Found some photos taken during the party.";
                    $bit = 2;
                }elseif ( $suspectId == 3 ) {
                    //professor case
                    $painterHouseSeen = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'suspect_id'   =>  10, 'action' => 4] )->count();
                    $insert['item_id'] = 4;
                    // $msg = "Good Luck! We found gloves with paint..";
                    $msg = "Found Professor's gloves with some paint stains on them.";
                    $bit = 3;
                    if($painterHouseSeen)
                        $compareBtn = 1;
                }elseif ( $suspectId == 10 ) {
                    //painter Tim case
                    $professorHouseSeen = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'suspect_id'   =>  3, 'action' => 4] )->count();
                    $insert['item_id'] = 4;
                    // $msg = "Gloves found with stains of paint, used for painting the sconces..";
                    $msg = "Found Tim's gloves with stains of the paint used on the mansion's sconces.";
                    $bit = 4;
                    if($professorHouseSeen)
                        $compareBtn = 1;
                }

                CiSeenItems::create( $insert );
            }

            $suspectName = $suspectsDetail->name;
        }else{
            $msg = "Insufficient details";
        }
        //publish socket content too
        $data = [
            'msg' => $msg, 
            'data' => $data, 
            'status'=> $status, 
            'shbit' => $bit, 
            'link' => $link, 
            'imgpath'=> $imgpath, 
            'compareBtn' => $compareBtn,
            'action'    =>  'search_house',
            'suspectName'   =>  $suspectName
        ];
        //publish search house data
        $this->publishData ($data, $this->eventId, $this->teamId);

        return $data;
    }

    public function accessSecurityCamra( $encId )
    {
        // meeting token check starts here
        $eStatus    =   $this->eventStatus( $encId );
        $user_id = $this->userId;
        $team_id = $this->teamId;
        $event_id = $this->eventId;
        // meeting token check ends here
        $accessSecurityCamraSeen = CiSeenItems::where(['team_id'   =>  $this->teamId, 'event_id'  =>  $this->eventId, 'item_id' => 5] )->count();
        if( !$accessSecurityCamraSeen ){
            $insert = [
                    'user_id'   =>  $this->userId,
                    'team_id'   =>  $this->teamId,
                    'event_id'  =>  $this->eventId,
                    'action'    =>  2,
                    'item_id'   =>  5,
                    'item_name' =>  'Security Photos',
                    'encId'    =>  $encId
                ];
            CiSeenItems::create( $insert );
            
            //publish socket content too
            $data = [
                'action'    =>  'security_camera'
            ];
            //publish search house data

            $this->publishData ($data, $this->eventId, $this->teamId);
        }

        return redirect()->route('crimeinvestigation.security_photos', $encId);
    }

    public function enablePoliceReport ($user_id, $team_id, $event_id)
    {
        $seenPoliceReport = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'item_id' => 7] )->count();
        if( !$seenPoliceReport ){
            $insert = [
                    'user_id'   =>  $user_id,
                    'team_id'   =>  $team_id,
                    'event_id'  =>  $event_id,
                    'action'    =>  0,
                    'item_id'   =>  7,
                    'item_name' =>  'Old Police Report'
                ];
            CiSeenItems::create( $insert );
        }
        return true;
    }

    public function getFingerprints (Request $request){
        $suspectId = $request->s_id; 
        $msg = "Something went wrong";
        $link = $data = null; $status = 0;
        $suspectName = '';
        $this->userId = $user_id = $this->decodeId($request->user_id);
        $team_id = $this->decodeId($request->team_id);
        $event_id = $this->decodeId($request->event_id);

        if( $suspectId ){
            $suspectId = $this->decodeId($request->s_id);
            $fingerprintsSeen = CiSeenItems::where(['team_id'   =>  $team_id,'event_id'  =>  $event_id,'suspect_id'=>  $suspectId, 'item_id'   =>  3] )->count();
            $data = CiGuests::find($suspectId);
            if($data){
                $link = asset('assets/crime_investigation/images/fingerprints').'/'.$data->fingerprints_img;
                $suspectName = $data->name;
            }

            if( !$fingerprintsSeen ){
                $insert = [
                        'user_id'   =>  $user_id,
                        'team_id'   =>  $team_id,
                        'event_id'  =>  $event_id,
                        'item_id'   =>  3,
                        'item_name' =>  'Finger Prints',
                        'action'    =>  3,
                        'suspect_id'=>  $suspectId
                    ];

                CiSeenItems::create( $insert );
            }
            $status = 1;
            $msg = "Success";
        }else{
            $msg = "Insufficient details";
        }
        $data = [
            'msg'       =>  $msg, 
            'data'      =>  $link, 
            'status'    =>  $status,
            'action'    =>  'take_fingerprints',
            'suspectName'   =>  $suspectName
        ];

        $this->publishData($data, $event_id, $team_id);

        return $data;
    }

    public function searchMansion (Request $request){
        $msg = "Something went wrong";
        $status = 0;

        $this->userId = $user_id = $this->decodeId($request->user_id);
        $team_id = $this->decodeId($request->team_id);
        $event_id = $this->decodeId($request->event_id);

        $seenMansion = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'action' => 7, 'item_id' => 9] )->count();

        if( !$seenMansion ){
            $insert = [
                    'user_id'   =>  $user_id,
                    'team_id'   =>  $team_id,
                    'event_id'  =>  $event_id,
                    'action'    =>  7,
                    'item_id'   =>  9,
                    'item_name' =>  'Search Mansion'
                ];
            CiSeenItems::create( $insert );
            $msg = 'Good job, detective! A secret corridor has been found in the mansion. Click on view details to learn more.';
            $status = 1;
        }

        $data = [
            'msg'   =>  $msg, 
            'status'=>  $status, 
            'action'=>  'search_mansion'
        ];

        $this->publishData($data, $event_id, $team_id);

        return $data;
    }

    public function catchThief (Request $request){

        $msg = "Something went wrong";
        $status = 0;

        $this->userId = $user_id = $this->decodeId($request->user_id);
        $team_id = $this->decodeId($request->team_id);
        $event_id = $this->decodeId($request->event_id);

        $thiefLocated = CiSeenItems::where(['team_id'   =>  $team_id,'event_id'  =>  $event_id, 'item_id'   =>  8] )->count();

        if( !$thiefLocated ){
            $this->locateThief( $user_id, $team_id, $event_id );
            $msg = 'The old thief could not be located';
            $status = 1;
        }

        $data = [
            'msg'   =>  $msg, 
            'status'=>  $status, 
            'action'=>  'catch_theif'
        ];

        $this->publishData($data, $event_id, $team_id);
        
        return $data;
        
    }

    public function locateThief ( $user_id, $team_id, $event_id ){
        $seenThief = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'action' => 7, 'item_id' => 8] )->count();
        if( !$seenThief ){
            $insert = [
                    'user_id'   =>  $user_id,
                    'team_id'   =>  $team_id,
                    'event_id'  =>  $event_id,
                    'action'    =>  7,
                    'item_id'   =>  8,
                    'item_name' =>  'Search thief'
                ];
            CiSeenItems::create( $insert );
        }
        return true;
    }

    public function compareGloves (Request $request){
        $msg = "Something went wrong";
        $status = $bit = 0;
        $this->userId = $user_id = $this->decodeId($request->user_id);
        $team_id = $this->decodeId($request->team_id);
        $event_id = $this->decodeId($request->event_id);
        $suspectId = $request->s_id; 
        if( $suspectId ){
            $suspectId = $this->decodeId($request->s_id);
            $lampSeen = CiSeenItems::where(['team_id'   =>  $team_id, 'event_id'  =>  $event_id, 'suspect_id'   =>  $suspectId, 'action' => 10] )->count();
            if(!$lampSeen){
                $insert = [
                    'user_id'   =>  $user_id,
                    'team_id'   =>  $team_id,
                    'event_id'  =>  $event_id,
                    'action'    =>  10,
                    'item_id'   =>  10,
                    'item_name' =>  'Compare gloves'
                ];
                CiSeenItems::create( $insert );
                $bit = 1;
            }
            $msg = " Paint used on the mansions sconces matches with the stains found on the Professor's gloves.";
            $status = 1;
        }

        $data = [
            'msg'   =>  $msg, 
            'status'=>  $status, 
            'cgbit'   =>  $bit,
            'action'=>  'compare_gloves'
        ];

        $this->publishData($data, $event_id, $team_id);
        
        return $data;
    }

    public function gameOver(Request $request){
        $encId = $request->encId;
        $msg = 'Game over!';

        if($encId){
            $this->eventStatus( $encId );
    
            $this->updateGameStatus( $this->eventId, $this->teamId );
            $this->updateEventStatus ( $this->eventId );
        }
        
        $data = [
            'msg'   =>  $msg, 
            'result'=>  1,
            'action'=>  'game_over'
        ];

        $teamMembers = EventJoin::getOptions( $this->eventId, $this->teamId);
        $members = "";
        
        foreach( $teamMembers as $member ){
            $members .= '<li class="col-3 col-md-2"><div class="player_img"><img src="'. asset( 'assets/front/images/icons/avtar'.$member->avatar.'.png' ) .'" alt="" class="img-fluid" /><h5 class="text-center">'.$member->name.'</h5></div></li>';
        }
        $data['teamname']      =    Team::where(['id' => $this->teamId])->first()->name;
        $data['html'] = $members;
        $data['team_id']       =  $this->encodeId( $this->teamId );

        $this->publishData($data, $this->eventId, $this->teamId );
        
        return $data;
    }

    private function updateGameStatus ( $eventId, $teamId, $rank = false )
    {

        $where = [
            'event_id'  =>  $eventId,
            'team_id'   =>  $teamId
        ];

        if( $rank ){
            $winnerTeams = MainGameStatus::where(['event_id' => $eventId, 'status' => 2])->orderBy('rank', 'DESC')->first();
            if( isset( $winnerTeams->rank ) && $winnerTeams->rank > 0 ){
                $rank = $winnerTeams->rank + 1;
            }else{
                $rank = 1;
            }
        }else{
            $rank = 0;
        }

        $timerDetails = MainGameStatus::where($where)->get();
        
        $insert = $where;
        if( !$timerDetails->count() ){
            $insert['start_time']  =  $this->getServerTime( $eventId );
            $insert['status']   =   2;
            $insert['rank']     =   $rank;

            MainGameStatus::create( $insert );
        }else{
            $timerDetails = $timerDetails->first();
            $timerDetails->status = 2;
            $timerDetails->rank = $rank;
            $timerDetails->save();
        }
    }

    private function QueshintTimer( $eventId, $teamId )
    {
        $hintMinutes = $this->defaulthintMinutes;
        $hintSeconds = $this->defaulthintSeconds;
        $this->eventId  =   $eventId;
        $this->teamId   =   $teamId;
        //current server time
        // $serverTime = $this->getServerTime( $this->eventId );
        $serverTime = $this->currentUTC;
        $eventDetails = Event::find( $this->eventId )->game;

        $this->gameKey = $eventDetails->key;

        $where = [
            'team_id'   =>  $this->teamId,
            'event_id'  =>  $this->eventId
        ];
        
        if( $eventDetails->key == 'escape_room' ){
            //timer calculations
            $dis = '1';
            $answered   =  CiAnswers::where( $where )
                            ->orderBy('question', 'DESC')
                            ->get();

            if( $answered->count() ){
                $dis .= '_2';
                $answered = $answered->first();
                $ques   =   $answered->question;

                //calculate the timer
                $start_time = $answered->answered_at;
            }else{
                $dis .= '_3';
                $where['game_key']  =  $this->gameKey;
                $timerDetails = MainGameStatus::where( $where )->first();
                
                if( $timerDetails->count() ){
                    $dis .= '_4';
                    $start_time = $timerDetails->start_time;
                    
                }
            }
            // echo $serverTime; die;
            if(isset($start_time) && !empty($start_time) ){
                //Add 15 minuts to start time to make hint timer 
                $timeToInc = '+'.$hintMinutes.' minutes';
                $hintTimer = strtotime($timeToInc, $start_time);
                // echo $serverTime.' | '.$hintTimer; die;
                //subtract server time from hint timer
                if( $serverTime < $hintTimer ){
                    $hintRunningTime = $this->getTimeDifference($serverTime, $hintTimer);
                    // print_r($hintRunningTime); die;
                    $this->hintMinutes = $hintRunningTime['minutes'];
                    $this->hintSeconds = $hintRunningTime['seconds'];
                }else{
                    $this->hintMinutes = 0;
                    $this->hintSeconds = 0;
                }
            }

            $data = [
                'hintMinutes' => $this->hintMinutes,
                'hintSeconds' => $this->hintSeconds,
                'action'      => 'hintTimer'
            ];
        }
    }

    public function unlockQuesHint ( Request $request ){
        $encId = $request->encId;
        $msg = 'Something went wrong!';
        $hintMinutes = $this->defaulthintMinutes;
        $hintSeconds = $this->defaulthintSeconds;
        $bit = $status = $ques = 1;

        if($encId){
            $this->eventStatus( $encId );
            $where = [
                'team_id'   =>  $this->teamId,
                'event_id'  =>  $this->eventId
            ];
            
            $eventDetails = Event::find( $this->eventId )->game;

            $this->gameKey = $eventDetails->key;

            if( $eventDetails->key == 'escape_room' ){
                //timer calculations
                $answered   =  CiAnswers::where( $where )
                                ->orderBy('question', 'DESC')
                                ->get();
                
                $insert = [
                    'team_id'   =>  $this->teamId,
                    'event_id'  =>  $this->eventId,
                    'item_name' =>  'hint_unlock',
                    'action'    =>  8,
                    'suspect_id'=>  0
                ];

                $ques = $initial = 1;
        
                if( $answered->count() ){
                    $answered = $answered->first();
                    if( $answered->question < 3 ){
                        $ques   =   $answered->question + 1;
                    }else{
                        $bit = $status = 0;
                    }
                }

                if( $status != 0 ){
                    $input = $initial.$ques;
                    //get which question is active
                    $insert['item_id']   =  $input;
                    $recordCheck = CiSeenItems::where($insert)->count();
                    if(!$recordCheck){
                        $insert['user_id']   =  $this->userId;
                        CiSeenItems::create( $insert );
                    }else{
                        $bit = 0;
                    }   
                }
                
            }else{
                //something went wrong;
                $bit = $status = 0;
            }

        }else{
            //something went wrong;
            $bit = $status = 0;
        }
        
        $data = [
            'msg'   =>  $msg, 
            'result'=>  $status,
            'action'=>  'enable_hint',
            'ques'  =>  $ques,
            'hintMinutes'   =>  $hintMinutes,
            'hintSeconds'   =>  $hintSeconds
        ];
        if( $bit == 1 ){
            $this->publishData($data, $this->eventId, $this->teamId );
        }
        
        return $data;
    }

    private function getTeamRank ( $team_id, $event_id ){
        $where = [
            'team_id'   =>  $team_id,
            'event_id'  =>  $event_id,
            'status'    =>  2
        ];
        
        $teamRank = MainGameStatus::where($where)->first()->rank;

        $dictionary = [
            1   =>  'First',
            2   =>  'Second',
            3   =>  'Third',
            4   =>  'Fourth',
            5   =>  'Fifth',
            6   =>  'Sixth',
            7   =>  'Seventh',
            8   =>  'Eighth',
            9   =>  'Ninth',
            10  =>  'Tenth',
            11  =>  'Eleventh',
            12  =>  'Twelve',
            13  =>  'Thirteen',
            14  =>  'Fouthteen',
            15  =>  'Fifteen',
        ];

        return $dictionary[$teamRank];
    }

    private function updateEventStatus ( $eventId )
    {
        $event = Event::where(['id' => $eventId]);

        if( $event->count() ){
            if( $event->first()->status != 2 ){
                Event::where(['id' => $eventId])->update(['status' => 2]);
            }
        }
    }

}//class ends..