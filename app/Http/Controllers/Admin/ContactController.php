<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Idea;
use App\Services\ContactExporter;
use App\Services\ContactSegment;
use App\Services\ContactTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request, ContactSegment $segment)
    {
        $filters = ContactSegment::clean($request->validate(ContactSegment::validationRules()));

        $contacts = $segment->apply(Contact::query(), $filters)
            ->with('interests:id,name')
            ->withCount('preorders')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $ideas = Idea::orderBy('name')->get(['id', 'name']);

        return view('admin.contacts.index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'ideas' => $ideas,
            'description' => $segment->describe($filters, $ideas->pluck('name', 'id')->all()),
        ]);
    }

    public function export(Request $request, ContactSegment $segment, ContactExporter $exporter)
    {
        $filters = ContactSegment::clean($request->validate(ContactSegment::validationRules() + [
            'consented_only' => ['nullable', 'boolean'],
        ]));

        return $exporter->download(
            $segment->apply(Contact::query(), $filters),
            'contacts',
            $filters,
            $request,
            $request->boolean('consented_only'),
        );
    }

    public function show(Contact $contact, ContactTimeline $timeline)
    {
        $contact->load([
            'interests',
            'groups',
            'firstLandingPage.idea',
            'leads.landingPage.idea',
            'preorders.landingPage.idea',
        ]);

        $dynamicGroups = ContactGroup::where('type', ContactGroup::TYPE_DYNAMIC)
            ->orderBy('name')
            ->get()
            ->filter(fn (ContactGroup $group) => $group->membersQuery()->whereKey($contact->id)->exists());

        return view('admin.contacts.show', [
            'contact' => $contact,
            'timeline' => $timeline->for($contact),
            'dynamicGroups' => $dynamicGroups,
            'manualGroups' => ContactGroup::where('type', ContactGroup::TYPE_MANUAL)->orderBy('name')->get(),
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function addInterest(Request $request, Contact $contact)
    {
        $data = $request->validate(['idea_id' => ['required', 'integer', 'exists:ideas,id']]);

        DB::table('contact_idea_interests')->insertOrIgnore([
            'contact_id' => $contact->id,
            'idea_id' => $data['idea_id'],
            'source' => 'admin',
            'created_at' => now(),
        ]);

        return back()->with('success', 'علاقه به ایده ثبت شد.');
    }

    public function removeInterest(Contact $contact, Idea $idea)
    {
        $contact->interests()->detach($idea->id);

        return back()->with('success', "علاقه به «{$idea->name}» حذف شد.");
    }

    public function addToGroup(Request $request, Contact $contact)
    {
        $data = $request->validate(['contact_group_id' => ['required', 'integer', 'exists:contact_groups,id']]);

        $group = ContactGroup::findOrFail($data['contact_group_id']);

        if ($group->isDynamic()) {
            return back()->withErrors(['contact_group_id' => 'عضویت گروه پویا با قواعد آن تعیین می‌شود و دستی قابل افزودن نیست.']);
        }

        $group->manualMembers()->syncWithoutDetaching([
            $contact->id => ['added_by' => $request->user()->id, 'created_at' => now()],
        ]);

        return back()->with('success', "به گروه «{$group->name}» اضافه شد.");
    }

    public function removeFromGroup(Contact $contact, ContactGroup $group)
    {
        $group->manualMembers()->detach($contact->id);

        return back()->with('success', "از گروه «{$group->name}» حذف شد.");
    }
}
