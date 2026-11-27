<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\FeeDiscount;
use App\Models\LedgerEntry;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\SchoolNotice;
use App\Services\PasswordCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The school's books: accounts and sub-accounts, money in and out (including Daawah),
 * one-off fees, discounts, requisitions and financial statements.
 */
class FinanceController extends Controller
{
    /** The one-off fees the office collects, by their account code. */
    private const FEES = [
        'registration' => ['code' => 'registration_fees', 'title' => 'Registration Fees', 'setting' => 'school.registration_fee_ugx'],
        'certificate' => ['code' => 'certificate_fees', 'title' => 'Certificate Fees', 'setting' => 'school.certificate_fee_ugx'],
    ];

    public function accounts(): View
    {
        $totals = LedgerEntry::selectRaw('account_id, sum(amount_ugx) as total')->groupBy('account_id')->pluck('total', 'account_id');

        return view('admin.finance.accounts', [
            'accounts' => Account::with('children')->whereNull('parent_id')->orderBy('type')->orderBy('name')->get(),
            'totals' => $totals,
            'entries' => LedgerEntry::with('account.parent', 'recorder', 'student')->latest('entry_date')->latest('id')->limit(50)->get(),
            'all' => $this->accountOptions(),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:income,expense'],
            'parent_id' => ['nullable', 'exists:accounts,id'],
        ]);
        if ($data['parent_id'] ?? null) {
            $data['type'] = Account::findOrFail($data['parent_id'])->type;
        }
        Account::create($data);

        return back()->with('status', 'Account added.');
    }

    /** Record money received or spent. */
    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'amount_ugx' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:191'],
            'entry_date' => ['required', 'date'],
        ]);
        LedgerEntry::create([...$data, 'recorded_by' => $request->user()->id]);

        return back()->with('status', 'Recorded.');
    }

    public function daawah(): View
    {
        $ids = Account::whereIn('code', ['daawah_income', 'daawah_expense'])->pluck('id');
        $accountIds = Account::whereIn('id', $ids)->orWhereIn('parent_id', $ids)->pluck('id');
        $entries = LedgerEntry::with('account.parent', 'recorder')->whereIn('account_id', $accountIds)->latest('entry_date')->latest('id')->limit(200)->get();

        return view('admin.finance.daawah', [
            'entries' => $entries,
            'in' => LedgerEntry::whereIn('account_id', $accountIds)->whereHas('account', fn ($q) => $q->where('type', 'income'))->sum('amount_ugx'),
            'out' => LedgerEntry::whereIn('account_id', $accountIds)->whereHas('account', fn ($q) => $q->where('type', 'expense'))->sum('amount_ugx'),
            'accounts' => Account::with('parent')->whereIn('id', $accountIds)->get(),
        ]);
    }

    public function statements(Request $request): View
    {
        $tz = config('school.timezone');
        $from = $request->date('from', 'Y-m-d', $tz) ?? now($tz)->startOfMonth();
        $to = $request->date('to', 'Y-m-d', $tz) ?? now($tz);

        $rows = LedgerEntry::with('account.parent')
            ->whereDate('entry_date', '>=', $from->toDateString())->whereDate('entry_date', '<=', $to->toDateString())
            ->get()->groupBy('account_id')
            ->map(fn ($list) => ['account' => $list->first()->account, 'total' => $list->sum('amount_ugx')])
            ->sortBy(fn ($r) => $r['account']->fullName());

        return view('admin.finance.statements', [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'income' => $rows->filter(fn ($r) => $r['account']->type === 'income'),
            'expense' => $rows->filter(fn ($r) => $r['account']->type === 'expense'),
        ]);
    }

    public function fees(string $kind): View
    {
        $fee = self::FEES[$kind] ?? abort(404);
        $account = Account::byCode($fee['code']);

        return view('admin.finance.fees', [
            'kind' => $kind, 'fee' => $fee, 'amount' => config($fee['setting']),
            'entries' => LedgerEntry::with('student', 'recorder')->where('account_id', $account->id)->latest('entry_date')->latest('id')->paginate(50),
        ]);
    }

    public function storeFee(Request $request, string $kind, PasswordCodeService $people): RedirectResponse
    {
        $fee = self::FEES[$kind] ?? abort(404);
        $data = $request->validate(['login' => ['required', 'string', 'max:191'], 'amount_ugx' => ['required', 'integer', 'min:1'], 'entry_date' => ['required', 'date']]);

        $students = $people->accountsFor($data['login'])->where('role', User::ROLE_STUDENT);
        if ($students->count() !== 1) {
            return back()->withErrors(['login' => $students->isEmpty() ? 'No student found.' : 'More than one student found. Use the student number.'])->withInput();
        }
        $student = $students->first();

        LedgerEntry::create([
            'account_id' => Account::byCode($fee['code'])->id, 'amount_ugx' => $data['amount_ugx'], 'entry_date' => $data['entry_date'],
            'student_id' => $student->id, 'description' => $fee['title'].': '.$student->name, 'recorded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Recorded UGX '.number_format($data['amount_ugx'])." from {$student->name}.");
    }

    public function discounts(): View
    {
        return view('admin.finance.discounts', ['discounts' => FeeDiscount::with('student')->latest()->paginate(50)]);
    }

    public function storeDiscount(Request $request, PasswordCodeService $people): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'percent' => ['required', 'integer', 'between:1,100'],
            'reason' => ['required', 'string', 'max:191'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $students = $people->accountsFor($data['login'])->where('role', User::ROLE_STUDENT);
        if ($students->isEmpty()) {
            return back()->withErrors(['login' => 'No student found.'])->withInput();
        }

        foreach ($students as $student) {
            FeeDiscount::create(['student_id' => $student->id, 'percent' => $data['percent'], 'reason' => $data['reason'], 'ends_on' => $data['ends_on'] ?? null, 'created_by' => $request->user()->id]);
        }

        return back()->with('status', "{$data['percent']}% discount given to ".$students->pluck('name')->join(', ').'.');
    }

    public function destroyDiscount(FeeDiscount $discount): RedirectResponse
    {
        $discount->delete();

        return back()->with('status', 'Discount removed.');
    }

    public function requisitions(Request $request): View
    {
        $user = $request->user();

        return view('admin.finance.requisitions', [
            'requisitions' => Requisition::with('requester', 'account.parent')
                ->when(! $user->isAdmin() && $user->role !== User::ROLE_FINANCE, fn ($q) => $q->where('requested_by', $user->id))
                ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")->latest()->limit(100)->get(),
            'accounts' => $this->accountOptions('expense'),
            'canDecide' => $user->isAdmin(),
            'canPay' => in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true),
        ]);
    }

    public function storeRequisition(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'details' => ['nullable', 'string', 'max:5000'],
            'amount_ugx' => ['required', 'integer', 'min:1'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('type', 'expense')],
        ]);
        Requisition::create([...$data, 'requested_by' => $request->user()->id]);

        return back()->with('status', 'Requisition sent for approval.');
    }

    /** Approve or decline (administrators), then pay out (administrators or finance), which records the spending. */
    public function decideRequisition(Request $request, Requisition $requisition): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['action' => ['required', 'in:approve,decline,pay']]);

        if (in_array($data['action'], ['approve', 'decline'], true)) {
            abort_unless($user->isAdmin() && $requisition->status === 'pending', 403);
            $requisition->update(['status' => $data['action'] === 'approve' ? 'approved' : 'declined', 'decided_by' => $user->id]);
        } else {
            abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true) && $requisition->status === 'approved', 403);
            DB::transaction(function () use ($requisition, $user) {
                $entry = LedgerEntry::create([
                    'account_id' => $requisition->account_id, 'amount_ugx' => $requisition->amount_ugx, 'entry_date' => now(config('school.timezone'))->toDateString(),
                    'description' => 'Requisition: '.$requisition->title, 'recorded_by' => $user->id,
                ]);
                $requisition->update(['status' => 'paid', 'ledger_entry_id' => $entry->id]);
            });
        }

        $requisition->requester->notify(new SchoolNotice('Requisition '.$requisition->status, $requisition->title, route('requisitions.index')));

        return back()->with('status', "Requisition {$requisition->status}.");
    }

    /** @return Collection<int, Account> */
    private function accountOptions(?string $type = null)
    {
        return Account::with('parent')->when($type, fn ($q) => $q->where('type', $type))->get()->sortBy(fn (Account $a) => $a->type.$a->fullName())->values();
    }
}
