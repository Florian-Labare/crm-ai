import React, { useState, useEffect, useCallback } from 'react';
import {
  Shield,
  CheckCircle2,
  XCircle,
  Clock,
  AlertTriangle,
  Upload,
  Download,
  Trash2,
  Eye,
  ChevronDown,
  ChevronRight,
  FileText,
  CreditCard,
  Receipt,
  Award,
  RefreshCw,
  X,
  Calendar,
  Check,
} from 'lucide-react';
import api from '../api/apiClient';
import { toast } from 'react-toastify';

interface ComplianceDocument {
  id: number;
  file_name: string;
  status: 'pending' | 'validated' | 'rejected' | 'expired';
  validated_at: string | null;
  expires_at: string | null;
  uploaded_at: string;
  notes: string | null;
  rejection_reason: string | null;
}

interface ChecklistItem {
  requirement_id: number;
  document_type: string;
  label: string;
  category: string;
  besoin: string;
  besoin_label: string;
  is_mandatory: boolean;
  status: 'missing' | 'pending' | 'valid' | 'rejected' | 'expired';
  is_valid: boolean;
  document: ComplianceDocument | null;
}

interface CategoryGroup {
  category: string;
  label: string;
  items: ChecklistItem[];
}

interface ComplianceStatus {
  client_id: number;
  besoins: string[];
  compliance_score: number;
  is_fully_compliant: boolean;
  valid_count: number;
  total_mandatory: number;
  checklist: ChecklistItem[];
  grouped_by_category: CategoryGroup[];
}

interface Props {
  clientId: number;
  clientBesoins?: string[];
}

const statusConfig = {
  valid: {
    icon: CheckCircle2,
    color: 'text-[#28C76F]',
    bg: 'bg-[#28C76F]/10',
    border: 'border-[#28C76F]',
    label: 'Validé',
  },
  pending: {
    icon: Clock,
    color: 'text-[#FF9F43]',
    bg: 'bg-[#FF9F43]/10',
    border: 'border-[#FF9F43]',
    label: 'En attente',
  },
  rejected: {
    icon: XCircle,
    color: 'text-[#EA5455]',
    bg: 'bg-[#EA5455]/10',
    border: 'border-[#EA5455]',
    label: 'Rejeté',
  },
  expired: {
    icon: AlertTriangle,
    color: 'text-[#FF9F43]',
    bg: 'bg-[#FF9F43]/10',
    border: 'border-[#FF9F43]',
    label: 'Expiré',
  },
  missing: {
    icon: FileText,
    color: 'text-[#B9B9C3]',
    bg: 'bg-[#F3F2F7]',
    border: 'border-[#EBE9F1]',
    label: 'Manquant',
  },
};

const categoryIcons: Record<string, typeof Shield> = {
  identity: CreditCard,
  fiscal: Receipt,
  regulatory: FileText,
};

export const VuexyReglementaireSection: React.FC<Props> = ({ clientId }) => {
  const [loading, setLoading] = useState(true);
  const [complianceData, setComplianceData] = useState<ComplianceStatus | null>(null);
  const [expandedCategories, setExpandedCategories] = useState<Record<string, boolean>>({
    identity: true,
    fiscal: true,
    regulatory: true,
  });
  const [uploadingFor, setUploadingFor] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [expiresAt, setExpiresAt] = useState<string>('');
  const [uploadModalItem, setUploadModalItem] = useState<ChecklistItem | null>(null);

  const fetchComplianceStatus = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get(`/clients/${clientId}/compliance/status`);
      setComplianceData(res.data.data);
    } catch (err) {
      console.error('Erreur chargement compliance:', err);
      toast.error('Erreur lors du chargement du statut de conformité');
    } finally {
      setLoading(false);
    }
  }, [clientId]);

  useEffect(() => {
    fetchComplianceStatus();
  }, [fetchComplianceStatus]);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setSelectedFile(e.target.files[0]);
    }
  };

  const handleUpload = async () => {
    if (!selectedFile || !uploadModalItem) return;

    try {
      setUploadingFor(uploadModalItem.document_type);
      const formData = new FormData();
      formData.append('file', selectedFile);
      formData.append('document_type', uploadModalItem.document_type);
      if (expiresAt) {
        formData.append('expires_at', expiresAt);
      }

      await api.post(`/clients/${clientId}/compliance/upload`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      toast.success('Document uploadé avec succès');
      setUploadModalItem(null);
      setSelectedFile(null);
      setExpiresAt('');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur upload:', err);
      toast.error("Erreur lors de l'upload du document");
    } finally {
      setUploadingFor(null);
    }
  };

  const handleValidate = async (documentId: number) => {
    try {
      await api.post(`/clients/${clientId}/compliance/${documentId}/validate`);
      toast.success('Document validé');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur validation:', err);
      toast.error('Erreur lors de la validation');
    }
  };

  const handleReject = async (documentId: number) => {
    const reason = prompt('Motif du rejet :');
    if (!reason) return;

    try {
      await api.post(`/clients/${clientId}/compliance/${documentId}/reject`, { reason });
      toast.success('Document rejeté');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur rejet:', err);
      toast.error('Erreur lors du rejet');
    }
  };

  const handleDownload = async (documentId: number, fileName: string) => {
    try {
      const res = await api.get(`/clients/${clientId}/compliance/${documentId}/download`, {
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', fileName);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err) {
      console.error('Erreur téléchargement:', err);
      toast.error('Erreur lors du téléchargement');
    }
  };

  const handleDelete = async (documentId: number) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) return;

    try {
      await api.delete(`/clients/${clientId}/compliance/${documentId}`);
      toast.success('Document supprimé');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur suppression:', err);
      toast.error('Erreur lors de la suppression');
    }
  };

  const toggleCategory = (category: string) => {
    setExpandedCategories(prev => ({ ...prev, [category]: !prev[category] }));
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <RefreshCw className="w-8 h-8 text-[#7367F0] animate-spin" />
      </div>
    );
  }

  if (!complianceData) {
    return (
      <div className="text-center py-12 text-[#6E6B7B]">
        Impossible de charger les données de conformité
      </div>
    );
  }

  const { compliance_score, is_fully_compliant, valid_count, total_mandatory, grouped_by_category, besoins } = complianceData;

  // Couleur du score
  const getScoreColor = (score: number) => {
    if (score >= 100) return 'text-[#28C76F]';
    if (score >= 70) return 'text-[#FF9F43]';
    return 'text-[#EA5455]';
  };

  const getScoreGradient = (score: number) => {
    if (score >= 100) return 'from-[#28C76F] to-[#48DA89]';
    if (score >= 70) return 'from-[#FF9F43] to-[#FFB976]';
    return 'from-[#EA5455] to-[#EF6E6F]';
  };

  return (
    <div className="space-y-6">
      {/* Header avec score de conformité */}
      <div className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)] overflow-hidden">
        <div className="p-6 border-b border-[#EBE9F1]">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            {/* Score circulaire */}
            <div className="flex items-center gap-6">
              <div className="relative">
                <svg className="w-24 h-24 transform -rotate-90">
                  <circle
                    cx="48"
                    cy="48"
                    r="40"
                    fill="none"
                    stroke="#EBE9F1"
                    strokeWidth="8"
                  />
                  <circle
                    cx="48"
                    cy="48"
                    r="40"
                    fill="none"
                    stroke={is_fully_compliant ? '#28C76F' : compliance_score >= 70 ? '#FF9F43' : '#EA5455'}
                    strokeWidth="8"
                    strokeLinecap="round"
                    strokeDasharray={`${(compliance_score / 100) * 251} 251`}
                    className="transition-all duration-1000"
                  />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                  <span className={`text-2xl font-bold ${getScoreColor(compliance_score)}`}>
                    {compliance_score}%
                  </span>
                </div>
              </div>

              <div>
                <h2 className="text-xl font-semibold text-[#5E5873] mb-1">
                  Conformité réglementaire
                </h2>
                <p className="text-sm text-[#6E6B7B]">
                  {valid_count}/{total_mandatory} documents obligatoires validés
                </p>
                {is_fully_compliant ? (
                  <div className="mt-2 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#28C76F]/10 text-[#28C76F] text-sm font-medium">
                    <Award size={16} />
                    Dossier complet
                  </div>
                ) : (
                  <div className="mt-2 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-sm font-medium">
                    <AlertTriangle size={16} />
                    Documents manquants
                  </div>
                )}
              </div>
            </div>

            {/* Besoins du client */}
            {besoins.length > 0 && (
              <div className="flex flex-col items-start lg:items-end">
                <span className="text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                  Besoins exprimés
                </span>
                <div className="flex flex-wrap gap-2">
                  {besoins.map((besoin) => (
                    <span
                      key={besoin}
                      className="px-3 py-1 rounded-full bg-[#7367F0]/10 text-[#7367F0] text-xs font-semibold capitalize"
                    >
                      {besoin}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Barre de progression */}
        <div className="px-6 py-3 bg-[#F8F8F8]">
          <div className="flex items-center gap-4">
            <div className="flex-1 h-2 bg-[#EBE9F1] rounded-full overflow-hidden">
              <div
                className={`h-full bg-gradient-to-r ${getScoreGradient(compliance_score)} transition-all duration-1000`}
                style={{ width: `${compliance_score}%` }}
              />
            </div>
            <span className="text-sm font-medium text-[#5E5873] whitespace-nowrap">
              {valid_count} / {total_mandatory}
            </span>
          </div>
        </div>
      </div>

      {/* Checklist par catégorie */}
      <div className="space-y-4">
        {grouped_by_category.map((group) => {
          const CategoryIcon = categoryIcons[group.category] || FileText;
          const isExpanded = expandedCategories[group.category];
          const validInGroup = group.items.filter(item => item.is_valid).length;
          const totalInGroup = group.items.filter(item => item.is_mandatory).length;

          return (
            <div
              key={group.category}
              className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)] overflow-hidden"
            >
              {/* Header de catégorie */}
              <div
                className="p-4 flex items-center justify-between cursor-pointer hover:bg-[#F8F8F8] transition-colors"
                onClick={() => toggleCategory(group.category)}
              >
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white">
                    <CategoryIcon size={20} />
                  </div>
                  <div>
                    <h3 className="font-semibold text-[#5E5873]">{group.label}</h3>
                    <p className="text-xs text-[#6E6B7B]">
                      {validInGroup}/{totalInGroup} validé{validInGroup > 1 ? 's' : ''}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  {/* Mini barre de progression */}
                  <div className="hidden sm:flex items-center gap-2">
                    <div className="w-24 h-1.5 bg-[#EBE9F1] rounded-full overflow-hidden">
                      <div
                        className="h-full bg-[#28C76F] transition-all duration-500"
                        style={{ width: totalInGroup > 0 ? `${(validInGroup / totalInGroup) * 100}%` : '0%' }}
                      />
                    </div>
                  </div>
                  {isExpanded ? <ChevronDown size={20} className="text-[#6E6B7B]" /> : <ChevronRight size={20} className="text-[#6E6B7B]" />}
                </div>
              </div>

              {/* Liste des documents */}
              {isExpanded && (
                <div className="border-t border-[#EBE9F1]">
                  {group.items.map((item, index) => {
                    const config = statusConfig[item.status];
                    const StatusIcon = config.icon;

                    return (
                      <div
                        key={item.requirement_id}
                        className={`p-4 flex items-center justify-between gap-4 ${
                          index !== group.items.length - 1 ? 'border-b border-[#EBE9F1]' : ''
                        } hover:bg-[#F8F8F8] transition-colors group`}
                      >
                        {/* Info document */}
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                          <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${config.bg}`}>
                            <StatusIcon size={18} className={config.color} />
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                              <span className="font-medium text-[#5E5873] truncate">
                                {item.label}
                              </span>
                              {item.is_mandatory && (
                                <span className="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-[#EA5455]/10 text-[#EA5455]">
                                  Requis
                                </span>
                              )}
                            </div>
                            {item.document && (
                              <p className="text-xs text-[#6E6B7B] truncate mt-0.5">
                                {item.document.file_name}
                                {item.document.expires_at && (
                                  <span className="ml-2">
                                    • Expire le {new Date(item.document.expires_at).toLocaleDateString('fr-FR')}
                                  </span>
                                )}
                              </p>
                            )}
                            {item.status === 'rejected' && item.document?.rejection_reason && (
                              <p className="text-xs text-[#EA5455] mt-0.5">
                                Motif : {item.document.rejection_reason}
                              </p>
                            )}
                          </div>
                        </div>

                        {/* Badge de statut */}
                        <div className={`hidden sm:flex px-3 py-1 rounded-full text-xs font-semibold ${config.bg} ${config.color}`}>
                          {config.label}
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          {item.document ? (
                            <>
                              <button
                                onClick={() => handleDownload(item.document!.id, item.document!.file_name)}
                                className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all"
                                title="Télécharger"
                              >
                                <Download size={16} />
                              </button>
                              {item.document.status === 'pending' && (
                                <>
                                  <button
                                    onClick={() => handleValidate(item.document!.id)}
                                    className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#28C76F]/10 text-[#28C76F] hover:bg-[#28C76F] hover:text-white transition-all"
                                    title="Valider"
                                  >
                                    <Check size={16} />
                                  </button>
                                  <button
                                    onClick={() => handleReject(item.document!.id)}
                                    className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#EA5455]/10 text-[#EA5455] hover:bg-[#EA5455] hover:text-white transition-all"
                                    title="Rejeter"
                                  >
                                    <XCircle size={16} />
                                  </button>
                                </>
                              )}
                              <button
                                onClick={() => handleDelete(item.document!.id)}
                                className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all"
                                title="Supprimer"
                              >
                                <Trash2 size={16} />
                              </button>
                            </>
                          ) : (
                            <button
                              onClick={() => setUploadModalItem(item)}
                              className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#7367F0]/10 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-all text-sm font-medium"
                            >
                              <Upload size={14} />
                              Importer
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Modal d'upload */}
      {uploadModalItem && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4"
          style={{ backgroundColor: 'rgba(94, 88, 115, 0.4)', backdropFilter: 'blur(4px)' }}
          onClick={() => {
            setUploadModalItem(null);
            setSelectedFile(null);
            setExpiresAt('');
          }}
        >
          <div
            className="bg-white rounded-xl shadow-2xl w-full max-w-md animate-modalSlideIn"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="p-6 border-b border-[#EBE9F1] flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-lg bg-[#7367F0]/10 flex items-center justify-center text-[#7367F0]">
                  <Upload size={20} />
                </div>
                <div>
                  <h3 className="font-semibold text-[#5E5873]">Importer un document</h3>
                  <p className="text-sm text-[#6E6B7B]">{uploadModalItem.label}</p>
                </div>
              </div>
              <button
                onClick={() => {
                  setUploadModalItem(null);
                  setSelectedFile(null);
                  setExpiresAt('');
                }}
                className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all"
              >
                <X size={18} />
              </button>
            </div>

            <div className="p-6 space-y-4">
              {/* Zone de drop */}
              <div className="border-2 border-dashed border-[#EBE9F1] rounded-xl p-6 text-center hover:border-[#7367F0] transition-colors">
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  onChange={handleFileSelect}
                  className="hidden"
                  id="file-upload"
                />
                <label htmlFor="file-upload" className="cursor-pointer">
                  {selectedFile ? (
                    <div className="flex items-center justify-center gap-3">
                      <FileText size={24} className="text-[#7367F0]" />
                      <span className="text-[#5E5873] font-medium">{selectedFile.name}</span>
                    </div>
                  ) : (
                    <>
                      <Upload size={32} className="mx-auto text-[#B9B9C3] mb-2" />
                      <p className="text-[#6E6B7B]">
                        Cliquez pour sélectionner un fichier
                      </p>
                      <p className="text-xs text-[#B9B9C3] mt-1">
                        PDF, JPG ou PNG (max 10 Mo)
                      </p>
                    </>
                  )}
                </label>
              </div>

              {/* Date d'expiration (pour CNI, etc.) */}
              {(uploadModalItem.document_type === 'cni' || uploadModalItem.document_type === 'passeport') && (
                <div>
                  <label className="block text-sm font-medium text-[#5E5873] mb-1">
                    <Calendar size={14} className="inline mr-1" />
                    Date d'expiration
                  </label>
                  <input
                    type="date"
                    value={expiresAt}
                    onChange={(e) => setExpiresAt(e.target.value)}
                    className="w-full px-4 py-2 border border-[#EBE9F1] rounded-lg focus:ring-2 focus:ring-[#7367F0]/20 focus:border-[#7367F0] outline-none transition-all"
                  />
                </div>
              )}
            </div>

            <div className="p-6 border-t border-[#EBE9F1] flex justify-end gap-3">
              <button
                onClick={() => {
                  setUploadModalItem(null);
                  setSelectedFile(null);
                  setExpiresAt('');
                }}
                className="px-4 py-2 rounded-lg bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EBE9F1] transition-colors font-medium"
              >
                Annuler
              </button>
              <button
                onClick={handleUpload}
                disabled={!selectedFile || uploadingFor === uploadModalItem.document_type}
                className="px-4 py-2 rounded-lg bg-[#7367F0] text-white hover:bg-[#6558E8] transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                {uploadingFor === uploadModalItem.document_type ? (
                  <>
                    <RefreshCw size={16} className="animate-spin" />
                    Upload en cours...
                  </>
                ) : (
                  <>
                    <Upload size={16} />
                    Importer
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      <style>{`
        @keyframes modalSlideIn {
          from {
            opacity: 0;
            transform: translateY(-20px) scale(0.98);
          }
          to {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }
        .animate-modalSlideIn {
          animation: modalSlideIn 0.3s ease-out;
        }
      `}</style>
    </div>
  );
};

export default VuexyReglementaireSection;
