#!/usr/bin/env python3
"""
Script de health-check pour pyannote

Vérifie que:
1. Python et les dépendances sont installées
2. Le modèle pyannote est téléchargé et accessible
3. Le token HuggingFace est valide (si configuré)

Usage:
    python3 check_pyannote.py

Retourne un JSON avec le statut
"""

import sys
import json
import os

def check_health():
    result = {
        'available': False,
        'python_version': sys.version,
        'checks': {
            'torch': {'status': 'pending', 'message': ''},
            'pyannote': {'status': 'pending', 'message': ''},
            'model': {'status': 'pending', 'message': ''},
            'huggingface_token': {'status': 'pending', 'message': ''},
        },
        'errors': [],
        'warnings': []
    }

    # Check 1: PyTorch
    try:
        import torch
        result['checks']['torch'] = {
            'status': 'ok',
            'message': f'PyTorch {torch.__version__} installed',
            'cuda_available': torch.cuda.is_available(),
            'device': 'cuda' if torch.cuda.is_available() else 'cpu'
        }
    except ImportError as e:
        result['checks']['torch'] = {
            'status': 'error',
            'message': f'PyTorch not installed: {str(e)}'
        }
        result['errors'].append('PyTorch is required but not installed')
        print(json.dumps(result, indent=2))
        return result

    # Check 2: pyannote.audio
    try:
        import pyannote.audio
        result['checks']['pyannote'] = {
            'status': 'ok',
            'message': f'pyannote.audio installed'
        }
    except ImportError as e:
        result['checks']['pyannote'] = {
            'status': 'error',
            'message': f'pyannote.audio not installed: {str(e)}'
        }
        result['errors'].append('pyannote.audio is required but not installed')
        print(json.dumps(result, indent=2))
        return result

    # Check 3: HuggingFace Token
    hf_token = os.getenv('HUGGINGFACE_TOKEN')
    if hf_token:
        result['checks']['huggingface_token'] = {
            'status': 'ok',
            'message': 'Token configured',
            'token_prefix': hf_token[:10] + '...' if len(hf_token) > 10 else '***'
        }
    else:
        result['checks']['huggingface_token'] = {
            'status': 'warning',
            'message': 'No token configured - model access may fail'
        }
        result['warnings'].append('HUGGINGFACE_TOKEN not set - diarization may fail on first use')

    # Check 4: Model availability (without loading it fully)
    try:
        from pyannote.audio import Pipeline
        from huggingface_hub import hf_hub_download
        from huggingface_hub.utils import HfHubHTTPError

        # Try to check if model config exists (lightweight check)
        try:
            config_path = hf_hub_download(
                repo_id="pyannote/speaker-diarization-3.1",
                filename="config.yaml",
                token=hf_token,
                local_files_only=True  # Only check local cache first
            )
            result['checks']['model'] = {
                'status': 'ok',
                'message': 'Model cached locally',
                'config_path': config_path
            }
        except Exception:
            # Model not cached locally, try remote check
            try:
                config_path = hf_hub_download(
                    repo_id="pyannote/speaker-diarization-3.1",
                    filename="config.yaml",
                    token=hf_token,
                    local_files_only=False
                )
                result['checks']['model'] = {
                    'status': 'ok',
                    'message': 'Model accessible (downloaded config)',
                    'config_path': config_path
                }
            except HfHubHTTPError as e:
                if '401' in str(e) or 'Unauthorized' in str(e):
                    result['checks']['model'] = {
                        'status': 'error',
                        'message': 'Model requires authentication - check HUGGINGFACE_TOKEN'
                    }
                    result['errors'].append('HuggingFace authentication failed')
                elif '403' in str(e) or 'Forbidden' in str(e):
                    result['checks']['model'] = {
                        'status': 'error',
                        'message': 'Model license not accepted - visit https://huggingface.co/pyannote/speaker-diarization-3.1'
                    }
                    result['errors'].append('pyannote model license not accepted on HuggingFace')
                else:
                    result['checks']['model'] = {
                        'status': 'error',
                        'message': f'Model access error: {str(e)}'
                    }
                    result['errors'].append(f'Cannot access model: {str(e)}')
            except Exception as e:
                result['checks']['model'] = {
                    'status': 'warning',
                    'message': f'Could not verify model: {str(e)}'
                }
                result['warnings'].append('Model status unknown - may work on first use')

    except Exception as e:
        result['checks']['model'] = {
            'status': 'error',
            'message': f'Error checking model: {str(e)}'
        }
        result['errors'].append(f'Model check failed: {str(e)}')

    # Final status
    all_ok = all(
        check['status'] in ['ok', 'warning']
        for check in result['checks'].values()
    )
    result['available'] = all_ok and len(result['errors']) == 0

    return result


if __name__ == '__main__':
    result = check_health()
    print(json.dumps(result, indent=2))
    sys.exit(0 if result['available'] else 1)
