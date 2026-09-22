<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesCourseTeacher;
use App\Models\CourseResource;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseResourcesController extends Controller
{
    use ResolvesCourseTeacher;

    public function store(Request $request, SectionCourse $sectionCourse): RedirectResponse
    {
        $this->authorizeTeacher($request, $sectionCourse);

        $data = $request->validate([
            'title' => 'required|max:150',
            'type' => 'required|in:file,link',
            'description' => 'nullable|string',
            'url' => 'required_if:type,link|nullable|url',
            'attachment' => 'required_if:type,file|nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $payload = [
            'section_course_id' => $sectionCourse->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'status' => 'A',
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ];

        if ($data['type'] === 'link') {
            $payload['url'] = $data['url'];
        } else {
            $payload['attachment_path'] = $request->file('attachment')->store('course-resources', 'local');
            $payload['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
        }

        CourseResource::create($payload);

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Ressource ajoutée.']);
    }

    public function destroy(Request $request, CourseResource $courseResource): RedirectResponse
    {
        $sectionCourse = $courseResource->sectionCourse;
        $this->authorizeTeacher($request, $sectionCourse);

        if ($courseResource->attachment_path) {
            Storage::disk('local')->delete($courseResource->attachment_path);
        }

        $courseResource->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $courseResource->delete();

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Ressource supprimée.']);
    }

    /** Rôles voyant toute ressource, sans restriction — même liste que SectionCoursesController::MANAGE_ROLES. */
    private const MANAGE_ROLES = ['Power User', 'Secrétariat', 'Directeur'];

    /**
     * Même portée de lecture que SectionCoursesController::show() pour ce
     * SectionCourse — un fichier ne doit jamais être atteignable par une URL
     * devinée si la page qui le liste, elle, est verrouillée. Dupliquer la
     * même règle ici (plutôt que par un des liens qu'elle checke déjà) parce
     * que ce endpoint n'a pas de dépendance sur show() dans la chaîne
     * d'appel HTTP.
     */
    public function downloadAttachment(Request $request, CourseResource $courseResource): StreamedResponse
    {
        abort_unless(
            $courseResource->attachment_path && Storage::disk('local')->exists($courseResource->attachment_path),
            404
        );

        $schoolId = session('active_school_id');
        $currentRole = $request->user()->activeRoleAt($schoolId ?? 0);
        $usr = $request->user()->scopedUserSchoolRole($schoolId ?? 0);
        $sectionCourse = $courseResource->sectionCourse;

        if ($currentRole === 'Professeur') {
            abort_unless($usr && $this->professorTeachesSectionCourse($sectionCourse, $usr), 403);
        } elseif (! in_array($currentRole, self::MANAGE_ROLES, true)) {
            $mySectionIds = $usr
                ? SectionUserSchoolRole::where('user_school_role_id', $usr->id)->pluck('section_id')
                : collect();
            abort_unless($mySectionIds->contains($sectionCourse->sectionUser?->section_id), 403);
        }

        return Storage::disk('local')->download($courseResource->attachment_path, $courseResource->attachment_original_name);
    }

    private function authorizeTeacher(Request $request, SectionCourse $sectionCourse): void
    {
        $schoolId = session('active_school_id');
        $usr = $request->user()->scopedUserSchoolRole($schoolId ?? 0);
        $currentRole = $request->user()->activeRoleAt($schoolId ?? 0);

        abort_unless(
            $currentRole === 'Professeur' && $usr && $this->professorTeachesSectionCourse($sectionCourse, $usr),
            403
        );
    }
}
