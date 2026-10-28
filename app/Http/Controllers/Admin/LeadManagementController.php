<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        ]);

        $query = Lead::query()
            ->with('landingPage.idea')
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['campaign'])) {
            $query->where(
                'campaign',
                $filters['campaign']
            );
        }

        $leads = $query->paginate(20)->withQueryString();

        return view('admin.leads.index', compact('leads'));
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
