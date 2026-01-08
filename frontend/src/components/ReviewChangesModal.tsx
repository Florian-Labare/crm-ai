import React, { useState, useEffect } from 'react';
import {
  AlertTriangle,
  Check,
  X,
  ChevronDown,
  ChevronUp,
  RefreshCw,
  CheckCircle,
  XCircle,
  AlertCircle,
  Zap,
} from 'lucide-react';
import api from '../api/apiClient';

interface ChangeItem {
  field: string;
  label: string;
  current_value: any;
  new_value: any;
  has_change: boolean;
  is_conflict: boolean;
  is_critical: boolean;
  is_relational?: boolean;
  requires_review: boolean;
  relational_fields?: string[];
  current_display: string;
  new_display: string;
}

interface PendingChange {
  id: number;
  client: {
    id: number;
    nom: string;
    prenom: string;
    full_name: string;
  };
  source: string;
  status: string;
  changes_diff: Record<string, ChangeItem>;
  actual_changes: Record<string, ChangeItem>;
  changes_count: number;
  conflicts_count: number;
  created_at: string;
}

interface ReviewChangesModalProps {
  pendingChangeId: number;
  onClose: () => void;
  onApplied: () => void;
}

export const ReviewChangesModal: React.FC<ReviewChangesModalProps> = ({
  pendingChangeId,
  onClose,
  onApplied,
}) => {
  const [pendingChange, setPendingChange] = useState<PendingChange | null>(null);
  const [loading, setLoading] = useState(true);
  const [applying, setApplying] = useState(false);
  const [decisions, setDecisions] = useState<Record<string, 'accept' | 'reject' | 'skip'>>({});
  const [error, setError] = useState<string | null>(null);
  const [expanded, setExpanded] = useState(true);

  useEffect(() => {
    loadPendingChange();
  }, [pendingChangeId]);

  const loadPendingChange = async () => {
    try {
      setLoading(true);
      const response = await api.get(`/pending-changes/${pendingChangeId}`);
      setPendingChange(response.data);

      // Initialiser les décisions par défaut
      const initialDecisions: Record<string, 'accept' | 'reject' | 'skip'> = {};
      Object.entries(response.data.changes_diff || {}).forEach(([field, change]: [string, any]) => {
        if (change.has_change) {
          // Par défaut: accept si pas de conflit, skip si conflit
          initialDecisions[field] = change.is_conflict ? 'skip' : 'accept';
        }
      });
      setDecisions(initialDecisions);
    } catch (err: any) {
      setError(err.message || 'Erreur lors du chargement');
    } finally {
      setLoading(false);
    }
  };

  const handleDecisionChange = (field: string, decision: 'accept' | 'reject' | 'skip') => {
    setDecisions(prev => ({ ...prev, [field]: decision }));
  };

  const handleApply = async () => {
    try {
      setApplying(true);
      setError(null);
      await api.post(`/pending-changes/${pendingChangeId}/apply`, { decisions });
      onApplied();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Erreur lors de l\'application');
    } finally {
      setApplying(false);
    }
  };

  const handleAcceptAll = async () => {
    try {
      setApplying(true);
      setError(null);
      await api.post(`/pending-changes/${pendingChangeId}/accept-all`);
      onApplied();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Erreur lors de l\'application');
    } finally {
      setApplying(false);
    }
  };

  const handleRejectAll = async () => {
    try {
      setApplying(true);
      setError(null);
      await api.post(`/pending-changes/${pendingChangeId}/reject-all`);
      onApplied();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Erreur lors du rejet');
    } finally {
      setApplying(false);
    }
  };

  const handleAutoApplySafe = async () => {
    try {
      setApplying(true);
      setError(null);
      await api.post(`/pending-changes/${pendingChangeId}/auto-apply-safe`);
      onApplied();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Erreur lors de l\'application automatique');
    } finally {
      setApplying(false);
    }
  };

  const acceptedCount = Object.values(decisions).filter(d => d === 'accept').length;
  const rejectedCount = Object.values(decisions).filter(d => d === 'reject').length;

  const formatValue = (value: any): string => {
    if (value === null || value === undefined || value === '') return '(vide)';
    if (Array.isArray(value)) {
      return value.length ? `${value.length} élément(s)` : '(vide)';
    }
    if (typeof value === 'object') {
      return '(objet)';
    }
    return String(value);
  };

  const formatKeyLabel = (key: string): string => key.replace(/_/g, ' ');

  const getRelationalFields = (change: ChangeItem): string[] => {
    if (change.relational_fields?.length) {
      return change.relational_fields;
    }

    const value = change.new_value;
    if (!value || typeof value !== 'object') {
      return [];
    }

    if (Array.isArray(value)) {
      const fields: string[] = [];
      value.forEach((item) => {
        if (!item || typeof item !== 'object') {
          return;
        }
        Object.keys(item).forEach((key) => {
          if (!fields.includes(key)) {
            fields.push(key);
          }
        });
      });
      return fields;
    }

    return Object.keys(value);
  };

  const getRelationalDetails = (change: ChangeItem): string[] => {
    const currentValue = change.current_value;
    const newValue = change.new_value;

    if (!newValue || typeof newValue !== 'object') {
      return [];
    }

    if (Array.isArray(newValue)) {
      if (!newValue.length) {
        return [];
      }

      const currentArray = Array.isArray(currentValue) ? currentValue : [];
      return newValue.map((item, index) => {
        if (!item || typeof item !== 'object') {
          return `Élément ${index + 1}: ${formatValue(item)}`;
        }

        const fields = Object.entries(item).map(([key, value]) => {
          const currentItem = currentArray[index] || {};
          const currentFieldValue = currentItem?.[key];
          if (currentFieldValue !== undefined && currentFieldValue !== value) {
            return `${formatKeyLabel(key)}: ${formatValue(currentFieldValue)} → ${formatValue(value)}`;
          }
          return `${formatKeyLabel(key)}: ${formatValue(value)}`;
        });

        return `Élément ${index + 1}: ${fields.join(', ')}`;
      });
    }

    const details: string[] = [];
    Object.keys(newValue).forEach((key) => {
      const newFieldValue = newValue[key];
      const currentFieldValue = currentValue?.[key];
      if (currentFieldValue === undefined || currentFieldValue === null || currentFieldValue === '') {
        details.push(`${formatKeyLabel(key)}: ${formatValue(newFieldValue)}`);
        return;
      }

      if (currentFieldValue !== newFieldValue) {
        details.push(
          `${formatKeyLabel(key)}: ${formatValue(currentFieldValue)} → ${formatValue(newFieldValue)}`
        );
      }
    });

    return details;
  };

  if (loading) {
    return (
      <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div className="bg-white rounded-xl p-8 shadow-2xl">
          <RefreshCw className="animate-spin h-8 w-8 text-[#7367F0] mx-auto" />
          <p className="mt-4 text-[#5E5873]">Chargement des modifications...</p>
        </div>
      </div>
    );
  }

  if (!pendingChange) {
    return null;
  }

  const changes = Object.entries(pendingChange.changes_diff || {}).filter(
    ([_, change]: [string, any]) => change.has_change
  );

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="px-6 py-4 border-b border-[#EBE9F1] flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-[#5E5873]">
              Validation des modifications
            </h2>
            <p className="text-sm text-[#6E6B7B] mt-1">
              Client: <span className="font-semibold">{pendingChange.client?.full_name || 'Inconnu'}</span>
              {' '}&bull;{' '}
              {pendingChange.changes_count} modification(s)
              {pendingChange.conflicts_count > 0 && (
                <span className="text-[#EA5455] ml-2">
                  ({pendingChange.conflicts_count} conflit(s))
                </span>
              )}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-[#F8F8F8] rounded-lg transition-colors"
          >
            <X size={20} className="text-[#6E6B7B]" />
          </button>
        </div>

        {/* Warning banner for conflicts */}
        {pendingChange.conflicts_count > 0 && (
          <div className="mx-6 mt-4 p-4 bg-[#FF9F43]/10 border border-[#FF9F43]/30 rounded-lg flex items-start gap-3">
            <AlertTriangle className="text-[#FF9F43] flex-shrink-0 mt-0.5" size={20} />
            <div>
              <p className="font-semibold text-[#FF9F43]">Attention : Conflits détectés</p>
              <p className="text-sm text-[#6E6B7B] mt-1">
                {pendingChange.conflicts_count} champ(s) contiennent déjà des valeurs qui seront écrasées.
                Vérifiez attentivement avant de valider.
              </p>
            </div>
          </div>
        )}

        {/* Error banner */}
        {error && (
          <div className="mx-6 mt-4 p-4 bg-[#EA5455]/10 border border-[#EA5455]/30 rounded-lg flex items-start gap-3">
            <XCircle className="text-[#EA5455] flex-shrink-0 mt-0.5" size={20} />
            <p className="text-[#EA5455]">{error}</p>
          </div>
        )}

        {/* Changes list */}
        <div className="flex-1 overflow-y-auto p-6">
          <div className="flex items-center justify-between mb-4">
            <button
              onClick={() => setExpanded(!expanded)}
              className="flex items-center gap-2 text-[#6E6B7B] hover:text-[#5E5873]"
            >
              {expanded ? <ChevronUp size={18} /> : <ChevronDown size={18} />}
              <span className="text-sm font-medium">
                {expanded ? 'Réduire' : 'Développer'} les détails
              </span>
            </button>
            <div className="text-sm text-[#6E6B7B]">
              <span className="text-[#28C76F]">{acceptedCount} accepté(s)</span>
              {' '}&bull;{' '}
              <span className="text-[#EA5455]">{rejectedCount} rejeté(s)</span>
            </div>
          </div>

          <div className="space-y-3">
            {changes.map(([field, change]: [string, any]) => (
              <div
                key={field}
                className={`border rounded-lg overflow-hidden ${
                  change.is_conflict
                    ? 'border-[#FF9F43]/50 bg-[#FF9F43]/5'
                    : 'border-[#EBE9F1]'
                }`}
              >
                <div className="px-4 py-3 flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    {change.is_conflict ? (
                      <AlertCircle size={18} className="text-[#FF9F43]" />
                    ) : change.is_critical ? (
                      <AlertTriangle size={18} className="text-[#00CFE8]" />
                    ) : (
                      <CheckCircle size={18} className="text-[#28C76F]" />
                    )}
                    <div>
                      <span className="font-semibold text-[#5E5873]">{change.label}</span>
                      {getRelationalFields(change).length > 0 && (
                        <div className="text-xs text-[#6E6B7B] mt-0.5">
                          Champs: {getRelationalFields(change).join(', ')}
                        </div>
                      )}
                      {change.is_conflict && (
                        <span className="ml-2 text-xs px-2 py-0.5 bg-[#FF9F43]/20 text-[#FF9F43] rounded-full">
                          Conflit
                        </span>
                      )}
                      {change.is_critical && !change.is_conflict && (
                        <span className="ml-2 text-xs px-2 py-0.5 bg-[#00CFE8]/20 text-[#00CFE8] rounded-full">
                          Critique
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Decision buttons */}
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => handleDecisionChange(field, 'accept')}
                      className={`p-2 rounded-lg transition-colors ${
                        decisions[field] === 'accept'
                          ? 'bg-[#28C76F] text-white'
                          : 'bg-[#F8F8F8] text-[#6E6B7B] hover:bg-[#28C76F]/20'
                      }`}
                      title="Accepter"
                    >
                      <Check size={16} />
                    </button>
                    <button
                      onClick={() => handleDecisionChange(field, 'reject')}
                      className={`p-2 rounded-lg transition-colors ${
                        decisions[field] === 'reject'
                          ? 'bg-[#EA5455] text-white'
                          : 'bg-[#F8F8F8] text-[#6E6B7B] hover:bg-[#EA5455]/20'
                      }`}
                      title="Rejeter"
                    >
                      <X size={16} />
                    </button>
                  </div>
                </div>

                {/* Expanded diff view */}
                {expanded && (
                  <div className="px-4 py-3 bg-[#F8F8F8] border-t border-[#EBE9F1]">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-[#6E6B7B] mb-1">Valeur actuelle</p>
                        <p className={`text-sm ${
                          change.current_display === '(vide)'
                            ? 'text-[#B9B9C3] italic'
                            : 'text-[#5E5873] font-medium'
                        }`}>
                          {change.current_display}
                        </p>
                      </div>
                      <div>
                        <p className="text-xs text-[#6E6B7B] mb-1">Nouvelle valeur</p>
                        <p className="text-sm text-[#7367F0] font-medium">
                          {change.new_display}
                        </p>
                      </div>
                    </div>
                    {getRelationalDetails(change).length > 0 && (
                      <div className="mt-3 text-xs text-[#6E6B7B]">
                        <p className="font-semibold text-[#5E5873] mb-1">Détails</p>
                        <ul className="space-y-1">
                          {getRelationalDetails(change).map((detail, index) => (
                            <li key={`${change.field}-detail-${index}`}>{detail}</li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>

          {changes.length === 0 && (
            <div className="text-center py-8 text-[#6E6B7B]">
              Aucune modification détectée
            </div>
          )}
        </div>

        {/* Footer with actions */}
        <div className="px-6 py-4 border-t border-[#EBE9F1] bg-[#F8F8F8]">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <button
                onClick={handleAutoApplySafe}
                disabled={applying}
                className="flex items-center gap-2 px-4 py-2 bg-[#00CFE8]/10 text-[#00CFE8] rounded-lg hover:bg-[#00CFE8]/20 transition-colors disabled:opacity-50"
              >
                <Zap size={16} />
                <span className="text-sm font-medium">Auto (sans conflits)</span>
              </button>
              <button
                onClick={handleRejectAll}
                disabled={applying}
                className="flex items-center gap-2 px-4 py-2 bg-[#EA5455]/10 text-[#EA5455] rounded-lg hover:bg-[#EA5455]/20 transition-colors disabled:opacity-50"
              >
                <XCircle size={16} />
                <span className="text-sm font-medium">Tout rejeter</span>
              </button>
            </div>

            <div className="flex items-center gap-3">
              <button
                onClick={onClose}
                disabled={applying}
                className="px-4 py-2 text-[#6E6B7B] hover:bg-[#EBE9F1] rounded-lg transition-colors disabled:opacity-50"
              >
                Annuler
              </button>
              <button
                onClick={handleAcceptAll}
                disabled={applying}
                className="flex items-center gap-2 px-4 py-2 bg-[#28C76F]/10 text-[#28C76F] rounded-lg hover:bg-[#28C76F]/20 transition-colors disabled:opacity-50"
              >
                <CheckCircle size={16} />
                <span className="font-medium">Tout accepter</span>
              </button>
              <button
                onClick={handleApply}
                disabled={applying || acceptedCount === 0}
                className="flex items-center gap-2 px-6 py-2 bg-[#7367F0] text-white rounded-lg hover:bg-[#7367F0]/90 transition-colors disabled:opacity-50"
              >
                {applying ? (
                  <RefreshCw size={16} className="animate-spin" />
                ) : (
                  <Check size={16} />
                )}
                <span className="font-medium">
                  Appliquer ({acceptedCount})
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReviewChangesModal;
