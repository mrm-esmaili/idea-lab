<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\Idea;
use App\Services\ContactExporter;
use App\Services\ContactSegment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactGroupController extends Controller
{
    public function index()
    {
        $groups = ContactGroup::orderBy('name')->get()
            ->each(fn (ContactGroup $group) => $group->setAttribute('member_count', $group->membersQuery()->count()));

        return view('admin.groups.index', compact('groups'));
    }

    public function create(Request $request)
    {
        // از فیلترهای فهرست مخاطبان می‌توان مستقیم گروه پویا ساخت
        $rules = ContactSegment::clean($request->validate(ContactSegment::validationRules()));

        $group = new ContactGroup([
            'type' => $rules ? ContactGroup::TYPE_DYNAMIC : ContactGroup::TYPE_MANUAL,
            'rules' => $rules,
        ]);

        return view('admin.groups.create', [
            'group' => $group,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $group = ContactGroup::create($this->validated($request) + [
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.groups.show', $group)
            ->with('success', "گروه «{$group->name}» ساخته شد.");
    }

    public function show(ContactGroup $group, ContactSegment $segment)
    {
        $members = $group->membersQuery()
            ->with('interests:id,name')
            ->orderByDesc('contacts.id')
            ->paginate(30);

        $ideas = Idea::pluck('name', 'id')->all();

        return view('admin.groups.show', [
            'group' => $group,
            'members' => $members,
            'description' => $group->isDynamic() ? $segment->describe($group->rules ?? [], $ideas) : [],
        ]);
    }

    public function edit(ContactGroup $group)
    {
        return view('admin.groups.edit', [
            'group' => $group,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ContactGroup $group)
    {
        $data = $this->validated($request);

        if ($data['type'] !== $group->type) {
            throw ValidationException::withMessages(['type' => 'نوع گروه پس از ساخت قابل تغییر نیست.']);
        }

        $group->update($data);

        return redirect()
            ->route('admin.groups.show', $group)
            ->with('success', 'گروه به‌روزرسانی شد.');
    }

    public function destroy(ContactGroup $group)
    {
        $group->delete();

        return redirect()
            ->route('admin.groups.index')
            ->with('success', "گروه «{$group->name}» حذف شد. مخاطبان حذف نمی‌شوند.");
    }

    public function export(Request $request, ContactGroup $group, ContactExporter $exporter)
    {
        $request->validate(['consented_only' => ['nullable', 'boolean']]);

        return $exporter->download(
            $group->membersQuery(),
            'group:' . $group->id,
            ['group' => $group->name],
            $request,
            $request->boolean('consented_only'),
        );
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in([ContactGroup::TYPE_MANUAL, ContactGroup::TYPE_DYNAMIC])],
            'rules' => ['nullable', 'array'],
        ] + collect(ContactSegment::validationRules())
            ->except('q')
            ->mapWithKeys(fn ($rule, $key) => ["rules.{$key}" => $rule])
            ->all());

        $rules = ContactSegment::clean($data['rules'] ?? []);
        unset($rules['q']);

        if ($data['type'] === ContactGroup::TYPE_DYNAMIC && $rules === []) {
            throw ValidationException::withMessages(['rules' => 'گروه پویا دست‌کم یک قاعده لازم دارد؛ بدون قاعده همه مخاطبان را شامل می‌شود.']);
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'rules' => $data['type'] === ContactGroup::TYPE_DYNAMIC ? $rules : null,
        ];
    }
}
