<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Idea;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ثبت هزینه‌ها (مرحله ۵۶)؛ هر تغییر در سابقه ممیزی ثبت می‌شود.
 */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
            'category' => ['nullable', Rule::in(array_keys(Expense::CATEGORIES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Expense::with(['idea', 'campaign', 'creator'])->latest('incurred_on')->latest('id');

        if (! empty($filters['idea'])) {
            $query->where('idea_id', $filters['idea']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('incurred_on', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('incurred_on', '<=', $filters['to']);
        }

        $total = (int) (clone $query)->sum('amount_rial');

        return view('admin.expenses.index', [
            'expenses' => $query->paginate(30)->withQueryString(),
            'total' => $total,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.expenses.create', $this->formData(new Expense([
            'incurred_on' => now(),
            'cost_type' => 'variable',
            'category' => 'ads',
        ])));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $expense = Expense::create($this->validated($request) + ['created_by' => $request->user()->id]);

        $audit->log($expense, 'expense.created', $expense->only(['idea_id', 'campaign_id', 'category', 'cost_type', 'amount_rial', 'incurred_on', 'description']));

        return redirect()->route('admin.expenses.index')->with('success', 'هزینه ثبت شد.');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expenses.edit', $this->formData($expense));
    }

    public function update(Request $request, Expense $expense, AuditLogger $audit)
    {
        $before = $expense->only(['idea_id', 'campaign_id', 'category', 'cost_type', 'amount_rial', 'description']);
        $before['incurred_on'] = $expense->incurred_on?->toDateString();

        $expense->update($this->validated($request));

        $audit->log($expense, 'expense.updated', [
            'before' => $before,
            'after' => $expense->only(['idea_id', 'campaign_id', 'category', 'cost_type', 'amount_rial', 'description'])
                + ['incurred_on' => $expense->incurred_on->toDateString()],
        ]);

        return redirect()->route('admin.expenses.index')->with('success', 'هزینه به‌روزرسانی شد.');
    }

    public function destroy(Expense $expense, AuditLogger $audit)
    {
        $audit->log($expense, 'expense.deleted', $expense->only(['idea_id', 'campaign_id', 'category', 'amount_rial', 'description'])
            + ['incurred_on' => $expense->incurred_on->toDateString()]);

        $expense->delete();

        return redirect()->route('admin.expenses.index')->with('success', 'هزینه حذف شد.');
    }

    private function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
            'campaigns' => Campaign::with('landingPage:id,idea_id')->orderBy('name')->get(['id', 'name', 'slug', 'landing_page_id']),
        ];
    }

    private function validated(Request $request): array
    {
        $request->merge(['amount_toman' => Money::normalizeInput($request->input('amount_toman'))]);

        $data = $request->validate([
            'idea_id' => ['nullable', 'integer', 'exists:ideas,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'cost_type' => ['required', Rule::in(array_keys(Expense::COST_TYPES))],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ], [], [
            'idea_id' => 'ایده',
            'campaign_id' => 'کمپین',
            'category' => 'دسته',
            'cost_type' => 'نوع هزینه',
            'amount_toman' => 'مبلغ',
            'incurred_on' => 'تاریخ',
            'description' => 'شرح',
        ]);

        // هزینه کمپین به ایده همان کمپین تعلق دارد
        if (! empty($data['campaign_id'])) {
            $campaignIdeaId = Campaign::with('landingPage')->find($data['campaign_id'])->landingPage->idea_id;

            if (! empty($data['idea_id']) && (int) $data['idea_id'] !== $campaignIdeaId) {
                throw ValidationException::withMessages(['campaign_id' => 'کمپین انتخاب‌شده متعلق به این ایده نیست.']);
            }

            $data['idea_id'] = $campaignIdeaId;
        }

        return [
            'idea_id' => $data['idea_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'category' => $data['category'],
            'cost_type' => $data['cost_type'],
            'amount_rial' => Money::tomanToRial((int) $data['amount_toman']),
            'incurred_on' => Carbon::parse($data['incurred_on']),
            'description' => $data['description'],
        ];
    }
}
