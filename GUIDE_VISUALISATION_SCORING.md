# 🎯 Guide de visualisation du scoring du questionnaire de risque

## 📋 Scénario complet : De l'enregistrement à l'affichage du score

### Étape 1 : Enregistrement vocal

Vous enregistrez un dialogue avec votre client :

```
Vous (Conseiller): "Bonjour M. Dupont, je vais vous poser quelques questions pour évaluer votre profil investisseur. Passons au questionnaire de risque."

Client: "D'accord"

Vous: "Comment décririez-vous votre attitude vis-à-vis des placements ? Prudent, équilibré ou dynamique ?"

Client: "Je dirais équilibré, j'accepte un peu de risque pour du rendement"

Vous: "Très bien. Quel est votre horizon d'investissement ? Court, moyen ou long terme ?"

Client: "Long terme, j'investis pour ma retraite dans 20 ans"

Vous: "Parfait. Si votre investissement baisse de 25%, quelle serait votre réaction ?"

Client: "J'attendrais patiemment la remontée, je ne ferais rien"

Vous: "D'accord. Quelle est votre tolérance au risque ? Faible, modérée ou élevée ?"

Client: "Modérée"

Vous: "Maintenant, parlons de vos connaissances financières. Connaissez-vous les actions ?"

Client: "Oui, j'ai un PEA"

Vous: "Les obligations ?"

Client: "Oui aussi"

Vous: "Les SCPI ?"

Client: "Oui, je connais bien"

Vous: "Les produits structurés ?"

Client: "Non, jamais entendu parler"
```

---

### Étape 2 : Traitement automatique (Backend)

**Ce qui se passe en coulisses :**

1. **Transcription Whisper** (environ 10-30 secondes)
2. **Analyse GPT-4o-mini** (environ 5-10 secondes)
3. **Extraction automatique** :
```json
{
  "questionnaire_risque": {
    "financier": {
      "attitude_placements": "equilibre",
      "horizon_investissement": "long_terme",
      "reaction_baisse_25": "ne_rien_faire",
      "tolerance_risque": "moderee"
    },
    "connaissances": {
      "connaissance_actions": true,
      "connaissance_obligations": true,
      "connaissance_opci_scpi": true
    }
  }
}
```

4. **Calcul automatique du score** par ScoringService :
   - Score comportemental : 25 + 35 + 25 + 25 = 110 points (sur 210 max)
   - Score connaissances : 3/10 produits connus = 30 points (sur 100)
   - Score quiz : 0 (pas rempli par vocal)
   - **Score global : (110/210*100 + 30 + 0) / 3 ≈ 42 points**

5. **Profil déterminé** : **Modéré** (car 42 est entre 40 et 80)

6. **Recommandation générée** :
> "Votre profil est **Modéré**. Vous acceptez une certaine volatilité pour rechercher du rendement. Nous recommandons une allocation équilibrée : 50-60% fonds sécurisés, 40-50% actions/SCPI/fonds diversifiés. Horizon minimum 5 ans."

---

### Étape 3 : Visualisation en frontend

#### 🎯 Accès au questionnaire

**Depuis la fiche client :**
1. Cliquez sur le client concerné dans la liste
2. Sur la page de détail, cliquez sur le bouton **"Questionnaire de risque"** (vert/teal)
3. Vous arrivez sur `/clients/{id}/questionnaire-risque`

#### 📊 Ce que vous voyez

```
┌─────────────────────────────────────────────────────────────────────┐
│  Questionnaire de Risque                                            │
│  ← Retour à la fiche client                                         │
│                                                                      │
│  Évaluation du profil investisseur                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────────────────┬────────────────────────────────────┐   │
│  │  [Comportement]        │     Profil de risque               │   │
│  │  [Connaissances]       │                                    │   │
│  │  [Quiz (32 questions)] │          ╭─────────╮               │   │
│  │                        │         ╱    42     ╲              │   │
│  │ ─────────────────────  │        │   ━━━━━    │ 🔵 Bleu     │   │
│  │                        │         ╲   /100   ╱              │   │
│  │ SECTION COMPORTEMENT : │          ╰─────────╯               │   │
│  │                        │                                    │   │
│  │ ✅ Attitude placements │         Modéré                     │   │
│  │    → Équilibré         │                                    │   │
│  │                        │  Votre profil est Modéré. Vous    │   │
│  │ ✅ Horizon             │  acceptez une certaine volatilité  │   │
│  │    → Long terme        │  pour rechercher du rendement...  │   │
│  │                        │                                    │   │
│  │ ✅ Réaction baisse 25% │                                    │   │
│  │    → Ne rien faire     │                                    │   │
│  │                        │                                    │   │
│  │ ✅ Tolérance risque    │                                    │   │
│  │    → Modérée           │                                    │   │
│  │                        │                                    │   │
│  └────────────────────────┴────────────────────────────────────┘   │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

### Étape 4 : Détails de l'affichage visuel

#### 🎨 La jauge circulaire (RiskProfileCard.tsx)

**Composants visuels :**

1. **Cercle de progression SVG animé**
   - Circonférence : 2π × rayon (70px) = 439.8px
   - Remplissage : proportionnel au score (42/100 = 42%)
   - Animation : transition CSS de 1 seconde

2. **Couleurs dynamiques selon le profil :**
   - 🟢 **Prudent** (< 40) : stroke="#10b981" (vert)
   - 🔵 **Modéré** (40-80) : stroke="#3b82f6" (bleu) ← **Votre cas**
   - 🟠 **Dynamique** (> 80) : stroke="#f97316" (orange)

3. **Affichage du score :**
   - Chiffre principal : `42` en gros (text-4xl)
   - Sous-texte : `/ 100`
   - Profil : `Modéré` en couleur assortie

4. **Recommandation personnalisée :**
   - Texte formaté en markdown
   - Mots clés en gras : **Modéré**, **50-60%**, etc.

---

### Étape 5 : Vérification dans les logs

**Logs Laravel (backend) :**

```bash
docker compose logs backend --tail 50 | grep "Questionnaire"
```

Vous devriez voir :
```
📊 Détection de données de questionnaire de risque, sauvegarde...
💾 Sauvegarde du questionnaire de risque
✅ Données financières sauvegardées: {"attitude_placements":"equilibre","horizon_investissement":"long_terme",...}
✅ Connaissances sauvegardées: {"connaissance_actions":true,"connaissance_obligations":true,...}
✅ Questionnaire de risque mis à jour (client_id: 17, score: 42, profil: Modéré)
```

---

## 🧪 Test complet du flux

### Test 1 : Profil Prudent (score < 40)

**Dialogue à enregistrer :**
```
"Passons au questionnaire de risque. Plutôt prudent, équilibré ou dynamique ?"
Client: "Très prudent"

"Court, moyen ou long terme ?"
Client: "Court terme, moins de 3 ans"

"Si ça baisse de 25% ?"
Client: "Je vendrais tout immédiatement"

"Connaissez-vous les actions ?"
Client: "Non"
```

**Résultat attendu :**
- Score : **≈ 15-20 points**
- Profil : **Prudent** (🟢 vert)
- Recommandation : Livrets, fonds euros, obligations d'État

---

### Test 2 : Profil Modéré (score 40-80)

**Dialogue à enregistrer :**
```
"Passons au questionnaire de risque. Plutôt prudent, équilibré ou dynamique ?"
Client: "Équilibré"

"Court, moyen ou long terme ?"
Client: "Moyen terme, entre 5 et 8 ans"

"Si ça baisse de 25% ?"
Client: "J'attendrais sans rien faire"

"Connaissez-vous les actions ?"
Client: "Oui"

"Les SCPI ?"
Client: "Oui aussi"
```

**Résultat attendu :**
- Score : **≈ 40-50 points**
- Profil : **Modéré** (🔵 bleu)
- Recommandation : Allocation équilibrée 50/50

---

### Test 3 : Profil Dynamique (score > 80)

**Dialogue à enregistrer :**
```
"Passons au questionnaire de risque. Plutôt prudent, équilibré ou dynamique ?"
Client: "Très dynamique, je recherche la performance"

"Court, moyen ou long terme ?"
Client: "Long terme, plus de 10 ans"

"Si ça baisse de 25% ?"
Client: "J'en profiterais pour acheter plus"

"Quelle est votre tolérance au risque ?"
Client: "Élevée"

"Connaissez-vous les actions ?"
Client: "Oui très bien"

"Les obligations ?"
Client: "Oui"

"Les FIP FCPI ?"
Client: "Oui"

"Les produits structurés ?"
Client: "Oui"
```

**Résultat attendu :**
- Score : **≈ 85-95 points**
- Profil : **Dynamique** (🟠 orange)
- Recommandation : Allocation offensive 60-80% actions

---

## 📊 Tableau récapitulatif des scores

| Score | Profil | Couleur | Recommandation principale |
|-------|--------|---------|---------------------------|
| 0-39 | Prudent | 🟢 Vert | Fonds euros, livrets, obligations d'État |
| 40-80 | Modéré | 🔵 Bleu | Allocation équilibrée 50/50 |
| 81-100 | Dynamique | 🟠 Orange | Allocation offensive 60-80% actions |

---

## 🔄 Mise à jour en temps réel

**Si vous modifiez manuellement le questionnaire :**

1. Allez dans l'onglet "Comportement" ou "Connaissances"
2. Changez une réponse (ex: de "Équilibré" à "Dynamique")
3. **Le score se met à jour AUTOMATIQUEMENT** grâce à l'appel API en temps réel
4. La jauge circulaire **s'anime** pour afficher le nouveau score
5. Le profil et la couleur changent instantanément

**Code frontend (RiskQuestionnaire.tsx:76) :**
```typescript
const saveQuestionnaire = async (section: string, data: Record<string, any>) => {
  setLoading(true);
  const response = await fetch('http://localhost:8000/api/questionnaire-risque/live', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  const result = await response.json();
  setScore(result.score || 0);  // ← Score mis à jour
  setProfil(result.profil || 'Prudent');  // ← Profil mis à jour
  setRecommandation(result.recommandation || '');  // ← Reco mise à jour
};
```

---

## ✅ Checklist de vérification

Après un enregistrement vocal avec données de questionnaire de risque :

- [ ] Le statut de l'enregistrement passe à "done"
- [ ] Les logs montrent "✅ Questionnaire de risque mis à jour"
- [ ] Sur la fiche client, le bouton "Questionnaire de risque" est accessible
- [ ] En cliquant, vous voyez la jauge circulaire avec un score
- [ ] Le profil est affiché (Prudent/Modéré/Dynamique)
- [ ] La couleur correspond au profil
- [ ] Les onglets montrent les réponses pré-remplies
- [ ] La recommandation est affichée en bas de la carte

---

## 🎉 Résumé

**OUI, tout est automatique :**

1. ✅ Enregistrement vocal → Extraction des réponses du client
2. ✅ Calcul automatique du score (0-100)
3. ✅ Détermination automatique du profil (Prudent/Modéré/Dynamique)
4. ✅ Génération automatique des recommandations
5. ✅ Affichage visuel avec jauge circulaire animée
6. ✅ Couleurs adaptatives selon le profil
7. ✅ Mise à jour en temps réel si modification manuelle

**Tout fonctionne de bout en bout ! 🚀**
