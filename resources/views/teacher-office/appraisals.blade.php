@extends('layouts.app')
@section('title', 'Appraisals')
@section('content')
<div class="card">
    <h2>Teachers, last 90 days</h2>
    <table>
        <tr><th>Teacher</th><th>Live classes held</th><th>Student rating</th><th>Work plans</th></tr>
        @foreach ($teachers as $t)
            <tr><td>{{ $t->name }}</td><td>{{ $t->classes_held }}</td><td>{{ $t->rating ? number_format($t->rating, 1).' / 5 ('.$t->ratings.')' : '—' }}</td><td>{{ $t->plans }}</td></tr>
        @endforeach
    </table>
</div>
<div class="card">
    <h2>Write an appraisal</h2>
    <form method="post" action="{{ route('admin.appraisals.store') }}">@csrf
        <div class="row">
            <div><label>Teacher</label><select name="teacher_id">@foreach ($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
            <div><label>Period</label><input name="period" value="{{ now()->year }} Term {{ ceil(now()->month / 3) }}" required></div>
            <div><label>Rating</label><select name="rating">@foreach ([5 => 'Excellent', 4 => 'Very good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $n => $l)<option value="{{ $n }}">{{ $n }} · {{ $l }}</option>@endforeach</select></div>
        </div>
        <p><label>Strengths</label><textarea name="strengths" rows="2" style="width:100%"></textarea></p>
        <p><label>To improve</label><textarea name="to_improve" rows="2" style="width:100%"></textarea></p>
        <button>Save appraisal</button>
    </form>
</div>
<div class="card">
    <h2>Past appraisals</h2>
    <table>@forelse ($appraisals as $a)<tr><td>{{ $a->teacher->name }}</td><td>{{ $a->period }}</td><td>{{ $a->rating }}/5</td><td class="muted">{{ $a->strengths }} {{ $a->to_improve ? '· To improve: '.$a->to_improve : '' }}</td></tr>@empty<tr><td class="muted">None yet.</td></tr>@endforelse</table>
</div>
@endsection
