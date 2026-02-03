---
name: audit-prompts
description: Analyser tous les prompts IA du projet (extracteurs, services) et verifier leur compatibilite avec Mistral. Utiliser pour auditer les prompts avant ou apres une migration vers Mistral.
allowed-tools: Read, Grep, Glob
---

# Audit des Prompts IA pour Mistral

## Objectif

Analyser tous les prompts du projet pour s'assurer qu'ils sont optimises pour Mistral (et compatibles OpenAI en fallback).

## Fichiers a analyser

### Services avec prompts LLM

```
backend/app/Services/Ai/RouterService.php
backend/app/Services/Ai/Extractors/ClientExtractor.php
backend/app/Services/Ai/Extractors/ConjointExtractor.php
backend/app/Services/Ai/Extractors/PrevoyanceExtractor.php
backend/app/Services/Ai/Extractors/RetraiteExtractor.php
backend/app/Services/Ai/Extractors/EpargneExtractor.php
backend/app/Services/Ai/Extractors/ClientRevenusExtractor.php
backend/app/Services/Ai/Extractors/ClientPassifsExtractor.php
backend/app/Services/Ai/Extractors/ClientActifsFinanciersExtractor.php
backend/app/Services/Ai/Extractors/ClientBiensImmobiliersExtractor.php
backend/app/Services/Ai/Extractors/ClientAutresEpargnesExtractor.php
backend/app/Services/MeetingSummaryService.php
```

## Checklist de compatibilite Mistral

### 1. Structure du prompt systeme

- [ ] **Role clair** : Le prompt systeme doit definir clairement le role de l'assistant
- [ ] **Instructions explicites** : Pas d'instructions implicites qui fonctionnent avec GPT mais pas Mistral
- [ ] **Format JSON** : Instruction explicite "Reponds UNIQUEMENT avec du JSON valide" si mode JSON active
- [ ] **Pas de references a GPT/OpenAI** : Remplacer toute reference a "GPT" par "LLM" ou "assistant"

### 2. Format des exemples

- [ ] **Exemples clairs** : Les exemples doivent etre au format `Input → Output`
- [ ] **JSON valide** : Tous les exemples JSON doivent etre syntaxiquement valides
- [ ] **Coherence** : Les exemples doivent correspondre exactement au schema demande

### 3. Instructions de sortie

- [ ] **JSON explicite** : Si `response_format: json_object` est utilise, le prompt DOIT mentionner "JSON"
- [ ] **Schema documente** : Tous les champs attendus sont documentes avec leur type
- [ ] **Valeurs par defaut** : Comportement clair si une info n'est pas trouvee (retourner `{}` ou omettre le champ)

### 4. Gestion des edge cases

- [ ] **Donnees manquantes** : Instruction claire sur quoi faire si l'info n'est pas dans la transcription
- [ ] **Ambiguite** : Instruction sur comment gerer les cas ambigus ("en cas de doute, ne pas extraire")
- [ ] **Erreurs** : Le prompt gere les transcriptions vides ou hors sujet

### 5. Specificites Mistral

- [ ] **Temperature** : 0.1 pour extraction deterministe, 0.2-0.3 pour generation creative
- [ ] **Longueur** : Mistral supporte bien les longs prompts mais prefere la concision
- [ ] **Langue** : Si le prompt est en francais, les instructions et exemples doivent etre coherents
- [ ] **Emojis** : Mistral gere les emojis mais ils peuvent affecter le parsing JSON

### 6. Anti-patterns a eviter

- [ ] **"As an AI trained by OpenAI"** : A supprimer
- [ ] **"GPT-4"** : A remplacer par "assistant" ou "LLM"
- [ ] **Instructions contradictoires** : Verifier la coherence du prompt
- [ ] **Trop de regles** : Simplifier si possible (Mistral peut etre confus avec trop d'exceptions)

## Format du rapport

Pour chaque fichier, reporter :

```
## [NomExtractor]

**Fichier** : `backend/app/Services/Ai/Extractors/NomExtractor.php`

### Points positifs
- ...

### Problemes detectes
- **[SEVERITE]** Description du probleme
  > Correction recommandee

### Score de compatibilite Mistral : X/10
```

Severites : CRITIQUE, HAUTE, MOYENNE, BASSE, INFO

## Criteres de scoring

- **10/10** : Prompt parfaitement compatible Mistral
- **8-9/10** : Quelques ajustements mineurs recommandes
- **6-7/10** : Modifications necessaires pour une compatibilite optimale
- **4-5/10** : Risques de comportements differents entre Mistral et OpenAI
- **< 4/10** : Refactoring important necessaire

## Recommandations generales pour Mistral

### Structure optimale d'un prompt systeme

```
Tu es un assistant specialise en [DOMAINE].

OBJECTIF :
[Description claire de la tache]

REGLES :
1. [Regle 1]
2. [Regle 2]
...

FORMAT DE SORTIE :
Reponds UNIQUEMENT avec un JSON valide au format :
{
  "champ1": "type",
  "champ2": "type"
}

EXEMPLES :
Input: "..."
Output: {"champ1": "valeur"}

Input: "..."
Output: {}
```

### Differences cles Mistral vs OpenAI

| Aspect | OpenAI GPT-4o-mini | Mistral Small |
|--------|-------------------|---------------|
| JSON mode | Implicite si demande | Explicite requis |
| Instructions | Tolerant aux ambiguites | Prefere la precision |
| Exemples | Optionnels | Fortement recommandes |
| Langue | Multilingue natif | Excellent en francais |
| Temperature | 0-2 | 0-1 |

## Execution de l'audit

1. Lire chaque fichier listee ci-dessus
2. Extraire les methodes `getSystemPrompt()` et `buildPrompt()`
3. Evaluer selon la checklist
4. Generer le rapport avec scores et recommandations
