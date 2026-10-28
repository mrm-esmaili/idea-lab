<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Preorder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreorderManagementController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => [
                'nullable',
                Rule::in(array_keys(config('statuses.preorder'))),
            ],
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
        ]);

        $query = Preorder::with([
            'landingPage.idea',
            'refundPolicy',
        ])->latest();

        if (!empty($data['idea'])) {
            $query->whereHas(
                'landingPage',
                fn ($page) => $page->where('idea_id', $data['idea'])
            );
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $preorders = $query
            ->paginate(20)
            ->withQueryString();

        $ideas = Idea::orderBy('name')->get(['id', 'name']);

        return view(
            'admin.preorders.index',
            compact('preorders', 'ideas')
        );
    }

    public function update(Request $request, Preorder $preorder)
    {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(array_keys(config('statuses.preorder'))),
            ],
        ]);

        $preorder->update($data);

        return redirect()
            ->route('admin.preorders.index')
            ->with('success', 'وضعیت درخواست به‌روزرسانی شد.');
    }
}
