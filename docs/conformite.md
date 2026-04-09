# Système de Conformité / Compliance

## Vue d'ensemble

Le module de conformité permet de gérer les documents réglementaires associés à chaque client. Il couvre deux niveaux :

1. **Documents d'identité et bancaires** — obligatoires pour tous les clients (CNI, avis d'imposition, RIB)
2. **Documents réglementaires par besoin** — activés selon les besoins du client (prévoyance, retraite, épargne, santé, immobilier, fiscalité)

Le système attribue à chaque client un **badge tricolore** (vert / orange / rouge) visible depuis la liste des clients et le tableau de bord global.

---

## Architecture

```
client_compliance_documents          ← documents uploadés
        │
        ├── compliance_document_requirements   ← liaison many-to-many (pivot enrichi)
        │
compliance_requirements              ← référentiel des exigences
```

### Tables

| Table | Rôle |
|---|---|
| `client_compliance_documents` | Tous les documents uploadés pour un client |
| `compliance_requirements` | Référentiel des exigences (CNI, DER, lettre de mission…) |
| `compliance_document_requirements` | Liaison entre un document signé et une exigence |
| `import_audit_logs` | Traçabilité RGPD des actions d'import |

---

## Types de documents

### Documents globaux (obligatoires pour tous les clients)

| Code | Label | Catégorie |
|---|---|---|
| `cni` | Carte d'identité | Identité |
| `avis_imposition_n1` | Avis d'imposition N-1 | Fiscal |
| `rib` | RIB | Bancaire |

### Documents réglementaires par besoin

Ces documents ne sont requis que si le client a un **document signé taggé** avec le besoin correspondant.

| Besoin | Code | Label | Obligatoire |
|---|---|---|---|
| **Prévoyance** | `lettre_mission_prevoyance` | Lettre de mission | ✅ |
| | `der_prevoyance` | DER | ✅ |
| | `fiche_conseil_prevoyance` | Fiche conseil | ❌ |
| **Retraite** | `lettre_mission_retraite` | Lettre de mission | ✅ |
| | `der_retraite` | DER | ✅ |
| | `fiche_conseil_retraite` | Fiche conseil | ❌ |
| **Épargne** | `lettre_mission_epargne` | Lettre de mission | ✅ |
| | `der_epargne` | DER | ✅ |
| | `fiche_conseil_epargne` | Fiche conseil | ❌ |
| **Santé** | `lettre_mission_sante` | Lettre de mission | ✅ |
| | `fiche_ipid_sante` | Fiche IPID | ✅ |
| | `devis_sante` | Devis | ❌ |
| **Immobilier** | `lettre_mission_immobilier` | Lettre de mission | ✅ |
| | `der_immobilier` | DER | ✅ |
| **Fiscalité** | `lettre_mission_fiscalite` | Lettre de mission | ✅ |
| | `der_fiscalite` | DER | ✅ |

---

## Statuts d'un document

| Statut | Description |
|---|---|
| `pending` | Uploadé, en attente de validation |
| `validated` | Validé par un utilisateur |
| `rejected` | Rejeté (raison obligatoire) |
| `expired` | Expiré (calculé à partir de `expires_at`) |

### Statut d'une liaison document signé ↔ exigence

| Statut | Description |
|---|---|
| `pending` | Document lié mais liaison non validée |
| `validated` | Liaison approuvée — l'exigence est satisfaite |
| `rejected` | Liaison refusée |

---

## Badge tricolore

Le badge résume la conformité globale d'un client sur les **trois documents obligatoires** (CNI, avis d'imposition, RIB).

| Couleur | Condition |
|---|---|
| 🟢 **Vert** | Les 3 documents obligatoires sont validés et non expirés |
| 🟠 **Orange** | Au moins un document est en attente (`pending`) ou partiel |
| 🔴 **Rouge** | Au moins un document obligatoire manque |

Le badge affiche également :
- Le nombre de documents **expirés**
- Le nombre de documents **expirant dans moins de 90 jours**

---

## Score de conformité

Le score (`0–100 %`) est calculé sur l'ensemble des exigences applicables au client :

```
score = documents_valides / total_exigences_obligatoires × 100
```

Les exigences applicables sont déterminées dynamiquement :
- Toujours : les exigences `besoin = global`
- Conditionnellement : les exigences dont le `besoin` correspond au tag d'un document signé importé pour ce client

---

## Flux métier

### 1. Upload d'un document standard

```
POST /clients/{client}/compliance/upload
  │
  ├── Validation : PDF/JPG/JPEG/PNG, max 10 Mo
  ├── Stockage : S3
  ├── Création : ClientComplianceDocument (status = pending)
  └── Log
```

### 2. Validation / Rejet

```
POST /clients/{client}/compliance/{document}/validate
  └── status → validated, validated_at, validated_by

POST /clients/{client}/compliance/{document}/reject
  └── status → rejected, rejection_reason (requis, max 500 car.)
```

### 3. Upload d'un document signé (multi-tag)

Un document signé est un document physique (PDF scanné ou signé électroniquement) qui peut couvrir plusieurs besoins à la fois (ex : une lettre de mission signée couvrant prévoyance + retraite).

```
POST /clients/{client}/compliance/upload-signed
  │
  ├── Paramètres : file, tags[] (min 1), custom_label (optionnel), expires_at
  ├── Tags valides : prevoyance | retraite | epargne | sante | immobilier | fiscalite
  ├── document_type → "signed_document"
  ├── category → "signed"
  └── status → pending
```

### 4. Liaison document signé ↔ exigences

Après upload, le document signé doit être **lié** aux exigences qu'il satisfait.

```
POST /clients/{client}/compliance/{document}/link
  └── requirement_ids : array d'IDs d'exigences
      └── Crée des pivots (status = pending) dans compliance_document_requirements
```

### 5. Validation / Rejet d'une liaison

```
POST /clients/{client}/compliance/{document}/validate-link/{requirement}
  └── pivot : status → validated

POST /clients/{client}/compliance/{document}/reject-link/{requirement}
  └── pivot : status → rejected

DELETE /clients/{client}/compliance/{document}/unlink/{requirement}
  └── Supprime le pivot
```

### 6. Téléchargement et suppression

```
GET    /clients/{client}/compliance/{document}/download
DELETE /clients/{client}/compliance/{document}
  └── Supprime fichier S3 + enregistrement DB
```

---

## Détermination du statut d'une exigence

Pour chaque exigence dans la checklist d'un client, le statut est résolu dans l'ordre suivant :

1. **Document direct** : un document dont le `document_type` correspond exactement au code de l'exigence
   - `validated` et non expiré → `valid`
   - `pending` → `pending`
   - `rejected` → `rejected`
   - expiré → `expired`

2. **Document signé lié** : un document signé dont la liaison avec l'exigence est active
   - liaison `validated` → `valid`
   - liaison `pending` → `pending`
   - liaison `rejected` → `rejected`

3. **Aucun document** → `missing`

---

## Dashboard global

`GET /compliance/dashboard`

Retourne pour tous les clients du cabinet :

```json
{
  "summary": {
    "total_clients": 42,
    "fully_compliant": 18,
    "partially_compliant": 15,
    "non_compliant": 9,
    "expired_documents": 3,
    "expiring_soon": 7
  },
  "alerts": [
    {
      "client_id": 12,
      "client_name": "Dupont Jean",
      "severity": "high",
      "type": "expired",
      "message": "CNI expirée",
      "expires_at": "2025-12-01"
    }
  ]
}
```

### Sévérité des alertes

| Sévérité | Condition |
|---|---|
| `high` | Document expiré |
| `medium` | Expire dans ≤ 30 jours |
| `low` | Expire dans 31–90 jours |

`GET /compliance/alerts` retourne la même liste avec pagination (`per_page`, `page`) et filtre (`all` / `expired` / `expiring_soon`).

---

## API complète — Référence

### Conformité client

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/clients/{id}/compliance/badge` | Badge tricolore |
| `GET` | `/clients/{id}/compliance/status` | Checklist détaillée |
| `GET` | `/clients/{id}/compliance/alerts` | Alertes d'expiration |
| `POST` | `/clients/{id}/compliance/upload` | Uploader un document |
| `POST` | `/clients/{id}/compliance/upload-signed` | Uploader un document signé |
| `POST` | `/clients/{id}/compliance/{doc}/validate` | Valider un document |
| `POST` | `/clients/{id}/compliance/{doc}/reject` | Rejeter un document |
| `GET` | `/clients/{id}/compliance/{doc}/download` | Télécharger |
| `DELETE` | `/clients/{id}/compliance/{doc}` | Supprimer |

### Liaisons document signé ↔ exigences

| Méthode | Route | Description |
|---|---|---|
| `POST` | `/clients/{id}/compliance/{doc}/link` | Lier à des exigences |
| `DELETE` | `/clients/{id}/compliance/{doc}/unlink/{req}` | Retirer une liaison |
| `POST` | `/clients/{id}/compliance/{doc}/validate-link/{req}` | Valider une liaison |
| `POST` | `/clients/{id}/compliance/{doc}/reject-link/{req}` | Rejeter une liaison |

### Dashboard global

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/compliance/dashboard` | Résumé + alertes |
| `GET` | `/compliance/alerts` | Alertes paginées |

---

## Formats de fichiers acceptés

| Format | MIME type | Taille max |
|---|---|---|
| PDF | `application/pdf` | 10 Mo |
| JPEG | `image/jpeg` | 10 Mo |
| PNG | `image/png` | 10 Mo |

---

## Traçabilité RGPD

Toutes les actions sur les données clients importées sont tracées dans `import_audit_logs` :

- **Base légale** : `consent` / `contract` / `legitimate_interest`
- **Consentement** : horodaté, avec IP et User-Agent
- **Droit à l'oubli** : `RgpdComplianceService::deleteSessionData()` supprime toutes les données d'une session
- **Export** : `RgpdComplianceService::exportAuditLogs()` pour portabilité

---

## Composants frontend

| Composant | Fichier | Usage |
|---|---|---|
| `ComplianceBadge` | `components/ComplianceBadge.tsx` | Badge sur la liste clients et fiche client |
| `ComplianceStatusCard` | `components/ComplianceStatusCard.tsx` | Cards du dashboard global |
| `ComplianceDashboard` | `pages/ComplianceDashboard.tsx` | Page `/compliance-dashboard` |

### Variants du `ComplianceBadge`

| Variant | Description |
|---|---|
| `badge` | Petit badge compact avec tooltip (utilisé dans les tableaux) |
| `inline` | Icône + texte sur une ligne |
| `detailed` | Carte avec barre de progression, stats et liste des manquants |

---

## Initialisation (Seeder)

Pour peupler le référentiel des exigences en base :

```bash
php artisan db:seed --class=ComplianceRequirementsSeeder
```

Crée les **21 exigences** du référentiel (3 globales + 18 par besoin).

---

## Modèles Eloquent — Relations clés

```
Client
  └── hasMany → ClientComplianceDocument

ClientComplianceDocument
  ├── belongsTo → Client
  ├── belongsTo → User (uploader)
  ├── belongsTo → User (validator)
  └── belongsToMany → ComplianceRequirement
        through: compliance_document_requirements
        pivot: status, validated_at, validated_by

ComplianceRequirement
  └── belongsToMany → ClientComplianceDocument
```

### Accessors utiles sur `ClientComplianceDocument`

| Accessor | Retourne |
|---|---|
| `days_until_expiration` | Nombre de jours avant expiration (négatif si expiré) |
| `document_label` | Label lisible du type de document |
| `category_label` | Label lisible de la catégorie |
| `display_label` | `custom_label` si défini, sinon `file_name` |

### Méthodes utiles

```php
$doc->isExpired();           // bool
$doc->isExpiringSoon(90);    // bool
$doc->isValid();             // validated && !expired
$doc->isSignedDocument();    // document_type === "signed_document"
```
