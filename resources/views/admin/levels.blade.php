@extends('layouts.app')
@section('title', 'Levels & Topics')
@section('content')
@foreach ($courses as $c)
    <details class="card">
        <summary><b>{{ $c->name }}</b> <span class="muted">({{ $c->mode }}) · {{ $c->levels->count() }} levels</span></summary>
        @foreach ($c->levels as $l)
            <div style="margin:12px 0 0 12px">
                <b>{{ $l->name }}</b> @if ($l->duration_months)<span class="muted">· about {{ $l->duration_months }} months</span>@endif
                <ol style="margin:4px 0 4px 18px">@foreach ($l->topics as $t)<li>{{ $t->title }} <form class="inline" method="post" action="{{ route('admin.topics.destroy', $t) }}">@csrf @method('delete')<button class="plain">×</button></form></li>@endforeach</ol>
                <form class="row" method="post" action="{{ route('admin.topics.store', $l) }}">@csrf<input name="title" placeholder="New topic" required><button>Add topic</button></form>
            </div>
        @endforeach
        <form class="row" method="post" action="{{ route('admin.levels.store', $c) }}" style="margin-top:14px">@csrf
            <input name="name" placeholder="New level name" required><input type="number" name="duration_months" placeholder="Months" min="1" style="width:100px"><button>Add level</button>
        </form>
    </details>
@endforeach
@endsection
