<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesCourseTeacher;
use App\Models\Devoir;
use App\Models\SectionCourse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevoirsController extends Controller
{
    use ResolvesCourseTeacher;

    public function store(Request $request, SectionCourse $sectionCourse): RedirectResponse
    {
        $this->authorizeTeacher($request, $sectionCourse);

        $data = $request->validate([
            'title' => 'required|max:150',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
        ]);

        $data['section_course_id'] = $sectionCourse->id;
        $data['status'] = 'A';
        $data['is_active'] = true;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        Devoir::create($data);

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Devoir ajouté.']);
    }

    public function update(Request $request, Devoir $devoir): RedirectResponse
    {
        $sectionCourse = $devoir->sectionCourse;
        $this->authorizeTeacher($request, $sectionCourse);

        $data = $request->validate([
            'title' => 'sometimes|required|max:150',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|required|date',
        ]);

        $data['updated_by'] = $request->user()->id;
        $devoir->update($data);

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Devoir mis à jour.']);
    }

    public function destroy(Request $request, Devoir $devoir): RedirectResponse
    {
        $sectionCourse = $devoir->sectionCourse;
        $this->authorizeTeacher($request, $sectionCourse);

        $devoir->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $devoir->delete();

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Devoir supprimé.']);
    }

    /**
     * Seul le professeur affecté à ce SectionCourse peut écrire — le gate
     * can-manage de la route laisse passer Power User/Secrétariat aussi,
     * cette vérification les exclut (même mécanique qu'AttendancesController,
     * commit 86ae522).
     */
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
