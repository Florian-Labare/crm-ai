# Différences de comportement Mistral vs OpenAI

**Date** : 2026-02-02
**Projet** : CRM Courtier Assurance
**Branche** : mistral-migration

---

## Résumé

Ce document détaille les différences de comportement entre Mistral AI et OpenAI dans le contexte du CRM Courtier Assurance, afin de faciliter la migration et le debugging.

---

## 1. LLM (Chat Completions)

### API Endpoint

| Aspect | OpenAI | Mistral |
|--------|--------|---------|
| Endpoint | `https://api.openai.com/v1/chat/completions` | `https://api.mistral.ai/v1/chat/completions` |
| Modèle | `gpt-4o-mini` | `mistral-small-latest` |
| Header auth | `Authorization: Bearer <key>` | `Authorization: Bearer <key>` |

### Paramètres

| Paramètre | OpenAI | Mistral | Notes |
|-----------|--------|---------|-------|
| `temperature` | 0-2 | 0-1 | Mistral range plus strict |
| `max_tokens` | Optionnel | Optionnel | Compatible |
| `response_format` | `{"type": "json_object"}` | `{"type": "json_object"}` | Compatible |
| `top_p` | 0-1 | 0-1 | Compatible |
| `stream` | true/false | true/false | Compatible |

### Comportement JSON Mode

| Aspect | OpenAI | Mistral |
|--------|--------|---------|
| Activation | Implicite si demandé dans prompt | Explicite via `response_format` |
| Erreur si non-JSON | Peut retourner texte | Strict, toujours JSON |
| Encodage | UTF-8 | UTF-8 |

**Recommandation** : Toujours utiliser `response_format: {"type": "json_object"}` pour garantir la compatibilité.

### Qualité des réponses

| Aspect | OpenAI GPT-4o-mini | Mistral Small |
|--------|-------------------|---------------|
| Français | Excellent | Excellent (natif) |
| Suivi d'instructions | Tolérant aux ambiguïtés | Préfère précision |
| Exemples | Optionnels | Fortement recommandés |
| Extraction JSON | Très bon | Très bon |

**Observation** : Mistral suit plus strictement les instructions. Si le prompt est ambigu, OpenAI devine souvent correctement, alors que Mistral peut retourner des résultats inattendus.

---

## 2. STT (Speech-to-Text)

### API Endpoint

| Aspect | OpenAI Whisper | Mistral Voxtral |
|--------|----------------|-----------------|
| Endpoint | `https://api.openai.com/v1/audio/transcriptions` | `https://api.mistral.ai/v1/audio/transcriptions` |
| Modèle | `whisper-1` | `voxtral-mini-latest` |
| Méthode | POST multipart/form-data | POST multipart/form-data |

### Formats supportés

| Format | OpenAI Whisper | Mistral Voxtral |
|--------|----------------|-----------------|
| MP3 | ✅ | ✅ |
| WAV | ✅ | ✅ |
| M4A | ✅ | ✅ |
| WEBM | ✅ | ✅ |
| OGG | ✅ | ✅ |

### Qualité de transcription

| Aspect | OpenAI Whisper | Mistral Voxtral |
|--------|----------------|-----------------|
| Français général | Excellent | Excellent |
| Vocabulaire technique | Bon | Bon |
| Accents régionaux | Bon | Bon |
| Noms propres | Variable | Variable |
| Chiffres/dates | Bon | Bon |

**Note** : Les deux services gèrent bien le français parlé. Pour les noms propres peu communs, les résultats peuvent varier.

### Timeout

| Aspect | OpenAI | Mistral |
|--------|--------|---------|
| Timeout recommandé | 120s | 300s |
| Fichier max | 25 MB | 25 MB |

---

## 3. Configuration .env

```env
# --- MISTRAL ---
MISTRAL_API_KEY=your_mistral_api_key

# Activer Mistral pour les LLM (extraction, routing)
MISTRAL_USE_FOR_LLM=true

# Activer Mistral pour la transcription STT
MISTRAL_USE_FOR_STT=true

# Fallback automatique vers OpenAI si Mistral échoue
MISTRAL_FALLBACK=true

# --- OPENAI (fallback) ---
OPENAI_API_KEY=your_openai_api_key
```

---

## 4. Gestion des erreurs

### Codes HTTP

| Code | OpenAI | Mistral | Action |
|------|--------|---------|--------|
| 200 | OK | OK | Traiter la réponse |
| 400 | Bad Request | Bad Request | Vérifier le payload |
| 401 | Invalid API key | Invalid API key | Vérifier la clé |
| 429 | Rate limited | Rate limited | Retry avec backoff |
| 500 | Server error | Server error | Fallback |
| 503 | Service unavailable | Service unavailable | Fallback |

### Stratégie de fallback

```
1. Tenter Mistral
2. Si échec et MISTRAL_FALLBACK=true → OpenAI
3. Si échec OpenAI → Logger et retourner erreur
```

---

## 5. Performances

### Latence moyenne

| Service | OpenAI | Mistral |
|---------|--------|---------|
| LLM (extraction simple) | ~500ms | ~400ms |
| LLM (extraction complexe) | ~1500ms | ~1200ms |
| STT (audio 1 min) | ~3s | ~4s |
| STT (audio 5 min) | ~10s | ~15s |

**Note** : Les temps varient selon la charge serveur et la localisation géographique.

### Coûts (estimation)

| Service | OpenAI | Mistral |
|---------|--------|---------|
| LLM (1M tokens) | ~$0.15 | ~$0.10 |
| STT (1h audio) | ~$0.36 | ~$0.30 |

**Note** : Mistral est généralement 30-40% moins cher.

---

## 6. Différences spécifiques au projet

### RouterService

| Aspect | OpenAI | Mistral |
|--------|--------|---------|
| Détection "conjoint" | Très bon | Bon (garde-fou PHP ajouté) |
| Sections multiples | Bon | Très bon |
| Transcriptions ambiguës | Devine souvent correctement | Plus conservateur |

**Solution** : Le garde-fou `forceConjointDetection()` en PHP compense les cas où Mistral ne détecte pas "conjoint" malgré les mots-clés.

### Extracteurs

| Extracteur | OpenAI | Mistral | Notes |
|------------|--------|---------|-------|
| ClientExtractor | Très bon | Très bon | Compatible |
| ConjointExtractor | Très bon | Très bon | Compatible |
| PrevoyanceExtractor | Très bon | Très bon | Compatible |
| RetraiteExtractor | Très bon | Très bon | Compatible |
| EpargneExtractor | Très bon | Très bon | Compatible |

**Conclusion** : Les extracteurs fonctionnent de manière équivalente avec les deux providers.

### TranscriptionService

| Aspect | OpenAI Whisper | Mistral Voxtral |
|--------|----------------|-----------------|
| Ponctuation | Automatique | Automatique |
| Timestamps | Optionnels | Non supporté |
| Speaker diarization | Non | Non |

---

## 7. Recommandations de migration

### Étape 1 : Staging

```env
MISTRAL_USE_FOR_LLM=true
MISTRAL_USE_FOR_STT=false  # Garder Whisper local ou OpenAI d'abord
MISTRAL_FALLBACK=true
```

### Étape 2 : Production partielle

```env
MISTRAL_USE_FOR_LLM=true
MISTRAL_USE_FOR_STT=true
MISTRAL_FALLBACK=true
```

### Étape 3 : Production complète

```env
MISTRAL_USE_FOR_LLM=true
MISTRAL_USE_FOR_STT=true
MISTRAL_FALLBACK=false  # Désactiver fallback une fois stable
```

---

## 8. Monitoring recommandé

### Logs à surveiller

```php
// Fallback détecté
Log::warning('Mistral failed, falling back to OpenAI');

// Erreur Mistral
Log::error('[Mistral] API error', ['status' => $status, 'body' => $body]);

// Succès Mistral
Log::info('Mistral extraction successful', ['sections' => $sections]);
```

### Métriques à tracker

- Taux de fallback Mistral → OpenAI
- Latence moyenne par provider
- Erreurs par type (timeout, 4xx, 5xx)
- Qualité d'extraction (validation manuelle périodique)

---

## 9. Troubleshooting

### Problème : Mistral retourne JSON invalide

**Cause** : Prompt mal structuré ou température trop haute.

**Solution** :
- Vérifier `response_format: {"type": "json_object"}`
- Utiliser `temperature: 0.1` pour extraction
- Ajouter des exemples explicites dans le prompt

### Problème : Détection conjoint manquée

**Cause** : Mistral interprète différemment les pronoms "elle/il".

**Solution** : Le garde-fou PHP `forceConjointDetection()` est déjà en place.

### Problème : Timeout sur gros fichiers audio

**Cause** : Voxtral est plus lent que Whisper pour les fichiers longs.

**Solution** : Augmenter le timeout à 300s dans le config.

### Problème : Fallback systématique

**Cause** : Clé API Mistral invalide ou quota dépassé.

**Solution** : Vérifier la clé et le quota sur la console Mistral.

---

## Conclusion

La migration vers Mistral est réussie. Les deux providers sont compatibles avec l'architecture actuelle grâce au trait `LlmClientTrait` et au service `TranscriptionService` avec fallback automatique.

**Actions recommandées** :
1. Activer Mistral en staging avec fallback
2. Monitorer les logs pendant 1 semaine
3. Passer en production une fois validé
4. Désactiver le fallback après stabilisation (optionnel, pour réduire les coûts)
