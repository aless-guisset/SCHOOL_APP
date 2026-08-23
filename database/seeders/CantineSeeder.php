<?php

namespace Database\Seeders;

use App\Models\CantineMenu;
use App\Models\CantineOrder;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder additif, idempotent : active le module cantine sur les écoles qui
 * ont des élèves, publie un menu à 2 options sur les jours récurrents [1,3,5]
 * pour les 2 dernières semaines, puis fait commander une partie des élèves.
 * Rien n'est dupliqué (contraintes uniques + recherche explicite par date) et
 * aucun user/école existant n'est modifié à part le flag cantine_enabled.
 */
class CantineSeeder extends Seeder
{
    private const DAYS = [1, 3, 5]; // lundi, mercredi, vendredi

    private const LABELS = ['Plat A', 'Plat B'];

    public function run(): void
    {
        $schools = School::where('is_active', true)->get();
        $menus = 0;
        $orders = 0;

        foreach ($schools as $school) {
            $students = SectionUserSchoolRole::where('is_active', true)
                ->whereHas('userschoolrole', fn ($q) => $q->where('school_id', $school->id)
                    ->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            $school->update(['cantine_enabled' => true]);

            foreach ([2, 1] as $weeksAgo) {
                foreach (self::DAYS as $day) {
                    $date = Carbon::now()->subWeeks($weeksAgo)->startOfWeek(Carbon::MONDAY)->addDays($day - 1);

                    // Recherche explicite par date plutôt que firstOrCreate() : sur la
                    // connexion SQLite de ce projet, le cast 'date' ne tronque pas la
                    // valeur stockée à Y-m-d, donc la recherche par égalité stricte de
                    // firstOrCreate() ne matche pas les lignes existantes lors d'un
                    // second run (doublons, puis UniqueConstraintViolationException sur
                    // cantine_orders.unique(['section_user_id', 'date'])).
                    $dayMenus = collect(self::LABELS)->map(function ($label) use ($school, $date, &$menus) {
                        $menu = CantineMenu::where('school_id', $school->id)
                            ->whereDate('date', $date->toDateString())
                            ->where('label', $label)
                            ->first();

                        if (! $menu) {
                            $menu = CantineMenu::create([
                                'school_id' => $school->id,
                                'date' => $date->toDateString(),
                                'label' => $label,
                                'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                            ]);
                        }

                        $menus++;

                        return $menu;
                    });

                    foreach ($students as $student) {
                        // Participation déterministe (hash élève+date) plutôt qu'un
                        // tirage fake() ré-évalué à chaque run : sinon un élève exclu
                        // lors d'un run finit, par pur hasard, par être inclus au run
                        // suivant, et une nouvelle CantineOrder est créée pour lui à
                        // chaque exécution — ce qui casse l'idempotence du seeder.
                        $participates = (crc32($student->id.'|'.$date->toDateString()) % 100) < 60;

                        if (! $participates) {
                            continue;
                        }

                        $order = CantineOrder::where('section_user_id', $student->id)
                            ->whereDate('date', $date->toDateString())
                            ->first();

                        if ($order) {
                            continue;
                        }

                        $isPresent = fake()->boolean(90);

                        CantineOrder::create([
                            'section_user_id' => $student->id,
                            'cantine_menu_id' => $dayMenus->random()->id,
                            'date' => $date->toDateString(),
                            'is_present' => $isPresent,
                            'note' => $isPresent ? null : 'Absent, non signalé',
                            'status' => 'A', 'is_active' => true, 'created_by' => 1, 'updated_by' => 1,
                        ]);
                        $orders++;
                    }
                }
            }
        }

        $this->command?->info("CantineSeeder : {$menus} option(s) de menu, {$orders} commande(s) traitée(s).");
    }
}
