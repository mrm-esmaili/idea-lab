<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadManagementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => [
                'nullable',
                Rule::in(array_keys(config('statuses.lead'))),
            ],
            'campaign' => ['nullable', 'string', 'max:255'],
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
            'intent' => ['nullable', Rule::in(array_keys(Lead::INTENTS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Lead::query()
            ->with(['landingPage.idea', 'contact'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['intent'])) {
            $query->where('intent', $filters['intent']);
        }

        if (! empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('phone', 'like', $term));
        }

        if (! empty($filters['idea'])) {
            $query->whereHas(
                'landingPage',
                fn ($page) => $page->where('idea_id', $filters['idea'])
            );
        }

        if (! empty($filters['campaign'])) {
            $query->where(
                'campaign',
                $filters['campaign']
            );
        }

        $leads = $query->paginate(20)->withQueryString();

        $ideas = Idea::orderBy('name')->get(['id', 'name']);

        return view('admin.leads.index', compact('leads', 'ideas'));
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(array_keys(config('statuses.lead'))),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead->update($data);

        return redirect()
            ->route('admin.leads.index')
            ->with('success', 'اطلاعات لید به‌روزرسانی شد.');
    }
}
