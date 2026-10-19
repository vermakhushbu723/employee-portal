<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Sends each user to their own portal's dashboard. */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        return redirect()->route($request->user()->homeRoute());
    }
}
