# Ralph Fix Plan - Migration Mistral

## High Priority
- [x] Vérifier que LlmClientTrait bascule correctement vers Mistral
- [x] Tester RouterService avec Mistral (détection des sections)
- [x] Tester ClientExtractor avec Mistral
- [x] Tester ConjointExtractor avec Mistral
- [x] Valider le fallback automatique vers OpenAI

## Medium Priority
- [x] Tester tous les autres extracteurs (Prévoyance, Retraite, Epargne, etc.)
- [x] Auditer les prompts pour compatibilité Mistral
- [x] Vérifier la transcription STT avec Mistral voxtral-mini
- [x] Documenter les différences de comportement Mistral vs OpenAI

## Low Priority
- [ ] Optimiser les prompts pour réduire les tokens Mistral
- [ ] Ajouter des logs de comparaison Mistral/OpenAI
- [ ] Mettre à jour la documentation API

## Completed
- [x] Configuration Mistral dans config/mistral.php
- [x] Création du trait LlmClientTrait
- [x] Tests LlmClientTrait (9 tests) - switch Mistral/OpenAI, fallback, exceptions
- [x] Tests RouterService (14 tests) - détection sections, garde-fou conjoint
- [x] Tests ClientExtractor (16 tests) - extraction données client
- [x] Tests ConjointExtractor (15 tests) - extraction données conjoint
- [x] Correction migrations SQLite (compatibilité tests)
- [x] Tests PrevoyanceExtractor (9 tests) - détection prévoyance, besoins_action
- [x] Tests RetraiteExtractor (9 tests) - détection retraite, TMI, âge départ
- [x] Tests EpargneExtractor (10 tests) - détection épargne, patrimoine, passifs
- [x] Audit prompts Mistral (12 fichiers) - Score global 9.1/10, tous compatibles
- [x] Tests TranscriptionService (11 tests) - Voxtral STT, fallback, timeout, empty response
- [x] Documentation Mistral vs OpenAI - Différences API, comportements, recommandations
