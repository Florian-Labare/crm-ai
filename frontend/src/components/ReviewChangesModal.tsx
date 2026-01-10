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
  const [overrides, setOverrides] = useState<Record<string, any>>({});
  const [overrideInputs, setOverrideInputs] = useState<Record<string, string>>({});
  const [editModes, setEditModes] = useState<Record<string, boolean>>({});
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
      setOverrides({});
      setOverrideInputs({});
      setEditModes({});
    } catch (err: any) {
      setError(err.message || 'Erreur lors du chargement');
    } finally {
      setLoading(false);
    }
  };

  const handleDecisionChange = (field: string, decision: 'accept' | 'reject' | 'skip') => {
    setDecisions(prev => ({ ...prev, [field]: decision }));
  };

  const setOverrideValue = (field: string, value: any) => {
    setOverrides(prev => ({ ...prev, [field]: value }));
    setDecisions(prev => ({ ...prev, [field]: 'accept' }));
  };

  const handleToggleEdit = (field: string, change: ChangeItem) => {
    const next = !editModes[field];
    setEditModes(prev => ({ ...prev, [field]: next }));
    if (!next) {
      setOverrides(prev => {
        const updated = { ...prev };
        delete updated[field];
        return updated;
      });
      setOverrideInputs(prev => {
        const updated = { ...prev };
        delete updated[field];
        return updated;
      });
      return;
    }
    if (typeof change.new_value === 'object' && change.new_value !== null) {
      setOverrideInputs(prev => ({
        ...prev,
        [field]: JSON.stringify(change.new_value, null, 2),
      }));
    }
  };

  const coerceScalarValue = (rawValue: string, originalValue: any): any => {
    if (typeof originalValue === 'number') {
      const num = Number(rawValue);
      return Number.isFinite(num) ? num : rawValue;
    }
    if (typeof originalValue === 'boolean') {
      return rawValue === 'true';
    }
    return rawValue;
  };

  const parseOverrideInput = (field: string, raw: string): any | null => {
    try {
      return JSON.parse(raw);
    } catch (err) {
      setError(`Le format JSON est invalide pour "${field}".`);
      return null;
    }
  };

  const cloneValue = (value: any): any => {
    try {
      return JSON.parse(JSON.stringify(value));
    } catch {
      return value;
    }
  };

  const ensureOverrideValue = (field: string, fallback: any) => {
    if (Object.prototype.hasOwnProperty.call(overrides, field)) {
      return overrides[field];
    }
    const nextValue = cloneValue(fallback);
    setOverrides(prev => ({ ...prev, [field]: nextValue }));
    return nextValue;
  };

  const updateOverrideValue = (field: string, nextValue: any) => {
    setOverrides(prev => ({ ...prev, [field]: nextValue }));
    setDecisions(prev => ({ ...prev, [field]: 'accept' }));
  };

  const updateArrayItem = (field: string, index: number, value: any, change: ChangeItem) => {
    const base = ensureOverrideValue(field, change.new_value);
    const next = Array.isArray(base) ? [...base] : [];
    next[index] = value;
    updateOverrideValue(field, next);
  };

  const removeArrayItem = (field: string, index: number, change: ChangeItem) => {
    const base = ensureOverrideValue(field, change.new_value);
    const next = Array.isArray(base) ? base.filter((_: any, idx: number) => idx !== index) : [];
    updateOverrideValue(field, next);
  };

  const addArrayItem = (field: string, template: any, change: ChangeItem) => {
    const base = ensureOverrideValue(field, change.new_value);
    const next = Array.isArray(base) ? [...base] : [];
    next.push(cloneValue(template));
    updateOverrideValue(field, next);
  };

  const updateObjectField = (field: string, key: string, value: any, change: ChangeItem) => {
    const base = ensureOverrideValue(field, change.new_value);
    const next = { ...(typeof base === 'object' && base !== null ? base : {}) };
    next[key] = value;
    updateOverrideValue(field, next);
  };

  const isArrayOfObjects = (value: any): value is Record<string, any>[] =>
    Array.isArray(value) && value.every(item => item && typeof item === 'object' && !Array.isArray(item));

  const isArrayOfPrimitives = (value: any): boolean =>
    Array.isArray(value) && value.every(item => item === null || typeof item !== 'object');

  const handleApply = async () => {
    try {
      setApplying(true);
      setError(null);
      const payloadOverrides: Record<string, any> = {};
      let hasInvalidOverride = false;
      Object.entries(overrides).forEach(([field, value]) => {
        payloadOverrides[field] = value;
      });

      Object.entries(overrideInputs).forEach(([field, value]) => {
        if (!editModes[field]) return;
        const parsed = parseOverrideInput(field, value);
        if (parsed === null) {
          hasInvalidOverride = true;
          return;
        }
        payloadOverrides[field] = parsed;
      });

      if (hasInvalidOverride) {
        setApplying(false);
        return;
      }

      await api.post(`/pending-changes/${pendingChangeId}/apply`, { decisions, overrides: payloadOverrides });
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

  const getDisplayedNewValue = (field: string, change: ChangeItem): string => {
    if (Object.prototype.hasOwnProperty.call(overrides, field)) {
      const overrideValue = overrides[field];
      if (typeof overrideValue === 'object' && overrideValue !== null) {
        return 'Valeur personnalisée';
      }
      return formatValue(overrideValue);
    }
    if (editModes[field] && overrideInputs[field] && typeof change.new_value === 'object') {
      return 'Valeur personnalisée';
    }
    return change.new_display || formatValue(change.new_value);
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

  const getRelationalDetails = (change: ChangeItem, fieldKey: string): string[] => {
    const currentValue = change.current_value;
    const newValue = Object.prototype.hasOwnProperty.call(overrides, fieldKey)
      ? overrides[fieldKey]
      : change.new_value;

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
                          {getDisplayedNewValue(field, change)}
                        </p>
                        <button
                          onClick={() => handleToggleEdit(field, change)}
                          className="mt-2 inline-flex items-center text-xs font-semibold text-[#7367F0] hover:text-[#5E50EE]"
                        >
                          {editModes[field] ? 'Annuler la modification' : 'Modifier la valeur'}
                        </button>
                        {editModes[field] && (
                          <div className="mt-2">
                            {typeof change.new_value === 'boolean' ? (
                              <select
                                value={String(Object.prototype.hasOwnProperty.call(overrides, field) ? overrides[field] : change.new_value)}
                                onChange={(e) => setOverrideValue(field, e.target.value === 'true')}
                                className="w-full px-3 py-2 border border-[#EBE9F1] rounded-lg text-sm text-[#5E5873] bg-white focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)]"
                              >
                                <option value="true">Oui</option>
                                <option value="false">Non</option>
                              </select>
                            ) : typeof change.new_value === 'object' && change.new_value !== null ? (
                              <div className="space-y-3">
                                {isArrayOfObjects(ensureOverrideValue(field, change.new_value)) && (
                                  <div className="space-y-3">
                                    {ensureOverrideValue(field, change.new_value).map((item: Record<string, any>, index: number) => {
                                      const allKeys = Array.from(
                                        new Set([
                                          ...Object.keys(item || {}),
                                          ...getRelationalFields(change),
                                        ])
                                      );
                                      return (
                                        <div key={`${field}-row-${index}`} className="border border-[#EBE9F1] rounded-lg p-3 bg-white">
                                          <div className="flex items-center justify-between mb-2">
                                            <span className="text-xs font-semibold text-[#6E6B7B]">
                                              Élément {index + 1}
                                            </span>
                                            <button
                                              onClick={() => removeArrayItem(field, index, change)}
                                              className="text-xs font-semibold text-[#EA5455] hover:text-[#D94849]"
                                            >
                                              Supprimer
                                            </button>
                                          </div>
                                          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            {allKeys.map((key) => (
                                              <label key={`${field}-${index}-${key}`} className="text-xs text-[#6E6B7B]">
                                                {formatKeyLabel(key)}
                                                <input
                                                  type="text"
                                                  value={item?.[key] ?? ''}
                                                  onChange={(e) => {
                                                    const nextItem = { ...(item || {}) };
                                                    nextItem[key] = e.target.value;
                                                    updateArrayItem(field, index, nextItem, change);
                                                  }}
                                                  className="mt-1 w-full px-3 py-2 border border-[#EBE9F1] rounded-lg text-sm text-[#5E5873] bg-white focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)]"
                                                />
                                              </label>
                                            ))}
                                          </div>
                                        </div>
                                      );
                                    })}
                                    <button
                                      onClick={() => addArrayItem(field, {}, change)}
                                      className="text-xs font-semibold text-[#7367F0] hover:text-[#5E50EE]"
                                    >
                                      + Ajouter un élément
                                    </button>
                                  </div>
                                )}
                                {isArrayOfPrimitives(ensureOverrideValue(field, change.new_value)) && (
                                  <div className="space-y-2">
                                    {ensureOverrideValue(field, change.new_value).map((item: any, index: number) => (
                                      <div key={`${field}-item-${index}`} className="flex items-center gap-2">
                                        <input
                                          type="text"
                                          value={item ?? ''}
                                          onChange={(e) => updateArrayItem(field, index, e.target.value, change)}
                                          className="flex-1 px-3 py-2 border border-[#EBE9F1] rounded-lg text-sm text-[#5E5873] bg-white focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)]"
                                        />
                                        <button
                                          onClick={() => removeArrayItem(field, index, change)}
                                          className="text-xs font-semibold text-[#EA5455] hover:text-[#D94849]"
                                        >
                                          Supprimer
                                        </button>
                                      </div>
                                    ))}
                                    <button
                                      onClick={() => addArrayItem(field, '', change)}
                                      className="text-xs font-semibold text-[#7367F0] hover:text-[#5E50EE]"
                                    >
                                      + Ajouter un élément
                                    </button>
                                  </div>
                                )}
                                {!Array.isArray(ensureOverrideValue(field, change.new_value)) && (
                                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    {Object.keys(ensureOverrideValue(field, change.new_value) || {}).map((key) => (
                                      <label key={`${field}-${key}`} className="text-xs text-[#6E6B7B]">
                                        {formatKeyLabel(key)}
                                        <input
                                          type="text"
                                          value={ensureOverrideValue(field, change.new_value)?.[key] ?? ''}
                                          onChange={(e) => updateObjectField(field, key, e.target.value, change)}
                                          className="mt-1 w-full px-3 py-2 border border-[#EBE9F1] rounded-lg text-sm text-[#5E5873] bg-white focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)]"
                                        />
                                      </label>
                                    ))}
                                  </div>
                                )}
                              </div>
                            ) : (
                              <input
                                type={typeof change.new_value === 'number' ? 'number' : 'text'}
                                value={String(Object.prototype.hasOwnProperty.call(overrides, field) ? overrides[field] : change.new_value ?? '')}
                                onChange={(e) => setOverrideValue(field, coerceScalarValue(e.target.value, change.new_value))}
                                className="w-full px-3 py-2 border border-[#EBE9F1] rounded-lg text-sm text-[#5E5873] bg-white focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)]"
                                placeholder="Saisir une valeur"
                              />
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                      {getRelationalDetails(change, field).length > 0 && (
                        <div className="mt-3 text-xs text-[#6E6B7B]">
                          <p className="font-semibold text-[#5E5873] mb-1">Détails</p>
                          <ul className="space-y-1">
                          {getRelationalDetails(change, field).map((detail, index) => (
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
