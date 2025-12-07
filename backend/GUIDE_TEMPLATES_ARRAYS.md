# Guide: Format des templates avec données répétitives

## Vue d'ensemble

Les nouvelles tables relationnelles permettent de gérer plusieurs entrées pour les revenus, passifs, actifs financiers, biens immobiliers et autres épargnes d'un client.

## Nouvelles tables créées

### 1. **client_revenus**
Gère les sources de revenus multiples (salaires, pensions, revenus locatifs, etc.)

**Colonnes :**
- `id` : Identifiant unique
- `client_id` : Référence au client
- `nature` : Type de revenu (salaire, pension, etc.)
- `periodicite` : Fréquence (mensuel, annuel, etc.)
- `montant` : Montant du revenu

**Utilisation dans les templates :**
```
Premier revenu:
- Nature: {{client_revenus[0].nature}}
- Périodicité: {{client_revenus[0].periodicite}}
- Montant: {{client_revenus[0].montant}}

Deuxième revenu:
- Nature: {{client_revenus[1].nature}}
- Périodicité: {{client_revenus[1].periodicite}}
- Montant: {{client_revenus[1].montant}}
```

### 2. **client_passifs**
Gère les emprunts et dettes multiples

**Colonnes :**
- `id` : Identifiant unique
- `client_id` : Référence au client
- `nature` : Type de prêt (immobilier, consommation, etc.)
- `preteur` : Nom de l'établissement prêteur
- `periodicite` : Fréquence de remboursement
- `montant_remboursement` : Montant des échéances
- `capital_restant_du` : Capital restant dû
- `duree_restante` : Durée restante en mois

**Utilisation dans les templates :**
```
Premier prêt:
- Nature: {{client_passifs[0].nature}}
- Prêteur: {{client_passifs[0].preteur}}
- Mensualité: {{client_passifs[0].montant_remboursement}} €
- Capital restant: {{client_passifs[0].capital_restant_du}} €
- Durée restante: {{client_passifs[0].duree_restante}} mois

Deuxième prêt:
- Nature: {{client_passifs[1].nature}}
- Prêteur: {{client_passifs[1].preteur}}
- Mensualité: {{client_passifs[1].montant_remboursement}} €
```

### 3. **client_actifs_financiers**
Gère les actifs financiers multiples (assurance-vie, PEA, compte-titres, etc.)

**Colonnes :**
- `id` : Identifiant unique
- `client_id` : Référence au client
- `nature` : Type de produit (assurance-vie, PEA, etc.)
- `etablissement` : Nom de l'établissement
- `detenteur` : Titulaire du contrat
- `date_ouverture_souscription` : Date d'ouverture/souscription
- `valeur_actuelle` : Valeur actuelle du contrat

**Utilisation dans les templates :**
```
Premier actif financier:
- Nature: {{client_actifs_financiers[0].nature}}
- Établissement: {{client_actifs_financiers[0].etablissement}}
- Détenteur: {{client_actifs_financiers[0].detenteur}}
- Date d'ouverture: {{client_actifs_financiers[0].date_ouverture_souscription}}
- Valeur: {{client_actifs_financiers[0].valeur_actuelle}} €

Deuxième actif financier:
- Nature: {{client_actifs_financiers[1].nature}}
- Établissement: {{client_actifs_financiers[1].etablissement}}
```

### 4. **client_biens_immobiliers**
Gère les biens immobiliers multiples

**Colonnes :**
- `id` : Identifiant unique
- `client_id` : Référence au client
- `designation` : Description du bien
- `detenteur` : Propriétaire(s)
- `forme_propriete` : Forme juridique (indivision, SCI, etc.)
- `valeur_actuelle_estimee` : Valeur estimée actuelle
- `annee_acquisition` : Année d'acquisition
- `valeur_acquisition` : Prix d'achat

**Utilisation dans les templates :**
```
Premier bien immobilier:
- Désignation: {{client_biens_immobiliers[0].designation}}
- Détenteur: {{client_biens_immobiliers[0].detenteur}}
- Forme de propriété: {{client_biens_immobiliers[0].forme_propriete}}
- Valeur estimée: {{client_biens_immobiliers[0].valeur_actuelle_estimee}} €
- Année d'acquisition: {{client_biens_immobiliers[0].annee_acquisition}}
- Valeur d'acquisition: {{client_biens_immobiliers[0].valeur_acquisition}} €

Deuxième bien immobilier:
- Désignation: {{client_biens_immobiliers[1].designation}}
- Valeur estimée: {{client_biens_immobiliers[1].valeur_actuelle_estimee}} €
```

### 5. **client_autres_epargnes**
Gère les autres formes d'épargne

**Colonnes :**
- `id` : Identifiant unique
- `client_id` : Référence au client
- `designation` : Description de l'épargne
- `detenteur` : Titulaire
- `valeur` : Valeur actuelle

**Utilisation dans les templates :**
```
Première épargne:
- Désignation: {{client_autres_epargnes[0].designation}}
- Détenteur: {{client_autres_epargnes[0].detenteur}}
- Valeur: {{client_autres_epargnes[0].valeur}} €

Deuxième épargne:
- Désignation: {{client_autres_epargnes[1].designation}}
- Valeur: {{client_autres_epargnes[1].valeur}} €
```

## Migration de l'ancien format

### Ancien format (recueil-global-pp-2025.docx)
```
Nature D: {natureD}
Périodicité D: {periodiciteD}
Montant D: {montantD}

Nature E: {natureE}
Périodicité E: {periodiciteE}
Montant E: {montantE}
```

### Nouveau format (recommandé)
```
Revenu 1:
- Nature: {{client_revenus[0].nature}}
- Périodicité: {{client_revenus[0].periodicite}}
- Montant: {{client_revenus[0].montant}} €

Revenu 2:
- Nature: {{client_revenus[1].nature}}
- Périodicité: {{client_revenus[1].periodicite}}
- Montant: {{client_revenus[1].montant}} €
```

## Boucles et affichage conditionnel

Pour des templates plus avancés, vous pouvez utiliser des sections répétitives dans Word qui seront traitées par le système de génération de documents.

## Variables supplémentaires disponibles

### Enfants (données existantes avec nouvel accès)
```
{{enfants[0].prenom}} {{enfants[0].nom}}
Né(e) le {{enfants[0].date_naissance}}
À charge fiscalement: {{enfants[0].fiscalement_a_charge}}
```

### Conjoint (données existantes)
```
{{conjoints.nom}} {{conjoints.prenom}}
Profession: {{conjoints.profession}}
Fumeur: {{conjoints.fumeur}}
Km parcourus/an: {{conjoints.km_parcourus_annuels}}
```

## Notes importantes

1. **Indexation à partir de 0** : Le premier élément est `[0]`, le deuxième `[1]`, etc.

2. **Gestion des éléments manquants** : Si un index n'existe pas (ex: `client_revenus[5]` alors que le client n'a que 2 revenus), la variable sera remplacée par une chaîne vide.

3. **Format des dates** : Les dates sont au format `Y-m-d` (ex: 2025-12-05)

4. **Format des montants** : Les montants sont des décimaux avec 2 décimales

5. **Variables spéciales toujours disponibles** :
   - `{{current_date}}` : Date actuelle
   - `{{enfants.count}}` : Nombre d'enfants

## Exemples de templates complets

Voir les fichiers de templates existants pour des exemples :
- `rc-prevoyance.docx` : Variables simples
- `rc-sante.docx` : Variables avec conditions
- `Template Mandat.docx` : Variables client et conjoint

## Support

Pour toute question sur le format des templates, consulter :
- `verify-templates-from-migrations.php` : Script de vérification
- `DocumentGeneratorService.php` : Service de génération
