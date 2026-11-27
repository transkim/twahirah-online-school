<form class="row" method="post" action="{{ route('admin.finance.entries.store') }}">@csrf
    <div><label>Account</label><select name="account_id" required>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->type === 'income' ? 'In' : 'Out' }}: {{ $a->fullName() }}</option>@endforeach</select></div>
    <div><label>Amount (UGX)</label><input type="number" name="amount_ugx" min="1" required style="width:130px"></div>
    <div><label>What for</label><input name="description" required maxlength="191"></div>
    <div><label>Date</label><input type="date" name="entry_date" value="{{ now(config('school.timezone'))->toDateString() }}" required></div>
    <button>Record</button>
</form>
