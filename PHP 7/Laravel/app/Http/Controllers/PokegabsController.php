<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
//models
use App\Models\Pokegabs;
use App\Models\PokegabTypes;
use App\Models\PokegabPower;
use App\Models\Tournament;
use App\Models\TournamentRounds;
use App\Models\TournamentWinners;
//traits
use App\Http\Traits\PokegabsMethods;
use App\Http\Traits\DateMethods;

class PokegabsController extends Controller
{
    use PokegabsMethods, DateMethods;

    PUBLIC FUNCTION __constructor(){
        
    }

    PUBLIC FUNCTION search_pokegab_page()
    {
        $active     =   $eliminated =   0;
        $tournament =   Tournament::where([
            'month'     =>  date('m'),
            'year'      =>  date('Y')
        ])->first();

        if( $tournament ){
            $tournament =   $tournament->toArray();
            $pendings   =   TournamentRounds::where([
                                'tournament_id' =>  $tournament['id'],
                                'status'        =>  1
                                ])->count();
            $active     =   $pendings * 2;
            $eliminated =   $tournament['total_players']    -   $active;
        }

        $pokegab['active']      =   $active;
        $pokegab['eliminated']  =   $eliminated;

        $pokegab['types']   =   PokegabTypes::all();
        $pokegab['power']   =   $this->power;
        return view('website/search')->with('data', $pokegab);
    }

    PUBLIC FUNCTION search_pokegab_method ( Request $request )
    {
        $nextFight  =   date('d-m-Y 23:59:59', strtotime('first day of next month'));
        $active     =   $eliminated =   0;

        if( isset($request->gabimon) && !empty($request->gabimon) )
        {
            $rules = [
                'gabimon' => 'required|integer|min:1|max:10000'
            ];
    
            $validation = \Validator::make($request->all(), $rules);
            
            if ($validation->fails()) {
                return redirect('/search')->withErrors(['error_msg' => $validation->messages()->all()[0] ]);
            }
        }else{
            $this->gabimonsSearch($request->all());
        }    

        $pokegab_id =   $request->input('gabimon');
        $pokegab    =  Pokegabs::where(
            [
                'id'   =>  $pokegab_id
            ]);

        if($pokegab->count())
        {
            $pokegab            =   $pokegab->first()->toArray();
            $pokegab['type']    =   PokegabTypes::find($pokegab['type'])->name;
            
            $tournament =   Tournament::where([
                                    'month'     =>  date('m'),
                                    'year'      =>  date('Y')
                                ])->first();
            
            $pokegab['fight_status']    =   'Eliminated';
            $pokegab['opponent']        =   'Eliminated';
            $pokegab['next_fight']      =   '1st '.date('F',strtotime('first day of +1 month'));
            $pokegab['opponent_data']   =   [];
            $pokegab['probability']     =   [100,0];

            if( $tournament ){
                $tournament =   $tournament->toArray();
                $pendings   =   TournamentRounds::where([
                                    'tournament_id' =>  $tournament['id'],
                                    'status'        =>  1
                                    ])->count();
                $active     =   $pendings * 2;
                $eliminated =   $tournament['total_players']    -   $active;

                $match      =   TournamentRounds::where([
                                'tournament_id' =>  $tournament['id'],
                                'status'        =>  1
                                ])
                                ->where(function($query) use ($pokegab_id)
                                {
                                    $query->where('player_first', $pokegab_id)
                                    ->orWhere('player_second', $pokegab_id);
                                })
                                ->orderBy('round', 'DESC')
                                ->first();

                if( $match )
                {
                    $match      =   $match->toArray();
                    $nextFight  =   date('d-m-Y 23:59:59');
                    $opponent   =   $match['player_second'];

                    $pokegab['next_fight']      =   'Tomorrow';
                    $pokegab['fight_status']    =   'Active';
                    
                    if( $match['day'] == date('d')){
                        $pokegab['next_fight']    =   'Today';
                    }

                    if( $match['player_second'] == $pokegab_id )
                        $opponent   =   $match['player_first'];
                    
                    if($opponent == 0){
                        //fake user opponet case
                        $opponent   =   (rand(10001,16384));
                    }else{
                        $pokegab['probability'] = $this->fightPokegabs( $pokegab_id, $opponent );
                        $pokegab_second =   Pokegabs::where(
                                            [
                                                'id'   =>  $opponent
                                            ]);
                        if($pokegab_second->count()){
                            $pokegab['opponent_data']    =   $pokegab_second->first()->toArray();
                        }
                    }
                    $pokegab['opponent']    =   $opponent;
                }
            }

            $pokegab['fights']   =   TournamentRounds::where(function($query) use ($pokegab_id)
                                            {
                                                $query->where('player_first', $pokegab_id)
                                                ->orWhere('player_second', $pokegab_id);
                                            })
                                            ->leftJoin('tournament', 'tournament.id', '=', 'tournament_rounds.tournament_id')
                                            ->where('tournament_id', $tournament['id'])
                                            ->orderBy('tournament_id', 'DESC')
                                            ->orderBy('round', 'DESC')
                                            ->get();
            $pokegab['active']      =   $active;
            $pokegab['eliminated']  =   $eliminated;

            $pokegab['nextFight']   =   $this->getTimeDifference(date('d-m-Y h:i:s'), $nextFight);

            return view('website/my_gabimon')->with('data', $pokegab);
        }else{
            return redirect()->back()->withErrors(['msg' => 'Invalid Gabimon.']);
        }
    }

    PUBLIC FUNCTION fight_pokegabs( Request $request )
    {
        $pokegab_first  =   $request->input('first');
        $pokegab_second =   $request->input('second');
        
        $probability = $this->fightPokegabs( $pokegab_first, $pokegab_second );

        $firstData      =   Pokegabs::where(
            [   'id'    =>  $pokegab_first   ])
            ->first();

        $secondData    =    Pokegabs::where(
            [   'id'   =>   $pokegab_second  ])
            ->first();
        
        if(!$firstData || !$secondData){
            return view('website/matchresult')->with('ret', 'Invalid Gabimon');
        }

        $powerData      =   PokegabPower::where([
                            'first_id'  =>  $firstData['type'],
                            'second_id' =>  $secondData['type']
                        ])->first();
        $power  =   $powerData->toArray();

        $firstData['pokegab']   =   $power['probability'];
        $secondData['pokegab']  =   (100 - $power['probability']);

        if(is_array($probability))
        {
            $firstData   =   $firstData->toArray();
            $firstData['probability'] = $probability[0];
            $firstData['type']  =   PokegabTypes::find($firstData['type'])->name;

            $secondData   =   $secondData->toArray();
            $secondData['probability'] = $probability[1];
            $secondData['type']  =   PokegabTypes::find($secondData['type'])->name;

            $data['first']  =   $firstData;
            $data['second'] =   $secondData;

            return view('website/matchresult')->with('data', $data);
        }else{
           return view('website/matchresult')->with('ret', 'Something went wrong!');
        }
    }

    PUBLIC FUNCTION pokegab_power_table_page()
    {
        return view('website/power_table');
    }

    PUBLIC FUNCTION tournament_winners()
    {
        $tournament =   Tournament::select('id','name','month','year')
                                    ->where('status', 2)
                                    ->orderBy('year', 'DESC')
                                    ->orderBy('month', 'DESC')
                                    ->first();
        if( $tournament ){
            $winners    =   TournamentWinners::select('position','winner', 'match_id')
                                                ->where(['tournament_id'   =>  $tournament['id']])
                                                ->orderBy('position', 'ASC')
                                                ->get();
            if( $winners->count()){
                $winners    =   $winners->toArray();
                foreach($winners as $keys => $val){
                    $winners[$keys]['pokegab']  =   Pokegabs::select('number','name','image')->find($val['winner'])->toArray();
                    $winners[$keys]['reward']   =   $this->rewards[$val['position']];
                }
            }
            $tournament['data']   =   $winners;
        }

        return view('website/winners')->with('data', $tournament);
    }
}