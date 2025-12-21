import React, { useState, useEffect } from 'react';
import { Users, RefreshCw, Check, AlertTriangle, Clock } from 'lucide-react';
import api from '../api/apiClient';
import { toast } from 'react-toastify';

interface Speaker {
  id: string;
  original_role: 'broker' | 'client';
  current_role: 'broker' | 'client';
  duration?: number;
  segments_count?: number;
  corrected: boolean;
}

interface DiarizationData {
  success: boolean;
  total_speakers: number;
  single_speaker_mode: boolean;
  stats?: {
    courtier_duration: number;
    client_duration: number;
    courtier_num_segments: number;
    client_num_segments: number;
  };
}

interface CorrectionData {
  applied: boolean;
  corrections: Record<string, { role: string; corrected_at: string }>;
  corrected_at: string | null;
  corrected_by: string | null;
}

interface SpeakerCorrectionProps {
  audioRecordId: number;
  onCorrectionApplied?: () => void;
}

export const SpeakerCorrection: React.FC<SpeakerCorrectionProps> = ({
  audioRecordId,
  onCorrectionApplied,
}) => {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [diarization, setDiarization] = useState<DiarizationData | null>(null);
  const [speakers, setSpeakers] = useState<Record<string, Speaker>>({});
  const [corrections, setCorrections] = useState<CorrectionData | null>(null);
  const [pendingChanges, setPendingChanges] = useState<Record<string, 'broker' | 'client'>>({});

  useEffect(() => {
    loadDiarizationData();
  }, [audioRecordId]);

  const loadDiarizationData = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await api.get(`/audio-records/${audioRecordId}/speakers`);

      if (response.data.success) {
        setDiarization(response.data.diarization);
        setSpeakers(response.data.speakers);
        setCorrections(response.data.corrections);
        setPendingChanges({});
      } else {
        setError(response.data.message || 'Erreur lors du chargement');
      }
    } catch (err: any) {
      if (err.response?.status === 404) {
        setError('Aucune donnée de diarisation disponible');
      } else {
        setError('Erreur de connexion');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleRoleChange = (speakerId: string, newRole: 'broker' | 'client') => {
    setPendingChanges((prev) => ({
      ...prev,
      [speakerId]: newRole,
    }));
  };

  const saveCorrections = async () => {
    if (Object.keys(pendingChanges).length === 0) {
      toast.info('Aucune modification à enregistrer');
      return;
    }

    try {
      setSaving(true);

      const correctionsArray = Object.entries(pendingChanges).map(([speaker_id, role]) => ({
        speaker_id,
        role,
      }));

      const response = await api.post(`/audio-records/${audioRecordId}/speakers/correct-batch`, {
        corrections: correctionsArray,
      });

      if (response.data.success) {
        toast.success('Corrections enregistrées');
        setSpeakers(response.data.speakers);
        setPendingChanges({});
        onCorrectionApplied?.();
      } else {
        toast.error(response.data.message || 'Erreur lors de la sauvegarde');
      }
    } catch (err) {
      toast.error('Erreur lors de la sauvegarde des corrections');
    } finally {
      setSaving(false);
    }
  };

  const resetCorrections = async () => {
    if (!window.confirm('Voulez-vous vraiment réinitialiser toutes les corrections ?')) {
      return;
    }

    try {
      setSaving(true);

      const response = await api.post(`/audio-records/${audioRecordId}/speakers/reset`);

      if (response.data.success) {
        toast.success('Corrections réinitialisées');
        setSpeakers(response.data.speakers);
        setPendingChanges({});
        setCorrections(null);
        onCorrectionApplied?.();
      } else {
        toast.error(response.data.message);
      }
    } catch (err) {
      toast.error('Erreur lors de la réinitialisation');
    } finally {
      setSaving(false);
    }
  };

  const formatDuration = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}m ${secs}s`;
  };

  const getCurrentRole = (speakerId: string): 'broker' | 'client' => {
    return pendingChanges[speakerId] || speakers[speakerId]?.current_role || 'client';
  };

  const hasChanges = Object.keys(pendingChanges).length > 0;

  if (loading) {
    return (
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center justify-center gap-2 text-gray-500">
          <RefreshCw className="animate-spin" size={20} />
          <span>Chargement des données de diarisation...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center gap-2 text-amber-600">
          <AlertTriangle size={20} />
          <span>{error}</span>
        </div>
      </div>
    );
  }

  if (!diarization) {
    return null;
  }

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold flex items-center gap-2">
          <Users size={20} />
          Identification des locuteurs
        </h3>
        {corrections?.applied && (
          <span className="text-sm text-green-600 flex items-center gap-1">
            <Check size={16} />
            Corrigé par {corrections.corrected_by}
          </span>
        )}
      </div>

      {/* Statistiques globales */}
      <div className="mb-4 p-3 bg-gray-50 rounded-lg">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span className="text-gray-500">Locuteurs détectés:</span>
            <span className="ml-2 font-medium">{diarization.total_speakers}</span>
          </div>
          {diarization.stats && (
            <>
              <div>
                <span className="text-gray-500">Temps courtier:</span>
                <span className="ml-2 font-medium">
                  {formatDuration(diarization.stats.courtier_duration)}
                </span>
              </div>
              <div>
                <span className="text-gray-500">Temps client:</span>
                <span className="ml-2 font-medium">
                  {formatDuration(diarization.stats.client_duration)}
                </span>
              </div>
              <div>
                <span className="text-gray-500">Segments:</span>
                <span className="ml-2 font-medium">
                  {diarization.stats.courtier_num_segments + diarization.stats.client_num_segments}
                </span>
              </div>
            </>
          )}
        </div>

        {diarization.single_speaker_mode && (
          <div className="mt-2 text-amber-600 text-sm flex items-center gap-1">
            <AlertTriangle size={14} />
            Mode locuteur unique - tout l'audio a été attribué au client
          </div>
        )}
      </div>

      {/* Liste des speakers */}
      <div className="space-y-3">
        {Object.entries(speakers).map(([speakerId, speaker]) => {
          const currentRole = getCurrentRole(speakerId);
          const isModified = pendingChanges[speakerId] !== undefined;

          return (
            <div
              key={speakerId}
              className={`p-4 border rounded-lg ${
                isModified ? 'border-blue-300 bg-blue-50' : 'border-gray-200'
              }`}
            >
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="font-medium">{speakerId}</span>
                    {speaker.corrected && !isModified && (
                      <span className="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">
                        Corrigé
                      </span>
                    )}
                    {isModified && (
                      <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                        Modifié
                      </span>
                    )}
                  </div>
                  <div className="text-sm text-gray-500 mt-1">
                    {speaker.duration && (
                      <span className="mr-4">
                        <Clock size={12} className="inline mr-1" />
                        {formatDuration(speaker.duration)}
                      </span>
                    )}
                    {speaker.segments_count && (
                      <span>{speaker.segments_count} segments</span>
                    )}
                    <span className="ml-4 text-gray-400">
                      (Identifié comme: {speaker.original_role === 'broker' ? 'Courtier' : 'Client'})
                    </span>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <select
                    value={currentRole}
                    onChange={(e) =>
                      handleRoleChange(speakerId, e.target.value as 'broker' | 'client')
                    }
                    className="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="broker">Courtier</option>
                    <option value="client">Client</option>
                  </select>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Actions */}
      <div className="mt-6 flex items-center justify-between">
        <button
          onClick={resetCorrections}
          disabled={saving || !corrections?.applied}
          className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Réinitialiser
        </button>

        <div className="flex items-center gap-3">
          {hasChanges && (
            <span className="text-sm text-blue-600">
              {Object.keys(pendingChanges).length} modification(s) en attente
            </span>
          )}
          <button
            onClick={saveCorrections}
            disabled={saving || !hasChanges}
            className={`px-4 py-2 rounded-lg font-medium text-white transition-colors ${
              hasChanges
                ? 'bg-blue-500 hover:bg-blue-600'
                : 'bg-gray-300 cursor-not-allowed'
            }`}
          >
            {saving ? (
              <span className="flex items-center gap-2">
                <RefreshCw className="animate-spin" size={16} />
                Enregistrement...
              </span>
            ) : (
              'Enregistrer les corrections'
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

export default SpeakerCorrection;
