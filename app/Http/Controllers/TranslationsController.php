<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TranslationsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Translation::query()
            ->when($request->language_code, fn ($q) => $q->where('language_code', $request->language_code))
            ->when($request->screen_name,   fn ($q) => $q->where('screen_name', 'like', "%{$request->screen_name}%"))
            ->when($request->search,        fn ($q) => $q->where('tag_key', 'like', "%{$request->search}%"))
            ->orderBy('language_code')
            ->orderBy('tag_key');

        return Inertia::render('admin/web/Translations/Index', [
            'translations' => $query->paginate(30)->withQueryString(),
            'filters'      => $request->only(['language_code', 'screen_name', 'search']),
            'languages'    => Translation::distinct()->orderBy('language_code')->pluck('language_code'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/web/Translations/Create', [
            'languages' => Translation::distinct()->orderBy('language_code')->pluck('language_code'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tag_key'          => ['required', 'string', 'max:100',
                Rule::unique('translations')->whereNull('deleted_at')
                    ->where('language_code', $request->language_code),
            ],
            'language_code'    => 'required|string|max:10',
            'translated_value' => 'required|string',
            'screen_name'      => 'nullable|string|max:100',
            'description'      => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active']  = true;

        Translation::create($data);
        Cache::forget("translations.{$data['language_code']}");

        return redirect()->route('translations.index')
            ->with('flash', ['type' => 'success', 'message' => 'Traduction créée.']);
    }

    public function edit(Translation $translation): Response
    {
        return Inertia::render('admin/web/Translations/Edit', [
            'translation' => $translation,
            'languages'   => Translation::distinct()->orderBy('language_code')->pluck('language_code'),
        ]);
    }

    public function update(Request $request, Translation $translation)
    {
        $data = $request->validate([
            'tag_key'          => ['required', 'string', 'max:100',
                Rule::unique('translations')->ignore($translation->id)->whereNull('deleted_at')
                    ->where('language_code', $request->language_code ?? $translation->language_code),
            ],
            'language_code'    => 'required|string|max:10',
            'translated_value' => 'required|string',
            'screen_name'      => 'nullable|string|max:100',
            'description'      => 'nullable|string',
            'is_active'        => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;

        // Invalider les deux locales si language_code change
        $oldLocale = $translation->language_code;
        $translation->update($data);
        Cache::forget("translations.{$oldLocale}");
        Cache::forget("translations.{$data['language_code']}");

        return redirect()->route('translations.index')
            ->with('flash', ['type' => 'success', 'message' => 'Traduction mise à jour.']);
    }

    public function destroy(Translation $translation)
    {
        $locale = $translation->language_code;
        $translation->update(['updated_by' => request()->user()->id]);
        $translation->delete();
        Cache::forget("translations.{$locale}");

        return redirect()->route('translations.index')
            ->with('flash', ['type' => 'success', 'message' => 'Traduction supprimée.']);
    }
}
