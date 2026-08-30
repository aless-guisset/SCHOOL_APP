<?php

namespace App\Http\Controllers;

use App\Models\ParentStudentLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParentAccessController extends Controller
{
    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['link_id' => 'required|integer']);

        $link = ParentStudentLink::where('id', $data['link_id'])
            ->where('status', 'A')->where('is_active', true)
            ->whereHas('parentUserSchoolRole', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        abort_unless($link, 403);

        session(['active_child_link_id' => $link->id]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Enfant actif changé.']);
    }
}
