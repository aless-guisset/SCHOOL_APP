<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #555; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
        th { background: #f2f2f2; }
        .grade { text-align: right; font-weight: bold; }
        .empty { color: #777; font-style: italic; padding: 12px 0; }
    </style>
</head>
<body>
    <h1>Bulletin — {{ $student->userschoolrole?->user?->firstname }} {{ $student->userschoolrole?->user?->lastname }}</h1>
    <div class="subtitle">
        Section : {{ $student->section?->name ?? '—' }}
        &nbsp;·&nbsp;
        Généré le {{ now()->format('d/m/Y') }}
    </div>

    @if ($grades->isEmpty())
        <p class="empty">Aucune note enregistrée.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Période</th>
                    <th>Matière</th>
                    <th style="text-align: right;">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grades as $g)
                    <tr>
                        <td>{{ $g->period }}</td>
                        <td>{{ $g->subject?->name ?? '—' }}</td>
                        <td class="grade">{{ number_format($g->grade, 2) }} / {{ number_format($g->max_grade, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
