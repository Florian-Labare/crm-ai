# Migration Frontend - API Resources

## 🎯 Résumé des Changements

Le backend utilise maintenant des **API Resources** qui changent la structure des réponses JSON.

---

## 📝 Changements par Endpoint

### 1. GET /api/clients (Liste)

#### ❌ AVANT
```json
[
  {
    "id": 1,
    "nom": "Dupont",
    "prenom": "Jean",
    "created_at": "2024-11-23 14:30:00",
    "updated_at": "2024-11-23 14:30:00"
  }
]
```

#### ✅ APRÈS
```json
{
  "data": [
    {
      "id": 1,
      "nom": "Dupont",
      "prenom": "Jean",
      "nom_complet": "Jean DUPONT",        ← NOUVEAU
      "created_at": "2024-11-23T14:30:00.000Z",  ← Format ISO
      "updated_at": "2024-11-23T14:30:00.000Z",
      "conjoint": null,                     ← Toujours présent
      "enfants": []                         ← Toujours présent
    }
  ]
}
```

**Code à changer** :
```typescript
// ❌ AVANT
const response = await api.get('/api/clients');
setClients(response.data); // Tableau direct

// ✅ APRÈS
const response = await api.get('/api/clients');
setClients(response.data.data); // Wrapper "data"
```

---

### 2. GET /api/clients/{id} (Détail)

#### ❌ AVANT
```json
{
  "id": 1,
  "nom": "Dupont",
  "prenom": "Jean",
  "enfants": [
    {
      "id": 1,
      "prenom": "Emma",
      "date_naissance": "2012-03-15"
    }
  ]
}
```

#### ✅ APRÈS
```json
{
  "data": {
    "id": 1,
    "nom": "Dupont",
    "prenom": "Jean",
    "nom_complet": "Jean DUPONT",          ← NOUVEAU
    "enfants": [
      {
        "id": 1,
        "prenom": "Emma",
        "nom_complet": "Emma",             ← NOUVEAU
        "date_naissance": "2012-03-15",
        "age": 12,                          ← NOUVEAU (calculé)
        "fiscalement_a_charge": true,
        "garde_alternee": false
      }
    ],
    "bae_prevoyance": {                    ← Structure complète
      "id": 1,
      "contrat_en_place": true,
      "cotisations": 150.00,
      "created_at": "2024-11-23T14:30:00.000Z"
    }
  }
}
```

**Code à changer** :
```typescript
// ❌ AVANT
const response = await api.get(`/api/clients/${id}`);
setClient(response.data);

// ✅ APRÈS
const response = await api.get(`/api/clients/${id}`);
setClient(response.data.data); // Wrapper "data"
```

---

### 3. POST /api/clients (Création)

#### ✅ APRÈS
```json
{
  "data": {
    "id": 10,
    "nom": "Nouveau",
    "prenom": "Client",
    "nom_complet": "Client NOUVEAU",
    "created_at": "2024-11-23T15:00:00.000Z"
  }
}
```

**Code à changer** :
```typescript
// ❌ AVANT
const response = await api.post('/api/clients', clientData);
setClient(response.data);

// ✅ APRÈS
const response = await api.post('/api/clients', clientData);
setClient(response.data.data); // Wrapper "data"
```

---

### 4. POST /api/audio/upload

#### ✅ APRÈS
```json
{
  "data": {
    "id": 5,
    "status": "pending",
    "path": "audio_uploads/xyz.mp3",
    "transcription": null,
    "processed_at": null,
    "created_at": "2024-11-23T15:00:00.000Z"
  },
  "message": "Audio en cours de traitement"  ← Message additionnel
}
```

**Code à changer** :
```typescript
// ❌ AVANT
const response = await api.post('/api/audio/upload', formData);
const audioId = response.data.audio_record_id;

// ✅ APRÈS
const response = await api.post('/api/audio/upload', formData);
const audioId = response.data.data.id;  // Structure changée
const message = response.data.message;   // Message disponible
```

---

## 🔧 Fichiers Frontend à Modifier

### 1. Types TypeScript (`src/types/api.ts`)

Ajouter les nouveaux champs :

```typescript
export interface Client {
  id: number;
  nom: string;
  prenom: string;
  nom_complet: string;  // ← NOUVEAU
  email?: string;
  telephone?: string;
  // ... autres champs

  // Relations (toujours présentes, peuvent être null)
  conjoint?: Conjoint | null;
  enfants?: Enfant[];
  bae_prevoyance?: BaePrevoyance | null;
  bae_retraite?: BaeRetraite | null;
  bae_epargne?: BaeEpargne | null;

  created_at: string;  // ISO format
  updated_at: string;  // ISO format
}

export interface Enfant {
  id: number;
  prenom: string;
  nom?: string;
  nom_complet: string;              // ← NOUVEAU
  date_naissance: string;
  age: number | null;               // ← NOUVEAU (calculé)
  fiscalement_a_charge?: boolean;
  garde_alternee?: boolean;
  created_at: string;
  updated_at: string;
}
```

### 2. Utilitaire d'Extraction (`src/utils/apiHelpers.ts`)

**CRÉER ce fichier** :

```typescript
/**
 * Extrait les données d'une réponse API Resource Laravel
 */
export function extractData<T>(response: { data: { data: T } }): T {
  return response.data.data;
}

/**
 * Extrait un tableau de données
 */
export function extractCollection<T>(response: { data: { data: T[] } }): T[] {
  return response.data.data;
}

/**
 * Extrait avec message additionnel
 */
export function extractWithMessage<T>(response: { data: { data: T; message?: string } }): {
  data: T;
  message?: string;
} {
  return {
    data: response.data.data,
    message: response.data.message,
  };
}
```

### 3. Mise à Jour des Pages

#### `src/pages/ClientListPage.tsx`

```typescript
// ❌ AVANT
const fetchClients = async () => {
  const response = await api.get('/api/clients');
  setClients(response.data);
};

// ✅ APRÈS
import { extractCollection } from '../utils/apiHelpers';

const fetchClients = async () => {
  const response = await api.get('/api/clients');
  setClients(extractCollection<Client>(response));
};
```

#### `src/pages/ClientDetailPage.tsx`

```typescript
// ❌ AVANT
const fetchClient = async () => {
  const res = await api.get(`/clients/${id}`);
  setClient(res.data);
};

// ✅ APRÈS
import { extractData } from '../utils/apiHelpers';

const fetchClient = async () => {
  const res = await api.get(`/clients/${id}`);
  setClient(extractData<Client>(res));
};
```

#### `src/components/AudioRecorder.tsx`

```typescript
// ❌ AVANT
const response = await api.post('/api/audio/upload', formData);
const audioId = response.data.audio_record_id;

// ✅ APRÈS
import { extractWithMessage } from '../utils/apiHelpers';

const response = await api.post('/api/audio/upload', formData);
const { data: audioRecord, message } = extractWithMessage(response);
const audioId = audioRecord.id;

// Afficher le message si nécessaire
if (message) {
  toast.info(message);
}
```

---

## 🎨 Utilisation des Nouveaux Champs

### nom_complet

```tsx
// Avant
<h1>{client.prenom} {client.nom?.toUpperCase()}</h1>

// Après - Plus simple !
<h1>{client.nom_complet}</h1>
```

### age (pour les enfants)

```tsx
// Avant - Calcul manuel
const age = client.enfants[0].date_naissance
  ? calculateAge(client.enfants[0].date_naissance)
  : null;

// Après - Déjà calculé !
const age = client.enfants[0].age;
```

---

## 📋 Checklist Migration

### Phase 1: Utilitaires
- [ ] Créer `src/utils/apiHelpers.ts`
- [ ] Créer les fonctions `extractData`, `extractCollection`, `extractWithMessage`

### Phase 2: Types
- [ ] Mettre à jour `src/types/api.ts`
- [ ] Ajouter `nom_complet` à `Client`
- [ ] Ajouter `nom_complet` et `age` à `Enfant`
- [ ] Ajouter les types pour BAE Resources

### Phase 3: Pages
- [ ] Mettre à jour `ClientListPage.tsx`
- [ ] Mettre à jour `ClientDetailPage.tsx`
- [ ] Mettre à jour `HomePage.tsx`
- [ ] Mettre à jour `AudioRecorder.tsx`

### Phase 4: Composants
- [ ] Mettre à jour `ClientInfoSection.tsx` (utiliser `nom_complet`)
- [ ] Vérifier tous les composants qui affichent des clients

### Phase 5: Tests
- [ ] Tester la liste des clients
- [ ] Tester le détail d'un client
- [ ] Tester l'upload audio
- [ ] Tester la création/modification client

---

## 🚀 Migration Automatique (Script)

Je peux créer un script qui fait une partie du travail automatiquement !

Voulez-vous que je :
1. ✅ Crée le fichier `apiHelpers.ts`
2. ✅ Mette à jour les types TypeScript
3. ✅ Modifie les pages principales

**Dites-moi et je lance la migration automatique !**
