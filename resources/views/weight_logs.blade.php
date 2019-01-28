@php
	use Carbon\Carbon;

    $dataArr = $weight_logs->toArray();
@endphp

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">

        <div class="col-md-6 col-md-offset-3">

        	<div class="panel panel-default">
        		<div class="panel-heading">
        			<a href="{{ url('/dashboard') }}" class="btn btn-link">
        				<i class="fa fa-arrow-left"></i>
        			</a> My Weight Logs &nbsp;&nbsp;

                    {{-- Show record count information if we have records to display --}}
                    @if( count( $weight_logs ) > 0 )
                        <em class="pull-right">
                            Showing {{ $dataArr['from'] }} to {{ $dataArr['to'] }} of {{ $dataArr['total'] }} total
                        </em>
                    @endif
        		</div>

        		{{-- Show the logs if we have some weight logs to display --}}
        		@if( count( $weight_logs ) > 0 )
        			{{-- We have weight logs, let's display them --}}
        			<div class="table-responsive">
        				<table class="table table-hover table-bordered">
        					<thead>
        						<tr>
        							<th style="width: 15%;">#</th><th>Weight</th><th style="width: 50%;">Date</th>
        						</tr>
        					</thead>
        					<tbody>
        						@foreach( $weight_logs as $i => $log )
        							<tr>
        								<td>{{ ( $i + 1 ) }}</td>
        								<td><strong>{{ $log->weight }} kg, {{ $log->pounds}} lbs</strong></td>

        								{{-- Show a formatted version of the time the weight log was recorded using the Carbon library that ships with Laravel --}}
        								<td>{{ Carbon::parse( $log->created_at )->toDayDateTimeString() }}</td>
        							</tr>
        						@endforeach
        					</tbody>
        				</table>
        			</div>

                    @if( $weight_logs->hasMorePages() )
                        {{-- Show the links for the more pages if there are more than 50 records --}}
                        <div class="panel-footer">
                            <div class="text-center">
                                {{ $weight_logs->links() }}
                            </div>
                        </div>
                    @endif
        		@else
        			{{-- We have nothing to display --}}
        			<div class="panel-body">
        				<h3 class="text-danger">
        					<em>No weight log to display at the moment.</em>
        				</h3>
        			</div>
        		@endif

        	</div>

        </div>
    </div>
</div>
@endsection
