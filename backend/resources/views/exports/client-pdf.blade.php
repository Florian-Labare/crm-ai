<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Client - {{ $client->prenom }} {{ $client->nom }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            color: #1F2937;
            line-height: 1.6;
            padding: 20px;
        }

        .header {
            text-align: center;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
        }

        .header h1 {
            font-size: 24pt;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10pt;
            opacity: 0.9;
        }

        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section-title {
            background-color: #EEF2FF;
            color: #312E81;
            font-size: 14pt;
            font-weight: bold;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-left: 4px solid #4F46E5;
        }

        .subsection-title {
            color: #6366F1;
            font-size: 12pt;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #E0E7FF;
        }

        .field {
            margin-bottom: 8px;
            padding: 5px 0;
        }

        .field-label {
            font-weight: bold;
            color: #374151;
            display: inline-block;
            width: 180px;
        }

        .field-value {
            color: #1F2937;
            display: inline;
        }

        .grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .grid-row {
            display: table-row;
        }

        .grid-cell {
            display: table-cell;
            padding: 5px 10px 5px 0;
            width: 50%;
        }

        .besoins-list {
            margin-left: 20px;
            margin-top: 10px;
        }

        .besoins-list li {
            margin-bottom: 8px;
            padding-left: 5px;
            color: #1F2937;
        }

        .notes-box {
            background-color: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 9pt;
            color: #6B7280;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #DBEAFE;
            color: #1E40AF;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: bold;
        }

        .badge-green {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .badge-red {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #E5E7EB;
        }

        th {
            background-color: #F3F4F6;
            font-weight: bold;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎧 WHISPER CRM</h1>
        <p>Fiche Client</p>
    </div>

    <!-- IDENTITÉ -->
    <div class="section">
        <div class="section-title">IDENTITÉ</div>
        <div class="grid">
            @if($client->civilite)
            <div class="field">
                <span class="field-label">Civilité :</span>
                <span class="field-value">{{ $client->civilite }}</span>
            </div>
            @endif
            @if($client->nom)
            <div class="field">
                <span class="field-label">Nom :</span>
                <span class="field-value">{{ strtoupper($client->nom) }}</span>
            </div>
            @endif
            @if($client->nom_jeune_fille)
            <div class="field">
                <span class="field-label">Nom de jeune fille :</span>
                <span class="field-value">{{ strtoupper($client->nom_jeune_fille) }}</span>
            </div>
            @endif
            @if($client->prenom)
            <div class="field">
                <span class="field-label">Prénom :</span>
                <span class="field-value">{{ $client->prenom }}</span>
            </div>
            @endif
            @if($client->datedenaissance)
            <div class="field">
                <span class="field-label">Date de naissance :</span>
                <span class="field-value">{{ \Carbon\Carbon::parse($client->datedenaissance)->format('d/m/Y') }}</span>
            </div>
            @endif
            @if($client->lieudenaissance)
            <div class="field">
                <span class="field-label">Lieu de naissance :</span>
                <span class="field-value">{{ $client->lieudenaissance }}</span>
            </div>
            @endif
            @if($client->nationalite)
            <div class="field">
                <span class="field-label">Nationalité :</span>
                <span class="field-value">{{ $client->nationalite }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- COORDONNÉES -->
    <div class="section">
        <div class="section-title">COORDONNÉES</div>
        @if($client->adresse)
        <div class="field">
            <span class="field-label">Adresse :</span>
            <span class="field-value">{{ $client->adresse }}</span>
        </div>
        @endif
        @if($client->code_postal)
        <div class="field">
            <span class="field-label">Code postal :</span>
            <span class="field-value">{{ $client->code_postal }}</span>
        </div>
        @endif
        @if($client->ville)
        <div class="field">
            <span class="field-label">Ville :</span>
            <span class="field-value">{{ $client->ville }}</span>
        </div>
        @endif
        @if($client->residence_fiscale)
        <div class="field">
            <span class="field-label">Résidence fiscale :</span>
            <span class="field-value">{{ $client->residence_fiscale }}</span>
        </div>
        @endif
        @if($client->telephone)
        <div class="field">
            <span class="field-label">Téléphone :</span>
            <span class="field-value">{{ $client->telephone }}</span>
        </div>
        @endif
        @if($client->email)
        <div class="field">
            <span class="field-label">Email :</span>
            <span class="field-value">{{ $client->email }}</span>
        </div>
        @endif
    </div>

    <!-- SITUATION PERSONNELLE -->
    <div class="section">
        <div class="section-title">SITUATION PERSONNELLE</div>
        @if($client->situationmatrimoniale)
        <div class="field">
            <span class="field-label">Situation matrimoniale :</span>
            <span class="field-value">{{ $client->situationmatrimoniale }}</span>
        </div>
        @endif
        @if($client->date_situation_matrimoniale)
        <div class="field">
            <span class="field-label">Date :</span>
            <span class="field-value">{{ \Carbon\Carbon::parse($client->date_situation_matrimoniale)->format('d/m/Y') }}</span>
        </div>
        @endif
        @if($client->situation_actuelle)
        <div class="field">
            <span class="field-label">Situation actuelle :</span>
            <span class="field-value">{{ $client->situation_actuelle }}</span>
        </div>
        @endif
        @if($client->nombreenfants !== null)
        <div class="field">
            <span class="field-label">Nombre d'enfants :</span>
            <span class="field-value">{{ $client->nombreenfants }}</span>
        </div>
        @endif

        @if($client->conjoint)
        <div class="subsection-title">Conjoint</div>
        @if($client->conjoint->nom)
        <div class="field">
            <span class="field-label">Nom :</span>
            <span class="field-value">{{ strtoupper($client->conjoint->nom) }}</span>
        </div>
        @endif
        @if($client->conjoint->prenom)
        <div class="field">
            <span class="field-label">Prénom :</span>
            <span class="field-value">{{ $client->conjoint->prenom }}</span>
        </div>
        @endif
        @if($client->conjoint->datedenaissance)
        <div class="field">
            <span class="field-label">Date de naissance :</span>
            <span class="field-value">{{ \Carbon\Carbon::parse($client->conjoint->datedenaissance)->format('d/m/Y') }}</span>
        </div>
        @endif
        @endif

        @if($client->enfants && $client->enfants->count() > 0)
        <div class="subsection-title">Enfants</div>
        <table>
            <thead>
                <tr>
                    <th>Prénom</th>
                    <th>Date de naissance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($client->enfants as $enfant)
                <tr>
                    <td>{{ $enfant->prenom }}</td>
                    <td>{{ $enfant->datedenaissance ? \Carbon\Carbon::parse($enfant->datedenaissance)->format('d/m/Y') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <!-- SITUATION PROFESSIONNELLE -->
    <div class="section">
        <div class="section-title">SITUATION PROFESSIONNELLE</div>
        @if($client->profession)
        <div class="field">
            <span class="field-label">Profession :</span>
            <span class="field-value">{{ $client->profession }}</span>
        </div>
        @endif
        @if($client->date_evenement_professionnel)
        <div class="field">
            <span class="field-label">Date événement :</span>
            <span class="field-value">{{ \Carbon\Carbon::parse($client->date_evenement_professionnel)->format('d/m/Y') }}</span>
        </div>
        @endif
        @if($client->revenusannuels)
        <div class="field">
            <span class="field-label">Revenus annuels :</span>
            <span class="field-value">{{ number_format($client->revenusannuels, 0, ',', ' ') }} €</span>
        </div>
        @endif
        <div class="field">
            <span class="field-label">Risques professionnels :</span>
            <span class="field-value">
                @if($client->risques_professionnels)
                    <span class="badge badge-red">Oui</span>
                @else
                    <span class="badge badge-green">Non</span>
                @endif
            </span>
        </div>
        @if($client->details_risques_professionnels)
        <div class="field">
            <span class="field-label">Détails risques :</span>
            <span class="field-value">{{ $client->details_risques_professionnels }}</span>
        </div>
        @endif
        @if($client->charge_clientele)
        <div class="field">
            <span class="field-label">Charge clientèle :</span>
            <span class="field-value">{{ $client->charge_clientele }}</span>
        </div>
        @endif

        @if($client->entreprise)
        <div class="subsection-title">Entreprise</div>
        @if($client->entreprise->nom)
        <div class="field">
            <span class="field-label">Nom :</span>
            <span class="field-value">{{ $client->entreprise->nom }}</span>
        </div>
        @endif
        @if($client->entreprise->forme_juridique)
        <div class="field">
            <span class="field-label">Forme juridique :</span>
            <span class="field-value">{{ $client->entreprise->forme_juridique }}</span>
        </div>
        @endif
        @if($client->entreprise->siret)
        <div class="field">
            <span class="field-label">SIRET :</span>
            <span class="field-value">{{ $client->entreprise->siret }}</span>
        </div>
        @endif
        @endif
    </div>

    <!-- MODE DE VIE -->
    <div class="section">
        <div class="section-title">MODE DE VIE</div>
        <div class="field">
            <span class="field-label">Fumeur :</span>
            <span class="field-value">
                @if($client->fumeur)
                    <span class="badge badge-red">Oui</span>
                @else
                    <span class="badge badge-green">Non</span>
                @endif
            </span>
        </div>
        <div class="field">
            <span class="field-label">Activités sportives :</span>
            <span class="field-value">
                @if($client->activites_sportives)
                    <span class="badge badge-green">Oui</span>
                @else
                    <span class="badge">Non</span>
                @endif
            </span>
        </div>
        @if($client->details_activites_sportives)
        <div class="field">
            <span class="field-label">Détails activités :</span>
            <span class="field-value">{{ $client->details_activites_sportives }}</span>
        </div>
        @endif
        @if($client->niveau_activites_sportives)
        <div class="field">
            <span class="field-label">Niveau :</span>
            <span class="field-value">{{ $client->niveau_activites_sportives }}</span>
        </div>
        @endif
    </div>

    <!-- BESOINS -->
    @if($client->besoins && is_array($client->besoins) && count($client->besoins) > 0)
    <div class="section">
        <div class="section-title">BESOINS IDENTIFIÉS</div>
        <ul class="besoins-list">
            @foreach($client->besoins as $besoin)
            <li>{{ $besoin }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- SANTÉ ET SOUHAITS -->
    @if($client->santeSouhait)
    <div class="section">
        <div class="section-title">SANTÉ ET SOUHAITS</div>
        @if($client->santeSouhait->contrat_en_place)
        <div class="field">
            <span class="field-label">Contrat en place :</span>
            <span class="field-value">{{ $client->santeSouhait->contrat_en_place }}</span>
        </div>
        @endif
        @if($client->santeSouhait->budget_mensuel_maximum)
        <div class="field">
            <span class="field-label">Budget mensuel maximum :</span>
            <span class="field-value">{{ number_format($client->santeSouhait->budget_mensuel_maximum, 0, ',', ' ') }} €</span>
        </div>
        @endif
        @if($client->santeSouhait->niveau_hospitalisation)
        <div class="field">
            <span class="field-label">Niveau hospitalisation :</span>
            <span class="field-value">{{ $client->santeSouhait->niveau_hospitalisation }}/10</span>
        </div>
        @endif
        @if($client->santeSouhait->niveau_dentaire)
        <div class="field">
            <span class="field-label">Niveau dentaire :</span>
            <span class="field-value">{{ $client->santeSouhait->niveau_dentaire }}/10</span>
        </div>
        @endif
        @if($client->santeSouhait->niveau_optique)
        <div class="field">
            <span class="field-label">Niveau optique :</span>
            <span class="field-value">{{ $client->santeSouhait->niveau_optique }}/10</span>
        </div>
        @endif
    </div>
    @endif

    <!-- NOTES -->
    @if($client->notes)
    <div class="section">
        <div class="section-title">NOTES</div>
        <div class="notes-box">
            {{ $client->notes }}
        </div>
    </div>
    @endif

    <div class="footer">
        <p>Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
        <p>Whisper CRM - Fiche client de {{ $client->prenom }} {{ strtoupper($client->nom) }}</p>
    </div>
</body>
</html>
