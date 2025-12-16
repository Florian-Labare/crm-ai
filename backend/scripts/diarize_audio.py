#!/usr/bin/env python3
"""
Script de diarisation audio pour identifier et séparer courtier/client

Utilise pyannote.audio pour:
1. Identifier les différents locuteurs dans l'enregistrement
2. Détecter automatiquement qui est le courtier (celui qui parle le plus souvent)
3. Extraire uniquement les segments du client
4. Retourner les timestamps et segments pour transcription

Usage:
    python3 diarize_audio.py <audio_file> <output_json>
"""

import sys
import json
import os
from pathlib import Path
import torch

# Patch torch.load pour forcer weights_only=False (nécessaire pour pyannote avec PyTorch 2.6+)
# Les modèles pyannote sont des sources de confiance (HuggingFace officiel)
_original_torch_load = torch.load
def _patched_torch_load(*args, **kwargs):
    kwargs['weights_only'] = False
    return _original_torch_load(*args, **kwargs)
torch.load = _patched_torch_load

from pyannote.audio import Pipeline

# Désactiver les warnings
import warnings
warnings.filterwarnings('ignore')


def load_pipeline():
    """Charge le pipeline de diarisation pyannote"""
    try:
        # Token HuggingFace requis (peut être défini dans .env)
        hf_token = os.getenv('HUGGINGFACE_TOKEN')

        if hf_token:
            pipeline = Pipeline.from_pretrained(
                "pyannote/speaker-diarization-3.1",
                use_auth_token=hf_token
            )
        else:
            # Essayer sans token (peut fonctionner si déjà téléchargé)
            pipeline = Pipeline.from_pretrained(
                "pyannote/speaker-diarization-3.1"
            )

        # Utiliser CPU par défaut (pas de GPU dans le container)
        if torch.cuda.is_available():
            pipeline.to(torch.device("cuda"))
        else:
            pipeline.to(torch.device("cpu"))

        return pipeline
    except Exception as e:
        print(f"Erreur lors du chargement du pipeline: {e}", file=sys.stderr)
        print("Assurez-vous d'avoir accepté la licence sur HuggingFace:", file=sys.stderr)
        print("https://huggingface.co/pyannote/speaker-diarization-3.1", file=sys.stderr)
        raise


def analyze_speakers(diarization):
    """
    Analyse les locuteurs pour identifier le courtier et le client

    Le courtier est identifié comme celui qui:
    - Parle le plus souvent (plus de tours de parole)
    - A des segments plus courts en moyenne (questions)

    Si un seul locuteur est détecté, on considère que c'est le client
    (enregistrement solo ou diarisation imparfaite)
    """
    speaker_stats = {}

    for turn, _, speaker in diarization.itertracks(yield_label=True):
        if speaker not in speaker_stats:
            speaker_stats[speaker] = {
                'total_duration': 0.0,
                'num_segments': 0,
                'segments': []
            }

        duration = turn.end - turn.start
        speaker_stats[speaker]['total_duration'] += duration
        speaker_stats[speaker]['num_segments'] += 1
        speaker_stats[speaker]['segments'].append({
            'start': turn.start,
            'end': turn.end,
            'duration': duration
        })

    # Cas spécial: aucun locuteur détecté
    if not speaker_stats:
        print("⚠️ Aucun locuteur détecté dans l'audio")
        return {
            'courtier': None,
            'clients': [],
            'stats': {},
            'single_speaker': True
        }

    # Calculer les métriques
    for speaker, stats in speaker_stats.items():
        stats['avg_segment_duration'] = stats['total_duration'] / stats['num_segments']

    # Cas spécial: un seul locuteur détecté
    if len(speaker_stats) == 1:
        single_speaker = list(speaker_stats.keys())[0]
        print(f"⚠️ Un seul locuteur détecté ({single_speaker}) - considéré comme client")
        return {
            'courtier': None,
            'clients': [single_speaker],
            'stats': speaker_stats,
            'single_speaker': True
        }

    # Identifier le courtier: celui avec le plus de tours de parole
    # (pose des questions fréquentes, parle plus souvent)
    courtier_speaker = max(
        speaker_stats.keys(),
        key=lambda s: speaker_stats[s]['num_segments']
    )

    # Le client est l'autre locuteur principal
    client_speakers = [s for s in speaker_stats.keys() if s != courtier_speaker]

    return {
        'courtier': courtier_speaker,
        'clients': client_speakers,
        'stats': speaker_stats,
        'single_speaker': False
    }


def extract_client_segments(diarization, speaker_analysis):
    """Extrait uniquement les segments du client"""
    client_segments = []
    courtier_speaker = speaker_analysis['courtier']

    # Cas spécial: un seul locuteur ou aucun courtier identifié
    # On prend TOUS les segments (considérés comme client)
    if speaker_analysis.get('single_speaker', False) or courtier_speaker is None:
        for turn, _, speaker in diarization.itertracks(yield_label=True):
            client_segments.append({
                'start': float(turn.start),
                'end': float(turn.end),
                'duration': float(turn.end - turn.start),
                'speaker': speaker
            })
        return client_segments

    # Cas normal: filtrer pour ne garder que les segments du client
    for turn, _, speaker in diarization.itertracks(yield_label=True):
        # Ne garder que les segments qui ne sont PAS du courtier
        if speaker != courtier_speaker:
            client_segments.append({
                'start': float(turn.start),
                'end': float(turn.end),
                'duration': float(turn.end - turn.start),
                'speaker': speaker
            })

    return client_segments


def main():
    if len(sys.argv) < 3:
        print("Usage: python3 diarize_audio.py <audio_file> <output_json>", file=sys.stderr)
        sys.exit(1)

    audio_file = sys.argv[1]
    output_json = sys.argv[2]

    if not os.path.exists(audio_file):
        print(f"Erreur: Fichier audio introuvable: {audio_file}", file=sys.stderr)
        sys.exit(1)

    print(f"🎙️ Diarisation de: {audio_file}")

    try:
        # Charger le pipeline
        print("📦 Chargement du modèle pyannote...")
        pipeline = load_pipeline()

        # Faire la diarisation
        print("🔍 Analyse des locuteurs...")
        diarization = pipeline(audio_file)

        # Analyser les locuteurs
        print("👥 Identification courtier/client...")
        speaker_analysis = analyze_speakers(diarization)

        # Extraire les segments du client
        print("✂️ Extraction des segments client...")
        client_segments = extract_client_segments(diarization, speaker_analysis)

        # Statistiques
        total_speakers = len(speaker_analysis['stats'])
        total_client_duration = sum(seg['duration'] for seg in client_segments)
        is_single_speaker = speaker_analysis.get('single_speaker', False)

        # Calculs différents selon le mode
        if is_single_speaker or speaker_analysis['courtier'] is None:
            total_courtier_duration = 0
            courtier_num_segments = 0
            print(f"\n📊 Résultats (mode locuteur unique):")
            print(f"   - Locuteurs détectés: {total_speakers}")
            print(f"   - Mode: Tout l'audio considéré comme client")
            print(f"   - Segments extraits: {len(client_segments)} ({total_client_duration:.1f}s)")
        else:
            total_courtier_duration = speaker_analysis['stats'][speaker_analysis['courtier']]['total_duration']
            courtier_num_segments = speaker_analysis['stats'][speaker_analysis['courtier']]['num_segments']
            print(f"\n📊 Résultats:")
            print(f"   - Locuteurs détectés: {total_speakers}")
            print(f"   - Courtier: {speaker_analysis['courtier']} ({courtier_num_segments} segments, {total_courtier_duration:.1f}s)")
            print(f"   - Client(s): {', '.join(speaker_analysis['clients'])}")
            print(f"   - Segments client extraits: {len(client_segments)} ({total_client_duration:.1f}s)")

        # Sauvegarder le résultat
        result = {
            'success': True,
            'total_speakers': total_speakers,
            'courtier_speaker': speaker_analysis['courtier'],
            'client_speakers': speaker_analysis['clients'],
            'client_segments': client_segments,
            'single_speaker_mode': is_single_speaker,
            'stats': {
                'courtier_duration': total_courtier_duration,
                'client_duration': total_client_duration,
                'courtier_num_segments': courtier_num_segments,
                'client_num_segments': len(client_segments)
            }
        }

        with open(output_json, 'w', encoding='utf-8') as f:
            json.dump(result, f, indent=2, ensure_ascii=False)

        print(f"\n✅ Résultats sauvegardés dans: {output_json}")

    except Exception as e:
        print(f"\n❌ Erreur: {str(e)}", file=sys.stderr)

        # Sauvegarder l'erreur
        error_result = {
            'success': False,
            'error': str(e),
            'client_segments': []
        }

        with open(output_json, 'w', encoding='utf-8') as f:
            json.dump(error_result, f, indent=2, ensure_ascii=False)

        sys.exit(1)


if __name__ == '__main__':
    main()
