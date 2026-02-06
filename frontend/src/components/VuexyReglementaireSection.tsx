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
  Link2,
  Tag,
  FileSignature,
} from 'lucide-react';
import api from '../api/apiClient';
import { toast } from 'react-toastify';
import { FileDropZone } from './FileDropZone';
import { SignedDocUploadModal } from './SignedDocUploadModal';
import { SignedDocLinkModal } from './SignedDocLinkModal';

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

interface AvailableSignedDoc {
  id: number;
  file_name: string;
  custom_label: string | null;
  display_label: string;
  tags: string[];
  uploaded_at: string;
}

interface LinkedSignedDoc {
  id: number;
  file_name: string;
  custom_label: string | null;
  display_label: string;
  status: 'pending' | 'validated' | 'rejected';
}

interface LinkedRequirement {
  id: number;
  label: string;
  besoin: string;
  status: 'pending' | 'validated' | 'rejected';
}

interface SignedDocument {
  id: number;
  file_name: string;
  custom_label: string | null;
  display_label: string;
  tags: string[];
  status: string;
  uploaded_at: string;
  expires_at: string | null;
  linked_requirements: LinkedRequirement[];
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
  is_expiring_soon?: boolean;
  days_until_expiration?: number | null;
  document: ComplianceDocument | null;
  linked_signed_doc?: LinkedSignedDoc | null;
  available_signed_docs?: AvailableSignedDoc[];
}

interface CategoryGroup {
  category: string;
  label: string;
  items: ChecklistItem[];
}

interface ComplianceStatus {
  client_id: number;
  tags_from_signed_docs: string[];
  compliance_score: number;
  is_fully_compliant: boolean;
  valid_count: number;
  total_mandatory: number;
  expired_count: number;
  expiring_soon_count: number;
  checklist: ChecklistItem[];
  grouped_by_category: CategoryGroup[];
  signed_documents: SignedDocument[];
  available_tags: Record<string, string>;
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

  // États pour documents signés
  const [showSignedUploadModal, setShowSignedUploadModal] = useState(false);
  const [isUploadingSigned, setIsUploadingSigned] = useState(false);
  const [linkModalItem, setLinkModalItem] = useState<ChecklistItem | null>(null);
  const [isLinking, setIsLinking] = useState(false);

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
    if (!confirm('Etes-vous sur de vouloir supprimer ce document ?')) return;

    try {
      await api.delete(`/clients/${clientId}/compliance/${documentId}`);
      toast.success('Document supprime');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur suppression:', err);
      toast.error('Erreur lors de la suppression');
    }
  };

  // Upload d'un document signe avec tags
  const handleUploadSigned = async (data: {
    file: File;
    tags: string[];
    customLabel: string;
    expiresAt: string;
  }) => {
    try {
      setIsUploadingSigned(true);
      const formData = new FormData();
      formData.append('file', data.file);
      data.tags.forEach((tag, index) => {
        formData.append(`tags[${index}]`, tag);
      });
      if (data.customLabel) {
        formData.append('custom_label', data.customLabel);
      }
      if (data.expiresAt) {
        formData.append('expires_at', data.expiresAt);
      }

      await api.post(`/clients/${clientId}/compliance/upload-signed`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      toast.success('Document signe importe avec succes');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur upload document signe:', err);
      toast.error("Erreur lors de l'import du document signe");
    } finally {
      setIsUploadingSigned(false);
    }
  };

  // Lier un document signe a une exigence
  const handleLinkDocument = async (documentId: number, requirementIds: number[]) => {
    try {
      setIsLinking(true);
      await api.post(`/clients/${clientId}/compliance/${documentId}/link`, {
        requirement_ids: requirementIds,
      });

      toast.success('Document lie a l\'exigence');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur liaison:', err);
      toast.error('Erreur lors de la liaison');
    } finally {
      setIsLinking(false);
    }
  };

  // Valider une liaison document-exigence
  const handleValidateLink = async (documentId: number, requirementId: number) => {
    try {
      await api.post(`/clients/${clientId}/compliance/${documentId}/validate-link/${requirementId}`);
      toast.success('Liaison validee');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur validation liaison:', err);
      toast.error('Erreur lors de la validation');
    }
  };

  // Rejeter une liaison document-exigence
  const handleRejectLink = async (documentId: number, requirementId: number) => {
    try {
      await api.post(`/clients/${clientId}/compliance/${documentId}/reject-link/${requirementId}`);
      toast.success('Liaison rejetee');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur rejet liaison:', err);
      toast.error('Erreur lors du rejet');
    }
  };

  // Retirer une liaison
  const handleUnlinkDocument = async (documentId: number, requirementId: number) => {
    if (!confirm('Retirer la liaison de ce document ?')) return;
    try {
      await api.delete(`/clients/${clientId}/compliance/${documentId}/unlink/${requirementId}`);
      toast.success('Liaison retiree');
      fetchComplianceStatus();
    } catch (err) {
      console.error('Erreur retrait liaison:', err);
      toast.error('Erreur lors du retrait de la liaison');
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

  const { compliance_score, is_fully_compliant, valid_count, total_mandatory, grouped_by_category, tags_from_signed_docs, expired_count, expiring_soon_count, signed_documents, available_tags } = complianceData;

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
                <div className="mt-2 flex flex-wrap gap-2">
                  {is_fully_compliant ? (
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#28C76F]/10 text-[#28C76F] text-sm font-medium">
                      <Award size={16} />
                      Dossier complet
                    </div>
                  ) : (
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-sm font-medium">
                      <AlertTriangle size={16} />
                      Documents manquants
                    </div>
                  )}
                  {expired_count > 0 && (
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#EA5455]/10 text-[#EA5455] text-sm font-medium">
                      <XCircle size={16} />
                      {expired_count} expiré{expired_count > 1 ? 's' : ''}
                    </div>
                  )}
                  {expiring_soon_count > 0 && (
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-sm font-medium">
                      <Clock size={16} />
                      {expiring_soon_count} expire{expiring_soon_count > 1 ? 'nt' : ''} bientôt
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Tags des documents signés importés */}
            {tags_from_signed_docs.length > 0 && (
              <div className="flex flex-col items-start lg:items-end">
                <span className="text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                  Tags des documents signés
                </span>
                <div className="flex flex-wrap gap-2">
                  {tags_from_signed_docs.map((tag) => (
                    <span
                      key={tag}
                      className="px-3 py-1 rounded-full bg-[#7367F0]/10 text-[#7367F0] text-xs font-semibold capitalize"
                    >
                      {available_tags?.[tag] || tag}
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

      {/* Section Documents signes importes */}
      <div className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)] overflow-hidden">
        <div className="p-4 border-b border-[#EBE9F1] flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#00CFE8] to-[#1CE7FF] flex items-center justify-center text-white">
              <FileSignature size={20} />
            </div>
            <div>
              <h3 className="font-semibold text-[#5E5873]">Documents signes importes</h3>
              <p className="text-xs text-[#6E6B7B]">
                {signed_documents?.length || 0} document{(signed_documents?.length || 0) > 1 ? 's' : ''} importe{(signed_documents?.length || 0) > 1 ? 's' : ''}
              </p>
            </div>
          </div>
          <button
            onClick={() => setShowSignedUploadModal(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00CFE8] text-white hover:bg-[#00B8CF] transition-all text-sm font-medium"
          >
            <Upload size={16} />
            Importer un document signe
          </button>
        </div>

        {/* Liste des documents signes */}
        {signed_documents && signed_documents.length > 0 ? (
          <div className="divide-y divide-[#EBE9F1]">
            {signed_documents.map((doc) => (
              <div key={doc.id} className="p-4 hover:bg-[#F8F8F8] transition-colors">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-3 flex-1 min-w-0">
                    <div className="w-10 h-10 rounded-lg bg-[#00CFE8]/10 flex items-center justify-center text-[#00CFE8] flex-shrink-0">
                      <FileText size={20} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-[#5E5873] truncate">{doc.display_label}</p>
                      <p className="text-xs text-[#6E6B7B] mt-0.5">
                        Importe le {new Date(doc.uploaded_at).toLocaleDateString('fr-FR')}
                      </p>
                      {/* Tags */}
                      <div className="flex flex-wrap gap-1.5 mt-2">
                        {doc.tags.map((tag) => (
                          <span
                            key={tag}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-[#7367F0]/10 text-[#7367F0] capitalize"
                          >
                            <Tag size={10} />
                            {available_tags?.[tag] || tag}
                          </span>
                        ))}
                      </div>
                      {/* Liaisons existantes */}
                      {doc.linked_requirements.length > 0 && (
                        <div className="mt-2 space-y-1">
                          {doc.linked_requirements.map((req) => (
                            <div key={req.id} className="flex items-center gap-2 text-xs">
                              <Link2 size={12} className="text-[#6E6B7B]" />
                              <span className="text-[#5E5873]">{req.label}</span>
                              <span className={`px-1.5 py-0.5 rounded font-medium ${
                                req.status === 'validated' ? 'bg-[#28C76F]/10 text-[#28C76F]' :
                                req.status === 'rejected' ? 'bg-[#EA5455]/10 text-[#EA5455]' :
                                'bg-[#FF9F43]/10 text-[#FF9F43]'
                              }`}>
                                {req.status === 'validated' ? 'Valide' : req.status === 'rejected' ? 'Rejete' : 'En attente'}
                              </span>
                              {req.status === 'pending' && (
                                <div className="flex gap-1">
                                  <button
                                    onClick={() => handleValidateLink(doc.id, req.id)}
                                    className="p-1 rounded bg-[#28C76F]/10 text-[#28C76F] hover:bg-[#28C76F] hover:text-white transition-all"
                                    title="Valider"
                                  >
                                    <Check size={12} />
                                  </button>
                                  <button
                                    onClick={() => handleRejectLink(doc.id, req.id)}
                                    className="p-1 rounded bg-[#EA5455]/10 text-[#EA5455] hover:bg-[#EA5455] hover:text-white transition-all"
                                    title="Rejeter"
                                  >
                                    <XCircle size={12} />
                                  </button>
                                </div>
                              )}
                              <button
                                onClick={() => handleUnlinkDocument(doc.id, req.id)}
                                className="p-1 rounded bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all"
                                title="Retirer la liaison"
                              >
                                <X size={12} />
                              </button>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    <button
                      onClick={() => handleDownload(doc.id, doc.file_name)}
                      className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all"
                      title="Telecharger"
                    >
                      <Download size={16} />
                    </button>
                    <button
                      onClick={() => handleDelete(doc.id)}
                      className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all"
                      title="Supprimer"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="p-8 text-center text-[#6E6B7B]">
            <FileSignature size={40} className="mx-auto mb-3 text-[#B9B9C3]" />
            <p>Aucun document signe importe</p>
            <p className="text-xs mt-1">Importez des documents signes et taggez-les par besoin</p>
          </div>
        )}
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
                            {/* Badge d'expiration */}
                            {item.status === 'expired' && (
                              <span className="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-[#EA5455]/10 text-[#EA5455]">
                                <XCircle size={10} />
                                Expiré
                              </span>
                            )}
                            {item.is_expiring_soon && item.days_until_expiration !== null && item.days_until_expiration !== undefined && (
                              <span className="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-[#FF9F43]/10 text-[#FF9F43]">
                                <Clock size={10} />
                                Expire dans {item.days_until_expiration} jour{item.days_until_expiration > 1 ? 's' : ''}
                              </span>
                            )}
                            {item.status === 'rejected' && item.document?.rejection_reason && (
                              <p className="text-xs text-[#EA5455] mt-0.5">
                                Motif : {item.document.rejection_reason}
                              </p>
                            )}
                            {/* Document signe lie */}
                            {item.linked_signed_doc && (
                              <div className="flex items-center gap-2 mt-1 text-xs">
                                <Link2 size={12} className="text-[#00CFE8]" />
                                <span className="text-[#5E5873]">Lie a: {item.linked_signed_doc.display_label}</span>
                                <span className={`px-1.5 py-0.5 rounded font-medium ${
                                  item.linked_signed_doc.status === 'validated' ? 'bg-[#28C76F]/10 text-[#28C76F]' :
                                  item.linked_signed_doc.status === 'rejected' ? 'bg-[#EA5455]/10 text-[#EA5455]' :
                                  'bg-[#FF9F43]/10 text-[#FF9F43]'
                                }`}>
                                  {item.linked_signed_doc.status === 'validated' ? 'Valide' : item.linked_signed_doc.status === 'rejected' ? 'Rejete' : 'En attente'}
                                </span>
                              </div>
                            )}
                            {/* Indicateur documents signes disponibles */}
                            {item.status === 'missing' && item.available_signed_docs && item.available_signed_docs.length > 0 && (
                              <button
                                onClick={() => setLinkModalItem(item)}
                                className="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 rounded-lg text-xs font-medium bg-[#00CFE8]/10 text-[#00CFE8] hover:bg-[#00CFE8] hover:text-white transition-all"
                              >
                                <Link2 size={12} />
                                {item.available_signed_docs.length} document{item.available_signed_docs.length > 1 ? 's' : ''} signe{item.available_signed_docs.length > 1 ? 's' : ''} disponible{item.available_signed_docs.length > 1 ? 's' : ''}
                              </button>
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
              {/* Zone de drop avec drag & drop */}
              <FileDropZone
                onFileSelect={(file) => setSelectedFile(file)}
                accept={['pdf', 'jpg', 'jpeg', 'png']}
                maxSize={10485760}
                label="Glissez un fichier ici ou cliquez pour sélectionner"
                selectedFile={selectedFile}
                onClear={() => setSelectedFile(null)}
              />

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

      {/* Modal upload document signe */}
      <SignedDocUploadModal
        isOpen={showSignedUploadModal}
        onClose={() => setShowSignedUploadModal(false)}
        onUpload={handleUploadSigned}
        availableTags={available_tags || {}}
        isUploading={isUploadingSigned}
      />

      {/* Modal liaison document signe */}
      <SignedDocLinkModal
        isOpen={!!linkModalItem}
        onClose={() => setLinkModalItem(null)}
        requirement={linkModalItem}
        onLink={handleLinkDocument}
        isLinking={isLinking}
      />

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
