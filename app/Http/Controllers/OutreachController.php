<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OutreachContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachController extends Controller
{
    public const PHASES = [
        'list'        => ['label' => '未着手',     'color' => 'gray'],
        'attacked'    => ['label' => 'アタック済', 'color' => 'blue'],
        'negotiating' => ['label' => '商談中',     'color' => 'amber'],
        'contracted'  => ['label' => '成約',       'color' => 'green'],
        'rejected'    => ['label' => '見送り',     'color' => 'red'],
        'hold'        => ['label' => '保留',       'color' => 'gray'],
    ];

    public const CONTACT_METHODS = [
        'email' => 'メール',
        'phone' => '電話',
        'form'  => 'フォーム',
        'visit' => '訪問',
        'other' => 'その他',
    ];

    public function index(): View
    {
        $allContacts = OutreachContact::query()
            ->with([
                'company.industry',
                'company.municipality.prefecture',
                'company.primaryDomain',
                'company.scores' => fn ($q) => $q->where('algo_version', 'v1'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('company_id')
            ->map(fn ($contacts) => $contacts->first());

        $companiesByPhase = collect(array_keys(self::PHASES))
            ->mapWithKeys(fn ($phase) => [$phase => collect()]);

        $today = now()->toDateString();

        foreach ($allContacts as $contact) {
            $company = $contact->company;
            if (!$company) {
                continue;
            }

            $scores = $company->scores->keyBy('axis');
            $opp    = (optional($scores->get('hp_weakness'))->value ?? 0)
                    + (optional($scores->get('self_update_fit'))->value ?? 0);
            $risk   = (optional($scores->get('dev_difficulty'))->value ?? 0)
                    + (optional($scores->get('portal_dependence'))->value ?? 0);

            $company->setAttribute('opportunity_score', $opp);
            $company->setAttribute('risk_score', $risk);
            $company->setAttribute('total_score', $opp + $risk);
            $company->setAttribute('latest_outreach', $contact);
            $company->setAttribute('next_action_overdue',
                $contact->next_action_at && $contact->next_action_at->toDateString() < $today
            );

            $phase = $contact->phase ?? 'list';
            if ($companiesByPhase->has($phase)) {
                $companiesByPhase[$phase]->push($company);
            }
        }

        $phaseCounts = $companiesByPhase->map(fn ($items) => $items->count());

        return view('outreach.index', compact('companiesByPhase', 'phaseCounts'));
    }

    public function updatePhase(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'phase' => ['required', 'string', 'in:' . implode(',', array_keys(self::PHASES))],
        ]);

        OutreachContact::create([
            'company_id' => $company->id,
            'phase'      => $validated['phase'],
            'created_by' => auth()->user()?->email,
        ]);

        return back()->with('status', 'フェーズを「' . self::PHASES[$validated['phase']]['label'] . '」に変更しました。');
    }

    public function storeContact(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'phase'          => ['required', 'string', 'in:' . implode(',', array_keys(self::PHASES))],
            'contact_method' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::CONTACT_METHODS))],
            'contacted_at'   => ['nullable', 'date'],
            'next_action'    => ['nullable', 'string', 'max:255'],
            'next_action_at' => ['nullable', 'date'],
            'memo'           => ['nullable', 'string', 'max:5000'],
        ]);

        OutreachContact::create([
            ...$validated,
            'company_id' => $company->id,
            'created_by' => auth()->user()?->email,
        ]);

        return back()->with('status', 'コンタクト記録を保存しました。');
    }

    public function destroyContact(Request $request, Company $company, OutreachContact $contact): RedirectResponse
    {
        abort_if($contact->company_id !== $company->id, 403);
        $contact->delete();

        return back()->with('status', 'コンタクト記録を削除しました。');
    }
}
