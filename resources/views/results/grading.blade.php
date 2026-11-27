@extends('layouts.app')
@section('title', 'Grading Settings')
@section('content')
<div class="card" style="max-width:640px">
    <h2>Grades</h2>
    <p class="muted">A score gets the highest grade whose starting mark it reaches. One grade must start at 0.</p>
    <form method="post" action="{{ route('admin.grading.update') }}">@csrf @method('put')
        <table>
            <tr><th>Grade</th><th>From mark</th><th>Comment on report cards</th></tr>
            @foreach ($bands->concat(array_fill(0, 2, null)) as $i => $b)
                <tr><td><input name="bands[{{ $i }}][name]" value="{{ $b?->name }}" style="width:70px"></td>
                    <td><input type="number" name="bands[{{ $i }}][min_score]" value="{{ $b?->min_score }}" min="0" max="100" style="width:90px"></td>
                    <td><input name="bands[{{ $i }}][comment]" value="{{ $b?->comment }}" style="width:100%"></td></tr>
            @endforeach
        </table>
        <p style="margin-top:10px"><button>Save grades</button></p>
    </form>
    <p class="muted">Report card totals count exams {{ $weights['exam'] }}%, homework {{ $weights['homework'] }}% and teacher assessments {{ $weights['assessment'] }}%.</p>
</div>
@endsection
