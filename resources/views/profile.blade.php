<!-- resources/views/profile.blade.php -->

@extends('layouts.app')

@section('content')
    <link href="{{asset('css/profile.css')}}" rel="stylesheet">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="profile-picture">
                    <!-- Add profile picture here -->
                    <img src="{{ asset('images/profile_picture.jpeg') }}" alt="Profile Picture" class="img-fluid rounded-circle">
                </div>
            </div>
            <div class="col-md-8">
                <h1 class="mb-4">Profile</h1>
                <div class="profile-info">
                    <p><strong>Name:</strong> {{ $user->name }}</p>
                    <p><strong>Email:</strong> {{ $user->email }}</p>
                    <p><strong>User Role:</strong> {{ $user->role }}</p>
                    <!-- Add more user profile information here as needed -->
                </div>
            </div>
        </div>
    </div>
@endsection
