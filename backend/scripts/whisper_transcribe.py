#!/usr/bin/env python3
"""
Script de transcription audio locale avec OpenAI Whisper
Utilisation: python whisper_transcribe.py <chemin_fichier_audio>
"""

import sys
import os
import json
import whisper

def transcribe_audio(audio_path: str, model_size: str = "base") -> dict:
    """
    Transcrit un fichier audio avec OpenAI Whisper

    Args:
        audio_path: Chemin vers le fichier audio
        model_size: Taille du modèle (tiny, base, small, medium, large)

    Returns:
        dict: {"text": transcription, "language": langue_detectee}
    """
    try:
        if not os.path.exists(audio_path):
            return {"error": f"Fichier non trouvé: {audio_path}"}

        # Charger le modèle Whisper
        model = whisper.load_model(model_size)

        # Transcription avec détection automatique de la langue
        result = model.transcribe(
            audio_path,
            language="fr",  # Forcer le français
            fp16=False  # Désactiver FP16 pour CPU
        )

        return {
            "text": result["text"].strip(),
            "language": result["language"],
            "language_probability": 1.0  # Whisper ne retourne pas cette info
        }

    except Exception as e:
        return {"error": str(e)}

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: python whisper_transcribe.py <audio_file>"}))
        sys.exit(1)

    audio_path = sys.argv[1]
    model_size = sys.argv[2] if len(sys.argv) > 2 else "base"

    # Vérifier les modèles valides
    valid_models = ["tiny", "base", "small", "medium", "large", "large-v2", "large-v3"]
    if model_size not in valid_models:
        model_size = "base"

    result = transcribe_audio(audio_path, model_size)
    print(json.dumps(result, ensure_ascii=False))

if __name__ == "__main__":
    main()
