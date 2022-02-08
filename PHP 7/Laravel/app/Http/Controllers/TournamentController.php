<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
//models
use App\Models\Tournament;

class TournamentController extends Controller
{
    PUBLIC FUNCTION get_tournaments(Request $request)
    {

        $filter = $request->query('filter');

        if (!empty($filter)) {
            $data = Tournament::sortable()
                        ->where('name', 'like', '%'.$filter.'%')
			->orderBy('id', 'DESC')
                        ->paginate(5);
        } else {
            $data = Tournament::sortable()->orderBy('id', 'DESC')->paginate(5);
        }

        return view('website/tournaments')->with('data', $data)->with('filter', $filter);
    }
}
