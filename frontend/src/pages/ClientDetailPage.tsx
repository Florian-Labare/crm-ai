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
import { Info, ClipboardList, Folder } from "lucide-react";
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
                  <div className="space-y-6">
                    <div className="vx-card">
                      <div className="flex justify-between items-center mb-6">
                        <h2 className="text-2xl font-bold text-[#5E5873]">Documents réglementaires</h2>
                        <button
                          onClick={() => setShowGenerateModal(true)}
                          className="bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg transition-all flex items-center space-x-2 shadow-md hover:shadow-lg font-semibold"
                        >
                          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                          </svg>
                          <span>Générer un document réglementaire</span>
                        </button>
                      </div>

                      {documents.length === 0 ? (
                        <div className="text-center py-12">
                          <svg className="mx-auto h-16 w-16 text-[#B9B9C3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                          <h3 className="mt-4 text-lg font-semibold text-[#5E5873]">Aucun document</h3>
                          <p className="mt-2 text-sm text-[#6E6B7B]">
                            Cliquez sur le bouton ci-dessus pour générer votre premier document réglementaire.
                          </p>
                        </div>
                      ) : (
                        <div className="space-y-3">
                          {documents.map((doc: any) => (
                            <div key={doc.id} className="border border-[#EBE9F1] rounded-lg p-4 hover:shadow-md transition-all bg-white">
                              <div className="flex justify-between items-start">
                                <div className="flex-1">
                                  <h3 className="font-semibold text-[#5E5873]">{doc.document_template?.name}</h3>
                                  <p className="text-sm text-[#6E6B7B] mt-1">{doc.document_template?.description}</p>
                                  <div className="flex items-center space-x-4 mt-2 text-xs text-[#6E6B7B]">
                                    <span>Généré le {new Date(doc.created_at).toLocaleDateString('fr-FR')}</span>
                                    <span>Par {doc.user?.name || 'Utilisateur'}</span>
                                    {doc.sent_by_email && (
                                      <span className="flex items-center space-x-1 text-[#28C76F]">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Envoyé par email</span>
                                      </span>
                                    )}
                                  </div>
                                </div>
                                <div className="flex space-x-2 ml-4">
                                  <button
                                    onClick={() => handleDownloadDocument(doc.id)}
                                    className="bg-gradient-to-br from-[#00CFE8] to-[#00A3C4] hover:from-[#00B8D4] hover:to-[#008FA3] text-white px-3 py-2 rounded-lg transition-all flex items-center space-x-1 text-sm font-semibold shadow-sm hover:shadow-md"
                                    title="Télécharger"
                                  >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                  </button>
                                  {!doc.sent_by_email && (
                                    <button
                                      onClick={() => handleSendDocumentByEmail(doc.id)}
                                      className="bg-gradient-to-br from-[#28C76F] to-[#1F9D57] hover:from-[#24B263] hover:to-[#1A8449] text-white px-3 py-2 rounded-lg transition-all flex items-center space-x-1 text-sm font-semibold shadow-sm hover:shadow-md"
                                      title="Envoyer par email"
                                    >
                                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                      </svg>
                                    </button>
                                  )}
                                  <button
                                    onClick={() => handleDeleteDocument(doc.id)}
                                    className="bg-gradient-to-br from-[#EA5455] to-[#D63031] hover:from-[#DC3545] hover:to-[#C23031] text-white px-3 py-2 rounded-lg transition-all flex items-center space-x-1 text-sm font-semibold shadow-sm hover:shadow-md"
                                    title="Supprimer"
                                  >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                  </button>
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
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
    </>
  );
};

export default ClientDetailPage;
