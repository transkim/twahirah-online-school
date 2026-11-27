<div><label>Course and level</label>
    <select name="course_level_pick" onchange="const [c, l] = this.value.split(':'); this.form.course_id.value = c; this.form.course_level_id.value = l || '';" required>
        <option value="">Choose…</option>
        @foreach ($courses as $c)
            <option value="{{ $c->id }}:">{{ $c->name }} ({{ $c->mode === 'zoom' ? 'Zoom' : 'Chat' }}) · all levels</option>
            @if ($c->levels->count() > 1)
                @foreach ($c->levels as $l)<option value="{{ $c->id }}:{{ $l->id }}">{{ $c->name }} ({{ $c->mode === 'zoom' ? 'Zoom' : 'Chat' }}) · {{ $l->name }}</option>@endforeach
            @endif
        @endforeach
    </select>
    <input type="hidden" name="course_id"><input type="hidden" name="course_level_id">
</div>
