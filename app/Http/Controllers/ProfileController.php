<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public  function show(){
        //display the profile information
        $user = Auth::user();

        //pass to the view
        return view('profile', [
            'user' => $user,
        ]);
    }

    public function update( Request $request ){
        //validation first
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        //get the authenticated user
        $user = Auth::user();

        $user->update([
            'name' => $request->input('name'),
        ]);

        //redirect back to profile page
        return redirect()->route('profile')->with('success', 'Profile Updated Successfully');

    }
}
