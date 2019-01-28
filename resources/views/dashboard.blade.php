@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">

        <div class="col-md-6 col-md-offset-1">
            <div class="panel panel-default">
                <div class="panel-heading">Dashboard</div>

                <div class="panel-body">
                    <h4>Hello <strong>{{ auth()->user()->name }}</strong>, nice to see you again!</h4>
                    
                    {{-- Show fitbit owner id --}}
                    @if( $accessToken )
                        <h5>FitBit Resource ID: <strong>{{ $accessToken->resource_owner_id }}</strong></h5>
                    @endif


                        {{-- This is where we output a message depending on whether the user lost weight or not --}}
                        {{-- check the status of the weight check that we did --}}
                        @if( ! empty( $data ) )
                            <hr />
                                {{-- check the status of the $data result --}}
                                @if( $data['status'] === false ) 
                                    {{-- We do not have a loss in weight --}}
                                    {{-- So let us display the details of why --}}
                                    <h4>{{ $data['details'] }}</h4>
                                @else
                                    {{-- We have a weight loss --}}
                                    {{-- Let us display a proper response --}}
                                    <h2>Congratulations, you lost weight!</h2>
                                    <p>Current Weight: <strong>{{ $data['most_recent']->weight }}KG</strong></p>
                                    <p>Previous Weight: <strong>{{ $data['previous']->weight }}KG</strong></p>
                                @endif
                            <hr />
                        @endif

                </div>

                <div class="panel-footer">
                    Joined On: {{ Auth::user()->created_at->toDayDateTimeString() }}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Actions
                </div>
                
                <ul class="list-group">
                    {{-- Check to see if the user has authorized our app --}}
                    @if( $accessToken )
                        {{-- The user has authorized our app, let's show the Deauthorize Application and Weight Logs link --}}
                        <a href="{{ url('/de-authorize') }}" class="list-group-item" id="deauthorize">
                            <i class="fa fa-stop"></i> De-Authorize Application
                        </a>
                    @else
                        {{-- The user has not authorized us, let's show the link for him or her to do so --}}
                        <a href="{{ url('/authorize') }}" class="list-group-item">
                            <i class="fa fa-play"></i> Authorize Application
                        </a>
                    @endif

                    <a href="{{ url('/my-weight-logs') }}" class="list-group-item">
                        <i class="fa fa-history"></i> My Weight Logs
                    </a>

                    <a href="{{ route('logout') }}" class="list-group-item" 
                        onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                        <i class="fa fa-power-off"></i> Logout
                    </a>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection
