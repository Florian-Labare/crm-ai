# Architecture Base de Données

## Vue d'ensemble

**SGBD :** MariaDB 11 (MySQL-compatible)
**Charset :** utf8mb4 (support émojis et caractères spéciaux)
**Collation :** utf8mb4_unicode_ci
**Migrations :** 50+ fichiers de migration Laravel
**ORM :** Eloquent avec relations complexes

## Schéma Global - Relations

```
┌─────────────┐
│    teams    │ ──┐
└─────────────┘   │
                  │
┌─────────────┐   │  ┌──────────────────┐
│    users    │ ──┼──│  team_user       │ (pivot)
└─────────────┘   │  └──────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────────────┐
│                      clients                         │
│  ┌─────────────────────────────────────────────┐   │
│  │ Données personnelles (nom, prénom, etc.)    │   │
│  │ Coordonnées (adresse, téléphone, email)     │   │
│  │ Situation familiale et professionnelle      │   │
│  │ Besoins (JSON array)                        │   │
│  └─────────────────────────────────────────────┘   │
└──────────────────┬──────────────────────────────────┘
                   │
        ┌──────────┴──────────────────────────────────────────┐
        │                                                      │
    ┌───▼────────┐  ┌──────────────┐  ┌────────────────┐    │
    │ conjoints  │  │   enfants    │  │ sante_souhaits │    │
    └────────────┘  └──────────────┘  └────────────────┘    │
                                                             │
    ┌─────────────────┐  ┌────────────────┐  ┌─────────────────┐
    │ bae_prevoyances │  │ bae_retraites  │  │  bae_epargnes   │
    └─────────────────┘  └────────────────┘  └─────────────────┘
                                                             │
    ┌──────────────────┐  ┌──────────────────┐  ┌────────────────────┐
    │ client_revenus   │  │ client_passifs   │  │ client_actifs_*    │
    └──────────────────┘  └──────────────────┘  └────────────────────┘
                                                             │
    ┌─────────────────────┐  ┌──────────────────────┐      │
    │ questionnaire_      │  │ generated_documents  │      │
    │ risques (+ 3 rel.)  │  └──────────────────────┘      │
    └─────────────────────┘                                 │
                                                            │
    ┌──────────────────┐  ┌────────────────────────┐       │
    │  audio_records   │  │  recording_sessions    │       │
    └──────────────────┘  └────────────────────────┘       │
                                                            │
    ┌──────────────────┐  ┌────────────────────────┐       │
    │  audit_logs      │  │  diarization_logs      │       │
    └──────────────────┘  └────────────────────────┘
```

## Tables Principales

### 1. teams

**Rôle :** Multi-tenancy - Isolation des données par cabinet de courtage

| Colonne | Type | Description |
|---------|------|-------------|
| id | BIGINT UNSIGNED | PK auto-increment |
| name | VARCHAR(255) | Nom du cabinet/team |
| created_at | TIMESTAMP | Date création |
| updated_at | TIMESTAMP | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)

**Relations :**
- `hasMany` → users (via team_user)
- `hasMany` → clients
- `hasMany` → audio_records
- `hasMany` → recording_sessions

---

### 2. users

**Rôle :** Utilisateurs (courtiers) du système

| Colonne | Type | Description |
|---------|------|-------------|
| id | BIGINT UNSIGNED | PK auto-increment |
| name | VARCHAR(255) | Nom complet |
| email | VARCHAR(255) UNIQUE | Email (login) |
| email_verified_at | TIMESTAMP NULL | Vérification email |
| password | VARCHAR(255) | Hash bcrypt |
| remember_token | VARCHAR(100) NULL | Token "remember me" |
| current_team_id | BIGINT UNSIGNED NULL | Team active |
| two_factor_secret | TEXT NULL | Secret 2FA |
| two_factor_recovery_codes | TEXT NULL | Codes récupération 2FA |
| two_factor_confirmed_at | TIMESTAMP NULL | Date confirmation 2FA |
| created_at | TIMESTAMP | Date création |
| updated_at | TIMESTAMP | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (email)
- INDEX (current_team_id)

**Relations :**
- `belongsToMany` → teams (via team_user)
- `hasMany` → clients (créateur)
- `hasMany` → audio_records

---

### 3. team_user (pivot)

**Rôle :** Table pivot pour relation many-to-many users ↔ teams

| Colonne | Type | Description |
|---------|------|-------------|
| id | BIGINT UNSIGNED | PK auto-increment |
| team_id | BIGINT UNSIGNED | FK vers teams |
| user_id | BIGINT UNSIGNED | FK vers users |
| role | VARCHAR(255) | admin, member, guest |
| created_at | TIMESTAMP | Date ajout |
| updated_at | TIMESTAMP | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (team_id, user_id)
- FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

---

### 4. clients

**Rôle :** Hub central - Toutes les informations client

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| team_id | BIGINT UNSIGNED | NO | FK vers teams (isolation) |
| user_id | BIGINT UNSIGNED | YES | FK vers users (créateur) |
| **IDENTITÉ** |
| civilite | VARCHAR(10) | YES | M., Mme, Mlle |
| nom | VARCHAR(255) | YES | Nom de famille |
| nom_jeune_fille | VARCHAR(255) | YES | Nom de jeune fille |
| prenom | VARCHAR(255) | YES | Prénom |
| date_naissance | DATE | YES | Date de naissance |
| lieu_naissance | VARCHAR(255) | YES | Ville de naissance |
| nationalite | VARCHAR(100) | YES | Nationalité |
| **COORDONNÉES** |
| adresse | TEXT | YES | Numéro et nom de rue |
| code_postal | VARCHAR(10) | YES | Code postal |
| ville | VARCHAR(255) | YES | Ville |
| residence_fiscale | VARCHAR(100) | YES | Pays résidence fiscale |
| telephone | VARCHAR(20) | YES | Téléphone |
| email | VARCHAR(255) | YES | Email |
| **SITUATION FAMILIALE** |
| situation_matrimoniale | VARCHAR(50) | YES | Marié(e), Célibataire, etc. |
| date_situation_matrimoniale | DATE | YES | Date du mariage/PACS/divorce |
| **SITUATION PROFESSIONNELLE** |
| situation_actuelle | VARCHAR(100) | YES | Salarié(e), Retraité(e), etc. |
| profession | VARCHAR(255) | YES | Métier |
| date_evenement_professionnel | DATE | YES | Date événement pro |
| risques_professionnels | BOOLEAN | YES | true/false |
| details_risques_professionnels | TEXT | YES | Détails des risques |
| revenus_annuels | DECIMAL(12,2) | YES | Revenus annuels |
| **ENTREPRISE** |
| chef_entreprise | BOOLEAN | YES | Chef d'entreprise |
| statut | VARCHAR(100) | YES | SARL, SAS, SASU, etc. |
| travailleur_independant | BOOLEAN | YES | Freelance |
| mandataire_social | BOOLEAN | YES | Mandataire social |
| **SANTÉ & LOISIRS** |
| fumeur | BOOLEAN | YES | Fumeur |
| activites_sportives | BOOLEAN | YES | Pratique sport |
| details_activites_sportives | TEXT | YES | Détails activités |
| niveau_activites_sportives | VARCHAR(50) | YES | Occasionnel, régulier, etc. |
| **BESOINS & MÉTADONNÉES** |
| besoins | JSON | YES | ["Prévoyance", "Retraite", ...] |
| transcription_path | VARCHAR(255) | YES | Chemin fichier transcription |
| consentement_audio | BOOLEAN | NO | Consentement enregistrement (défaut: false) |
| **DER (Document d'Entrée en Relation)** |
| der_charge_clientele_id | BIGINT UNSIGNED | YES | FK vers users (conseiller) |
| der_lieu_rdv | VARCHAR(255) | YES | Lieu RDV |
| der_date_rdv | DATE | YES | Date RDV |
| der_heure_rdv | TIME | YES | Heure RDV |
| **TIMESTAMPS** |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (team_id)
- INDEX (user_id)
- INDEX (der_charge_clientele_id)
- FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
- FOREIGN KEY (der_charge_clientele_id) REFERENCES users(id) ON DELETE SET NULL

**Scope Global :** `TeamScope` appliqué automatiquement

---

### 5. conjoints

**Rôle :** Informations sur le conjoint du client

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| civilite | VARCHAR(10) | YES | M., Mme |
| nom | VARCHAR(255) | YES | Nom de famille |
| nom_jeune_fille | VARCHAR(255) | YES | Nom de jeune fille |
| prenom | VARCHAR(255) | YES | Prénom |
| date_naissance | DATE | YES | Date de naissance |
| lieu_naissance | VARCHAR(255) | YES | Lieu de naissance |
| nationalite | VARCHAR(100) | YES | Nationalité |
| profession | VARCHAR(255) | YES | Métier |
| situation_actuelle_statut | VARCHAR(100) | YES | Salarié(e), Retraité(e), etc. |
| chef_entreprise | BOOLEAN | YES | Chef d'entreprise |
| date_evenement_professionnel | DATE | YES | Date événement pro |
| risques_professionnels | BOOLEAN | YES | Risques pro |
| details_risques_professionnels | TEXT | YES | Détails risques |
| telephone | VARCHAR(20) | YES | Téléphone |
| adresse | TEXT | YES | Adresse (si différente) |
| fumeur | BOOLEAN | YES | Fumeur |
| km_annuels | INT | YES | Km annuels (pour assurance auto) |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client (one-to-one)

---

### 6. enfants

**Rôle :** Enfants du client (relation one-to-many)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| nom | VARCHAR(255) | YES | Nom de famille |
| prenom | VARCHAR(255) | YES | Prénom |
| date_naissance | DATE | YES | Date de naissance |
| fiscalement_a_charge | BOOLEAN | YES | À charge fiscalement |
| garde_alternee | BOOLEAN | YES | Garde alternée |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client

---

### 7. sante_souhaits

**Rôle :** Besoins santé/mutuelle du client

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| mutuelle_actuelle | VARCHAR(255) | YES | Nom mutuelle actuelle |
| montant_cotisation | DECIMAL(10,2) | YES | Cotisation mensuelle |
| souhait_amelioration | TEXT | YES | Souhaits d'amélioration |
| garanties_souhaitees | JSON | YES | ["Optique", "Dentaire", ...] |
| niveau_couverture | VARCHAR(100) | YES | Base, Confort, Premium |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client (one-to-one)

---

### 8. bae_prevoyances (Besoin d'Analyse Épargne - Prévoyance)

**Rôle :** Besoins prévoyance (invalidité, décès)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| montant_itt_souhait | DECIMAL(10,2) | YES | Montant ITT souhaité |
| montant_invalidite_souhait | DECIMAL(10,2) | YES | Montant invalidité |
| capital_deces | DECIMAL(12,2) | YES | Capital décès souhaité |
| rente_conjoint | DECIMAL(10,2) | YES | Rente conjoint |
| rente_enfant | DECIMAL(10,2) | YES | Rente enfant |
| garanties_complementaires | JSON | YES | ["Fractures", "Hospitalisation", ...] |
| franchise_souhaitee | INT | YES | Franchise en jours |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client (one-to-one)

---

### 9. bae_retraites

**Rôle :** Besoins retraite (épargne retraite)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| age_depart_souhaite | INT | YES | Âge départ souhaité |
| revenus_foyer_apres_impot | DECIMAL(12,2) | YES | Revenus foyer après IR |
| tmi | DECIMAL(5,2) | YES | Tranche Marginale Imposition (%) |
| trimestres_valides | INT | YES | Nombre trimestres validés |
| montant_pension_estimee | DECIMAL(10,2) | YES | Pension estimée |
| besoin_complementaire | DECIMAL(10,2) | YES | Besoin retraite complémentaire |
| solution_envisagee | VARCHAR(100) | YES | PER, PERP, Assurance-vie, etc. |
| versements_reguliers | DECIMAL(10,2) | YES | Versements réguliers |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client (one-to-one)

---

### 10. bae_epargnes

**Rôle :** Besoins épargne/patrimoine

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| capacite_epargne_mensuelle | DECIMAL(10,2) | YES | Capacité épargne/mois |
| horizon_placement | VARCHAR(100) | YES | Court, Moyen, Long terme |
| objectif_patrimoine | TEXT | YES | Objectif (achat immo, donation, etc.) |
| montant_objectif | DECIMAL(12,2) | YES | Montant objectif |
| date_objectif | DATE | YES | Date objectif |
| tolerance_risque | VARCHAR(50) | YES | Prudent, Équilibré, Dynamique |
| supports_souhaites | JSON | YES | ["Immobilier", "Actions", "Obligations"] |
| projet_immobilier | BOOLEAN | YES | Projet d'achat immobilier |
| details_projet_immo | TEXT | YES | Détails projet |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client (one-to-one)

---

### 11. client_revenus

**Rôle :** Sources de revenus du client (one-to-many)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| type | VARCHAR(100) | YES | Salaire, Loyers, Dividendes, BNC, etc. |
| montant | DECIMAL(12,2) | YES | Montant mensuel ou annuel |
| frequence | VARCHAR(50) | YES | mensuel, annuel |
| description | TEXT | YES | Détails |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client

---

### 12. client_passifs

**Rôle :** Prêts, dettes, emprunts (one-to-many)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| type | VARCHAR(100) | YES | Prêt immo, Crédit conso, etc. |
| organisme | VARCHAR(255) | YES | Banque/organisme |
| montant_initial | DECIMAL(12,2) | YES | Montant emprunté |
| capital_restant_du | DECIMAL(12,2) | YES | Capital restant dû |
| mensualite | DECIMAL(10,2) | YES | Mensualité |
| taux | DECIMAL(5,2) | YES | Taux d'intérêt (%) |
| date_fin | DATE | YES | Date fin remboursement |
| description | TEXT | YES | Détails |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client

---

### 13. client_actifs_financiers

**Rôle :** Actifs financiers (AV, PEA, PER, etc.)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| type | VARCHAR(100) | YES | Assurance-vie, PEA, PER, SCPI, etc. |
| organisme | VARCHAR(255) | YES | Assureur/banque |
| montant | DECIMAL(12,2) | YES | Valorisation actuelle |
| date_souscription | DATE | YES | Date souscription |
| supports | JSON | YES | ["Fonds euros", "UC Actions", ...] |
| beneficiaires | TEXT | YES | Bénéficiaires |
| description | TEXT | YES | Détails |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client

---

### 14. client_biens_immobiliers

**Rôle :** Patrimoine immobilier

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| type | VARCHAR(100) | YES | Rés. principale, secondaire, locatif |
| adresse | TEXT | YES | Adresse complète |
| surface | INT | YES | Surface m² |
| valeur_estimee | DECIMAL(12,2) | YES | Valeur marchande |
| annee_acquisition | INT | YES | Année achat |
| prix_acquisition | DECIMAL(12,2) | YES | Prix d'achat |
| loyer_mensuel | DECIMAL(10,2) | YES | Loyer si locatif |
| charges_mensuelles | DECIMAL(10,2) | YES | Charges copro |
| description | TEXT | YES | Détails |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client

---

### 15. client_autres_epargnes

**Rôle :** Épargnes alternatives (or, crypto, objets d'art)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| type | VARCHAR(100) | YES | Or, Cryptomonnaies, Art, etc. |
| description | TEXT | YES | Détails |
| valeur_estimee | DECIMAL(12,2) | YES | Valorisation |
| date_acquisition | DATE | YES | Date achat |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relation :** `belongsTo` → client

---

### 16. audio_records

**Rôle :** Enregistrements audio traités

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| team_id | BIGINT UNSIGNED | NO | FK vers teams |
| user_id | BIGINT UNSIGNED | NO | FK vers users (créateur) |
| client_id | BIGINT UNSIGNED | YES | FK vers clients (si lié) |
| path | VARCHAR(255) | YES | Chemin fichier audio |
| transcription | LONGTEXT | YES | Transcription brute |
| status | VARCHAR(50) | NO | pending, processing, done, failed |
| error | TEXT | YES | Message d'erreur si failed |
| **DIARISATION** |
| diarization_enabled | BOOLEAN | NO | Diarisation activée (défaut: true) |
| diarization_status | VARCHAR(50) | YES | success, failed, skipped |
| speaker_count | INT | YES | Nombre de speakers détectés |
| confidence_score | DECIMAL(5,2) | YES | Score confiance (0-100) |
| correction_count | INT | YES | Nombre corrections utilisateur |
| needs_review | BOOLEAN | NO | Nécessite revue (défaut: false) |
| transcription_formatted | LONGTEXT | YES | Transcription avec speakers |
| **TIMESTAMPS** |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (team_id)
- INDEX (user_id)
- INDEX (client_id)
- INDEX (status)
- INDEX (needs_review)
- FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL

**Scope Global :** `TeamScope` appliqué automatiquement

---

### 17. recording_sessions

**Rôle :** Sessions d'enregistrement long (chunked)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | VARCHAR(36) | NO | PK UUID |
| team_id | BIGINT UNSIGNED | NO | FK vers teams |
| client_id | BIGINT UNSIGNED | YES | FK vers clients |
| total_parts | INT | NO | Nombre total de chunks |
| status | VARCHAR(50) | NO | uploading, finalizing, completed, failed |
| final_audio_path | VARCHAR(255) | YES | Fichier audio concatené |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (team_id)
- INDEX (client_id)
- FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL

**Scope Global :** `TeamScope` appliqué automatiquement

---

### 18. questionnaire_risques

**Rôle :** Questionnaire de profil risque (hub)

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients UNIQUE |
| score_global | INT | YES | Score total calculé |
| profil_calcule | VARCHAR(50) | YES | Prudent, Équilibré, Dynamique |
| recommandation | TEXT | YES | Recommandation produits |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (client_id)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE

**Relations :**
- `hasOne` → questionnaire_risque_financiers
- `hasOne` → questionnaire_risque_connaissances
- `hasMany` → questionnaire_risque_quizzes

---

### 19. questionnaire_risque_financiers

**Rôle :** Volet financier du questionnaire

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| questionnaire_risque_id | BIGINT UNSIGNED | NO | FK vers questionnaire_risques UNIQUE |
| situation_financiere | VARCHAR(255) | YES | Stable, Variable, etc. |
| objectifs_investissement | TEXT | YES | Objectifs |
| horizon_placement | VARCHAR(100) | YES | Court, Moyen, Long terme |
| tolerance_perte | VARCHAR(100) | YES | Tolérance à la perte |
| objectifs_rapport | TEXT | YES | Rapport objectifs |
| ... | ... | ... | (50+ champs détaillés) |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (questionnaire_risque_id)
- FOREIGN KEY (questionnaire_risque_id) REFERENCES questionnaire_risques(id) ON DELETE CASCADE

---

### 20. document_templates

**Rôle :** Templates de documents réglementaires

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| name | VARCHAR(255) | NO | Nom template |
| type | VARCHAR(50) | NO | word, pdf, html |
| category | VARCHAR(100) | YES | prevoyance, retraite, epargne, etc. |
| template_path | VARCHAR(255) | NO | Chemin fichier template |
| description | TEXT | YES | Description |
| variables | JSON | YES | Variables disponibles |
| active | BOOLEAN | NO | Template actif (défaut: true) |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (category)
- INDEX (active)

---

### 21. generated_documents

**Rôle :** Documents générés pour les clients

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| client_id | BIGINT UNSIGNED | NO | FK vers clients |
| template_id | BIGINT UNSIGNED | YES | FK vers document_templates |
| type | VARCHAR(50) | NO | pdf, word |
| name | VARCHAR(255) | NO | Nom fichier |
| path | VARCHAR(255) | NO | Chemin storage |
| size | BIGINT | YES | Taille en octets |
| generated_by | BIGINT UNSIGNED | YES | FK vers users |
| sent_at | TIMESTAMP | YES | Date envoi email |
| created_at | TIMESTAMP | NO | Date génération |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (client_id)
- INDEX (template_id)
- INDEX (generated_by)
- FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
- FOREIGN KEY (template_id) REFERENCES document_templates(id) ON DELETE SET NULL
- FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL

---

### 22. audit_logs

**Rôle :** Traçabilité RGPD des actions sensibles

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| user_id | BIGINT UNSIGNED | YES | FK vers users |
| action | VARCHAR(100) | NO | create, update, delete, export, etc. |
| model_type | VARCHAR(100) | NO | Client, AudioRecord, etc. |
| model_id | BIGINT UNSIGNED | YES | ID du modèle concerné |
| changes | JSON | YES | Détails des changements |
| ip_address | VARCHAR(45) | YES | IP de l'utilisateur |
| user_agent | TEXT | YES | User agent |
| created_at | TIMESTAMP | NO | Date action |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (user_id)
- INDEX (model_type, model_id)
- INDEX (action)
- INDEX (created_at)
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL

**Rotation :** Recommandé de purger après 2 ans (RGPD)

---

### 23. diarization_logs

**Rôle :** Métriques qualité diarisation

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| audio_record_id | BIGINT UNSIGNED | NO | FK vers audio_records |
| initial_speaker_count | INT | YES | Nombre speakers détectés |
| final_speaker_count | INT | YES | Après corrections |
| correction_count | INT | NO | Nombre corrections (défaut: 0) |
| processing_time | INT | YES | Temps traitement (secondes) |
| model_version | VARCHAR(50) | YES | Version modèle pyannote |
| created_at | TIMESTAMP | NO | Date création |

**Indexes :**
- PRIMARY KEY (id)
- INDEX (audio_record_id)
- FOREIGN KEY (audio_record_id) REFERENCES audio_records(id) ON DELETE CASCADE

---

### 24. personal_access_tokens (Laravel Sanctum)

**Rôle :** Tokens API pour authentification

| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| id | BIGINT UNSIGNED | NO | PK auto-increment |
| tokenable_type | VARCHAR(255) | NO | App\Models\User |
| tokenable_id | BIGINT UNSIGNED | NO | FK vers users |
| name | VARCHAR(255) | NO | Nom token |
| token | VARCHAR(64) | NO | Hash SHA-256 UNIQUE |
| abilities | TEXT | YES | Permissions JSON |
| last_used_at | TIMESTAMP | YES | Dernière utilisation |
| expires_at | TIMESTAMP | YES | Date expiration |
| created_at | TIMESTAMP | NO | Date création |
| updated_at | TIMESTAMP | NO | Date mise à jour |

**Indexes :**
- PRIMARY KEY (id)
- UNIQUE (token)
- INDEX (tokenable_type, tokenable_id)

---

## Volumétrie Estimée (par Cabinet)

### Hypothèses
- Cabinet moyen : 500-2000 clients actifs
- Enregistrements : 2-5 par client/an
- Documents : 10-20 par client/an

### Estimations par table (2000 clients actifs)

| Table | Nb lignes | Taille estimée | Croissance annuelle |
|-------|-----------|----------------|---------------------|
| clients | 2 000 | ~10 MB | +400 lignes/an |
| conjoints | 1 500 | ~5 MB | +300 lignes/an |
| enfants | 3 000 | ~3 MB | +500 lignes/an |
| sante_souhaits | 1 800 | ~5 MB | +360 lignes/an |
| bae_* (x3) | 4 500 | ~15 MB | +900 lignes/an |
| client_revenus | 4 000 | ~8 MB | +800 lignes/an |
| client_passifs | 2 500 | ~6 MB | +500 lignes/an |
| client_actifs_* | 6 000 | ~15 MB | +1200 lignes/an |
| audio_records | 8 000 | ~50 MB (meta) | +4000 lignes/an |
| generated_documents | 30 000 | ~20 MB (meta) | +20 000/an |
| audit_logs | 50 000 | ~100 MB | +50 000/an |

**Total BDD (sans BLOB) :** ~250 MB par cabinet/an
**Fichiers audio :** ~80 GB/an (10 min × 8000 enreg × 1 MB/min)
**Documents PDF/Word :** ~60 GB/an (2 MB × 30 000 docs)

---

## Optimisations Recommandées pour Scaling

### 1. Indexation
✅ **Déjà implémenté :**
- Foreign keys indexées
- team_id indexé partout (TeamScope)
- status, needs_review indexés (filtres fréquents)

⚠️ **À ajouter :**
- Index composite sur `(team_id, created_at)` pour tri rapide
- Full-text index sur `clients.nom, clients.prenom` pour recherche
- Index sur `audio_records.created_at` (requêtes temporelles)

### 2. Partitionnement
🔄 **Recommandations :**
- `audit_logs` : Partitionnement par mois (RANGE sur created_at)
- `audio_records` : Partitionnement par team_id (HASH)
- `generated_documents` : Archivage après 2 ans

### 3. Réplication
🔄 **Multi-cabinets (10-20 teams) :**
- Master-Slave replication (lecture sur slaves)
- Sharding par team_id (1 base = 5-10 teams max)

### 4. Stockage Fichiers
⚠️ **Actuellement :** Volume Docker local
🔄 **Recommandation :** Object Storage (S3, MinIO, OVH Object Storage)
- Audio files : S3 avec lifecycle (archivage Glacier après 6 mois)
- Documents : S3 + CloudFront CDN

### 5. Backup & Recovery
🔄 **Stratégie recommandée :**
- Backup quotidien complet (MariaDB dump)
- Backup incrémental toutes les 6h (binlogs)
- Rétention : 30 jours + 1 backup mensuel 1 an
- Point-in-time recovery (PITR) via binlogs

---

## Conformité RGPD

### Données personnelles sensibles
- **Clients :** Nom, prénom, date naissance, adresse, santé (fumeur, activités)
- **Conjoints :** Idem
- **Enfants :** Nom, prénom, date naissance
- **Audio :** Voix = donnée biométrique

### Mesures implémentées
✅ **Chiffrement :** APP_KEY Laravel (chiffrement data sensibles)
✅ **Audit :** audit_logs (traçabilité complète)
✅ **Isolation :** TeamScope (multi-tenancy strict)
✅ **Consentement :** `clients.consentement_audio`

### À renforcer
⚠️ **Chiffrement au repos :** MariaDB encryption at rest
⚠️ **Pseudonymisation :** Hash des noms dans audit_logs
⚠️ **Droit à l'oubli :** Script automatisé de suppression client + cascade
⚠️ **Exports RGPD :** API d'export données personnelles

---

**Version :** 1.0
**Date :** 2026-01-02
**Dernière migration :** 2025_12_18_100002_create_audit_logs_table
