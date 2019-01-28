{{ Request::header('Content-Type : application/xml') }}
@php
use Carbon\Carbon;
$dataArr = $weight_logs->toArray();
@endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>Jazmy Fitbit Weight status</title>
		<link>https://fitbit.jazmy.com</link>
		<description>Fitbit Weight Loss Tracker</description>
		@if( count( $weight_logs ) > 0 )
		@foreach( $weight_logs as $i => $log )
		<item>
			<title>{{ Carbon::parse( $log->created_at )->toDayDateTimeString() }} Status</title>
			<link>https://fitbit.jazmy.com/my-weight-logs</link>
			<description>{{ $log->status }}</description>
			<pubDate>{{ date('D, d M Y H:i:s', strtotime($log->created_at)) }} GMT</pubDate>
			<guid>https://fitbit.jazmy.com/my-weight-logs/{{ $i }}</guid>
		</item>
		@endforeach
		@endif
	</channel>
</rss>
