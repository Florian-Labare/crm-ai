# Rapport d'Audit des Prompts IA - Compatibilité Mistral

**Date** : 2026-02-02 (mis à jour)
**Projet** : CRM Courtier Assurance
**Branche** : mistral-migration
**Analyseur** : Claude Code (Ralph - Itération 6)

---

## Résumé Exécutif

| Fichier | Score | Statut |
|---------|-------|--------|
| RouterService.php | 9/10 | Compatible |
| ClientExtractor.php | 9/10 | Compatible |
| ConjointExtractor.php | 9/10 | Compatible |
| PrevoyanceExtractor.php | 9/10 | Compatible |
| RetraiteExtractor.php | 9/10 | Compatible |
| EpargneExtractor.php | 9/10 | Compatible |
| ClientRevenusExtractor.php | 9/10 | Compatible |
| ClientPassifsExtractor.php | 9/10 | Compatible |
| ClientActifsFinanciersExtractor.php | 9/10 | Compatible |
| ClientBiensImmobiliersExtractor.php | 9/10 | Compatible |
| ClientAutresEpargnesExtractor.php | 9/10 | Compatible |
| MeetingSummaryService.php | 8/10 | Compatible |
| LlmClientTrait.php | 10/10 | Compatible |

**Score Global : 9.1/10** - Excellente compatibilité Mistral

---

## Architecture LLM (LlmClientTrait)

**Fichier** : `backend/app/Services/Ai/Traits/LlmClientTrait.php`

### Points positifs
- Switch Mistral/OpenAI via feature flag (`MISTRAL_USE_FOR_LLM`)
- Fallback automatique vers OpenAI si Mistral échoue
- Mode JSON explicite avec `response_format: json_object`
- Temperature paramétrable (0.1 par défaut = extraction déterministe)
- Logging des réponses pour debug
- Timeout configuré (60s)

### Score de compatibilité Mistral : 10/10

---

## RouterService

**Fichier** : `backend/app/Services/Ai/RouterService.php`

### Points positifs
- Structure de prompt claire avec sections [OBJECTIF], [RÈGLES], [EXEMPLES]
- Instructions JSON explicites ("Réponds UNIQUEMENT avec du JSON valide")
- Nombreux exemples couvrant tous les cas (12 sections)
- Garde-fou PHP pour détecter le conjoint (`forceConjointDetection`)
- Aucune référence à GPT/OpenAI
- Prompts en français cohérents

### Problèmes détectés
- **[INFO]** Les emojis dans les logs (🔒) n'affectent pas le parsing JSON

### Score de compatibilité Mistral : 9/10

---

## ClientExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ClientExtractor.php`

### Points positifs
- Structure optimale : [OBJECTIF] → [RÈGLES ABSOLUES] → [CHAMPS] → [EXEMPLES] → [FORMAT]
- Gestion de l'épellation (email, téléphone) documentée
- Instructions explicites pour ignorer le conjoint
- Exemples clairs avec Input/Output
- Documentation du schéma JSON complète

### Problèmes détectés
- **[BASSE]** Pas d'exemple de sortie vide `{}` quand aucune info trouvée
  > Ajouter un exemple : `Input: "Le temps est beau aujourd'hui" → Output: {}`

### Score de compatibilité Mistral : 9/10

---

## ConjointExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ConjointExtractor.php`

### Points positifs
- Instructions très précises sur quand détecter le conjoint
- Distinction claire Client vs Conjoint vs Enfants
- Exemple de sortie vide `{}` présent
- Gestion de l'épellation documentée
- Structure JSON avec wrapper `conjoint: {}`

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## PrevoyanceExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/PrevoyanceExtractor.php`

### Points positifs
- Mots-clés de détection exhaustifs
- Règle critique `besoins_action` bien documentée
- Exemples couvrant : détection, suppression, non-détection
- Structure JSON claire avec `besoins`, `besoins_action`, `bae_prevoyance`

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## RetraiteExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/RetraiteExtractor.php`

### Points positifs
- Structure identique aux autres extracteurs (cohérence)
- Champs spécifiques retraite bien documentés (TMI, age_depart, etc.)
- Exemples variés et pertinents

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## EpargneExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/EpargneExtractor.php`

### Points positifs
- Champs patrimoniaux complets (actifs, passifs, charges)
- Exemples avec structures complexes (arrays)
- Gestion des détails (actifs_immo_details, passifs_details)

### Problèmes détectés
- **[INFO]** Ce service a beaucoup de champs - pourrait être subdivisé
  > Note : Déjà géré par les extracteurs spécialisés (Passifs, ActifsFinanciers, etc.)

### Score de compatibilité Mistral : 9/10

---

## ClientRevenusExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ClientRevenusExtractor.php`

### Points positifs
- Types de revenus bien catégorisés (salaire, pension, SCI, BNC, etc.)
- Gestion de la périodicité (mensuel/annuel)
- Exemples couvrant revenus multiples

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## ClientPassifsExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ClientPassifsExtractor.php`

### Points positifs
- Logique de déduplication intelligente (côté PHP)
- Fusion des passifs de même nature
- Conversion années → mois documentée
- Exemples variés (immobilier, auto, conso, professionnel)

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## ClientActifsFinanciersExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ClientActifsFinanciersExtractor.php`

### Points positifs
- Exclusion explicite des cryptos (géré par AutresEpargnes)
- Déduplication et sanitization côté PHP
- Détenteur (client/conjoint/commun) géré
- Exemples avec dates partielles (YYYY → YYYY-01-01)

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## ClientBiensImmobiliersExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ClientBiensImmobiliersExtractor.php`

### Points positifs
- Types de propriété bien gérés (pleine, indivision, SCI, usufruit)
- Déduplication par désignation normalisée
- Valeur actuelle vs valeur d'acquisition distinguées

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## ClientAutresEpargnesExtractor

**Fichier** : `backend/app/Services/Ai/Extractors/ClientAutresEpargnesExtractor.php`

### Points positifs
- Catégories bien définies (crypto, métaux, art, bijoux)
- Exclusion explicite des actifs financiers classiques
- Structure simple et claire

### Problèmes détectés
- Aucun problème majeur détecté

### Score de compatibilité Mistral : 9/10

---

## MeetingSummaryService

**Fichier** : `backend/app/Services/MeetingSummaryService.php`

### Points positifs
- Structure JSON complexe bien documentée
- Temperature légèrement plus haute (0.2) pour génération créative
- Exemple Input/Output présent
- Formatting texte côté PHP

### Problèmes détectés
- **[BASSE]** Un seul exemple fourni, pourrait en avoir plus
  > Recommandation : Ajouter 1-2 exemples de transcriptions plus longues

### Score de compatibilité Mistral : 8/10

---

## Recommandations Générales

### Déjà en place
- [x] Structure de prompt optimale pour Mistral
- [x] Instructions JSON explicites
- [x] Exemples Input/Output
- [x] Température basse pour extraction (0.1)
- [x] Fallback automatique vers OpenAI
- [x] Logging des réponses
- [x] Aucune référence à GPT/OpenAI dans les prompts

### Améliorations mineures suggérées

1. **Ajouter un exemple de sortie vide** dans ClientExtractor
2. **Ajouter des exemples supplémentaires** dans MeetingSummaryService
3. **Tester en production** avec `MISTRAL_USE_FOR_LLM=true`

---

## Configuration pour activer Mistral

Dans le fichier `.env` :

```env
# Activer Mistral pour les LLM
MISTRAL_API_KEY=your_mistral_api_key
MISTRAL_USE_FOR_LLM=true
MISTRAL_FALLBACK=true

# Activer Mistral pour la transcription (optionnel)
MISTRAL_USE_FOR_STT=true
```

---

## Conclusion

Les prompts du CRM sont **excellemment compatibles avec Mistral**. La migration peut être effectuée en toute confiance avec le fallback automatique activé.

**Actions recommandées** :
1. Activer `MISTRAL_USE_FOR_LLM=true` en staging
2. Surveiller les logs pour détecter d'éventuels fallbacks
3. Comparer les résultats Mistral vs OpenAI sur un échantillon de transcriptions
4. Passer en production une fois validé

---

*Rapport généré par l'audit automatisé Ralph*

---

## Annexe : Checklist de compatibilité Mistral

### Checklist validée pour tous les extracteurs

| Critère | Statut |
|---------|--------|
| Rôle clairement défini | ✅ |
| Instructions explicites (pas d'implicite GPT) | ✅ |
| Format JSON mentionné si mode JSON activé | ✅ |
| Pas de références à GPT/OpenAI | ✅ |
| Exemples au format Input → Output | ✅ |
| JSON syntaxiquement valide dans les exemples | ✅ |
| Schéma documenté avec types | ✅ |
| Comportement si info manquante (retourner {}) | ✅ |
| Gestion des cas ambigus | ✅ |
| Gestion des transcriptions vides/hors sujet | ✅ |
| Temperature déterministe (0.1) | ✅ |
| Prompts en français cohérents | ✅ |

### Différences clés Mistral vs OpenAI (référence)

| Aspect | OpenAI GPT-4o-mini | Mistral Small | Impact projet |
|--------|-------------------|---------------|---------------|
| JSON mode | Implicite si demandé | Explicite requis | ✅ Déjà explicite |
| Instructions | Tolérant aux ambiguïtés | Préfère la précision | ✅ Prompts précis |
| Exemples | Optionnels | Fortement recommandés | ✅ Nombreux exemples |
| Langue | Multilingue natif | Excellent en français | ✅ Tout en français |
| Temperature | 0-2 | 0-1 | ✅ Utilise 0.1 |
