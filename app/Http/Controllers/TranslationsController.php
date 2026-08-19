<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\Request;
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

        // Check if a soft-deleted row exists with the same tag_key + language_code
        $softDeleted = Translation::withTrashed()
            ->where('tag_key', $data['tag_key'])
            ->where('language_code', $data['language_code'])
            ->whereNotNull('deleted_at')
            ->first();

        if ($softDeleted) {
            // Restore and update the soft-deleted row, preserving its original creator
            $softDeleted->restore();
            unset($data['created_by']);
            $data['updated_by'] = $request->user()->id;
            $softDeleted->update($data);
        } else {
            // Create a new row
            Translation::create($data);
        }

        TranslationService::clearCache($data['language_code']);

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

        $oldLocale = $translation->language_code;

        // A different soft-deleted row may already occupy the new tag_key + language_code.
        // It's invisible to every read path, so hard-deleting it loses nothing and lets the
        // edited row keep its own identity instead of swapping ids with the trashed row.
        Translation::withTrashed()
            ->where('tag_key', $data['tag_key'])
            ->where('language_code', $data['language_code'])
            ->where('id', '!=', $translation->id)
            ->whereNotNull('deleted_at')
            ->first()
            ?->forceDelete();

        $translation->update($data);

        TranslationService::clearCache($oldLocale);
        TranslationService::clearCache($data['language_code']);

        return redirect()->route('translations.index')
            ->with('flash', ['type' => 'success', 'message' => 'Traduction mise à jour.']);
    }

    public function destroy(Translation $translation)
    {
        $locale = $translation->language_code;
        $translation->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $translation->delete();
        TranslationService::clearCache($locale);

        return redirect()->route('translations.index')
            ->with('flash', ['type' => 'success', 'message' => 'Traduction supprimée.']);
    }
}
