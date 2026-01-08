import React, { useState, useEffect } from 'react';
import { Activity, CheckCircle, AlertTriangle, XCircle, RefreshCw } from 'lucide-react';
import api from '../api/apiClient';

interface HealthCheck {
  status: string;
  message: string;
}

interface PyannoteHealth {
  available: boolean;
  checks: {
    torch: HealthCheck;
    pyannote: HealthCheck;
    model: HealthCheck;
    huggingface_token: HealthCheck;
  };
  errors: string[];
  warnings: string[];
  checked_at: string;
}

interface AudioSystemHealth {
  status: 'healthy' | 'degraded' | 'critical';
  timestamp: string;
  components: {
    pyannote: PyannoteHealth;
  };
  features: {
    transcription: boolean;
    diarization: boolean;
    speaker_separation: boolean;
  };
  message: string;
}

interface PyannoteHealthStatusProps {
  compact?: boolean;
  showDetails?: boolean;
  onStatusChange?: (available: boolean) => void;
}

export const PyannoteHealthStatus: React.FC<PyannoteHealthStatusProps> = ({
  compact = false,
  showDetails = true,
  onStatusChange,
}) => {
  const [health, setHealth] = useState<AudioSystemHealth | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    checkHealth();
  }, []);

  const checkHealth = async (forceRefresh = false) => {
    try {
      setError(null);
      if (forceRefresh) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      const endpoint = forceRefresh ? '/health/pyannote?refresh=true' : '/health/audio';
      const response = await api.get(endpoint);

      if (forceRefresh) {
        // Re-fetch full audio health after pyannote refresh
        const audioResponse = await api.get('/health/audio');
        setHealth(audioResponse.data);
        onStatusChange?.(audioResponse.data.components.pyannote.available);
      } else {
        setHealth(response.data);
        onStatusChange?.(response.data.components.pyannote.available);
      }
    } catch (err) {
      setError('Impossible de vérifier le statut');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'healthy':
        return <CheckCircle className="text-green-500" size={compact ? 16 : 20} />;
      case 'degraded':
        return <AlertTriangle className="text-amber-500" size={compact ? 16 : 20} />;
      case 'critical':
        return <XCircle className="text-red-500" size={compact ? 16 : 20} />;
      default:
        return <Activity className="text-gray-500" size={compact ? 16 : 20} />;
    }
  };

  const getCheckStatusBadge = (check: HealthCheck) => {
    const colors = {
      ok: 'bg-green-100 text-green-700',
      warning: 'bg-amber-100 text-amber-700',
      error: 'bg-red-100 text-red-700',
      pending: 'bg-gray-100 text-gray-700',
    };

    return (
      <span
        className={`px-2 py-0.5 rounded text-xs font-medium ${
          colors[check.status as keyof typeof colors] || colors.pending
        }`}
      >
        {check.status.toUpperCase()}
      </span>
    );
  };

  if (loading) {
    return (
      <div className={`flex items-center gap-2 ${compact ? 'text-sm' : ''}`}>
        <RefreshCw className="animate-spin text-gray-400" size={compact ? 14 : 16} />
        <span className="text-gray-500">Vérification...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`flex items-center gap-2 text-red-500 ${compact ? 'text-sm' : ''}`}>
        <XCircle size={compact ? 14 : 16} />
        <span>{error}</span>
      </div>
    );
  }

  if (!health) {
    return null;
  }

  if (compact) {
    return (
      <div className="flex items-center gap-2">
        {getStatusIcon(health.status)}
        <span className="text-sm">
          Diarisation: {health.features.diarization ? 'Active' : 'Inactive'}
        </span>
        <button
          onClick={() => checkHealth(true)}
          disabled={refreshing}
          className="p-1 hover:bg-gray-100 rounded"
          title="Rafraîchir"
        >
          <RefreshCw
            size={14}
            className={refreshing ? 'animate-spin text-gray-400' : 'text-gray-500'}
          />
        </button>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold flex items-center gap-2">
          <Activity size={20} />
          Statut du système audio
        </h3>
        <button
          onClick={() => checkHealth(true)}
          disabled={refreshing}
          className="flex items-center gap-2 px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <RefreshCw size={14} className={refreshing ? 'animate-spin' : ''} />
          {refreshing ? 'Actualisation...' : 'Actualiser'}
        </button>
      </div>

      {/* Status global */}
      <div
        className={`p-4 rounded-lg mb-4 ${
          health.status === 'healthy'
            ? 'bg-green-50 border border-green-200'
            : health.status === 'degraded'
            ? 'bg-amber-50 border border-amber-200'
            : 'bg-red-50 border border-red-200'
        }`}
      >
        <div className="flex items-center gap-3">
          {getStatusIcon(health.status)}
          <div>
            <p className="font-medium capitalize">{health.status}</p>
            <p className="text-sm text-gray-600">{health.message}</p>
          </div>
        </div>
      </div>

      {/* Features */}
      <div className="grid grid-cols-3 gap-4 mb-4">
        <div className="p-3 bg-gray-50 rounded-lg text-center">
          <div
            className={`text-2xl mb-1 ${
              health.features.transcription ? 'text-green-500' : 'text-red-500'
            }`}
          >
            {health.features.transcription ? '✓' : '✗'}
          </div>
          <p className="text-sm font-medium">Transcription</p>
        </div>
        <div className="p-3 bg-gray-50 rounded-lg text-center">
          <div
            className={`text-2xl mb-1 ${
              health.features.diarization ? 'text-green-500' : 'text-amber-500'
            }`}
          >
            {health.features.diarization ? '✓' : '⚠'}
          </div>
          <p className="text-sm font-medium">Diarisation</p>
        </div>
        <div className="p-3 bg-gray-50 rounded-lg text-center">
          <div
            className={`text-2xl mb-1 ${
              health.features.speaker_separation ? 'text-green-500' : 'text-amber-500'
            }`}
          >
            {health.features.speaker_separation ? '✓' : '⚠'}
          </div>
          <p className="text-sm font-medium">Séparation speakers</p>
        </div>
      </div>

      {/* Détails pyannote */}
      {showDetails && health.components.pyannote && (
        <div className="border-t pt-4">
          <h4 className="font-medium mb-3">Détails Pyannote</h4>
          <div className="space-y-2">
            {Object.entries(health.components.pyannote.checks || {}).map(([key, check]) => (
              <div key={key} className="flex items-center justify-between py-2 px-3 bg-gray-50 rounded">
                <span className="capitalize">{key.replace('_', ' ')}</span>
                <div className="flex items-center gap-2">
                  <span className="text-sm text-gray-500">{check.message}</span>
                  {getCheckStatusBadge(check)}
                </div>
              </div>
            ))}
          </div>

          {/* Warnings */}
          {health.components.pyannote.warnings?.length > 0 && (
            <div className="mt-3 p-3 bg-amber-50 rounded-lg">
              <p className="text-sm font-medium text-amber-700 mb-1">Avertissements:</p>
              <ul className="text-sm text-amber-600 list-disc list-inside">
                {health.components.pyannote.warnings.map((warning, i) => (
                  <li key={i}>{warning}</li>
                ))}
              </ul>
            </div>
          )}

          {/* Errors */}
          {health.components.pyannote.errors?.length > 0 && (
            <div className="mt-3 p-3 bg-red-50 rounded-lg">
              <p className="text-sm font-medium text-red-700 mb-1">Erreurs:</p>
              <ul className="text-sm text-red-600 list-disc list-inside">
                {health.components.pyannote.errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      <p className="text-xs text-gray-400 mt-4">
        Dernière vérification: {new Date(health.timestamp).toLocaleString()}
      </p>
    </div>
  );
};

export default PyannoteHealthStatus;
