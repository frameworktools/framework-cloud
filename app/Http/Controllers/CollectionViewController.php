<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CollectionViewController extends Controller
{
    public function show(Request $request, string $key)
    {
        $collection = Collection::where('key', $key)->whereHas('project')->firstOrFail();
        $owner = $request->user()?->id === $collection->project->user_id;
        abort_if($collection->visibility === 'private' && ! $owner, 404);
        if (! $owner && $collection->isExpired()) {
            return response()->view('collections/expired', ['collection' => $collection], 410)->header('Cache-Control', 'private, no-store');
        }
        if (! $owner && $collection->password_hash && ! hash_equals($collection->accessStamp(), (string) $request->session()->get('collections.'.$collection->id, ''))) {
            return response()->view('collections/password', ['collection' => $collection])->header('Cache-Control', 'private, no-store');
        }
        $items = $collection->items()->with(['document.project.user', 'collection'])->get()
            ->filter(fn ($item) => $item->isShareable($owner))->values();

        return response()->view('collections/show', compact('collection', 'items'))->header('Cache-Control', 'private, no-store');
    }

    public function unlock(Request $request, string $key)
    {
        $collection = Collection::where('key', $key)->whereHas('project')->firstOrFail();
        abort_if($collection->visibility === 'private', 404);
        if ($collection->isExpired()) {
            return redirect($collection->shareUrl());
        }
        $data = $request->validate(['password' => ['required', 'string', 'max:255']]);
        if (! $collection->password_hash || ! Hash::check($data['password'], $collection->password_hash)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }
        $request->session()->put('collections.'.$collection->id, $collection->accessStamp());

        return redirect($collection->shareUrl());
    }
}
