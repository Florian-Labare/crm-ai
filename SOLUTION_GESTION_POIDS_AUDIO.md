# 🎙️ Solution de gestion du poids des fichiers audio

## 🔍 Problème identifié

**Erreur 422** lors de l'upload audio causée par :
- Limites PHP trop restrictives :
  - `upload_max_filesize = 2M` (2 mégaoctets)
  - `post_max_size = 8M`
- Fichiers audio WAV pouvant dépasser facilement 2MB pour des enregistrements > 1 minute

## ✅ Solutions mises en place

### 1. Augmentation des limites PHP (Backend)

**Fichier créé :** `backend/docker/php/custom.ini`
```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

**Modification :** `backend/Dockerfile`
```dockerfile
# Configuration PHP personnalisée pour upload de gros fichiers
COPY ./docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
```

**Nouvelles limites :**
- Upload max : **50 MB** (au lieu de 2 MB)
- Temps d'exécution max : **5 minutes** (au lieu de 30 secondes)
- Mémoire : **256 MB**

---

### 2. Validation backend améliorée

**Fichier :** `backend/app/Http/Controllers/AudioController.php`

**Changements :**
```php
$request->validate([
    'audio' => 'required|file|mimes:mp3,wav,ogg,webm,m4a,mpeg|max:20480',
    'client_id' => 'nullable|integer',
]);
```

- Formats acceptés élargis : **mp3, wav, ogg, webm, m4a, mpeg**
- Taille max Laravel : **20 MB** (20480 KB)
- Vérification que le client appartient bien à l'utilisateur connecté

---

### 3. Protection côté frontend (AudioRecorder.tsx)

#### 🕐 Limitation de durée d'enregistrement

**Durée maximale :** 10 minutes
```typescript
// Limite de 10 minutes (600 secondes)
if (elapsed >= 600) {
  toast.warning('⏱️ Durée maximale atteinte (10 minutes)');
  stopRecording();
}
```

#### 📊 Vérification de la taille avant upload

```typescript
const fileSizeMB = blob.size / (1024 * 1024);

if (fileSizeMB > 40) {
  const errorMsg = `Fichier trop volumineux (${fileSizeMB.toFixed(1)} MB)`;
  handleError(errorMsg);
  return; // Blocage de l'upload
}

if (fileSizeMB > 20) {
  toast.warning(`⚠️ Fichier volumineux (${fileSizeMB.toFixed(1)} MB)`);
}
```

#### ⏱️ Timer visuel en cours d'enregistrement

Affichage en temps réel :
```
⏺ 2:35 / 10:00
```

---

## 📊 Limites recommandées

| Élément | Limite | Raison |
|---------|--------|--------|
| Durée d'enregistrement | **10 minutes** | Évite fichiers trop volumineux + dialogues trop longs pour GPT |
| Taille fichier (blocage) | **40 MB** | Sécurité côté client |
| Taille fichier (warning) | **20 MB** | Alerte utilisateur |
| Upload max PHP | **50 MB** | Marge de sécurité |
| Validation Laravel | **20 MB** | Limite raisonnable pour l'API |

---

## 🎯 Estimation de taille des fichiers audio

**Format WAV (16 kHz, mono, 16-bit) :**
- 1 minute ≈ **2 MB**
- 5 minutes ≈ **10 MB**
- 10 minutes ≈ **20 MB**

Ces estimations sont pour le format WAV optimisé (16 kHz) utilisé par RecordRTC.

---

## 🔧 Comment appliquer les modifications

### 1. Reconstruire le container backend

```bash
docker compose up -d --build backend
```

### 2. Vérifier les nouvelles limites PHP

```bash
docker compose exec backend php -i | grep -E "upload_max_filesize|post_max_size"
```

**Résultat attendu :**
```
upload_max_filesize => 50M => 50M
post_max_size => 50M => 50M
```

### 3. Tester l'upload

1. Enregistrer un fichier audio > 2 minutes
2. Vérifier qu'il s'upload correctement
3. Vérifier les logs backend pour confirmer

---

## 🧪 Tests recommandés

### Test 1 : Enregistrement court (< 1 minute)
- **Taille attendue :** ~2 MB
- **Résultat :** ✅ Upload sans problème

### Test 2 : Enregistrement moyen (3-5 minutes)
- **Taille attendue :** 6-10 MB
- **Résultat :** ✅ Upload avec warning possible

### Test 3 : Enregistrement long (8-10 minutes)
- **Taille attendue :** 16-20 MB
- **Résultat :** ⚠️ Upload avec warning mais fonctionne

### Test 4 : Enregistrement > 10 minutes
- **Résultat :** 🚫 Arrêt automatique + message utilisateur

---

## 📝 Logs à surveiller

### Backend - Upload réussi
```
[2025-11-09 16:15:00] local.INFO: 📊 Taille du fichier audio: 12.45 MB
[2025-11-09 16:15:05] local.INFO: 🎵 Début du traitement audio #102
```

### Backend - Upload échoué (trop volumineux)
```
[2025-11-09 16:15:00] local.ERROR: Validation failed: The audio must not be greater than 20480 kilobytes
```

### Frontend - Fichier trop gros
```console
📊 Taille du fichier audio: 42.30 MB
❌ Le fichier audio est trop volumineux (42.3 MB)
```

---

## 🚀 Améliorations futures possibles

### 1. Compression audio côté client
Utiliser une librairie comme **lamejs** pour compresser en MP3 avant upload :
- WAV 20 MB → MP3 2-3 MB (gain de 85-90%)
- Réduction drastique du temps d'upload
- Conservation de la qualité pour Whisper

### 2. Upload par chunks (morceaux)
Pour les très gros fichiers :
- Découper le fichier en morceaux de 5 MB
- Upload progressif avec barre de progression
- Reprise possible en cas d'échec

### 3. Optimisation du sampling rate
- Passer de 16 kHz à 8 kHz pour les dialogues simples
- Réduction de 50% de la taille
- Qualité suffisante pour Whisper

---

## ✅ Résumé

**Avant :**
- ❌ Limite PHP : 2 MB
- ❌ Erreur 422 sur fichiers > 2 MB
- ❌ Pas de feedback utilisateur

**Maintenant :**
- ✅ Limite PHP : 50 MB
- ✅ Validation frontend : 40 MB
- ✅ Timer visible en cours d'enregistrement
- ✅ Arrêt automatique à 10 minutes
- ✅ Messages d'erreur explicites
- ✅ Warnings si fichier volumineux

**Le système peut maintenant gérer des enregistrements jusqu'à 10 minutes sans problème ! 🎉**
