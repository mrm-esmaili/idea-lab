<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
                Rule::in([
                    'requested',
                    'contacted',
                    'interested',
                    'rejected',
                    'converted',
                ]),
            ],
        ]);

        $query = Preorder::with([
            'landingPage.idea',
        ])->latest();

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $preorders = $query
            ->paginate(20)
            ->withQueryString();

        return view(
            'admin.preorders.index',
            compact('preorders')
        );
    }

    public function update(Request $request, Preorder $preorder)
    {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'requested',
                    'contacted',
                    'interested',
                    'rejected',
                    'converted',
                ]),
            ],
        ]);

        $preorder->update($data);

        return redirect()
            ->route('admin.preorders.index')
            ->with('success', 'وضعیت درخواست به‌روزرسانی شد.');
    }
}
