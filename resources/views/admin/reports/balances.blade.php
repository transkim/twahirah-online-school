@extends('layouts.app')
@section('title', 'Fees Balances')
@section('content')
<div class="card stat red" style="max-width:360px"><span>Owed from the old portal</span><b>UGX {{ number_format($total) }}</b><small>{{ $rows->total() }} students</small></div>
<div class="card">
    <table>
        <tr><th>Student</th><th>Number</th><th>Phone</th><th>Courses</th><th>Owes (UGX)</th></tr>
        @foreach ($rows as $r)
            @php $s = $students[$r->student_id] ?? null; @endphp
            <tr><td>@if ($s)<a href="{{ route('admin.students.show', $s) }}">{{ $s->name }}</a>@endif</td><td>{{ $s?->student_number }}</td><td>{{ $s?->phone }}</td><td>{{ $r->courses }}</td><td class="bad">{{ number_format(-$r->owed) }}</td></tr>
        @endforeach
    </table>
    {{ $rows->links() }}
</div>
@endsection
