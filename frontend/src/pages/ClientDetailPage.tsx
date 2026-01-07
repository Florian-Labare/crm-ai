import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { RiskQuestionnaire } from "./RiskQuestionnaire";
import { ConfirmDialog } from "../components/ConfirmDialog";
import { VuexyClientHeader } from "../components/VuexyClientHeader";
import { VuexyClientInfoSection } from "../components/VuexyClientInfoSection";
import { VuexyTabs } from "../components/VuexyTabs";
import { VuexyDocumentsSection } from "../components/VuexyDocumentsSection";
import { ReviewChangesModal } from "../components/ReviewChangesModal";
import { Info, ClipboardList, Folder, AlertTriangle } from "lucide-react";
import { extractData } from "../utils/apiHelpers";
import type { Client } from "../types/api";

interface SanteSouhait {
  id: number;
  contrat_en_place?: string;
  budget_mensuel_maximum?: number;
  niveau_hospitalisation?: number;
  niveau_chambre_particuliere?: number;
  niveau_medecin_generaliste?: number;
  niveau_analyses_imagerie?: number;
  niveau_auxiliaires_medicaux?: number;
  niveau_pharmacie?: number;
  niveau_dentaire?: number;
  niveau_optique?: number;
  niveau_protheses_auditives?: number;
}

// Extension du type Client pour inclure les champs non-BAE
interface ExtendedClient extends Client {
  civilite?: string;
  nom_jeune_fille?: string;
  lieu_naissance?: string;
  situation_matrimoniale?: string;
  date_situation_matrimoniale?: string;
  situation_actuelle?: string;
  date_evenement_professionnel?: string;
  risques_professionnels?: boolean;
  details_risques_professionnels?: string;
  revenus_annuels?: number;
  residence_fiscale?: string;
  fumeur?: boolean;
  activites_sportives?: boolean;
  details_activites_sportives?: string;
  niveau_activites_sportives?: string;
  besoins?: string[] | null;
  transcription_path?: string;
  consentement_audio?: boolean;
  charge_clientele?: string;
  chef_entreprise?: boolean;
  statut?: string;
  travailleur_independant?: boolean;
  mandataire_social?: boolean;
  santeSouhait?: SanteSouhait;
}

interface PendingChangeItem {
  id: number;
  source: string;
  status: string;
  changes_count: number;
  conflicts_count: number;
  created_at: string;
}

const ClientDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [client, setClient] = useState<ExtendedClient | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<"info" | "questionnaires" | "documents">("info");
  const [documents, setDocuments] = useState<any[]>([]);
  const [templates, setTemplates] = useState<any[]>([]);
  const [showGenerateModal, setShowGenerateModal] = useState(false);
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null);
  const [selectedFormat, setSelectedFormat] = useState<'docx' | 'pdf'>('docx');
  const [generatingDocument, setGeneratingDocument] = useState(false);
  const [pendingChanges, setPendingChanges] = useState<PendingChangeItem[]>([]);
  const [selectedPendingChangeId, setSelectedPendingChangeId] = useState<number | null>(null);

  // États pour les dialogues de confirmation
  const [confirmDialog, setConfirmDialog] = useState<{
    isOpen: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
    type?: 'danger' | 'warning' | 'info';
  }>({
    isOpen: false,
    title: '',
    message: '',
    onConfirm: () => { },
  });

  const fetchClient = async () => {
    try {
      const res = await api.get(`/clients/${id}`);
      setClient(extractData<ExtendedClient>(res));
    } catch (err) {
      console.error("Erreur lors du chargement du client :", err);
      toast.error("Erreur lors du chargement du client");
    } finally {
      setLoading(false);
    }
  };

  const fetchPendingChanges = async () => {
    try {
      const res = await api.get(`/clients/${id}/pending-changes`);
      setPendingChanges(res.data.pending_changes || []);
    } catch (err) {
      console.error("Erreur lors du chargement des pending changes :", err);
    }
  };

  const fetchDocuments = async () => {
    try {
      const res = await api.get(`/clients/${id}/documents`);
      setDocuments(res.data.data || []);
    } catch (err) {
      console.error("Erreur lors du chargement des documents :", err);
      toast.error("Erreur lors du chargement des documents");
    }
  };

  const fetchTemplates = async () => {
    try {
      const res = await api.get('/document-templates');
      setTemplates(res.data.data || []);
    } catch (err) {
      console.error("Erreur lors du chargement des templates :", err);
      toast.error("Erreur lors du chargement des templates");
    }
  };

  const handleGenerateDocument = async () => {
    if (!selectedTemplateId) {
      toast.error("Veuillez sélectionner un template");
      return;
    }

    setGeneratingDocument(true);
    try {
      await api.post(`/clients/${id}/documents/generate`, {
        template_id: selectedTemplateId,
        format: selectedFormat,
      });
      toast.success(`Document ${selectedFormat.toUpperCase()} généré avec succès`);
      setShowGenerateModal(false);
      setSelectedTemplateId(null);
      setSelectedFormat('docx');
      fetchDocuments();
    } catch (err: any) {
      console.error("Erreur lors de la génération du document :", err);
      toast.error(err.response?.data?.message || "Erreur lors de la génération du document");
    } finally {
      setGeneratingDocument(false);
    }
  };

  const handleDownloadDocument = async (documentId: number) => {
    try {
      // Trouver le document dans la liste pour obtenir son format
      const doc = documents.find(d => d.id === documentId);
      const fileExtension = doc?.format || 'docx';

      const res = await api.get(`/documents/${documentId}/download`, {
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `document-${documentId}.${fileExtension}`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      toast.success("Document téléchargé");
    } catch (err) {
      console.error("Erreur lors du téléchargement :", err);
      toast.error("Erreur lors du téléchargement du document");
    }
  };

  const handleSendDocumentByEmail = (documentId: number) => {
    setConfirmDialog({
      isOpen: true,
      title: 'Envoyer par email',
      message: 'Voulez-vous envoyer ce document par email au client ?',
      type: 'info',
      onConfirm: async () => {
        try {
          await api.post(`/documents/${documentId}/send-email`);
          toast.success("Document envoyé par email");
          fetchDocuments();
        } catch (err: any) {
          console.error("Erreur lors de l'envoi :", err);
          toast.error(err.response?.data?.message || "Erreur lors de l'envoi du document");
        }
      },
    });
  };

  const handleDeleteDocument = (documentId: number) => {
    setConfirmDialog({
      isOpen: true,
      title: 'Supprimer le document',
      message: 'Êtes-vous sûr de vouloir supprimer ce document ? Cette action est irréversible.',
      type: 'danger',
      onConfirm: async () => {
        try {
          await api.delete(`/documents/${documentId}`);
          toast.success("Document supprimé");
          fetchDocuments();
        } catch (err) {
          console.error("Erreur lors de la suppression :", err);
          toast.error("Erreur lors de la suppression du document");
        }
      },
    });
  };

  useEffect(() => {
    fetchClient();
    fetchPendingChanges();
  }, [id]);

  useEffect(() => {
    if (activeTab === "documents") {
      fetchDocuments();
      fetchTemplates();
    }
  }, [activeTab, id]);

  const handleDelete = () => {
    setConfirmDialog({
      isOpen: true,
      title: 'Supprimer le client',
      message: 'Êtes-vous sûr de vouloir supprimer ce client ? Toutes ses données seront définitivement perdues.',
      type: 'danger',
      onConfirm: async () => {
        try {
          await api.delete(`/clients/${id}`);
          toast.success("Client supprimé avec succès");
          setTimeout(() => navigate("/clients"), 1000);
        } catch (err) {
          console.error(err);
          toast.error("Erreur lors de la suppression du client");
        }
      },
    });
  };

  const handleExportPDF = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${import.meta.env.VITE_API_URL}/clients/${id}/export/pdf`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error('Erreur lors de l\'export PDF');
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `fiche_client_${client?.nom}_${client?.prenom}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success("PDF téléchargé avec succès");
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de l'export PDF");
    }
  };

  const handleExportWord = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${import.meta.env.VITE_API_URL}/clients/${id}/export/word`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error('Erreur lors de l\'export Word');
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `fiche_client_${client?.nom}_${client?.prenom}.docx`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success("Document Word téléchargé avec succès");
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de l'export Word");
    }
  };

  const handleExportQuestionnairePdf = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${import.meta.env.VITE_API_URL}/clients/${id}/questionnaires/export/pdf`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` },
      });

      if (!response.ok) throw new Error('Erreur lors de l\'export du questionnaire');

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `questionnaire_client_${client?.nom}_${client?.prenom}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success("Questionnaire exporté en PDF");
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de l'export questionnaire");
    }
  };

  if (loading) return <div className="text-center mt-10">Chargement...</div>;
  if (!client) return <div className="text-center mt-10">Client introuvable.</div>;

  const formatDate = (date?: string) => {
    if (!date) return "Non renseigné";
    return new Date(date).toLocaleDateString("fr-FR");
  };

  const formatCurrency = (amount?: number) => {
    if (!amount) return "Non renseigné";
    return new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
    }).format(amount);
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-7xl mx-auto space-y-6">
          {/* Vuexy Client Header */}
          <VuexyClientHeader
            client={client}
            onEdit={() => navigate(`/clients/${client.id}/edit`)}
            onExportPDF={handleExportPDF}
            onExportWord={handleExportWord}
            onDelete={handleDelete}
            showEditButton={activeTab === "info"}
            showExportQuestionnaireButton={activeTab === "questionnaires"}
            onExportQuestionnairePDF={handleExportQuestionnairePdf}
          />

          {/* Pending Changes Banner */}
          {pendingChanges.length > 0 && (
            <div className="bg-gradient-to-r from-[#FF9F43]/10 to-[#FF9F43]/5 border border-[#FF9F43]/30 rounded-xl p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-[#FF9F43]/20 rounded-lg">
                    <AlertTriangle className="text-[#FF9F43]" size={24} />
                  </div>
                  <div>
                    <h3 className="font-semibold text-[#5E5873]">
                      {pendingChanges.length} modification(s) en attente de validation
                    </h3>
                    <p className="text-sm text-[#6E6B7B]">
                      Des modifications ont été extraites d'enregistrements audio et nécessitent votre validation.
                    </p>
                  </div>
                </div>
                <div className="flex gap-2">
                  {pendingChanges.map((pc) => (
                    <button
                      key={pc.id}
                      onClick={() => setSelectedPendingChangeId(pc.id)}
                      className="px-4 py-2 bg-[#FF9F43] text-white rounded-lg hover:bg-[#FF9F43]/90 transition-colors font-medium text-sm flex items-center gap-2"
                    >
                      <span>{pc.changes_count} champ(s)</span>
                      {pc.conflicts_count > 0 && (
                        <span className="bg-white/20 px-2 py-0.5 rounded text-xs">
                          {pc.conflicts_count} conflit(s)
                        </span>
                      )}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Vuexy Tabs */}
          <VuexyTabs
            defaultTab={activeTab}
            onTabChange={(tabId) => setActiveTab(tabId as "info" | "questionnaires" | "documents")}
            tabs={[
              {
                id: "info",
                label: "Informations client",
                icon: <Info size={18} />,
                content: (
                  <VuexyClientInfoSection
                    client={client}
                    formatDate={formatDate}
                    formatCurrency={formatCurrency}
                  />
                ),
              },
              {
                id: "questionnaires",
                label: "Questionnaires",
                icon: <ClipboardList size={18} />,
                content: (
                  <div className="bg-white rounded-lg p-6">
                    <RiskQuestionnaire clientIdProp={id!} embedded />
                  </div>
                ),
              },
              {
                id: "documents",
                label: "Documents",
                icon: <Folder size={18} />,
                content: (
                  <VuexyDocumentsSection
                    documents={documents}
                    onGenerateClick={() => setShowGenerateModal(true)}
                    onDownload={handleDownloadDocument}
                    onSendEmail={handleSendDocumentByEmail}
                    onDelete={handleDeleteDocument}
                  />
                ),
              },
            ]}
          />
        </div>
      </div>

      {/* Modal pour générer un document */}
      {showGenerateModal && (
        <div className="fixed inset-0 flex items-center justify-center z-50">
          {/* Backdrop avec blur */}
          <div
            className="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-all duration-300"
            onClick={() => {
              setShowGenerateModal(false);
              setSelectedTemplateId(null);
              setSelectedFormat('docx');
            }}
          />

          {/* Modale */}
          <div className="relative bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto transform transition-all duration-300 animate-slideIn">
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-bold text-gray-800">Sélectionner un template</h2>
                <button
                  onClick={() => {
                    setShowGenerateModal(false);
                    setSelectedTemplateId(null);
                    setSelectedFormat('docx');
                  }}
                  className="text-gray-400 hover:text-gray-600 transition-colors"
                >
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div className="space-y-3">
                {templates.map((template: any) => (
                  <div
                    key={template.id}
                    onClick={() => setSelectedTemplateId(template.id)}
                    className={`border-2 rounded-lg p-4 cursor-pointer transition-all ${selectedTemplateId === template.id
                      ? 'border-indigo-600 bg-indigo-50'
                      : 'border-gray-200 hover:border-indigo-300 hover:bg-gray-50'
                      }`}
                  >
                    <div className="flex items-start">
                      <div className="flex-shrink-0 mt-1">
                        <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${selectedTemplateId === template.id
                          ? 'border-indigo-600 bg-indigo-600'
                          : 'border-gray-300'
                          }`}>
                          {selectedTemplateId === template.id && (
                            <svg className="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 12 12">
                              <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />
                            </svg>
                          )}
                        </div>
                      </div>
                      <div className="ml-3 flex-1">
                        <h3 className="font-semibold text-gray-900">{template.name}</h3>
                        {template.description && (
                          <p className="text-sm text-gray-600 mt-1">{template.description}</p>
                        )}
                        <span className="inline-block mt-2 px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded">
                          {template.category}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Sélecteur de format */}
              <div className="mt-6 border-t border-gray-200 pt-4">
                <label className="block text-sm font-semibold text-gray-700 mb-3">Format du document</label>
                <div className="flex gap-3">
                  <button
                    onClick={() => setSelectedFormat('docx')}
                    className={`flex-1 px-4 py-3 rounded-lg border-2 transition-all ${
                      selectedFormat === 'docx'
                        ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                        : 'border-gray-200 hover:border-gray-300 text-gray-700'
                    }`}
                  >
                    <div className="flex items-center justify-center space-x-2">
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <span className="font-medium">DOCX</span>
                    </div>
                    <p className="text-xs mt-1">Modifiable avec Word</p>
                  </button>
                  <button
                    onClick={() => setSelectedFormat('pdf')}
                    className={`flex-1 px-4 py-3 rounded-lg border-2 transition-all ${
                      selectedFormat === 'pdf'
                        ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                        : 'border-gray-200 hover:border-gray-300 text-gray-700'
                    }`}
                  >
                    <div className="flex items-center justify-center space-x-2">
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                      </svg>
                      <span className="font-medium">PDF</span>
                    </div>
                    <p className="text-xs mt-1">Prêt à imprimer</p>
                  </button>
                </div>
              </div>

              <div className="mt-6 flex justify-end space-x-3">
                <button
                  onClick={() => {
                    setShowGenerateModal(false);
                    setSelectedTemplateId(null);
                    setSelectedFormat('docx');
                  }}
                  className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                  Annuler
                </button>
                <button
                  onClick={handleGenerateDocument}
                  disabled={!selectedTemplateId || generatingDocument}
                  className={`px-6 py-2 rounded-lg text-white transition-all ${!selectedTemplateId || generatingDocument
                    ? 'bg-gray-400 cursor-not-allowed'
                    : 'bg-indigo-600 hover:bg-indigo-700'
                    }`}
                >
                  {generatingDocument ? 'Génération...' : 'Générer le document'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Dialogue de confirmation */}
      <ConfirmDialog
        isOpen={confirmDialog.isOpen}
        onClose={() => setConfirmDialog({ ...confirmDialog, isOpen: false })}
        onConfirm={confirmDialog.onConfirm}
        title={confirmDialog.title}
        message={confirmDialog.message}
        type={confirmDialog.type}
      />

      {/* Review Changes Modal */}
      {selectedPendingChangeId && (
        <ReviewChangesModal
          pendingChangeId={selectedPendingChangeId}
          onClose={() => setSelectedPendingChangeId(null)}
          onApplied={() => {
            setSelectedPendingChangeId(null);
            fetchClient(); // Refresh client data
            fetchPendingChanges(); // Refresh pending changes list
            toast.success("Modifications appliquées avec succès");
          }}
        />
      )}
    </>
  );
};

export default ClientDetailPage;
