<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCanManage;
use App\Models\CantineMenu;
use App\Models\CantineOrder;
use App\Models\CantineTransaction;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CantineController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $date = $request->input('date') ? Carbon::parse($request->input('date'))->toDateString() : Carbon::today()->toDateString();

        $menus = CantineMenu::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'label', 'description']);

        $props = [
            'date' => $date,
            'is_past' => Carbon::parse($date)->lt(Carbon::today()),
            'menus' => $menus,
            // Renseigné uniquement sous as_parent=1 : nom de l'enfant dont on
            // affiche la commande (bandeau + neutralisation des actions
            // d'écriture côté Vue, que le rôle réel autoriserait sinon).
            'viewing_child' => null,
        ];

        $asParent = $request->boolean('as_parent');

        if (! $asParent && $this->userCanManage($schoolId)) {
            $orders = CantineOrder::whereIn('cantine_menu_id', $menus->pluck('id'))
                ->where('is_active', true)
                ->with(['sectionUser.userschoolrole.user', 'sectionUser.section', 'menu'])
                ->get();

            $props['roster'] = $orders->map(fn (CantineOrder $o) => [
                'id' => $o->id,
                'name' => $o->sectionUser?->userschoolrole?->user
                    ? "{$o->sectionUser->userschoolrole->user->lastname} {$o->sectionUser->userschoolrole->user->firstname}"
                    : '—',
                'section' => $o->sectionUser?->section?->name,
                'menu_label' => $o->menu?->label,
                'is_present' => $o->is_present,
                'note' => $o->note,
            ])->sortBy('name')->values();
        } else {
            // currentSectionUser() reste basé sur auth()->id() : c'est ce qui
            // garantit qu'un Parent n'a jamais de $sectionUser via ce chemin.
            // Pour l'AFFICHAGE (voir la commande de l'enfant), on résout
            // séparément l'élève à montrer, par l'un des deux chemins :
            //  - scopedUserSchoolRole() en navigation normale (résout vers
            //    l'enfant seulement si Parent est le rôle le plus privilégié) ;
            //  - parentLinkedStudent() sous as_parent=1, qui force la
            //    résolution enfant même pour un rôle plus privilégié (double
            //    rôle), et ignore alors la propre ligne section_user de
            //    l'appelant (ex: Professeur qui consulte "Mes enfants").
            // can_order reste false dans les deux cas : consulter la commande
            // de son enfant ne donne jamais le droit de commander pour lui.
            $ownSectionUser = $asParent ? null : $this->currentSectionUser($schoolId);
            $scopedUsr = $ownSectionUser
                ? null
                : ($asParent
                    ? $request->user()->parentLinkedStudent($schoolId)
                    : $request->user()->scopedUserSchoolRole($schoolId));
            $displaySectionUser = $ownSectionUser
                ?? ($scopedUsr ? $scopedUsr->sectionUserRoles()->first() : null);

            $props['can_order'] = (bool) $ownSectionUser;
            $props['viewing_child'] = $asParent && $scopedUsr?->user
                ? "{$scopedUsr->user->firstname} {$scopedUsr->user->lastname}"
                : null;
            $props['my_order'] = $displaySectionUser
                ? CantineOrder::where('section_user_id', $displaySectionUser->id)
                    ->whereDate('date', $date)
                    ->where('is_active', true)
                    ->first(['id', 'cantine_menu_id'])
                : null;
            $props['balance'] = $displaySectionUser ? $displaySectionUser->cantineBalance() : null;
            $props['meal_price'] = School::find($schoolId)?->cantine_meal_price;
            $props['can_top_up'] = $asParent && (bool) $displaySectionUser;
        }

        return Inertia::render('power-user/web/Cantine/Index', $props);
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $data = $request->validate([
            'date' => 'required|date',
            'label' => 'required|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['date'] = Carbon::parse($data['date'])->toDateString();

        $existing = CantineMenu::withTrashed()
            ->where('school_id', $schoolId)
            ->whereDate('date', $data['date'])
            ->where('label', $data['label'])
            ->first();

        if ($existing && ! $existing->trashed()) {
            return back()->withErrors(['label' => 'Cette option de menu existe déjà pour cette date.']);
        }

        if ($existing && $existing->trashed()) {
            $existing->restore();
            $existing->update([
                'description' => $data['description'] ?? null,
                'is_active' => true,
                'updated_by' => $request->user()->id,
            ]);
        } else {
            $data['school_id'] = $schoolId;
            $data['is_active'] = true;
            $data['created_by'] = $request->user()->id;
            CantineMenu::create($data);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Option de menu ajoutée.']);
    }

    public function updatePrice(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $data = $request->validate(['cantine_meal_price' => 'required|numeric|min:0|max:9999.99']);

        School::whereKey($schoolId)->update([
            'cantine_meal_price' => $data['cantine_meal_price'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Prix du repas mis à jour.']);
    }

    public function destroyMenu(CantineMenu $cantineMenu): RedirectResponse
    {
        abort_unless($cantineMenu->school_id == session('active_school_id'), 404);

        $cantineMenu->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $cantineMenu->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Option de menu supprimée.']);
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $sectionUser = $this->currentSectionUser($schoolId);
        abort_unless($sectionUser, 403);

        $data = $request->validate([
            'cantine_menu_id' => ['required', 'integer', Rule::exists('cantine_menus', 'id')->where('school_id', $schoolId)],
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        $menu = CantineMenu::findOrFail($data['cantine_menu_id']);
        abort_unless($menu->date->toDateString() === $date, 422);

        $existingOrder = CantineOrder::withTrashed()
            ->where('section_user_id', $sectionUser->id)
            ->whereDate('date', $date)
            ->first();

        // Changer de choix le même jour, sur une commande déjà active, ne
        // redébite pas : le repas du jour est déjà payé, seul le menu change.
        if ($existingOrder && ! $existingOrder->trashed()) {
            $existingOrder->update([
                'cantine_menu_id' => $menu->id,
                'updated_by' => $request->user()->id,
            ]);

            return back()->with('flash', ['type' => 'success', 'message' => 'Commande enregistrée.']);
        }

        $price = School::find($schoolId)?->cantine_meal_price;
        if ($price === null) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "Le prix du repas n'a pas encore été configuré par l'établissement.",
            ]);
        }

        if ($sectionUser->cantineBalance() < $price) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Solde insuffisant pour commander ce repas — demandez une recharge à votre parent.',
            ]);
        }

        DB::transaction(function () use ($existingOrder, $sectionUser, $menu, $date, $price, $request) {
            if ($existingOrder && $existingOrder->trashed()) {
                $existingOrder->restore();
                $existingOrder->update([
                    'cantine_menu_id' => $menu->id,
                    'is_active' => true,
                    'updated_by' => $request->user()->id,
                ]);
                $order = $existingOrder;
            } else {
                $order = CantineOrder::create([
                    'section_user_id' => $sectionUser->id,
                    'cantine_menu_id' => $menu->id,
                    'date' => $date,
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            CantineTransaction::create([
                'section_user_id' => $sectionUser->id,
                'type' => 'order_debit',
                'amount' => -$price,
                'cantine_order_id' => $order->id,
                'is_active' => true,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Commande enregistrée.']);
    }

    public function destroyOrder(CantineOrder $cantineOrder): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $sectionUser = $this->currentSectionUser($schoolId);

        abort_unless($sectionUser && $cantineOrder->section_user_id === $sectionUser->id, 403);
        abort_if(Carbon::parse($cantineOrder->date)->lt(Carbon::today()), 422);

        DB::transaction(function () use ($cantineOrder, $sectionUser) {
            $cantineOrder->delete();

            // Rembourse EXACTEMENT ce qui a été débité pour cette commande —
            // jamais le prix actuel de l'école, qui a pu changer entre-temps
            // (sinon un changement de tarif corromprait silencieusement le
            // registre en sur- ou sous-remboursant).
            $debited = CantineTransaction::where('cantine_order_id', $cantineOrder->id)
                ->where('type', 'order_debit')
                ->where('is_active', true)
                ->latest('id')
                ->value('amount');

            if ($debited !== null) {
                CantineTransaction::create([
                    'section_user_id' => $sectionUser->id,
                    'type' => 'order_refund',
                    'amount' => -$debited,
                    'cantine_order_id' => $cantineOrder->id,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Commande annulée.']);
    }

    public function storePresences(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $dateData = $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($dateData['date'])->toDateString();
        abort_if(Carbon::parse($date)->gt(Carbon::today()), 422);

        $validOrderIds = CantineOrder::whereHas('menu', fn ($q) => $q->where('school_id', $schoolId))
            ->whereDate('date', $date)
            ->pluck('id')->all();

        $data = $request->validate([
            'presences' => 'required|array',
            'presences.*.cantine_order_id' => ['required', 'integer', 'in:'.implode(',', $validOrderIds ?: [0])],
            'presences.*.is_present' => 'required|boolean',
            'presences.*.note' => 'nullable|string|max:1000',
        ]);

        foreach ($data['presences'] as $row) {
            CantineOrder::whereKey($row['cantine_order_id'])->update([
                'is_present' => $row['is_present'],
                'note' => $row['note'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Présences cantine enregistrées.']);
    }

    /**
     * Résout le `SectionUserSchoolRole` (ligne "élève dans une section") de
     * l'utilisateur authentifié pour l'école active — `null` s'il n'en a pas
     * (pas un élève de cette école, ex: Directeur). Jamais dérivé d'un champ
     * de la requête : c'est ce qui garantit qu'un élève ne peut agir que sur
     * sa propre commande.
     */
    private function currentSectionUser(?int $schoolId): ?SectionUserSchoolRole
    {
        return SectionUserSchoolRole::whereHas(
            'userschoolrole',
            fn ($q) => $q->where('user_id', auth()->id())->where('school_id', $schoolId)
        )->first();
    }

    /** Même liste de rôles que EnsureCanManage — décide quoi renvoyer dans index(), pas un contrôle d'accès en soi. */
    private function userCanManage(?int $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        $role = auth()->user()->activeRoleAt($schoolId);

        return in_array($role, EnsureCanManage::MANAGE_ROLES, true);
    }

    private function abortUnlessCantineEnabled(?int $schoolId): void
    {
        abort_unless(
            $schoolId && School::where('id', $schoolId)->where('cantine_enabled', true)->exists(),
            404
        );
    }
}
