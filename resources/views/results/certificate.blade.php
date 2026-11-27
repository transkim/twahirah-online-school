@extends('layouts.app')
@section('title', 'Certificate')
@section('content')
<style>
    .cert { max-width:820px; margin:0 auto; text-align:center; padding:48px 40px; border:6px double #2d8c1e; background:#fffdf6; color:#1d2b1a; }
    .cert h1 { font-size:34px; margin:10px 0; color:#1a3d12; } .cert .name { font-size:28px; font-weight:700; margin:18px 0; border-bottom:1px solid #c9a227; display:inline-block; padding:0 30px 6px; }
    @media print { .side, .top, .titlebar, .noprint { display:none !important; } .shell { display:block; } main { padding:0 !important; } }
</style>
<p class="noprint"><button onclick="window.print()">Print or save as PDF</button></p>
<div class="cert">
    <img src="{{ asset('images/logo.png') }}" alt="" style="height:80px">
    <div>{{ config('school.name') }}</div>
    <h1>Certificate of Achievement</h1>
    <div>This is to certify that</div>
    <div class="name">{{ $certificate->student->name }}</div>
    <div>has successfully passed <b>{{ $certificate->exam?->title ?? $certificate->course->name }}</b><br>
        in {{ $certificate->course->name }}{{ $certificate->level ? ', '.$certificate->level->name : '' }}{{ $certificate->score !== null ? ' with '.$certificate->score.'%' : '' }}.</div>
    <p style="margin-top:28px">{{ $certificate->issued_at->format('j F Y') }}</p>
    <p class="muted" style="font-size:12px">Certificate code {{ $certificate->code }} · check it at {{ route('certificates.verify', $certificate->code) }}</p>
</div>
@endsection
