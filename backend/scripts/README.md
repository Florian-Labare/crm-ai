# 🎤 Whisper Local - Transcription Audio

Ce dossier contient le script Python pour la transcription audio locale avec **faster-whisper**.

## 📦 Installation

Les dépendances Python sont automatiquement installées lors du build du container Docker backend.

Si vous souhaitez tester en local (hors Docker) :

```bash
pip install -r requirements.txt
```

## 🚀 Utilisation

### Via Docker (automatique)

Le script est automatiquement appelé par Laravel via `TranscriptionService.php` lors de l'upload d'un fichier audio.

### Test manuel

```bash
python3 whisper_transcribe.py <chemin_fichier_audio> [modele]
```

**Exemples :**

```bash
# Modèle base (par défaut)
python3 whisper_transcribe.py /path/to/audio.wav

# Modèle small (meilleure qualité)
python3 whisper_transcribe.py /path/to/audio.mp3 small

# Modèle tiny (plus rapide)
python3 whisper_transcribe.py /path/to/audio.wav tiny
```

## 🎯 Modèles disponibles

| Modèle | Taille | Vitesse | Qualité | Recommandation |
|--------|--------|---------|---------|----------------|
| `tiny` | ~75 MB | ⚡⚡⚡⚡⚡ | ⭐⭐ | Tests rapides |
| `base` | ~150 MB | ⚡⚡⚡⚡ | ⭐⭐⭐ | **POC (recommandé)** |
| `small` | ~500 MB | ⚡⚡⚡ | ⭐⭐⭐⭐ | Production |
| `medium` | ~1.5 GB | ⚡⚡ | ⭐⭐⭐⭐⭐ | Haute qualité |
| `large-v3` | ~3 GB | ⚡ | ⭐⭐⭐⭐⭐ | Maximum qualité |

## 🔧 Configuration

Le modèle utilisé est défini dans `.env` :

```env
WHISPER_MODEL=base
```

## 📊 Format de sortie

Le script retourne un JSON :

```json
{
  "text": "Transcription du fichier audio...",
  "language": "fr",
  "language_probability": 0.98
}
```

En cas d'erreur :

```json
{
  "error": "Message d'erreur..."
}
```

## 🐛 Troubleshooting

### Erreur : `ModuleNotFoundError: No module named 'faster_whisper'`

Réinstaller les dépendances :

```bash
docker compose exec backend pip3 install -r /var/www/html/scripts/requirements.txt
```

### Le modèle télécharge à chaque fois

Les modèles Whisper sont mis en cache dans `~/.cache/huggingface/`. Pour persister ce cache dans Docker, ajouter un volume dans `docker-compose.yml`.

### Performances lentes

- Utiliser un modèle plus petit (`tiny` ou `base`)
- Activer VAD (Voice Activity Detection) - déjà activé par défaut
- Si vous avez un GPU NVIDIA, modifier le script pour utiliser `device="cuda"`

## 📝 Avantages vs API OpenAI

✅ **Gratuit** (pas de coût API)
✅ **Privé** (données ne quittent pas le serveur)
✅ **Rapide** (pas de latence réseau)
✅ **Offline** (fonctionne sans internet)
⚠️ **Modèle base** : qualité légèrement inférieure à l'API mais suffisant pour POC

## 🔗 Liens utiles

- [faster-whisper Documentation](https://github.com/SYSTRAN/faster-whisper)
- [OpenAI Whisper](https://github.com/openai/whisper)
- [Hugging Face Models](https://huggingface.co/models?search=whisper)
