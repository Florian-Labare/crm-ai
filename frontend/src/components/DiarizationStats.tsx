import React, { useState, useEffect } from 'react';
import { BarChart3, AlertCircle, CheckCircle, Clock, RefreshCw } from 'lucide-react';
import api from '../api/apiClient';

interface DailyStats {
  date: string;
  total: number;
  success_count: number;
  failure_count: number;
}

interface TopError {
  error_message: string;
  error_code: string | null;
  count: number;
}

interface Stats {
  period: {
    start: string;
    end: string;
    days: number;
  };
  totals: {
    total: number;
    success: number;
    failed: number;
    timeout: number;
    fallback: number;
    skipped: number;
  };
  rates: {
    success_rate: number;
    failure_rate: number;
    single_speaker_rate: number;
  };
  performance: {
    avg_duration_ms: number;
    avg_success_duration_ms: number;
    avg_speakers_detected: number;
  };
  daily: DailyStats[];
  top_errors: TopError[];
}

interface HealthSummary {
  status: 'healthy' | 'degraded' | 'warning' | 'critical' | 'unknown';
  message: string;
  last_24h: {
    total: number;
    success: number;
    failures: number;
    success_rate: number;
  };
  consecutive_failures: number;
  checked_at: string;
}

interface RecentFailure {
  id: number;
  status: string;
  error_message: string;
  error_code: string | null;
  audio_record_id: number | null;
  duration_ms: number | null;
  audio_duration_seconds: number | null;
  created_at: string;
}

interface DiarizationStatsProps {
  defaultDays?: number;
}

export const DiarizationStats: React.FC<DiarizationStatsProps> = ({ defaultDays = 7 }) => {
  const [days, setDays] = useState(defaultDays);
  const [stats, setStats] = useState<Stats | null>(null);
  const [healthSummary, setHealthSummary] = useState<HealthSummary | null>(null);
  const [recentFailures, setRecentFailures] = useState<RecentFailure[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadStats();
  }, [days]);

  const loadStats = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await api.get(`/diarization/stats?days=${days}`);

      setStats(response.data.stats);
      setHealthSummary(response.data.health_summary);
      setRecentFailures(response.data.recent_failures || []);
    } catch (err) {
      setError('Impossible de charger les statistiques');
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'healthy':
        return 'text-green-500 bg-green-50';
      case 'warning':
        return 'text-amber-500 bg-amber-50';
      case 'degraded':
        return 'text-orange-500 bg-orange-50';
      case 'critical':
        return 'text-red-500 bg-red-50';
      default:
        return 'text-gray-500 bg-gray-50';
    }
  };

  const formatDuration = (ms: number): string => {
    if (ms < 1000) return `${ms}ms`;
    return `${(ms / 1000).toFixed(1)}s`;
  };

  if (loading) {
    return (
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center justify-center gap-2 text-gray-500">
          <RefreshCw className="animate-spin" size={20} />
          <span>Chargement des statistiques...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center gap-2 text-red-500">
          <AlertCircle size={20} />
          <span>{error}</span>
        </div>
      </div>
    );
  }

  if (!stats) {
    return null;
  }

  return (
    <div className="space-y-6">
      {/* Health Summary */}
      {healthSummary && (
        <div className={`rounded-lg p-4 ${getStatusColor(healthSummary.status)}`}>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              {healthSummary.status === 'healthy' ? (
                <CheckCircle size={24} />
              ) : (
                <AlertCircle size={24} />
              )}
              <div>
                <p className="font-semibold capitalize">{healthSummary.status}</p>
                <p className="text-sm opacity-80">{healthSummary.message}</p>
              </div>
            </div>
            <div className="text-right">
              <p className="text-2xl font-bold">{healthSummary.last_24h.success_rate}%</p>
              <p className="text-sm opacity-80">Taux de succès (24h)</p>
            </div>
          </div>
          {healthSummary.consecutive_failures > 0 && (
            <div className="mt-3 p-2 bg-white/50 rounded text-sm">
              <AlertCircle size={14} className="inline mr-1" />
              {healthSummary.consecutive_failures} échec(s) consécutif(s)
            </div>
          )}
        </div>
      )}

      {/* Stats Cards */}
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <BarChart3 size={20} />
            Statistiques de diarisation
          </h3>
          <select
            value={days}
            onChange={(e) => setDays(Number(e.target.value))}
            className="border rounded-lg px-3 py-1 text-sm"
          >
            <option value={7}>7 derniers jours</option>
            <option value={14}>14 derniers jours</option>
            <option value={30}>30 derniers jours</option>
          </select>
        </div>

        {/* Metrics Grid */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div className="p-4 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-gray-900">{stats.totals.total}</p>
            <p className="text-sm text-gray-500">Total diarisations</p>
          </div>
          <div className="p-4 bg-green-50 rounded-lg">
            <p className="text-2xl font-bold text-green-600">{stats.rates.success_rate}%</p>
            <p className="text-sm text-gray-500">Taux de succès</p>
          </div>
          <div className="p-4 bg-blue-50 rounded-lg">
            <p className="text-2xl font-bold text-blue-600">
              {formatDuration(stats.performance.avg_success_duration_ms)}
            </p>
            <p className="text-sm text-gray-500">Durée moyenne</p>
          </div>
          <div className="p-4 bg-purple-50 rounded-lg">
            <p className="text-2xl font-bold text-purple-600">
              {stats.performance.avg_speakers_detected}
            </p>
            <p className="text-sm text-gray-500">Locuteurs moyens</p>
          </div>
        </div>

        {/* Status Breakdown */}
        <div className="mb-6">
          <h4 className="font-medium mb-3">Répartition par statut</h4>
          <div className="flex gap-2 flex-wrap">
            <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
              Succès: {stats.totals.success}
            </span>
            <span className="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm">
              Échecs: {stats.totals.failed}
            </span>
            <span className="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm">
              Timeout: {stats.totals.timeout}
            </span>
            <span className="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-sm">
              Fallback: {stats.totals.fallback}
            </span>
            <span className="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
              Ignorés: {stats.totals.skipped}
            </span>
          </div>
        </div>

        {/* Daily Chart (simple bar representation) */}
        {stats.daily.length > 0 && (
          <div className="mb-6">
            <h4 className="font-medium mb-3">Activité journalière</h4>
            <div className="flex items-end gap-1 h-24">
              {stats.daily.map((day) => {
                const maxTotal = Math.max(...stats.daily.map((d) => d.total));
                const height = maxTotal > 0 ? (day.total / maxTotal) * 100 : 0;
                const successRatio = day.total > 0 ? (day.success_count / day.total) * 100 : 100;

                return (
                  <div
                    key={day.date}
                    className="flex-1 flex flex-col items-center"
                    title={`${day.date}: ${day.success_count}/${day.total} succès`}
                  >
                    <div
                      className="w-full rounded-t relative"
                      style={{
                        height: `${height}%`,
                        minHeight: day.total > 0 ? '4px' : '0',
                        background: `linear-gradient(to top, #22c55e ${successRatio}%, #ef4444 ${successRatio}%)`,
                      }}
                    />
                    <span className="text-xs text-gray-400 mt-1">
                      {new Date(day.date).getDate()}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Top Errors */}
        {stats.top_errors.length > 0 && (
          <div>
            <h4 className="font-medium mb-3">Erreurs fréquentes</h4>
            <div className="space-y-2">
              {stats.top_errors.map((err, i) => (
                <div
                  key={i}
                  className="flex items-center justify-between p-2 bg-red-50 rounded text-sm"
                >
                  <span className="text-red-700 truncate flex-1">{err.error_message}</span>
                  <span className="ml-2 px-2 py-0.5 bg-red-100 text-red-800 rounded font-medium">
                    {err.count}x
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Recent Failures */}
      {recentFailures.length > 0 && (
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <AlertCircle size={20} className="text-red-500" />
            Échecs récents
          </h3>
          <div className="space-y-3">
            {recentFailures.slice(0, 5).map((failure) => (
              <div key={failure.id} className="p-3 border border-red-100 rounded-lg">
                <div className="flex items-center justify-between mb-1">
                  <span className="font-medium text-red-600">{failure.status.toUpperCase()}</span>
                  <span className="text-sm text-gray-500">
                    {new Date(failure.created_at).toLocaleString()}
                  </span>
                </div>
                <p className="text-sm text-gray-600">{failure.error_message}</p>
                <div className="mt-2 flex gap-4 text-xs text-gray-400">
                  {failure.audio_record_id && (
                    <span>Audio #{failure.audio_record_id}</span>
                  )}
                  {failure.duration_ms && (
                    <span className="flex items-center gap-1">
                      <Clock size={12} />
                      {formatDuration(failure.duration_ms)}
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default DiarizationStats;
