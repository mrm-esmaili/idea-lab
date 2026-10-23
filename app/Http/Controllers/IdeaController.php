<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdeaController extends Controller
{
    public function index()
    {
        $ideas = Idea::latest()->get();

        return view('ideas.index', compact('ideas'));
    }

    public function create()
    {
        return view('ideas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($data['name']);

        if (Idea::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }

        $idea = Idea::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'ایده با موفقیت ایجاد شد.');
    }

    public function show(Idea $idea)
    {
        $idea->load('landingPages');

        return view('ideas.show', compact('idea'));
    }
}
