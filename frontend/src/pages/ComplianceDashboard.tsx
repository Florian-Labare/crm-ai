import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { usePage } from '../contexts/PageContext';
import {
  Shield,
  Users,
  CheckCircle,
  AlertTriangle,
  XCircle,
  Clock,
  RefreshCw,
  ChevronRight,
  Filter,
} from 'lucide-react';
import api from '../api/apiClient';
import { toast } from 'react-toastify';
import { ComplianceStatusCard } from '../components/ComplianceStatusCard';

interface DashboardSummary {
  total_clients: number;
  fully_compliant: number;
  partially_compliant: number;
  non_compliant: number;
  with_expired_docs: number;
  with_expiring_soon: number;
}

interface Alert {
  client_id: number;
  client_name: string;
  document_type: string;
  document_label: string;
  issue: 'expired' | 'expiring_soon';
  severity: 'high' | 'medium' | 'low';
  expires_at: string;
  days_overdue?: number;
  days_until_expiration?: number;
}

interface DashboardData {
  summary: DashboardSummary;
  alerts: Alert[];
}

type FilterType = 'all' | 'fully_compliant' | 'partially_compliant' | 'non_compliant' | 'expired' | 'expiring_soon';

export const ComplianceDashboard: React.FC = () => {
  const navigate = useNavigate();
  const { setPage } = usePage();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<DashboardData | null>(null);
  const [activeFilter, setActiveFilter] = useState<FilterType>('all');

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.get('/compliance/dashboard');
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (err) {
      console.error('Erreur chargement dashboard:', err);
      toast.error('Erreur lors du chargement du dashboard');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    setPage('Dashboard conformité', [], [
      { label: 'Conformité' },
      { label: 'Dashboard' },
    ]);
  }, [setPage]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  const handleClientClick = (clientId: number) => {
    navigate(`/clients/${clientId}`);
  };

  const getFilteredAlerts = (): Alert[] => {
    if (!data) return [];

    switch (activeFilter) {
      case 'expired':
        return data.alerts.filter(a => a.issue === 'expired');
      case 'expiring_soon':
        return data.alerts.filter(a => a.issue === 'expiring_soon');
      default:
        return data.alerts;
    }
  };

  const getSeverityConfig = (severity: string) => {
    switch (severity) {
      case 'high':
        return {
          bg: 'bg-[#EA5455]/10',
          text: 'text-[#EA5455]',
          border: 'border-l-[#EA5455]',
          icon: XCircle,
          label: 'Urgent',
        };
      case 'medium':
        return {
          bg: 'bg-[#FF9F43]/10',
          text: 'text-[#FF9F43]',
          border: 'border-l-[#FF9F43]',
          icon: AlertTriangle,
          label: 'Attention',
        };
      default:
        return {
          bg: 'bg-[#00CFE8]/10',
          text: 'text-[#00CFE8]',
          border: 'border-l-[#00CFE8]',
          icon: Clock,
          label: 'Info',
        };
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center">
        <RefreshCw className="w-8 h-8 text-[#7367F0] animate-spin" />
      </div>
    );
  }

  if (!data) {
    return (
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center">
        <div className="text-center">
          <Shield className="w-16 h-16 text-[#B9B9C3] mx-auto mb-4" />
          <p className="text-[#6E6B7B]">Impossible de charger le dashboard</p>
        </div>
      </div>
    );
  }

  const { summary } = data;
  const filteredAlerts = getFilteredAlerts();

  return (
    <div>
      <div className="w-full max-w-7xl mx-auto px-4 lg:px-6 py-6">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white shadow-lg shadow-purple-500/30">
              <Shield size={24} />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-[#5E5873]">Dashboard Conformité</h1>
              <p className="text-sm text-[#6E6B7B]">Vue d'ensemble de la conformité réglementaire</p>
            </div>
          </div>
        </div>

        {/* Cards statistiques */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
          <ComplianceStatusCard
            title="Total clients"
            value={summary.total_clients}
            icon={Users}
            color="purple"
            onClick={() => setActiveFilter('all')}
            isActive={activeFilter === 'all'}
          />
          <ComplianceStatusCard
            title="Conformes"
            value={summary.fully_compliant}
            icon={CheckCircle}
            color="green"
            subtitle={summary.total_clients > 0 ? `${Math.round((summary.fully_compliant / summary.total_clients) * 100)}%` : '0%'}
            onClick={() => setActiveFilter('fully_compliant')}
            isActive={activeFilter === 'fully_compliant'}
          />
          <ComplianceStatusCard
            title="Partiellement"
            value={summary.partially_compliant}
            icon={AlertTriangle}
            color="orange"
            subtitle="Documents manquants"
            onClick={() => setActiveFilter('partially_compliant')}
            isActive={activeFilter === 'partially_compliant'}
          />
          <ComplianceStatusCard
            title="Non conformes"
            value={summary.non_compliant}
            icon={XCircle}
            color="red"
            subtitle="Aucun document"
            onClick={() => setActiveFilter('non_compliant')}
            isActive={activeFilter === 'non_compliant'}
          />
          <ComplianceStatusCard
            title="Docs expirés"
            value={summary.with_expired_docs}
            icon={XCircle}
            color="red"
            subtitle="Action requise"
            onClick={() => setActiveFilter('expired')}
            isActive={activeFilter === 'expired'}
          />
          <ComplianceStatusCard
            title="Expire bientôt"
            value={summary.with_expiring_soon}
            icon={Clock}
            color="orange"
            subtitle="< 90 jours"
            onClick={() => setActiveFilter('expiring_soon')}
            isActive={activeFilter === 'expiring_soon'}
          />
        </div>

        {/* Liste des alertes */}
        <div className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)]">
          <div className="p-6 border-b border-[#EBE9F1]">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <AlertTriangle className="text-[#FF9F43]" size={20} />
                <h2 className="text-lg font-semibold text-[#5E5873]">
                  Alertes prioritaires
                </h2>
                <span className="px-2.5 py-0.5 rounded-full bg-[#EA5455]/10 text-[#EA5455] text-xs font-semibold">
                  {filteredAlerts.length}
                </span>
              </div>

              {/* Filtres */}
              <div className="flex items-center gap-2">
                <Filter size={16} className="text-[#6E6B7B]" />
                <select
                  value={activeFilter}
                  onChange={(e) => setActiveFilter(e.target.value as FilterType)}
                  className="text-sm border border-[#EBE9F1] rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-[#7367F0]/20 focus:border-[#7367F0] outline-none"
                >
                  <option value="all">Toutes les alertes</option>
                  <option value="expired">Documents expirés</option>
                  <option value="expiring_soon">Expire bientôt</option>
                </select>
              </div>
            </div>
          </div>

          {/* Liste */}
          <div className="divide-y divide-[#EBE9F1]">
            {filteredAlerts.length === 0 ? (
              <div className="p-12 text-center">
                <CheckCircle className="w-16 h-16 text-[#28C76F] mx-auto mb-4" />
                <p className="text-lg font-medium text-[#5E5873] mb-2">Aucune alerte</p>
                <p className="text-sm text-[#6E6B7B]">
                  Tous les documents sont en règle pour cette catégorie
                </p>
              </div>
            ) : (
              filteredAlerts.map((alert, index) => {
                const config = getSeverityConfig(alert.severity);
                const SeverityIcon = config.icon;

                return (
                  <div
                    key={`${alert.client_id}-${alert.document_type}-${index}`}
                    className={`p-4 flex items-center justify-between hover:bg-[#F8F8F8] transition-colors cursor-pointer border-l-4 ${config.border}`}
                    onClick={() => handleClientClick(alert.client_id)}
                  >
                    <div className="flex items-center gap-4">
                      <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${config.bg}`}>
                        <SeverityIcon size={20} className={config.text} />
                      </div>
                      <div>
                        <div className="flex items-center gap-2 mb-0.5">
                          <span className="font-semibold text-[#5E5873]">
                            {alert.client_name}
                          </span>
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${config.bg} ${config.text}`}>
                            {config.label}
                          </span>
                        </div>
                        <p className="text-sm text-[#6E6B7B]">
                          {alert.document_label}
                          {alert.issue === 'expired' ? (
                            <span className="text-[#EA5455] ml-2">
                              - Expiré depuis {alert.days_overdue} jour{(alert.days_overdue ?? 0) > 1 ? 's' : ''}
                            </span>
                          ) : (
                            <span className="text-[#FF9F43] ml-2">
                              - Expire dans {alert.days_until_expiration} jour{(alert.days_until_expiration ?? 0) > 1 ? 's' : ''}
                            </span>
                          )}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-4">
                      <span className="text-sm text-[#6E6B7B]">
                        {new Date(alert.expires_at).toLocaleDateString('fr-FR')}
                      </span>
                      <ChevronRight size={20} className="text-[#B9B9C3]" />
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* Info bas de page */}
        <div className="mt-6 text-center text-sm text-[#6E6B7B]">
          <p>
            Les alertes sont triées par urgence. Cliquez sur une ligne pour accéder à la fiche client.
          </p>
        </div>
      </div>
    </div>
  );
};

export default ComplianceDashboard;
