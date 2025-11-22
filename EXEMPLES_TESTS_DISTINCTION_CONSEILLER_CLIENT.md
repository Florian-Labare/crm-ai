# 📋 Exemples de tests - Distinction Conseiller/Client

Ce document contient des exemples de dialogues pour tester la distinction entre les paroles du conseiller et celles du client lors de l'analyse vocale.

## 🎯 Objectif

Le système doit **UNIQUEMENT** extraire les informations données par le **CLIENT** et **IGNORER** complètement les questions posées par le **CONSEILLER**.

---

## ✅ Exemple 1 : Informations d'identité

### Dialogue
```
Conseiller: Bonjour, quel est votre nom ?
Client: Je m'appelle Florian Labare
Conseiller: Et votre date de naissance ?
Client: Je suis né le 20 janvier 1985
```

### Résultat attendu
```json
{
  "nom": "Labare",
  "prenom": "Florian",
  "date_naissance": "1985-01-20"
}
```

### ❌ Ce qui NE doit PAS être extrait
- Les questions du conseiller ("quel est votre nom", "votre date de naissance")

---

## ✅ Exemple 2 : Questionnaire de risque - Tolérance au risque

### Dialogue
```
Conseiller: Passons maintenant au questionnaire de risque. Quelle est votre tolérance au risque ? Très faible, faible, modérée ou élevée ?
Client: Je dirais modérée, j'accepte un peu de risque pour rechercher du rendement
Conseiller: D'accord. Et quel est votre horizon d'investissement ?
Client: Long terme, j'investis pour ma retraite dans 20 ans
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "financier": {
      "tolerance_risque": "moderee",
      "horizon_investissement": "long_terme"
    }
  }
}
```

### ❌ Ce qui NE doit PAS être extrait
- "Très faible, faible, modérée ou élevée" (énumération du conseiller)
- "Passons maintenant au questionnaire de risque" (transition du conseiller)

---

## ✅ Exemple 3 : Connaissances produits financiers

### Dialogue
```
Conseiller: Connaissez-vous les produits financiers suivants : les actions, les obligations, les SCPI ?
Client: Oui, je connais les actions et les obligations. Par contre, je ne connais pas les SCPI
Conseiller: Et les FIP, FCPI ?
Client: Non, jamais entendu parler
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "connaissances": {
      "connaissance_actions": true,
      "connaissance_obligations": true
    }
  }
}
```

### ❌ Ce qui NE doit PAS être extrait
- `connaissance_opci_scpi` (car le client dit NE PAS connaître)
- `connaissance_fip_fcpi` (car le client dit ne pas connaître)
- Les énumérations du conseiller

---

## ✅ Exemple 4 : Comportement face au risque

### Dialogue
```
Conseiller: Si votre investissement baisse de 25%, quelle serait votre réaction ? Vendriez-vous tout, une partie, ne feriez-vous rien ou achèteriez-vous plus ?
Client: J'attendrais patiemment, je ne ferais rien. Je sais que les marchés fluctuent
Conseiller: Très bien. Comment décririez-vous votre attitude vis-à-vis des placements ?
Client: Je suis plutôt prudent
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "financier": {
      "reaction_baisse_25": "ne_rien_faire",
      "attitude_placements": "prudent"
    }
  }
}
```

---

## ✅ Exemple 5 : Informations personnelles (fumeur, activités sportives)

### Dialogue
```
Conseiller: Êtes-vous fumeur ?
Client: Non
Conseiller: Pratiquez-vous des activités sportives ?
Client: Oui, je fais de la course à pied en loisir
```

### Résultat attendu
```json
{
  "fumeur": false,
  "activites_sportives": true,
  "details_activites_sportives": "course à pied",
  "niveau_activites_sportives": "loisir"
}
```

---

## ✅ Exemple 6 : Besoins exprimés

### Dialogue
```
Conseiller: Quels sont vos besoins en matière d'assurance ?
Client: J'ai besoin d'une mutuelle pour mes enfants et d'une prévoyance
Conseiller: D'accord, autre chose ?
Client: Oui, aussi une assurance vie
```

### Résultat attendu
```json
{
  "besoins": ["mutuelle pour enfants", "prévoyance", "assurance vie"],
  "besoins_action": "replace"
}
```

---

## ✅ Exemple 7 : Coordonnées (adresse, téléphone, email)

### Dialogue
```
Conseiller: Quelle est votre adresse ?
Client: J'habite au 132 rue Pelleport à Paris, code postal 7 5 0 2 0
Conseiller: Et votre numéro de téléphone ?
Client: 0 6 1 2 3 4 5 6 7 8
Conseiller: Et votre email ?
Client: Mon email c'est f l o r i a n arobase gmail point com
```

### Résultat attendu
```json
{
  "adresse": "132 rue Pelleport",
  "ville": "Paris",
  "code_postal": "75020",
  "telephone": "0612345678",
  "email": "florian@gmail.com"
}
```

---

## ✅ Exemple 8 : Détection de contexte - "Passons au questionnaire de risque"

### Dialogue
```
Conseiller: Bien, merci pour ces informations. Maintenant, nous allons passer au questionnaire de risque. Prudent, équilibré ou dynamique ?
Client: Équilibré
Conseiller: Court, moyen ou long terme ?
Client: Moyen terme
Conseiller: Si ça baisse de 25% ?
Client: J'attendrais patiemment
Conseiller: Parfait. Connaissez-vous les actions ?
Client: Oui
Conseiller: Les SCPI ?
Client: Non, je ne connais pas
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "financier": {
      "attitude_placements": "equilibre",
      "horizon_investissement": "moyen_terme",
      "reaction_baisse_25": "ne_rien_faire"
    },
    "connaissances": {
      "connaissance_actions": true
    }
  }
}
```

### ✅ Ce qui DOIT être fait
- Détecter "nous allons passer au questionnaire de risque" comme déclencheur de contexte
- Toutes les réponses suivantes du client sont mappées vers questionnaire_risque
- Les questions courtes ("Court, moyen ou long terme ?") sont correctement comprises grâce au contexte

### ❌ Ce qui NE doit PAS être extrait
- `connaissance_opci_scpi` (car le client dit ne pas connaître)

---

## ✅ Exemple 9 : Détection de contexte - "Connaissances financières"

### Dialogue
```
Conseiller: Parlons de vos connaissances financières. Connaissez-vous les actions ?
Client: Oui, j'ai un PEA
Conseiller: Les obligations ?
Client: Oui aussi
Conseiller: Les produits structurés ?
Client: Non, jamais entendu parler
Conseiller: Les SCPI ?
Client: Oui, je connais bien
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "connaissances": {
      "connaissance_actions": true,
      "connaissance_obligations": true,
      "connaissance_opci_scpi": true
    }
  }
}
```

### ✅ Ce qui DOIT être fait
- Détecter "Parlons de vos connaissances financières" comme déclencheur
- Activer le contexte questionnaire de risque
- Questions courtes ("Les obligations ?") sont comprises dans le contexte

---

## ✅ Exemple 10 : Détection de contexte - "Profil investisseur"

### Dialogue
```
Conseiller: Je vais maintenant évaluer votre profil investisseur. Quelle est votre tolérance au risque ?
Client: Je dirais modérée
Conseiller: Votre horizon ?
Client: Long terme, pour ma retraite dans 20 ans
Conseiller: En cas de forte baisse des marchés ?
Client: Je ne ferais rien, j'attendrais la remontée
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "financier": {
      "tolerance_risque": "moderee",
      "horizon_investissement": "long_terme",
      "reaction_baisse_25": "ne_rien_faire"
    }
  }
}
```

---

## ✅ Exemple 11 : Cas complexe - Dialogue complet

### Dialogue
```
Conseiller: Bonjour, je vais vous poser quelques questions pour compléter votre dossier. Quel est votre nom ?
Client: Je m'appelle Guillaume Huck
Conseiller: Très bien. Êtes-vous marié ?
Client: Oui, marié depuis 2010
Conseiller: Avez-vous des enfants ?
Client: Oui, j'ai 2 enfants
Conseiller: Quelle est votre profession ?
Client: Je suis développeur
Conseiller: Et vos revenus annuels approximatifs ?
Client: Environ 45000 euros
Conseiller: Passons au questionnaire de risque. Comment décririez-vous votre attitude vis-à-vis des placements ? Très prudent, prudent, équilibré ou dynamique ?
Client: Équilibré
Conseiller: Quel est votre horizon d'investissement ?
Client: Moyen terme, entre 5 et 8 ans
Conseiller: Connaissez-vous les actions ?
Client: Oui, je connais les actions
```

### Résultat attendu
```json
{
  "nom": "Huck",
  "prenom": "Guillaume",
  "situation_matrimoniale": "marié",
  "date_situation_matrimoniale": "2010-01-01",
  "nombre_enfants": 2,
  "profession": "développeur",
  "revenus_annuels": 45000,
  "questionnaire_risque": {
    "financier": {
      "attitude_placements": "equilibre",
      "horizon_investissement": "moyen_terme"
    },
    "connaissances": {
      "connaissance_actions": true
    }
  }
}
```

### ❌ Ce qui NE doit PAS être extrait
- Toutes les questions du conseiller
- Les formulations de politesse ("Très bien", "Bonjour", etc.)
- Les énumérations d'options proposées par le conseiller

---

## 🧪 Comment tester

1. **Préparer un enregistrement audio** avec un dialogue conseiller/client
2. **Uploader l'audio** via l'interface du CRM
3. **Attendre le traitement** (transcription Whisper + analyse GPT)
4. **Vérifier les données extraites** dans la fiche client et le questionnaire de risque
5. **Confirmer** que seules les réponses du client ont été extraites

---

## 📊 Indicateurs de succès

✅ **Le système fonctionne correctement si:**
- Les questions du conseiller n'apparaissent pas dans les données extraites
- Seules les réponses du client sont enregistrées
- Le questionnaire de risque ne contient que les informations données par le client
- Les connaissances produits correspondent uniquement à ce que le client dit connaître

❌ **Le système a un problème si:**
- Des questions du conseiller sont extraites comme données du client
- Des produits mentionnés par le conseiller (mais pas confirmés par le client) apparaissent dans les connaissances
- Des informations inventées ou supposées apparaissent

---

## ✅ Exemple 12 : Changement de contexte - Fin du questionnaire de risque

### Dialogue
```
Conseiller: Passons au questionnaire de risque. Quel est votre horizon d'investissement ?
Client: Long terme
Conseiller: Votre tolérance au risque ?
Client: Modérée
Conseiller: Parfait, merci. Maintenant, parlons de vos besoins en assurance. De quoi avez-vous besoin ?
Client: J'ai besoin d'une mutuelle et d'une prévoyance
```

### Résultat attendu
```json
{
  "questionnaire_risque": {
    "financier": {
      "horizon_investissement": "long_terme",
      "tolerance_risque": "moderee"
    }
  },
  "besoins": ["mutuelle", "prévoyance"],
  "besoins_action": "replace"
}
```

### ✅ Ce qui DOIT être fait
- Détecter "Passons au questionnaire de risque" → activation contexte questionnaire
- Extraire les réponses dans questionnaire_risque
- Détecter "parlons de vos besoins en assurance" → changement de contexte
- Extraire "mutuelle et prévoyance" dans besoins (PAS dans questionnaire_risque)

---

## 🔍 Cas limites à surveiller

### Cas 1 : Client qui répond par "Oui" à une question fermée
```
Conseiller: Êtes-vous né en 1985 ?
Client: Oui
```
→ Devrait extraire `date_naissance: "1985-01-01"` car confirmé par le client

### Cas 2 : Client qui choisit parmi des options énumérées
```
Conseiller: Prudent, équilibré ou dynamique ?
Client: Dynamique
```
→ Devrait extraire `attitude_placements: "dynamique"`

### Cas 3 : Client qui nie une information
```
Conseiller: Connaissez-vous les SCPI ?
Client: Non
```
→ NE DOIT PAS extraire `connaissance_opci_scpi`

### Cas 4 : Réponse partielle du client
```
Conseiller: Connaissez-vous les actions, les obligations et les SCPI ?
Client: Je connais les actions et les obligations, mais pas les SCPI
```
→ Doit extraire uniquement `connaissance_actions: true` et `connaissance_obligations: true`

---

## 📝 Notes importantes

1. **Le LLM (GPT-4o-mini) est suffisamment intelligent** pour :
   - Distinguer les questions des réponses grâce aux règles ajoutées au prompt
   - Détecter automatiquement le changement de contexte/section
   - Comprendre les questions courtes grâce au contexte activé

2. **La transcription Whisper ne fait PAS de diarisation** (identification des locuteurs), mais le contexte linguistique suffit

3. **Le prompt contient des exemples détaillés** pour guider l'IA dans tous les cas de figure

4. **En cas de doute, le système ne doit PAS extraire** l'information (principe de précaution)

5. **La détection de contexte permet** :
   - De comprendre les questions ultra-courtes ("Court, moyen ou long terme ?")
   - D'activer automatiquement l'extraction vers questionnaire_risque
   - De gérer les transitions entre sections

---

## 🚀 Prochaines améliorations possibles

Si nécessaire, on pourrait ajouter :
- **Diarisation Whisper** pour identifier physiquement les différents locuteurs
- **Préfixes dans la transcription** : "[Conseiller]" et "[Client]"
- **Analyse en deux passes** : 1) identification des locuteurs, 2) extraction des données

Mais avec les règles actuelles, le système devrait déjà bien fonctionner ! 🎉
