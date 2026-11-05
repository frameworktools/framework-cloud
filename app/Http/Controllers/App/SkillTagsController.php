<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SkillTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SkillTagsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Skills/Tags', ['tags' => SkillTag::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('skill_tags', 'name')]]);
        $key = Str::slug($data['name']);
        validator(['key' => $key], ['key' => ['required', Rule::unique('skill_tags')]])->validate();
        SkillTag::create($data + ['key' => $key, 'position' => (SkillTag::max('position') ?? -1) + 1]);

        return back();
    }

    public function update(Request $request, SkillTag $tag): RedirectResponse
    {
        $tag->update($request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('skill_tags')->ignore($tag->id)]]));

        return back();
    }
}
