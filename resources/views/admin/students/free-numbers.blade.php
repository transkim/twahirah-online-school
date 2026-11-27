@extends('layouts.app')
@section('title', 'Free Student Numbers')
@section('content')
<div class="card">
    <p>The next new student gets <b>{{ $next }}</b>.</p>
    <h2>Numbers not in use this year ({{ $free->count() }})</h2>
    <p>{{ $free->isEmpty() ? 'None. Every number up to the highest is in use.' : $free->join(', ') }}</p>
</div>
@endsection
