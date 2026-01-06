# Pipeline IA - Traitement Audio & Extraction Données

## Vue d'ensemble

Le pipeline IA est le cœur du système. Il transforme un enregistrement audio brut (conversation courtier-client) en données structurées dans la base de données.

### Flux Complet

```
┌─────────────────┐
│ Audio Record    │ (WebM, 10 min, 10 MB)
│ Frontend Upload │
└────────┬────────┘
         │
         ↓
┌─────────────────────────────────────────────────────────────┐
│                  ÉTAPE 1: TRANSCRIPTION                      │
├─────────────────────────────────────────────────────────────┤
│ Service: TranscriptionService                                │
│ API: OpenAI Whisper ou Whisper local                        │
│ Input: audio.webm                                           │
│ Output: Texte brut (français)                               │
│ Durée: 20-60s pour 10 min audio                            │
└────────┬────────────────────────────────────────────────────┘
         │
         ↓
┌─────────────────────────────────────────────────────────────┐
│              ÉTAPE 2: DIARISATION (Optionnel)                │
├─────────────────────────────────────────────────────────────┤
│ Service: DiarizationService                                  │
│ Modèle: Pyannote.audio 3.1                                  │
│ Input: audio.wav + transcription                            │
│ Output: Transcription formatée avec speakers                │
│   [COURTIER]: Bonjour, comment vous appelez-vous ?         │
│   [CLIENT]: Jean Dupont, né le 15 mai 1980.                │
│ Durée: 30-120s pour 10 min audio                           │
└────────┬────────────────────────────────────────────────────┘
         │
         ↓
┌─────────────────────────────────────────────────────────────┐
│                  ÉTAPE 3: ROUTING                            │
├─────────────────────────────────────────────────────────────┤
│ Service: RouterService                                       │
│ Modèle: GPT-4o-mini (temperature: 0.1)                     │
│ Input: Transcription                                        │
│ Output: ["client", "conjoint", "prevoyance", ...]          │
│ Durée: 1-3s                                                 │
│ Coût: ~$0.0001                                              │
└────────┬────────────────────────────────────────────────────┘
         │
         ↓
┌─────────────────────────────────────────────────────────────┐
│              ÉTAPE 4: EXTRACTION MODULAIRE                   │
├─────────────────────────────────────────────────────────────┤
│ Service: AnalysisService                                     │
│ Pattern: Strategy Pattern (10+ extracteurs)                 │
│                                                              │
│ Pour chaque section détectée:                               │
│   ├─ ClientExtractor        → {nom, prenom, date_naissance} │
│   ├─ ConjointExtractor      → {prenom, profession, ...}    │
│   ├─ PrevoyanceExtractor    → {montant_itt, capital_deces}│
│   ├─ RetraiteExtractor      → {age_depart, tmi, ...}      │
│   ├─ EpargneExtractor       → {capacite_epargne, ...}     │
│   ├─ ClientRevenusExtractor → [{type, montant}, ...]      │
│   └─ ...                                                    │
│                                                              │
│ Durée: 5-15s (parallelisable)                              │
│ Coût: ~$0.001-0.003 (10 extracteurs)                       │
└────────┬────────────────────────────────────────────────────┘
         │
         ↓
┌─────────────────────────────────────────────────────────────┐
│                  ÉTAPE 5: NORMALISATION                      │
├─────────────────────────────────────────────────────────────┤
│ Service: AiDataNormalizer                                    │
│ Transformations:                                             │
│   ├─ Dates: "15/05/1980" → "1980-05-15"                   │
│   ├─ Téléphones: "06 12 34 56 78" → "0612345678"          │
│   ├─ Booléens: "je ne suis PAS fumeur" → false            │
│   ├─ Besoins: mapping mots-clés → valeurs normalisées     │
│   └─ Garde-fou: Nettoyage confusion client/conjoint       │
│                                                              │
│ Durée: <1s                                                  │
└────────┬────────────────────────────────────────────────────┘
         │
         ↓
┌─────────────────────────────────────────────────────────────┐
│                  ÉTAPE 6: SYNCHRONISATION BDD                │
├─────────────────────────────────────────────────────────────┤
│ Services: ClientSyncService, ConjointSyncService, etc.      │
│ Pattern: AbstractSyncService (méthode sync() commune)       │
│                                                              │
│ Pour chaque type de relation:                               │
│   ├─ Client principal: Update or Create                    │
│   ├─ Conjoint: Update or Create (one-to-one)              │
│   ├─ Enfants: Sync array (create/update/delete)           │
│   ├─ Revenus: Sync array (one-to-many)                    │
│   ├─ BAE sections: Update or Create (one-to-one)          │
│   └─ ...                                                    │
│                                                              │
│ Durée: 2-5s                                                 │
└────────┬────────────────────────────────────────────────────┘
         │
         ↓
┌─────────────────┐
│ Client Updated  │
│ Status: 'done'  │
│ Frontend: Toast │
└─────────────────┘
```

**Durée totale :** 60-180 secondes (1-3 minutes)
**Coût OpenAI :** $0.006-0.010 par enregistrement (10 min audio)

---

## I. Transcription (Étape 1)

### A. TranscriptionService

**Localisation :** `backend/app/Services/TranscriptionService.php`

**Responsabilité :** Convertir audio → texte (français)

#### Méthodes

```php
class TranscriptionService
{
    /**
     * Transcrit un fichier audio.
     *
     * @param string $audioPath Chemin du fichier audio
     * @param string|null $mode 'openai' ou 'whisper_local'
     * @return string Transcription
     */
    public function transcribe(string $audioPath, ?string $mode = null): string;
}
```

### B. Mode OpenAI Whisper API

**API :** https://api.openai.com/v1/audio/transcriptions
**Modèle :** whisper-1 (large-v2 équivalent)
**Prix :** $0.006/minute audio
**Rate limit :** 50 req/min (plan standard)

#### Requête HTTP

```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
])
->attach('file', file_get_contents($audioPath), 'audio.wav')
->post('https://api.openai.com/v1/audio/transcriptions', [
    'model' => 'whisper-1',
    'language' => 'fr',
    'response_format' => 'text',
]);

$transcription = $response->body();
```

#### Formats supportés

- WAV, MP3, M4A, WebM
- Taille max : 25 MB
- Durée max : Aucune limite officielle (recommandé < 30 min)

#### Performance

| Durée audio | Temps traitement | Coût |
|-------------|------------------|------|
| 1 min | 3-5s | $0.006 |
| 5 min | 10-20s | $0.030 |
| 10 min | 20-40s | $0.060 |
| 30 min | 60-120s | $0.180 |

### C. Mode Whisper Local

**Modèle :** Whisper large-v3 (2.9 GB)
**Hardware :** GPU NVIDIA recommandé (10-100x plus rapide que CPU)
**Librairie :** openai-whisper ou faster-whisper

#### Script Python

**Localisation :** `backend/scripts/whisper_transcribe.py`

```python
#!/usr/bin/env python3
import whisper
import sys

def transcribe_audio(audio_path: str) -> str:
    # Load model (cached après premier load)
    model = whisper.load_model("large-v3")

    # Transcription
    result = model.transcribe(
        audio_path,
        language="fr",
        fp16=True,  # Mixed precision (si GPU)
        beam_size=5,
        best_of=5,
    )

    return result["text"]

if __name__ == "__main__":
    audio_path = sys.argv[1]
    transcription = transcribe_audio(audio_path)
    print(transcription)
```

#### Appel depuis Laravel

```php
$process = new Process([
    'python3',
    base_path('scripts/whisper_transcribe.py'),
    $audioPath,
]);

$process->mustRun();
$transcription = $process->getOutput();
```

#### Performance (GPU RTX 3070)

| Durée audio | Temps traitement | RAM GPU |
|-------------|------------------|---------|
| 1 min | 2-3s | 2 GB |
| 5 min | 8-12s | 2 GB |
| 10 min | 15-25s | 2 GB |
| 30 min | 45-75s | 2 GB |

#### Performance (CPU 8 cores)

| Durée audio | Temps traitement |
|-------------|------------------|
| 1 min | 30-60s |
| 5 min | 2-5 min |
| 10 min | 5-10 min |
| 30 min | 15-30 min |

**⚠️ Conclusion :** Whisper local CPU est **trop lent** pour production. GPU indispensable.

---

## II. Diarisation (Étape 2)

### A. DiarizationService

**Localisation :** `backend/app/Services/DiarizationService.php`

**Responsabilité :** Identifier qui parle (courtier vs client)

**Modèle :** Pyannote.audio 3.1 (HuggingFace)
**Token requis :** HUGGINGFACE_TOKEN
**Licence :** MIT (acceptation requise sur HuggingFace)

#### Méthodes

```php
class DiarizationService
{
    /**
     * Applique la diarisation sur un audio et retourne les segments.
     *
     * @param string $audioPath Chemin du fichier audio
     * @return array Segments avec timestamps et speakers
     */
    public function diarize(string $audioPath): array;

    /**
     * Formatte la transcription avec les speakers.
     *
     * @param string $transcription Transcription brute
     * @param array $segments Segments de diarisation
     * @return string Transcription formatée
     */
    public function formatTranscription(string $transcription, array $segments): string;
}
```

### B. Script Python Pyannote

**Localisation :** `backend/scripts/diarize_audio.py`

```python
#!/usr/bin/env python3
from pyannote.audio import Pipeline
import sys
import json
import os

def diarize_audio(audio_path: str) -> dict:
    # Load pipeline (nécessite HUGGINGFACE_TOKEN)
    token = os.getenv("HUGGINGFACE_TOKEN")
    pipeline = Pipeline.from_pretrained(
        "pyannote/speaker-diarization-3.1",
        use_auth_token=token
    )

    # Apply diarization
    diarization = pipeline(audio_path)

    # Extract segments
    segments = []
    for turn, _, speaker in diarization.itertracks(yield_label=True):
        segments.append({
            "start": turn.start,
            "end": turn.end,
            "speaker": speaker
        })

    return {
        "speaker_count": len(set(s["speaker"] for s in segments)),
        "segments": segments
    }

if __name__ == "__main__":
    audio_path = sys.argv[1]
    result = diarize_audio(audio_path)
    print(json.dumps(result))
```

### C. Formatage Transcription

**Principe :** Matcher segments diarisation avec transcription

```php
public function formatTranscription(string $transcription, array $segments): string
{
    // Simplification : 2 speakers = COURTIER + CLIENT
    // Speaker0 = premier à parler (généralement courtier)
    // Speaker1 = second (client)

    $speakerMap = [
        'SPEAKER_00' => '[COURTIER]',
        'SPEAKER_01' => '[CLIENT]',
    ];

    $formatted = '';
    foreach ($segments as $segment) {
        $speaker = $speakerMap[$segment['speaker']] ?? '[SPEAKER]';
        $text = $segment['text']; // Extrait de transcription

        $formatted .= "{$speaker}: {$text}\n";
    }

    return $formatted;
}
```

**Exemple Output :**

```
[COURTIER]: Bonjour Monsieur, comment vous appelez-vous ?
[CLIENT]: Jean Dupont, né le 15 mai 1980 à Paris.
[COURTIER]: Quelle est votre profession ?
[CLIENT]: Je suis architecte, chef d'entreprise en SARL.
[COURTIER]: Avez-vous des enfants ?
[CLIENT]: Oui, j'ai deux enfants, Alicia et Léana.
```

### D. Correction Manuelle Diarisation

**Problème :** Pyannote peut se tromper (confusion courtier/client, 3+ speakers détectés)

**Solution :** Interface frontend de correction

#### API Routes

```php
// Récupérer speakers détectés
GET /api/audio-records/{audioRecord}/speakers

// Corriger un segment
POST /api/audio-records/{audioRecord}/speakers/correct
{
  "segment_index": 5,
  "new_speaker": "CLIENT"
}

// Correction batch
POST /api/audio-records/{audioRecord}/speakers/correct-batch
{
  "corrections": [
    {"segment_index": 5, "new_speaker": "CLIENT"},
    {"segment_index": 7, "new_speaker": "COURTIER"}
  ]
}

// Reset diarisation
POST /api/audio-records/{audioRecord}/speakers/reset
```

#### Controller

**Localisation :** `backend/app/Http/Controllers/SpeakerCorrectionController.php`

```php
class SpeakerCorrectionController extends Controller
{
    public function correct(Request $request, AudioRecord $audioRecord)
    {
        $validated = $request->validate([
            'segment_index' => 'required|integer',
            'new_speaker' => 'required|in:COURTIER,CLIENT',
        ]);

        // Récupérer diarisation JSON
        $diarization = json_decode($audioRecord->diarization_data, true);
        $segments = $diarization['segments'];

        // Corriger le segment
        $segments[$validated['segment_index']]['speaker'] = $validated['new_speaker'];

        // Sauvegarder
        $audioRecord->update([
            'diarization_data' => json_encode(['segments' => $segments]),
            'correction_count' => $audioRecord->correction_count + 1,
        ]);

        // Log correction
        DiarizationLog::create([
            'audio_record_id' => $audioRecord->id,
            'correction_count' => $audioRecord->correction_count,
        ]);

        return response()->json(['message' => 'Correction appliquée']);
    }
}
```

---

## III. Routing (Étape 3)

### A. RouterService

**Localisation :** `backend/app/Services/Ai/RouterService.php`

**Responsabilité :** Détecter quelles sections métier sont concernées par la transcription

**Modèle :** GPT-4o-mini
**Temperature :** 0.1 (déterministe)
**Format :** JSON obligatoire

#### Méthode Principale

```php
class RouterService
{
    /**
     * Détecte les sections concernées par la transcription.
     *
     * @param string $transcription Transcription vocale
     * @return array Tableau de sections (ex: ["client", "prevoyance"])
     */
    public function detectSections(string $transcription): array
    {
        $prompt = $this->buildPrompt($transcription);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'OpenAI-Organization' => env('OPENAI_ORG_ID'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
        ]);

        $data = json_decode($response->json()['choices'][0]['message']['content'], true);

        return $data['sections'] ?? ['client'];
    }
}
```

### B. Sections Disponibles

| Section | Description | Mots-clés |
|---------|-------------|-----------|
| **client** | Identité, coordonnées, situation familiale/pro | nom, prénom, adresse, profession, etc. |
| **conjoint** | Informations sur le conjoint | ma femme, mon mari, mon conjoint, etc. |
| **prevoyance** | Besoins prévoyance | invalidité, ITT, arrêt de travail, décès |
| **retraite** | Besoins retraite | retraite, PER, TMI, départ |
| **epargne** | Besoins épargne/patrimoine | épargne, investissement, assurance vie |
| **sante** | Besoins santé/mutuelle | mutuelle, santé, hospitalisation |
| **emprunteur** | Assurance emprunteur | prêt immobilier, crédit |
| **revenus** | Sources de revenus | salaire, loyers, dividendes |
| **passifs** | Prêts, dettes | prêt, emprunt, crédit |
| **actifs_financiers** | Actifs financiers | AV, PEA, PER, SCPI |
| **biens_immobiliers** | Patrimoine immobilier | maison, appartement, SCI |
| **autres_epargnes** | Épargnes alternatives | or, cryptomonnaies, art |

### C. Garde-Fou Conjoint

**Problème :** GPT peut oublier de détecter "conjoint" même si mots-clés présents

**Solution :** Détection forcée par regex

```php
private function forceConjointDetection(string $transcription, array $sections): array
{
    $text = mb_strtolower($transcription);

    $conjointPatterns = [
        '/\bma femme\b/u',
        '/\bmon mari\b/u',
        '/\bmon épouse\b/u',
        '/\bmon conjoint\b/u',
        '/\bma conjointe\b/u',
        '/\bmon partenaire\b/u',
        '/\bma compagne\b/u',
        '/\bmon compagnon\b/u',
    ];

    foreach ($conjointPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            if (!in_array('conjoint', $sections)) {
                $sections[] = 'conjoint';
                Log::info('🔒 [RouterService] Section "conjoint" forcée');
            }
            break;
        }
    }

    return $sections;
}
```

---

## IV. Extraction Modulaire (Étape 4)

### A. AnalysisService (Orchestrateur)

**Localisation :** `backend/app/Services/Ai/AnalysisService.php`

**Responsabilité :** Coordonner les extracteurs spécialisés

#### Flux

```php
class AnalysisService
{
    public function extractClientData(string $transcription): array
    {
        // 1. Routing
        $sections = $this->router->detectSections($transcription);

        // 2. Extraction par section
        $mergedData = [];
        foreach ($sections as $section) {
            $extractorData = $this->extractSection($section, $transcription);
            $mergedData = $this->mergeData($mergedData, $extractorData);
        }

        // 3. Garde-fou: Nettoyer données client si = conjoint
        $mergedData = $this->cleanClientDataIfConjointDetected($mergedData, $sections);

        // 4. Normalisation
        $normalizedData = $this->normalizer->normalize($mergedData, $transcription);

        return $normalizedData;
    }

    private function extractSection(string $section, string $transcription): array
    {
        return match ($section) {
            'client' => $this->clientExtractor->extract($transcription),
            'conjoint' => $this->conjointExtractor->extract($transcription),
            'prevoyance' => $this->prevoyanceExtractor->extract($transcription),
            'retraite' => $this->retraiteExtractor->extract($transcription),
            'epargne' => $this->epargneExtractor->extract($transcription),
            'revenus' => $this->clientRevenusExtractor->extract($transcription),
            // ... autres extracteurs
            default => []
        };
    }
}
```

### B. Extracteurs Spécialisés

Chaque extracteur a son **prompt dédié** et extrait des **champs spécifiques**.

#### 1. ClientExtractor

**Localisation :** `backend/app/Services/Ai/Extractors/ClientExtractor.php`

**Champs extraits :**
- Identité : civilite, nom, prenom, date_naissance, lieu_naissance, nationalite
- Coordonnées : adresse, code_postal, ville, telephone, email
- Situation familiale : situation_matrimoniale, date_situation_matrimoniale
- Enfants : tableau d'objets `[{prenom, date_naissance, fiscalement_a_charge}, ...]`
- Situation pro : situation_actuelle, profession, revenus_annuels, risques_professionnels
- Entreprise : chef_entreprise, statut, travailleur_independant, mandataire_social
- Santé : fumeur, activites_sportives, details_activites_sportives

**Prompt System (extrait) :**

```
Tu es un assistant spécialisé en extraction de données client pour un CRM d'assurance.

🎯 OBJECTIF :
Extraire UNIQUEMENT les informations personnelles du CLIENT PRINCIPAL (celui qui parle, qui dit "je").

🚫 RÈGLES ABSOLUES :
1. N'extrais QUE le CLIENT PRINCIPAL : phrases avec "je", "moi", "mon", "ma", "mes"
2. IGNORE TOTALEMENT le CONJOINT : "ma femme", "mon mari", etc. → NE PAS EXTRAIRE
3. IGNORE le CONSEILLER : questions, propositions du courtier

✅ CHAMPS À EXTRAIRE (si mentionnés) :
- "civilite" (string) : "M.", "Mme", "Mlle"
- "nom" (string) : nom de famille
- "prenom" (string) : prénom
- "date_naissance" (string) : format "YYYY-MM-DD"
- ...

📋 STRUCTURE ENFANTS :
Si le client mentionne ses enfants, retourne un tableau avec ces champs par enfant :
- "nom" (string)
- "prenom" (string)
- "date_naissance" (string)
- "fiscalement_a_charge" (boolean)

🚨 TRÈS IMPORTANT - CAPTURER TOUS LES ENFANTS :
- Si le client dit "j'ai deux enfants, Alicia et Léana", tu DOIS retourner LES DEUX enfants
- Ne JAMAIS oublier un enfant mentionné !
```

**Exemple Output :**

```json
{
  "civilite": "M.",
  "nom": "Dupont",
  "prenom": "Jean",
  "date_naissance": "1980-05-15",
  "lieu_naissance": "Paris",
  "situation_matrimoniale": "Marié(e)",
  "telephone": "0601020304",
  "email": "jean.dupont@example.com",
  "profession": "architecte",
  "chef_entreprise": true,
  "statut": "SARL",
  "fumeur": false,
  "enfants": [
    {"prenom": "Alicia", "date_naissance": "2010-03-15", "fiscalement_a_charge": true},
    {"prenom": "Léana", "date_naissance": "2015-07-22", "fiscalement_a_charge": true}
  ]
}
```

#### 2. ConjointExtractor

**Localisation :** `backend/app/Services/Ai/Extractors/ConjointExtractor.php`

**Champs extraits (sous clé `conjoint`) :**
- nom, nom_jeune_fille, prenom, date_naissance, lieu_naissance, nationalite
- profession, situation_actuelle_statut, chef_entreprise
- risques_professionnels, details_risques_professionnels
- telephone, adresse

**Prompt System (extrait) :**

```
Tu es un assistant spécialisé en extraction de données CONJOINT pour un CRM d'assurance.

🎯 OBJECTIF :
Détecter si le client parle de son CONJOINT et extraire les données associées.

🚫 RÈGLES ABSOLUES :
1. N'extrais QUE le CONJOINT : Cherche UNIQUEMENT "mon conjoint", "ma femme", "mon mari", "elle"/"il" (parlant du conjoint)
2. IGNORE TOTALEMENT le CLIENT PRINCIPAL : "je", "moi" → IGNORE
3. 🚨 IGNORE LES ENFANTS : "mon fils", "ma fille", "Alicia", "Léana" → CE NE SONT PAS DES CONJOINTS !

✅ SI LE CLIENT PARLE DE SON CONJOINT :
Retourne :
{
  "conjoint": {
    "nom": "...",
    "prenom": "...",
    "profession": "...",
    ...
  }
}

❌ SI LE CLIENT NE PARLE PAS DE SON CONJOINT :
Retourne : {}
```

**Exemple Output :**

```json
{
  "conjoint": {
    "prenom": "Sophie",
    "profession": "infirmière",
    "date_naissance": "1982-08-20"
  }
}
```

#### 3. PrevoyanceExtractor

**Champs extraits (sous clé `bae_prevoyance`) :**
- montant_itt_souhait
- montant_invalidite_souhait
- capital_deces
- rente_conjoint
- rente_enfant
- garanties_complementaires (array)
- franchise_souhaitee

#### 4. RetraiteExtractor

**Champs extraits (sous clé `bae_retraite`) :**
- age_depart_souhaite
- revenus_foyer_apres_impot
- tmi (Tranche Marginale Imposition)
- trimestres_valides
- montant_pension_estimee
- besoin_complementaire
- solution_envisagee
- versements_reguliers

#### 5. EpargneExtractor

**Champs extraits (sous clé `bae_epargne`) :**
- capacite_epargne_mensuelle
- horizon_placement
- objectif_patrimoine
- montant_objectif
- date_objectif
- tolerance_risque
- supports_souhaites (array)
- projet_immobilier
- details_projet_immo

#### 6. ClientRevenusExtractor

**Champs extraits (sous clé `revenus`) :**

```json
{
  "revenus": [
    {"type": "Salaire", "montant": 4500, "frequence": "mensuel"},
    {"type": "Loyers", "montant": 1200, "frequence": "mensuel"}
  ]
}
```

#### 7-10. Autres Extracteurs

- **ClientPassifsExtractor :** Prêts, emprunts
- **ClientActifsFinanciersExtractor :** AV, PEA, PER, SCPI
- **ClientBiensImmobiliersExtractor :** Patrimoine immobilier
- **ClientAutresEpargnesExtractor :** Or, cryptos, art

### C. Fusion Intelligente

**Problème :** 10+ extracteurs peuvent retourner données conflictuelles

**Solution :** Méthode `mergeData()` avec règles métier

```php
private function mergeData(array $existing, array $new): array
{
    foreach ($new as $key => $value) {
        if (!isset($existing[$key])) {
            // Clé n'existe pas → ajouter
            $existing[$key] = $value;
        } elseif (is_array($existing[$key]) && is_array($value)) {
            // Cas spécial: besoins (concaténer + dédupliquer)
            if ($key === 'besoins') {
                $existing[$key] = array_values(array_unique(array_merge($existing[$key], $value)));
            }
            // Cas spécial: enfants (concaténer)
            elseif ($key === 'enfants') {
                $existing[$key] = array_merge($existing[$key], $value);
            }
            // Cas spécial: objets BAE (fusion récursive)
            elseif (in_array($key, ['bae_prevoyance', 'bae_retraite', 'bae_epargne', 'conjoint'])) {
                $existing[$key] = $this->mergeData($existing[$key], $value);
            }
        } else {
            // Valeur scalaire : nouvelle valeur écrase ancienne (si non vide)
            if ($value !== null && $value !== '') {
                $existing[$key] = $value;
            }
        }
    }

    return $existing;
}
```

### D. Garde-Fou Client/Conjoint

**Problème :** GPT peut confondre client principal et conjoint

**Solution :** Vérifier similarité données client vs conjoint

```php
private function cleanClientDataIfConjointDetected(array $data, array $sections): array
{
    if (!in_array('conjoint', $sections) || !isset($data['conjoint'])) {
        return $data;
    }

    $conjointData = $data['conjoint'];
    $fieldsToCheck = ['nom', 'prenom', 'date_naissance', 'profession'];

    $matchingFields = 0;
    $checkedFields = 0;

    foreach ($fieldsToCheck as $field) {
        if (isset($data[$field]) && isset($conjointData[$field])) {
            $checkedFields++;
            if (mb_strtolower(trim($data[$field])) === mb_strtolower(trim($conjointData[$field]))) {
                $matchingFields++;
            }
        }
    }

    // Si ≥2 champs correspondent → probable confusion
    if ($checkedFields >= 2 && $matchingFields >= 2) {
        Log::warning('🔒 GARDE-FOU: Données client correspondent au conjoint ! Nettoyage...');

        // Supprimer les champs client qui correspondent au conjoint
        foreach ($fieldsToCheck as $field) {
            if (isset($data[$field]) && isset($conjointData[$field]) &&
                mb_strtolower($data[$field]) === mb_strtolower($conjointData[$field])) {
                unset($data[$field]);
            }
        }
    }

    return $data;
}
```

---

## V. Normalisation (Étape 5)

### A. AiDataNormalizer

**Localisation :** `backend/app/Services/Ai/AiDataNormalizer.php`

**Responsabilité :** Validation et transformation des données extraites

#### Transformations Principales

##### 1. Dates

```php
/**
 * Normalise une date en format ISO (YYYY-MM-DD).
 *
 * Gère: "15/05/1980", "15 mai 1980", "1980-05-15"
 */
private function normalizeDate(?string $date): ?string
{
    if (empty($date)) {
        return null;
    }

    // Déjà au bon format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    // Format DD/MM/YYYY ou DD-MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $matches)) {
        return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
    }

    // Format "15 mai 1980"
    $months = [
        'janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4,
        'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8,
        'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12,
    ];
    foreach ($months as $monthName => $monthNum) {
        if (stripos($date, $monthName) !== false) {
            preg_match('/(\d{1,2})\s+' . $monthName . '\s+(\d{4})/i', $date, $matches);
            if ($matches) {
                return sprintf('%04d-%02d-%02d', $matches[2], $monthNum, $matches[1]);
            }
        }
    }

    // Fallback: Carbon parse
    try {
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    } catch (\Exception $e) {
        Log::warning("Date non parsable: {$date}");
        return null;
    }
}
```

##### 2. Téléphones

```php
/**
 * Normalise un numéro de téléphone français.
 *
 * Gère: "06 12 34 56 78", "06.12.34.56.78", "+33612345678"
 * Output: "0612345678"
 */
private function normalizePhone(?string $phone): ?string
{
    if (empty($phone)) {
        return null;
    }

    // Supprimer espaces, points, tirets
    $phone = preg_replace('/[\s\.\-\(\)]/', '', $phone);

    // Supprimer +33 et ajouter 0
    if (str_starts_with($phone, '+33')) {
        $phone = '0' . substr($phone, 3);
    }
    if (str_starts_with($phone, '0033')) {
        $phone = '0' . substr($phone, 4);
    }

    // Vérifier format 10 chiffres
    if (!preg_match('/^0[1-9]\d{8}$/', $phone)) {
        Log::warning("Téléphone invalide: {$phone}");
        return null;
    }

    return $phone;
}
```

##### 3. Booléens (Détection Négation)

```php
/**
 * Détecte les négations dans la transcription pour corriger les booléens.
 *
 * Ex: "je ne suis PAS fumeur" → fumeur: false
 */
private function detectNegation(string $transcription, string $keyword): bool
{
    $text = mb_strtolower($transcription);

    // Patterns de négation
    $negationPatterns = [
        "/\bne\s+(suis|fais)\s+pas\s+{$keyword}\b/u",
        "/\bpas\s+{$keyword}\b/u",
        "/\b(aucun|aucune)\s+{$keyword}\b/u",
    ];

    foreach ($negationPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return true; // Négation détectée
        }
    }

    return false;
}

// Utilisation
$data['fumeur'] = $data['fumeur'] ?? false;
if ($this->detectNegation($transcription, 'fumeur')) {
    $data['fumeur'] = false;
}
```

##### 4. Besoins (Mapping)

```php
/**
 * Normalise les besoins en valeurs standardisées.
 *
 * Input: ["protection", "retraite complémentaire", "épargne"]
 * Output: ["Prévoyance", "Retraite", "Épargne"]
 */
private function normalizeBesoins(array $besoins): array
{
    $mapping = [
        'prévoyance' => 'Prévoyance',
        'protection' => 'Prévoyance',
        'invalidité' => 'Prévoyance',
        'arrêt de travail' => 'Prévoyance',
        'itt' => 'Prévoyance',
        'décès' => 'Prévoyance',

        'retraite' => 'Retraite',
        'retraite complémentaire' => 'Retraite',
        'per' => 'Retraite',
        'perp' => 'Retraite',

        'épargne' => 'Épargne',
        'patrimoine' => 'Épargne',
        'investissement' => 'Épargne',
        'assurance vie' => 'Épargne',

        'santé' => 'Santé',
        'mutuelle' => 'Santé',
        'complémentaire santé' => 'Santé',
    ];

    $normalized = [];
    foreach ($besoins as $besoin) {
        $besoinLower = mb_strtolower(trim($besoin));
        $normalized[] = $mapping[$besoinLower] ?? ucfirst($besoin);
    }

    return array_values(array_unique($normalized));
}
```

---

## VI. Synchronisation BDD (Étape 6)

### A. SyncServices Architecture

**Pattern :** AbstractSyncService avec méthode `sync()` commune

**Localisation :** `backend/app/Services/`

#### AbstractSyncService

```php
abstract class AbstractSyncService
{
    /**
     * Synchronise les données extraites avec la BDD.
     *
     * @param int $clientId ID du client
     * @param array $data Données extraites
     * @return void
     */
    abstract public function sync(int $clientId, array $data): void;
}
```

### B. Implémentations

#### 1. ClientSyncService

```php
class ClientSyncService extends AbstractSyncService
{
    public function sync(int $clientId, array $data): void
    {
        $client = Client::findOrFail($clientId);

        // Update données client principal
        $client->update(Arr::only($data, [
            'civilite', 'nom', 'prenom', 'date_naissance', 'lieu_naissance',
            'situation_matrimoniale', 'profession', 'revenus_annuels',
            'chef_entreprise', 'statut', 'fumeur', 'activites_sportives',
            'besoins', // JSON array
            // ... tous les champs fillable
        ]));

        Log::info("✅ Client #{$clientId} mis à jour", ['changes' => $client->getChanges()]);
    }
}
```

#### 2. ConjointSyncService

```php
class ConjointSyncService extends AbstractSyncService
{
    public function sync(int $clientId, array $data): void
    {
        if (!isset($data['conjoint']) || empty($data['conjoint'])) {
            return; // Pas de données conjoint
        }

        $client = Client::findOrFail($clientId);
        $conjointData = $data['conjoint'];

        // Update or Create (one-to-one)
        $client->conjoint()->updateOrCreate(
            ['client_id' => $clientId],
            $conjointData
        );

        Log::info("✅ Conjoint du client #{$clientId} synchronisé");
    }
}
```

#### 3. EnfantSyncService

```php
class EnfantSyncService extends AbstractSyncService
{
    public function sync(int $clientId, array $data): void
    {
        if (!isset($data['enfants']) || !is_array($data['enfants'])) {
            return;
        }

        $client = Client::findOrFail($clientId);
        $existingEnfants = $client->enfants;

        foreach ($data['enfants'] as $enfantData) {
            // Matcher par prénom (simplification)
            $existing = $existingEnfants->firstWhere('prenom', $enfantData['prenom']);

            if ($existing) {
                // Update
                $existing->update($enfantData);
            } else {
                // Create
                $client->enfants()->create($enfantData);
            }
        }

        Log::info("✅ Enfants du client #{$clientId} synchronisés", ['count' => count($data['enfants'])]);
    }
}
```

#### 4. BaePrevoyanceSyncService

```php
class BaePrevoyanceSyncService extends AbstractSyncService
{
    public function sync(int $clientId, array $data): void
    {
        if (!isset($data['bae_prevoyance'])) {
            return;
        }

        $client = Client::findOrFail($clientId);

        $client->baePrevoyance()->updateOrCreate(
            ['client_id' => $clientId],
            $data['bae_prevoyance']
        );

        Log::info("✅ BAE Prévoyance du client #{$clientId} synchronisée");
    }
}
```

---

## VII. Performance & Optimisations

### A. Temps Traitement Total

| Étape | Durée (10 min audio) |
|-------|----------------------|
| 1. Transcription (Whisper API) | 20-40s |
| 2. Diarisation (Pyannote) | 30-60s |
| 3. Routing (GPT) | 1-3s |
| 4. Extraction (10 extracteurs) | 10-20s |
| 5. Normalisation | <1s |
| 6. Sync BDD | 2-5s |
| **TOTAL** | **60-130s** (1-2 min) |

### B. Coûts OpenAI

| Opération | Modèle | Prix unitaire | Quantité/enreg | Coût/enreg |
|-----------|--------|---------------|----------------|------------|
| Transcription | Whisper | $0.006/min | 10 min | $0.060 |
| Routing | GPT-4o-mini | ~$0.0001/req | 1 req | $0.0001 |
| Extraction | GPT-4o-mini | ~$0.0003/req | 10 req | $0.003 |
| **TOTAL** | | | | **$0.063** |

**Pour 4000 enregistrements/mois :**
- Coût total : $252/mois
- Par cabinet (20) : $12.60/mois

### C. Optimisations Possibles

#### 1. Cache Extractions

```php
public function extract(string $transcription): array
{
    $cacheKey = 'extraction:client:' . md5($transcription);

    return Cache::remember($cacheKey, 3600, function () use ($transcription) {
        return $this->callOpenAI($transcription);
    });
}
```

**Économie :** 10-20% si transcriptions similaires

#### 2. Batch Extraction (1 requête vs 10)

**Actuellement :** 10 requêtes GPT (1 par extracteur)

**Optimisation :** 1 seule requête avec prompt global

```php
$prompt = "Extrais client, conjoint, prévoyance, retraite, épargne de cette transcription";
$result = $this->callOpenAI($prompt); // 1 requête vs 10
```

**Économie :** ~80% coûts extraction GPT

#### 3. Whisper Local (GPU)

**Coût actuel :** $0.060/enregistrement (10 min)
**Alternative :** GPU server €100/mois

**Break-even :** €100/mois ÷ €0.055/enreg = 1820 enregistrements/mois
**Rentable si :** >2000 enregistrements/mois (donc oui pour 20 cabinets)

---

## VIII. Monitoring & Métriques

### A. Logs Structurés

Chaque étape log des métriques :

```php
Log::info('[TranscriptionService] Transcription terminée', [
    'audio_record_id' => $audioRecord->id,
    'duration_ms' => $durationMs,
    'transcription_length' => strlen($transcription),
    'mode' => 'openai',
]);

Log::info('[RouterService] Sections détectées', [
    'sections' => $sections,
    'duration_ms' => $durationMs,
]);

Log::info('[AnalysisService] Extraction terminée', [
    'audio_record_id' => $audioRecord->id,
    'sections_processed' => count($sections),
    'data_keys_extracted' => array_keys($normalizedData),
    'duration_ms' => $durationMs,
]);
```

### B. Métriques Business

**DiarizationLog :**
- Nombre corrections utilisateur
- Précision calculée
- Speaker count initial vs final

**AuditLog :**
- Actions extraction IA
- Temps traitement par étape

**Dashboards Grafana :**
- Temps traitement moyen par étape
- Coûts OpenAI cumulés
- Taux d'erreur extraction
- Qualité diarisation

---

**Version :** 1.0
**Date :** 2026-01-02
**Performance :** 60-130s traitement (10 min audio)
**Coût :** $0.06-0.10 par enregistrement
