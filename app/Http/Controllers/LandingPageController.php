<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\LandingPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LandingPageController extends Controller
{
    public function create(Idea $idea)
    {
        return view('landing-pages.create', compact('idea'));
    }

    public function store(Request $request, Idea $idea)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cta_text' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = Str::slug($data['name']);

        if (LandingPage::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }

        $landingPage = $idea->landingPages()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cta_text' => $data['cta_text'] ?? null,
            'content' => [],
            'is_active' => true,
        ]);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'Landing Page ساخته شد.');
    }
}
