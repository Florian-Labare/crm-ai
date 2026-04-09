import React, { useState, useEffect } from 'react';
import { CheckCircle, AlertCircle, XCircle, FileWarning, Clock } from 'lucide-react';
import api from '../api/apiClient';

interface ComplianceData {
  color: 'red' | 'orange' | 'green';
  label: string;
  score: number;
  valid_count: number;
  pending_count: number;
  missing_count: number;
  total_required: number;
  missing_documents: string[];
  expired_count?: number;
  expiring_soon_count?: number;
}

interface ComplianceBadgeProps {
  clientId: number;
  variant?: 'badge' | 'inline' | 'detailed';
  showTooltip?: boolean;
  className?: string;
}

const colorConfig = {
  green: {
    bg: 'bg-[#28C76F]/10',
    text: 'text-[#28C76F]',
    border: 'border-[#28C76F]',
    icon: CheckCircle,
  },
  orange: {
    bg: 'bg-[#FF9F43]/10',
    text: 'text-[#FF9F43]',
    border: 'border-[#FF9F43]',
    icon: AlertCircle,
  },
  red: {
    bg: 'bg-[#EA5455]/10',
    text: 'text-[#EA5455]',
    border: 'border-[#EA5455]',
    icon: XCircle,
  },
};

export const ComplianceBadge: React.FC<ComplianceBadgeProps> = ({
  clientId,
  variant = 'badge',
  showTooltip = true,
  className = '',
}) => {
  const [data, setData] = useState<ComplianceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [isTooltipOpen, setIsTooltipOpen] = useState(false);

  useEffect(() => {
    loadComplianceStatus();
  }, [clientId]);

  const loadComplianceStatus = async () => {
    try {
      const response = await api.get(`/clients/${clientId}/compliance/badge`);
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (err) {
      console.error('Erreur chargement compliance:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className={`animate-pulse ${className}`}>
        <div className="w-20 h-6 bg-gray-200 rounded-full" />
      </div>
    );
  }

  if (!data) {
    return null;
  }

  const config = colorConfig[data.color];
  const Icon = config.icon;

  const hasExpirationAlerts = (data.expired_count ?? 0) > 0 || (data.expiring_soon_count ?? 0) > 0;
  const showTooltipContent = data.missing_documents.length > 0 || hasExpirationAlerts;

  // Variant: badge (petit badge compact)
  if (variant === 'badge') {
    return (
      <div className={`relative inline-flex items-center gap-1 ${className}`}>
        <button
          onClick={() => showTooltip && setIsTooltipOpen(!isTooltipOpen)}
          onMouseEnter={() => showTooltip && setIsTooltipOpen(true)}
          onMouseLeave={() => showTooltip && setIsTooltipOpen(false)}
          className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full ${config.bg} ${config.text} font-semibold text-xs uppercase tracking-wider transition-all hover:opacity-80`}
        >
          <Icon size={14} />
          {data.label}
        </button>

        {/* Badge alerte expiration */}
        {(data.expired_count ?? 0) > 0 && (
          <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-[#EA5455]/10 text-[#EA5455] font-semibold text-xs">
            <XCircle size={12} />
            {data.expired_count}
          </span>
        )}
        {(data.expiring_soon_count ?? 0) > 0 && (
          <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] font-semibold text-xs">
            <Clock size={12} />
            {data.expiring_soon_count}
          </span>
        )}

        {/* Tooltip */}
        {showTooltip && isTooltipOpen && showTooltipContent && (
          <div className="absolute z-50 top-full left-0 mt-2 w-72 bg-white rounded-lg shadow-xl border border-[#EBE9F1] p-3">
            {/* Alertes d'expiration */}
            {hasExpirationAlerts && (
              <div className="mb-3">
                {(data.expired_count ?? 0) > 0 && (
                  <div className="flex items-center gap-2 mb-1 text-[#EA5455]">
                    <XCircle size={14} />
                    <span className="text-sm font-medium">
                      {data.expired_count} document{(data.expired_count ?? 0) > 1 ? 's' : ''} expiré{(data.expired_count ?? 0) > 1 ? 's' : ''}
                    </span>
                  </div>
                )}
                {(data.expiring_soon_count ?? 0) > 0 && (
                  <div className="flex items-center gap-2 text-[#FF9F43]">
                    <Clock size={14} />
                    <span className="text-sm font-medium">
                      {data.expiring_soon_count} document{(data.expiring_soon_count ?? 0) > 1 ? 's' : ''} expire{(data.expiring_soon_count ?? 0) > 1 ? 'nt' : ''} bientôt
                    </span>
                  </div>
                )}
              </div>
            )}

            {/* Documents manquants */}
            {data.missing_documents.length > 0 && (
              <>
                {hasExpirationAlerts && <div className="border-t border-[#EBE9F1] my-2" />}
                <div className="flex items-center gap-2 mb-2">
                  <FileWarning size={16} className="text-[#EA5455]" />
                  <span className="font-semibold text-[#5E5873] text-sm">
                    Documents manquants
                  </span>
                </div>
                <ul className="space-y-1">
                  {data.missing_documents.map((doc, idx) => (
                    <li key={idx} className="text-sm text-[#6E6B7B] flex items-center gap-2">
                      <span className="w-1.5 h-1.5 rounded-full bg-[#EA5455]" />
                      {doc}
                    </li>
                  ))}
                </ul>
              </>
            )}

            <div className="mt-2 pt-2 border-t border-[#EBE9F1]">
              <span className="text-xs text-[#6E6B7B]">
                {data.valid_count}/{data.total_required} documents validés
              </span>
            </div>
          </div>
        )}
      </div>
    );
  }

  // Variant: inline (icône + texte sur une ligne)
  if (variant === 'inline') {
    return (
      <div className={`flex items-center gap-2 ${className}`}>
        <div className={`w-3 h-3 rounded-full ${config.bg} ${config.border} border-2`} />
        <span className={`text-sm font-medium ${config.text}`}>
          {data.label}
        </span>
        {data.missing_count > 0 && (
          <span className="text-xs text-[#6E6B7B]">
            ({data.missing_count} manquant{data.missing_count > 1 ? 's' : ''})
          </span>
        )}
      </div>
    );
  }

  // Variant: detailed (carte détaillée)
  if (variant === 'detailed') {
    return (
      <div className={`bg-white rounded-xl border border-[#EBE9F1] p-4 ${className}`}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-semibold text-[#5E5873]">Dossier client</h3>
          <div className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full ${config.bg} ${config.text} font-semibold text-xs uppercase tracking-wider`}>
            <Icon size={14} />
            {data.label}
          </div>
        </div>

        {/* Progress bar */}
        <div className="mb-4">
          <div className="flex justify-between text-sm mb-1">
            <span className="text-[#6E6B7B]">Complétude</span>
            <span className={`font-semibold ${config.text}`}>{data.score}%</span>
          </div>
          <div className="h-2 bg-[#F3F2F7] rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all duration-500 ${
                data.color === 'green' ? 'bg-[#28C76F]' :
                data.color === 'orange' ? 'bg-[#FF9F43]' : 'bg-[#EA5455]'
              }`}
              style={{ width: `${data.score}%` }}
            />
          </div>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-3 gap-3 mb-4">
          <div className="text-center p-2 bg-[#28C76F]/10 rounded-lg">
            <div className="text-lg font-bold text-[#28C76F]">{data.valid_count}</div>
            <div className="text-xs text-[#6E6B7B]">Validés</div>
          </div>
          <div className="text-center p-2 bg-[#FF9F43]/10 rounded-lg">
            <div className="text-lg font-bold text-[#FF9F43]">{data.pending_count}</div>
            <div className="text-xs text-[#6E6B7B]">En attente</div>
          </div>
          <div className="text-center p-2 bg-[#EA5455]/10 rounded-lg">
            <div className="text-lg font-bold text-[#EA5455]">{data.missing_count}</div>
            <div className="text-xs text-[#6E6B7B]">Manquants</div>
          </div>
        </div>

        {/* Missing documents list */}
        {data.missing_documents.length > 0 && (
          <div>
            <h4 className="text-sm font-medium text-[#5E5873] mb-2">
              Documents manquants :
            </h4>
            <ul className="space-y-1">
              {data.missing_documents.map((doc, idx) => (
                <li key={idx} className="text-sm text-[#6E6B7B] flex items-center gap-2">
                  <XCircle size={14} className="text-[#EA5455]" />
                  {doc}
                </li>
              ))}
            </ul>
          </div>
        )}

        {data.color === 'green' && (
          <div className="flex items-center gap-2 text-[#28C76F]">
            <CheckCircle size={18} />
            <span className="text-sm font-medium">Dossier complet</span>
          </div>
        )}
      </div>
    );
  }

  return null;
};

export default ComplianceBadge;
