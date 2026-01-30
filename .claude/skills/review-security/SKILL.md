---
name: review-security
description: Audit de securite et conformite RGPD du code. Utiliser automatiquement apres avoir ecrit ou modifie du code qui touche aux donnees personnelles, a l'authentification, aux uploads de fichiers ou aux endpoints API.
allowed-tools: Read, Grep, Glob
---

# Audit Securite et RGPD

## Checklist securite a verifier

### 1. Injection SQL
- [ ] Pas de requetes SQL brutes avec des variables utilisateur
- [ ] Utilisation d'Eloquent ou Query Builder (binding automatique)
- [ ] Pas de `DB::raw()` avec des inputs non sanitises

### 2. XSS (Cross-Site Scripting)
- [ ] Cote React : pas de `dangerouslySetInnerHTML` sans sanitisation
- [ ] Les donnees affichees sont echappees par defaut (React le fait)
- [ ] Pas de concatenation HTML cote backend dans les reponses JSON

### 3. Authentification & Autorisation
- [ ] Routes protegees par middleware `auth:sanctum`
- [ ] Verification `team_id` dans les requetes (multi-tenant)
- [ ] Pas d'IDOR (un user ne peut pas acceder aux donnees d'un autre)
- [ ] Route model binding avec scope team

### 4. Upload de fichiers
- [ ] Validation MIME type (`mimes:pdf,jpg,jpeg,png`)
- [ ] Taille maximale (`max:10240`)
- [ ] Stockage sur S3 (pas en local public)
- [ ] Pas d'execution de fichiers uploades
- [ ] Noms de fichiers sanitises

### 5. RGPD - Donnees personnelles
- [ ] Suppression en cascade via Observer (ClientObserver)
- [ ] Audit trail pour les suppressions (AuditService)
- [ ] Pas de donnees personnelles dans les logs (sauf ID)
- [ ] Droit a l'oubli : possibilite de supprimer toutes les donnees d'un client
- [ ] Consentement trace pour les imports de donnees externes
- [ ] Purge automatique des fichiers audio anciens (PurgeOldAudioRecords)

### 6. API
- [ ] Rate limiting sur les endpoints sensibles (audio upload, auth)
- [ ] Validation de tous les inputs
- [ ] Reponses d'erreur sans stack trace en production
- [ ] Pas de donnees sensibles dans les reponses (mots de passe, tokens)

### 7. Secrets
- [ ] Pas de cles API en dur dans le code
- [ ] `.env` dans `.gitignore`
- [ ] Variables sensibles uniquement via `env()`

## Format du rapport

Pour chaque probleme trouve, reporter :
```
**[SEVERITE]** Fichier:ligne - Description du probleme
> Recommandation de correction
```

Severites : CRITIQUE, HAUTE, MOYENNE, BASSE, INFO
