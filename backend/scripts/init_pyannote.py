#!/usr/bin/env python3
"""
Script d'initialisation de pyannote

Ce script:
1. Vérifie si pyannote est installé
2. L'installe si nécessaire
3. Pré-télécharge le modèle de diarisation pour éviter le délai au premier enregistrement

Usage:
    python3 init_pyannote.py [--install] [--download-model]

    --install        : Installe pyannote-audio si manquant
    --download-model : Télécharge le modèle de diarisation

    Sans arguments   : Vérifie l'installation et télécharge le modèle si token disponible
"""

import sys
import os
import subprocess

def check_pyannote_installed():
    """Vérifie si pyannote est installé"""
    try:
        import pyannote.audio
        print("✅ pyannote.audio est installé")
        return True
    except ImportError:
        print("❌ pyannote.audio n'est PAS installé")
        return False

def install_pyannote():
    """Installe pyannote-audio"""
    print("📦 Installation de pyannote-audio...")
    try:
        result = subprocess.run(
            [sys.executable, '-m', 'pip', 'install', '--break-system-packages', 'pyannote.audio'],
            capture_output=True,
            text=True,
            timeout=600  # 10 minutes max
        )
        if result.returncode == 0:
            print("✅ pyannote-audio installé avec succès")
            return True
        else:
            print(f"❌ Échec de l'installation: {result.stderr}")
            return False
    except subprocess.TimeoutExpired:
        print("❌ Timeout lors de l'installation")
        return False
    except Exception as e:
        print(f"❌ Erreur: {e}")
        return False

def download_model():
    """Pré-télécharge le modèle de diarisation"""
    hf_token = os.getenv('HUGGINGFACE_TOKEN')

    if not hf_token:
        print("⚠️ HUGGINGFACE_TOKEN non défini - impossible de télécharger le modèle")
        print("   Définissez la variable d'environnement et réexécutez ce script")
        return False

    print("📥 Téléchargement du modèle de diarisation...")
    print("   (Cela peut prendre plusieurs minutes la première fois)")

    try:
        import torch

        # Patch torch.load pour forcer weights_only=False (nécessaire pour pyannote)
        # Les modèles pyannote sont des sources de confiance (HuggingFace officiel)
        original_load = torch.load
        def patched_load(*args, **kwargs):
            kwargs['weights_only'] = False
            return original_load(*args, **kwargs)
        torch.load = patched_load

        from pyannote.audio import Pipeline

        pipeline = Pipeline.from_pretrained(
            "pyannote/speaker-diarization-3.1",
            use_auth_token=hf_token
        )

        # Restaurer torch.load original
        torch.load = original_load

        # Configurer pour CPU
        pipeline.to(torch.device("cpu"))

        print("✅ Modèle de diarisation téléchargé et prêt")
        return True

    except Exception as e:
        print(f"❌ Erreur lors du téléchargement du modèle: {e}")
        print("\n💡 Assurez-vous d'avoir:")
        print("   1. Accepté la licence sur https://huggingface.co/pyannote/speaker-diarization-3.1")
        print("   2. Un token HuggingFace valide")
        return False

def check_system():
    """Vérifie les prérequis système"""
    print("\n=== Vérification système ===\n")

    # Python version
    print(f"Python: {sys.version}")

    # FFmpeg
    try:
        result = subprocess.run(['ffmpeg', '-version'], capture_output=True, text=True)
        if result.returncode == 0:
            version = result.stdout.split('\n')[0]
            print(f"FFmpeg: ✅ {version}")
        else:
            print("FFmpeg: ❌ Non disponible")
    except:
        print("FFmpeg: ❌ Non trouvé")

    # Torch
    try:
        import torch
        print(f"PyTorch: ✅ {torch.__version__}")
        print(f"CUDA disponible: {'✅ Oui' if torch.cuda.is_available() else '❌ Non (CPU uniquement)'}")
    except ImportError:
        print("PyTorch: ❌ Non installé")

    # Token HuggingFace
    hf_token = os.getenv('HUGGINGFACE_TOKEN')
    if hf_token and len(hf_token) > 10:
        print(f"HUGGINGFACE_TOKEN: ✅ Défini ({len(hf_token)} caractères)")
    else:
        print("HUGGINGFACE_TOKEN: ❌ Non défini ou invalide")

    print()

def main():
    args = sys.argv[1:]

    print("🎙️ Initialisation de pyannote pour la diarisation audio\n")

    # Vérification système
    check_system()

    # Installation si demandée ou si nécessaire
    if '--install' in args or not check_pyannote_installed():
        if not check_pyannote_installed():
            success = install_pyannote()
            if not success:
                print("\n❌ Échec de l'installation de pyannote")
                sys.exit(1)

    # Téléchargement du modèle si demandé
    if '--download-model' in args or len(args) == 0:
        if check_pyannote_installed():
            download_model()

    print("\n✅ Initialisation terminée")

if __name__ == '__main__':
    main()
