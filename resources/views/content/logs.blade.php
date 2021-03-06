@extends('layouts.main')

@section('title', "/{$board->board_uri}/ Staff Logs")
@section('description', $board->descriptios)

@section('content')
<main class="board-logs">
	<section class="board-logs-table grid-container">
		<div class="smooth-box">
			<table class="logs-table">
				<colgroup>
					<col style="width: 12.5em;" />
					<col style="min-width: 5em;" />
					<col style="width: auto;" />
				</colgrop>
				<thead>
					<tr>
						<th>Time</th>
						<th>User</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($logs as $log)
					<tr>
						<td><time datetime="{{ $log->created_at }}">{{ $log->created_at }}</time></td>
						<td>{{ $log->user->username }}</td>
						<td>@lang($log->action_name, json_decode($log->action_details, true))</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</section>
</main>
@stop

@section('footer')
	@include('nav.boardpages')
@stop