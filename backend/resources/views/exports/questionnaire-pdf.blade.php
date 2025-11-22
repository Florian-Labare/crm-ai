<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; color: #4338ca; margin-bottom: 10px; }
        h2 { font-size: 16px; color: #4b5563; margin-top: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background: #f3f4f6; text-transform: uppercase; font-size: 11px; }
        .muted { color: #6b7280; font-size: 12px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <h1>Questionnaire de risque - {{ $client->prenom }} {{ strtoupper($client->nom) }}</h1>
    <p class="muted">Généré le {{ now()->format('d/m/Y à H:i') }}</p>

    <table>
        <tr>
            <th>Score global</th>
            <th>Profil calculé</th>
            <th>Recommandation</th>
        </tr>
        <tr>
            <td>{{ $questionnaire->score_global }} / 100</td>
            <td>{{ $questionnaire->profil_calcule }}</td>
            <td>{{ $questionnaire->recommandation }}</td>
        </tr>
    </table>

    @if(!empty($financierResponses))
        <h2>Réponses comportementales</h2>
        <table>
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody>
                @foreach($financierResponses as $item)
                    <tr>
                        <td>{{ $item['question'] }}</td>
                        <td>{{ $item['answer'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($connaissanceResponses))
        <h2>Connaissances produits</h2>
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody>
                @foreach($connaissanceResponses as $item)
                    <tr>
                        <td>{{ $item['question'] }}</td>
                        <td>{{ $item['answer'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($quizResponses))
        <h2>Quiz</h2>
        <table>
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quizResponses as $item)
                    <tr>
                        <td>{{ $item['question'] }}</td>
                        <td>{{ $item['answer'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted">Score quiz : {{ $questionnaire->quiz->score_quiz ?? 0 }} / 100</p>
    @endif
</body>
</html>
