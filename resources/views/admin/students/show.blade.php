@extends('layouts.app')
@section('title', $student->name)
@section('content')
@php $tz = config('school.timezone'); @endphp
<h1>{{ $student->name }}</h1>
<p class="muted">{{ collect([$student->phone, $student->email, $student->country, $student->student_number])->filter()->join(' · ') }}</p>

<div class="card">
    <h2>Courses</h2>
    <table>
        <tr><th>Course</th><th>Status</th><th>Level</th><th>Teacher</th><th>Days a week</th><th>Old balance</th><th>Change teacher</th></tr>
        @forelse ($student->enrolments as $e)
            <tr>
                <td>{{ $e->course->name }} <span class="muted">({{ $e->course->mode }})</span></td>
                <td><span @class(['pill', 'ok' => $e->status === 'active'])>{{ $e->status === 'active' ? 'studying' : $e->status }}</span>@if ($e->ended_on) <span class="muted">{{ \Illuminate\Support\Carbon::parse($e->ended_on)->format('j M Y') }}</span>@endif</td>
                <td>{{ $e->level?->name }}</td>
                <td>{{ $e->teacher?->name ?? 'Not assigned' }}</td>
                <td>{{ $e->days_per_week ?? '—' }}</td>
                <td>@if ($e->legacy_balance_ugx < 0)<span class="bad">UGX {{ number_format(-$e->legacy_balance_ugx) }} owing</span>@elseif ($e->legacy_balance_ugx > 0)<span class="muted">UGX {{ number_format($e->legacy_balance_ugx) }} credit</span>@else<span class="muted">—</span>@endif</td>
                <td>
                    <form class="row" method="post" action="{{ route('admin.students.teacher', [$student, $e]) }}">@csrf
                        <select name="teacher_id">@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($t->id === $e->teacher_id)>{{ $t->name }}</option>@endforeach</select>
                        <button>Move</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Not on any course yet.</td></tr>
        @endforelse
    </table>
    <form class="row" method="post" action="{{ route('admin.students.enrol', $student) }}" style="margin-top:12px">@csrf
        <div><label>Add a course</label><select name="course_id" required>@foreach ($courses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->mode }})</option>@endforeach</select></div>
        <div><label>Days a week (Zoom)</label><select name="days_per_week"><option value="">—</option><option>2</option><option>3</option><option>5</option></select></div>
        <button>Add course</button>
    </form>
    <p class="muted">A teacher and the class group are picked automatically.</p>
</div>

<div class="card">
    <h2>Subscriptions</h2>
    <table>
        <tr><th>Plan</th><th>From</th><th>Until</th><th>Zoom sessions</th><th>Status</th><th>Note</th></tr>
        @forelse ($student->subscriptions as $s)
            <tr>
                <td>{{ $s->plan->name }}</td>
                <td>{{ $s->starts_at->timezone($tz)->format('j M Y') }}</td>
                <td>{{ $s->ends_at->timezone($tz)->format('j M Y') }}</td>
                <td>{{ $s->sessions_total === null ? '—' : $s->sessionsLeft().' of '.$s->sessions_total.' left' }}</td>
                <td><span @class(['pill', 'ok' => $s->isUsable()])>{{ $s->isUsable() ? 'active' : ($s->starts_at->isFuture() ? 'starts later' : 'ended') }}</span></td>
                <td class="muted">{{ $s->note }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No subscriptions yet.</td></tr>
        @endforelse
    </table>
    <div class="grid" style="margin-top:12px">
        <form method="post" action="{{ route('admin.students.payments', $student) }}">@csrf
            <h2>Record a payment</h2>
            <p><select name="plan_id" required>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} · UGX {{ number_format($p->price_ugx) }}</option>@endforeach</select></p>
            <p class="row"><select name="method"><option value="cash">Cash</option><option value="mtn">MTN Mobile Money</option><option value="airtel">Airtel Money</option><option value="bank">Bank</option><option value="card">Card</option></select>
                <input name="provider_reference" placeholder="Transaction ID"></p>
            <button>Record payment</button>
        </form>
        <form method="post" action="{{ route('admin.students.grant', $student) }}">@csrf
            <h2>Give a free subscription</h2>
            <p><select name="plan_id" required>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></p>
            <p><input name="note" placeholder="Reason, e.g. scholarship" required style="width:100%"></p>
            <button>Give subscription</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Payments</h2>
    <table>
        <tr><th>When</th><th>Plan</th><th>Amount</th><th>Via</th><th>Reference</th><th>Status</th></tr>
        @forelse ($student->payments as $p)
            <tr><td>{{ $p->created_at->timezone($tz)->format('j M Y, H:i') }}</td><td>{{ $p->plan?->name }}</td><td>UGX {{ number_format($p->amount_ugx) }}</td><td>{{ strtoupper($p->method) }}</td><td class="muted">{{ $p->provider_reference ?? $p->reference }}</td><td><span @class(['pill', 'ok' => $p->status === 'paid'])>{{ $p->status }}</span></td></tr>
        @empty
            <tr><td colspan="6" class="muted">No payments yet.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2>Attendance (last 30 marks)</h2>
    <table>
        <tr><th>Date</th><th>Course</th><th>Mark</th><th>How</th></tr>
        @forelse ($attendance as $a)
            <tr><td>{{ $a->date }}</td><td>{{ $a->enrolment->course->name }}</td><td>{{ $a->status }}</td><td class="muted">{{ ['login' => 'signed in', 'zoom' => 'joined Zoom', 'manual' => 'marked by teacher'][$a->source] ?? $a->source }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No attendance yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
