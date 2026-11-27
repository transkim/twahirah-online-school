@extends('layouts.auth')
@section('title', 'Check a certificate')
@section('heading', 'Check a certificate')
@section('subheading', 'Certificate code '.strtoupper($code))
@section('content')
    @if ($certificate)
        <div class="notice">This certificate is real. It was given to <b>{{ $certificate->student->name }}</b> for {{ $certificate->course->name }}{{ $certificate->level ? ', '.$certificate->level->name : '' }} on {{ $certificate->issued_at->format('j F Y') }}.</div>
    @else
        <div class="notice">No certificate has this code.</div>
    @endif
@endsection
