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
import { TemplateSelectModal } from "../components/TemplateSelectModal";
import { DocumentFormModal } from "../components/DocumentFormModal";
import { SectionEditModal, type SectionType } from "../components/SectionEditModal";
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

interface DocumentFormField {
  variable: string;
  column: string;
  label: string;
  value: string | number | null;
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
  const [generatingDocument, setGeneratingDocument] = useState(false);
  const [showDocumentFormModal, setShowDocumentFormModal] = useState(false);
  const [formTemplateId, setFormTemplateId] = useState<number | null>(null);
  const [formFields, setFormFields] = useState<DocumentFormField[]>([]);
  const [formValues, setFormValues] = useState<Record<string, string>>({});
  const [formLoading, setFormLoading] = useState(false);
  const [formSaving, setFormSaving] = useState(false);
  const [formFormat, setFormFormat] = useState<'docx' | 'pdf'>('docx');
  const [formError, setFormError] = useState<string | null>(null);
  const [pendingChanges, setPendingChanges] = useState<PendingChangeItem[]>([]);
  const [selectedPendingChangeId, setSelectedPendingChangeId] = useState<number | null>(null);

  // État pour le modal d'édition par section
  const [editModal, setEditModal] = useState<{
    isOpen: boolean;
    sectionType: SectionType | null;
    data: any;
    isNew: boolean;
  }>({
    isOpen: false,
    sectionType: null,
    data: null,
    isNew: false,
  });

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

  useEffect(() => {
    if (showDocumentFormModal && formTemplateId) {
      loadDocumentForm(formTemplateId);
    }
  }, [showDocumentFormModal, formTemplateId]);

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

  const generateDocument = async (templateId: number, format: 'docx' | 'pdf') => {
    setGeneratingDocument(true);
    try {
      await api.post(`/clients/${id}/documents/generate`, {
        template_id: templateId,
        format,
      });
      toast.success(`Document ${format.toUpperCase()} généré avec succès`);
      fetchDocuments();
    } catch (err: any) {
      console.error("Erreur lors de la génération du document :", err);
      toast.error(err.response?.data?.message || "Erreur lors de la génération du document");
    } finally {
      setGeneratingDocument(false);
    }
  };

  const loadDocumentForm = async (templateId: number) => {
    try {
      setFormLoading(true);
      setFormError(null);
      const res = await api.get(`/clients/${id}/document-templates/${templateId}/form`);
      const fields: DocumentFormField[] = res.data?.data?.fields || [];
      setFormFields(fields);

      const values: Record<string, string> = {};
      fields.forEach((field) => {
        const value = field.value === null || field.value === undefined ? '' : String(field.value);
        values[field.variable] = value;
      });
      setFormValues(values);
    } catch (err: any) {
      console.error("Erreur lors du chargement du formulaire :", err);
      setFormError(err.response?.data?.message || "Erreur lors du chargement du formulaire");
    } finally {
      setFormLoading(false);
    }
  };

  const handleSaveForm = async (generateAfterSave: boolean) => {
    if (!formTemplateId) {
      return;
    }

    try {
      setFormSaving(true);
      setFormError(null);
      await api.post(`/clients/${id}/document-templates/${formTemplateId}/form`, {
        values: formValues,
      });

      toast.success("Formulaire enregistré");

      if (generateAfterSave) {
        await generateDocument(formTemplateId, formFormat);
        setShowDocumentFormModal(false);
        setFormTemplateId(null);
        setFormFields([]);
        setFormValues({});
      }
    } catch (err: any) {
      console.error("Erreur lors de l'enregistrement du formulaire :", err);
      setFormError(err.response?.data?.message || "Erreur lors de l'enregistrement du formulaire");
    } finally {
      setFormSaving(false);
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

  // Handler pour ouvrir le modal d'édition par section
  const handleEditSection = (sectionType: SectionType, data?: any, isNew?: boolean) => {
    setEditModal({
      isOpen: true,
      sectionType,
      data: data || {},
      isNew: isNew || false,
    });
  };

  // Handler pour supprimer un élément (enfant, revenu, actif, etc.)
  const handleDeleteItem = (type: 'enfant' | 'revenu' | 'conjoint' | 'actif' | 'bien' | 'passif' | 'epargne', itemId: number) => {
    const typeLabels: Record<string, string> = {
      enfant: 'enfant',
      revenu: 'revenu',
      conjoint: 'conjoint',
      actif: 'actif financier',
      bien: 'bien immobilier',
      passif: 'passif',
      epargne: 'épargne',
    };

    setConfirmDialog({
      isOpen: true,
      title: `Supprimer ${typeLabels[type]}`,
      message: `Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.`,
      type: 'danger',
      onConfirm: async () => {
        try {
          // Construire l'URL selon le type
          let url = '';
          switch (type) {
            case 'enfant':
              url = `/clients/${id}/enfants/${itemId}`;
              break;
            case 'revenu':
              url = `/clients/${id}/revenus/${itemId}`;
              break;
            case 'conjoint':
              url = `/clients/${id}/conjoint`;
              break;
            case 'actif':
              url = `/clients/${id}/actifs-financiers/${itemId}`;
              break;
            case 'bien':
              url = `/clients/${id}/biens-immobiliers/${itemId}`;
              break;
            case 'passif':
              url = `/clients/${id}/passifs/${itemId}`;
              break;
            case 'epargne':
              url = `/clients/${id}/autres-epargnes/${itemId}`;
              break;
          }

          await api.delete(url);
          toast.success(`${typeLabels[type].charAt(0).toUpperCase() + typeLabels[type].slice(1)} supprimé avec succès`);
          fetchClient();
        } catch (err) {
          console.error(err);
          toast.error(`Erreur lors de la suppression`);
        }
      },
    });
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

  const selectedFormTemplate = templates.find((template: any) => template.id === formTemplateId);

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
                    onEditSection={handleEditSection}
                    onDeleteItem={handleDeleteItem}
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

      {/* Modal pour générer un document - Nouveau design Vuexy */}
      {showGenerateModal && (
        <TemplateSelectModal
          templates={templates}
          onClose={() => setShowGenerateModal(false)}
          onConfirm={(templateId, format) => {
            // Ouvrir le formulaire de document avec le template et format sélectionnés
            setFormTemplateId(templateId);
            setFormFormat(format);
            setShowGenerateModal(false);
            setShowDocumentFormModal(true);
          }}
          isLoading={generatingDocument}
        />
      )}

      {showDocumentFormModal && (
        <DocumentFormModal
          templateName={selectedFormTemplate?.name || 'Formulaire du document'}
          fields={formFields}
          values={formValues}
          onChange={(variable, value) => setFormValues((prev) => ({ ...prev, [variable]: value }))}
          onClose={() => {
            setShowDocumentFormModal(false);
            setFormTemplateId(null);
            setFormFields([]);
            setFormValues({});
            setFormError(null);
          }}
          onSave={() => handleSaveForm(false)}
          onSaveAndGenerate={() => handleSaveForm(true)}
          isLoading={formLoading}
          isSaving={formSaving}
          isGenerating={generatingDocument}
          error={formError}
        />
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

      {/* Section Edit Modal */}
      {editModal.isOpen && editModal.sectionType && (
        <SectionEditModal
          sectionType={editModal.sectionType}
          initialData={editModal.data}
          clientId={parseInt(id!, 10)}
          isNew={editModal.isNew}
          onClose={() => setEditModal({ isOpen: false, sectionType: null, data: null, isNew: false })}
          onSaved={() => {
            setEditModal({ isOpen: false, sectionType: null, data: null, isNew: false });
            fetchClient();
          }}
        />
      )}
    </>
  );
};

export default ClientDetailPage;
