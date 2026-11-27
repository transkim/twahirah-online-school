@extends('layouts.app')
@section('title', 'System Settings')
@section('content')
<div class="card" style="max-width:720px">
    <h2>School settings</h2>
    <form method="post" action="{{ route('admin.settings.update') }}">@csrf @method('put')
        <table>
            @foreach ($fields as $key => $f)
                <tr><td><b>{{ $f['label'] }}</b>@if ($f['help'])<br><span class="muted">{{ $f['help'] }}</span>@endif</td>
                    <td><input type="number" name="{{ str_replace('.', '__', $key) }}" value="{{ config($key) }}" min="{{ $f['min'] }}" max="{{ $f['max'] }}" required style="width:150px"></td></tr>
            @endforeach
        </table>
        <p style="margin-top:12px"><button>Save settings</button></p>
    </form>
    <p class="muted">Connections such as the SMS account, Mobile Money, Zoom and the AI helper key are set on the server by the developer.</p>
</div>
@endsection
