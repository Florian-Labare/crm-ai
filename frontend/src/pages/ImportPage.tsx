import React, { useEffect, useState, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { useAuth } from "../contexts/AuthContext";
import {
  Upload,
  FileSpreadsheet,
  ArrowLeft,
  CheckCircle,
  XCircle,
  AlertTriangle,
  RefreshCw,
  Play,
  Trash2,
  Eye,
  Users,
  Database,
  Plus,
  TestTube,
  Link,
  Shield,
  FileCheck,
  Lock,
} from "lucide-react";
import { SmartMappingForm } from "../components/SmartMappingForm";

interface ImportSession {
  id: number;
  original_filename: string;
  status: string;
  total_rows: number;
  processed_rows: number;
  success_count: number;
  error_count: number;
  duplicate_count: number;
  detected_columns?: string[];
  ai_suggested_mappings?: Record<string, { suggested_field: string; confidence: number }>;
  created_at: string;
  mapping?: { id: number; name: string };
  // RGPD fields
  rgpd_consent_given?: boolean;
  legal_basis?: string;
  legal_basis_details?: string;
  consent_timestamp?: string;
  retention_until?: string;
}

interface EnhancedFieldOption {
  value: string;
  label: string;
  group: string;
  table: string;
  field: string;
  index?: number | null;
}

interface SessionStats {
  total_rows: number;
  processed_rows: number;
  progress_percentage: number;
  pending: number;
  valid: number;
  invalid: number;
  duplicate: number;
  imported: number;
}

interface DatabaseConnection {
  id: number;
  name: string;
  driver: string;
  host: string;
  port: number;
  database: string;
  username: string;
  schema?: string;
  is_active: boolean;
  last_tested_at: string | null;
  created_by: string;
}


const STATUS_LABELS: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  pending: { label: "En attente", color: "bg-gray-100 text-gray-600", icon: <RefreshCw size={14} /> },
  analyzing: { label: "Analyse...", color: "bg-blue-100 text-blue-600", icon: <RefreshCw size={14} className="animate-spin" /> },
  mapping: { label: "Mapping requis", color: "bg-yellow-100 text-yellow-600", icon: <AlertTriangle size={14} /> },
  processing: { label: "En cours...", color: "bg-purple-100 text-purple-600", icon: <RefreshCw size={14} className="animate-spin" /> },
  completed: { label: "Terminé", color: "bg-green-100 text-green-600", icon: <CheckCircle size={14} /> },
  failed: { label: "Échec", color: "bg-red-100 text-red-600", icon: <XCircle size={14} /> },
};

const SUPPORTED_FILE_FORMATS = [
  { ext: ".xlsx", mime: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", label: "Excel (XLSX)" },
  { ext: ".xls", mime: "application/vnd.ms-excel", label: "Excel (XLS)" },
  { ext: ".csv", mime: "text/csv", label: "CSV" },
  { ext: ".json", mime: "application/json", label: "JSON" },
  { ext: ".xml", mime: "text/xml", label: "XML" },
  { ext: ".sql", mime: "text/plain", label: "SQL Dump" },
  { ext: ".txt", mime: "text/plain", label: "Texte" },
];

const DRIVER_LABELS: Record<string, string> = {
  mysql: "MySQL / MariaDB",
  pgsql: "PostgreSQL",
  sqlite: "SQLite",
  sqlsrv: "SQL Server",
};

const LEGAL_BASES: Record<string, string> = {
  consent: "Consentement de la personne",
  contract: "Exécution d'un contrat",
  legal_obligation: "Obligation légale",
  vital_interests: "Intérêts vitaux",
  public_task: "Mission d'intérêt public",
  legitimate_interest: "Intérêt légitime",
};

const ImportPage: React.FC = () => {
  const navigate = useNavigate();
  const { isAdmin } = useAuth();
  const [sessions, setSessions] = useState<ImportSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedSession, setSelectedSession] = useState<ImportSession | null>(null);
  const [sessionStats, setSessionStats] = useState<SessionStats | null>(null);
  const [columnMappings, setColumnMappings] = useState<Record<string, string>>({});
  const [availableFields, setAvailableFields] = useState<Record<string, string[]>>({});
  const [enhancedFields, setEnhancedFields] = useState<EnhancedFieldOption[]>([]);

  // Database connection state
  const [activeTab, setActiveTab] = useState<"files" | "database">("files");
  const [dbConnections, setDbConnections] = useState<DatabaseConnection[]>([]);
  const [selectedConnection, setSelectedConnection] = useState<DatabaseConnection | null>(null);
  const [dbTables, setDbTables] = useState<string[]>([]);
  const [selectedTable, setSelectedTable] = useState<string>("");
  const [showConnectionForm, setShowConnectionForm] = useState(false);
  const [connectionForm, setConnectionForm] = useState({
    name: "",
    driver: "mysql",
    host: "",
    port: "",
    database: "",
    username: "",
    password: "",
    schema: "",
  });
  const [testingConnection, setTestingConnection] = useState(false);

  // RGPD Consent state
  const [showConsentModal, setShowConsentModal] = useState(false);
  const [consentForm, setConsentForm] = useState({
    legal_basis: "",
    legal_basis_details: "",
    confirm_authorization: false,
  });
  const [submittingConsent, setSubmittingConsent] = useState(false);

  // Redirect if not admin
  useEffect(() => {
    if (!isAdmin) {
      toast.error("Accès non autorisé");
      navigate("/");
    }
  }, [isAdmin, navigate]);

  const fetchSessions = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.get("/import/sessions");
      setSessions(response.data.data?.data || []);
    } catch (error) {
      console.error("Error fetching sessions:", error);
      toast.error("Erreur lors du chargement des sessions");
    } finally {
      setLoading(false);
    }
  }, []);

  const fetchAvailableFields = useCallback(async () => {
    try {
      const response = await api.get("/import/mappings/fields");
      const data = response.data.data;
      setAvailableFields(data);

      // Stocker les champs enrichis pour le SmartMappingForm
      if (data._enhanced?.flat) {
        setEnhancedFields(data._enhanced.flat);
      }
    } catch (error) {
      console.error("Error fetching fields:", error);
    }
  }, []);

  const fetchDbConnections = useCallback(async () => {
    try {
      const response = await api.get("/import/database-connections");
      setDbConnections(response.data.data || []);
    } catch (error) {
      console.error("Error fetching DB connections:", error);
    }
  }, []);

  useEffect(() => {
    fetchSessions();
    fetchAvailableFields();
    fetchDbConnections();
  }, [fetchSessions, fetchAvailableFields, fetchDbConnections]);

  const testConnection = async (isNew = true) => {
    setTestingConnection(true);
    try {
      const endpoint = isNew
        ? "/import/database-connections/test"
        : `/import/database-connections/${selectedConnection?.id}/test`;

      const response = await api.post(endpoint, isNew ? connectionForm : {});

      if (response.data.success) {
        toast.success("Connexion réussie !");
      } else {
        toast.error(response.data.message || "Échec de la connexion");
      }
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur de connexion");
    } finally {
      setTestingConnection(false);
    }
  };

  const saveConnection = async () => {
    if (!connectionForm.name || !connectionForm.database) {
      toast.error("Nom et base de données requis");
      return;
    }

    try {
      await api.post("/import/database-connections", connectionForm);
      toast.success("Connexion enregistrée");
      setShowConnectionForm(false);
      setConnectionForm({
        name: "",
        driver: "mysql",
        host: "",
        port: "",
        database: "",
        username: "",
        password: "",
        schema: "",
      });
      fetchDbConnections();
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de l'enregistrement");
    }
  };

  const selectDbConnection = async (conn: DatabaseConnection) => {
    setSelectedConnection(conn);
    setSelectedTable("");
    try {
      const response = await api.get(`/import/database-connections/${conn.id}/tables`);
      setDbTables(response.data.data || []);
    } catch (error: any) {
      toast.error("Erreur lors de la récupération des tables");
      setDbTables([]);
    }
  };

  const startDatabaseImport = async () => {
    if (!selectedConnection || !selectedTable) {
      toast.error("Sélectionnez une connexion et une table");
      return;
    }

    try {
      const response = await api.post(`/import/database-connections/${selectedConnection.id}/import`, {
        table: selectedTable,
      });
      toast.success("Session d'import créée");
      setActiveTab("files");
      fetchSessions();
      setSelectedSession(response.data.data);
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de la création de la session");
    }
  };

  const deleteDbConnection = async (connId: number) => {
    if (!confirm("Supprimer cette connexion ?")) return;

    try {
      await api.delete(`/import/database-connections/${connId}`);
      toast.success("Connexion supprimée");
      if (selectedConnection?.id === connId) {
        setSelectedConnection(null);
        setDbTables([]);
      }
      fetchDbConnections();
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de la suppression");
    }
  };

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const allowedExtensions = [".xlsx", ".xls", ".csv", ".json", ".xml", ".sql", ".txt"];
    const fileExt = "." + file.name.split(".").pop()?.toLowerCase();

    if (!allowedExtensions.includes(fileExt)) {
      toast.error("Format non supporté. Formats acceptés : Excel, CSV, JSON, XML, SQL");
      return;
    }

    setUploading(true);
    const formData = new FormData();
    formData.append("file", file);

    try {
      const response = await api.post("/import/upload", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      toast.success("Fichier uploadé, analyse en cours...");
      fetchSessions();
      setSelectedSession(response.data.data);
    } catch (error: any) {
      console.error("Upload error:", error);
      toast.error(error.response?.data?.message || "Erreur lors de l'upload");
    } finally {
      setUploading(false);
      event.target.value = "";
    }
  };

  const fetchSessionDetails = async (session: ImportSession) => {
    try {
      const response = await api.get(`/import/sessions/${session.id}`);
      setSelectedSession(response.data.data.session);
      setSessionStats(response.data.data.stats);

      if (response.data.data.session.status === "mapping") {
        const suggestionsRes = await api.get(`/import/sessions/${session.id}/suggestions`);
        const suggestions = suggestionsRes.data.data.suggestions || {};
        const initialMappings: Record<string, string> = {};
        Object.entries(suggestions).forEach(([col, suggestion]: [string, any]) => {
          if (suggestion.suggested_field && suggestion.confidence >= 0.6) {
            initialMappings[col] = suggestion.suggested_field;
          }
        });
        setColumnMappings(initialMappings);
      }
    } catch (error) {
      console.error("Error fetching session details:", error);
      toast.error("Erreur lors du chargement des détails");
    }
  };

  const handleMappingChange = (column: string, field: string) => {
    setColumnMappings((prev) => ({
      ...prev,
      [column]: field,
    }));
  };

  const submitMapping = async () => {
    if (!selectedSession) return;

    try {
      await api.post(`/import/sessions/${selectedSession.id}/mapping`, {
        column_mappings: columnMappings,
      });
      toast.success("Mapping configuré");
      fetchSessionDetails(selectedSession);
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de la configuration du mapping");
    }
  };

  const submitConsent = async () => {
    if (!selectedSession) return;
    if (!consentForm.legal_basis || !consentForm.legal_basis_details || !consentForm.confirm_authorization) {
      toast.error("Veuillez remplir tous les champs obligatoires");
      return;
    }

    setSubmittingConsent(true);
    try {
      await api.post(`/import/sessions/${selectedSession.id}/consent`, consentForm);
      toast.success("Consentement RGPD enregistré");
      setShowConsentModal(false);
      setConsentForm({ legal_basis: "", legal_basis_details: "", confirm_authorization: false });

      // Now start the import
      await api.post(`/import/sessions/${selectedSession.id}/start`);
      toast.success("Import lancé");
      fetchSessionDetails(selectedSession);
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de l'enregistrement");
    } finally {
      setSubmittingConsent(false);
    }
  };

  const startImport = async () => {
    if (!selectedSession) return;

    // Check if consent is already given
    if (!selectedSession.rgpd_consent_given) {
      setShowConsentModal(true);
      return;
    }

    try {
      await api.post(`/import/sessions/${selectedSession.id}/start`);
      toast.success("Import lancé");
      fetchSessionDetails(selectedSession);
    } catch (error: any) {
      if (error.response?.data?.requires_consent) {
        setShowConsentModal(true);
      } else {
        toast.error(error.response?.data?.message || "Erreur lors du lancement");
      }
    }
  };

  const importValidRows = async () => {
    if (!selectedSession) return;

    try {
      const response = await api.post(`/import/sessions/${selectedSession.id}/import-valid`);
      toast.success(`${response.data.data.imported_count} clients importés`);
      fetchSessionDetails(selectedSession);
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors de l'import");
    }
  };

  const deleteSession = async (sessionId: number) => {
    if (!confirm("Supprimer cette session d'import ?")) return;

    try {
      await api.delete(`/import/sessions/${sessionId}`);
      toast.success("Session supprimée");
      if (selectedSession?.id === sessionId) {
        setSelectedSession(null);
        setSessionStats(null);
      }
      fetchSessions();
    } catch (error) {
      toast.error("Erreur lors de la suppression");
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-screen bg-[#F8F8F8]">
        <div className="flex flex-col items-center space-y-4">
          <div className="w-16 h-16 border-4 border-[#7367F0] border-t-transparent rounded-full animate-spin"></div>
          <p className="text-[#6E6B7B] font-semibold">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-7xl mx-auto space-y-8">
          {/* Header */}
          <div className="vx-card">
            <div className="flex justify-between items-start">
              <div>
                <button
                  onClick={() => navigate("/")}
                  className="flex items-center gap-2 text-[#6E6B7B] hover:text-[#7367F0] mb-4 transition-colors"
                >
                  <ArrowLeft size={18} />
                  Retour au tableau de bord
                </button>
                <h1 className="text-3xl font-bold text-[#5E5873] mb-2">Import de données</h1>
                <p className="text-[#6E6B7B]">
                  Importez vos données clients depuis des fichiers ou des bases de données externes
                </p>
              </div>

              {/* Tab Buttons */}
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setActiveTab("files")}
                  className={`flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold transition-all duration-200 ${
                    activeTab === "files"
                      ? "bg-[#7367F0] text-white"
                      : "bg-[#F3F2F7] text-[#5E5873] hover:bg-[#E8E6EF]"
                  }`}
                >
                  <FileSpreadsheet size={18} />
                  Fichiers
                </button>
                <button
                  onClick={() => setActiveTab("database")}
                  className={`flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold transition-all duration-200 ${
                    activeTab === "database"
                      ? "bg-[#7367F0] text-white"
                      : "bg-[#F3F2F7] text-[#5E5873] hover:bg-[#E8E6EF]"
                  }`}
                >
                  <Database size={18} />
                  Base de données
                </button>
              </div>
            </div>

            {/* Supported Formats */}
            <div className="mt-4 flex flex-wrap items-center gap-2">
              <span className="text-sm text-[#6E6B7B]">Formats supportés :</span>
              {SUPPORTED_FILE_FORMATS.map((format) => (
                <span
                  key={format.ext}
                  className="px-2 py-1 bg-[#F3F2F7] rounded text-xs font-medium text-[#5E5873]"
                >
                  {format.label}
                </span>
              ))}
            </div>
          </div>

          {/* File Import Tab */}
          {activeTab === "files" && (
            <>
              {/* Upload Zone */}
              <div className="vx-card">
                <div className="border-2 border-dashed border-[#D8D6DE] rounded-xl p-8 text-center hover:border-[#7367F0] transition-colors">
                  <label className="cursor-pointer">
                    <input
                      type="file"
                      accept=".xlsx,.xls,.csv,.json,.xml,.sql,.txt"
                      onChange={handleFileUpload}
                      className="hidden"
                      disabled={uploading}
                    />
                    <div className="flex flex-col items-center">
                      {uploading ? (
                        <RefreshCw size={48} className="text-[#7367F0] animate-spin mb-4" />
                      ) : (
                        <Upload size={48} className="text-[#B9B9C3] mb-4" />
                      )}
                      <p className="text-[#5E5873] font-semibold mb-2">
                        {uploading ? "Upload en cours..." : "Glissez-déposez ou cliquez pour sélectionner"}
                      </p>
                      <p className="text-sm text-[#6E6B7B]">
                        Excel, CSV, JSON, XML ou SQL (max 50 Mo)
                      </p>
                    </div>
                  </label>
                </div>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Sessions List */}
                <div className="lg:col-span-1">
                  <div className="vx-card">
                    <h2 className="text-lg font-semibold text-[#5E5873] mb-4">Sessions d'import</h2>
                    {sessions.length === 0 ? (
                  <div className="text-center py-8 text-[#6E6B7B]">
                    <FileSpreadsheet size={48} className="mx-auto mb-4 opacity-50" />
                    <p>Aucune session d'import</p>
                    <p className="text-sm mt-2">Uploadez un fichier pour commencer</p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {sessions.map((session) => {
                      const status = STATUS_LABELS[session.status] || STATUS_LABELS.pending;
                      return (
                        <div
                          key={session.id}
                          onClick={() => fetchSessionDetails(session)}
                          className={`p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 ${
                            selectedSession?.id === session.id
                              ? "border-[#7367F0] bg-[#7367F0]/5"
                              : "border-transparent bg-[#F8F8F8] hover:border-[#7367F0]/30"
                          }`}
                        >
                          <div className="flex items-start justify-between">
                            <div className="flex-1 min-w-0">
                              <p className="font-semibold text-[#5E5873] truncate">
                                {session.original_filename}
                              </p>
                              <p className="text-sm text-[#6E6B7B] mt-1">
                                {session.total_rows} lignes
                              </p>
                            </div>
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                deleteSession(session.id);
                              }}
                              className="p-1 text-[#EA5455] hover:bg-[#EA5455]/10 rounded"
                            >
                              <Trash2 size={16} />
                            </button>
                          </div>
                          <div className="flex items-center gap-2 mt-3">
                            <span className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${status.color}`}>
                              {status.icon}
                              {status.label}
                            </span>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>

            {/* Session Details */}
            <div className="lg:col-span-2">
              {selectedSession ? (
                <div className="space-y-6">
                  {/* Stats */}
                  {sessionStats && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div className="vx-card text-center">
                        <p className="text-2xl font-bold text-[#7367F0]">{sessionStats.valid}</p>
                        <p className="text-sm text-[#6E6B7B]">Valides</p>
                      </div>
                      <div className="vx-card text-center">
                        <p className="text-2xl font-bold text-[#FF9F43]">{sessionStats.duplicate}</p>
                        <p className="text-sm text-[#6E6B7B]">Doublons</p>
                      </div>
                      <div className="vx-card text-center">
                        <p className="text-2xl font-bold text-[#EA5455]">{sessionStats.invalid}</p>
                        <p className="text-sm text-[#6E6B7B]">Erreurs</p>
                      </div>
                      <div className="vx-card text-center">
                        <p className="text-2xl font-bold text-[#28C76F]">{sessionStats.imported}</p>
                        <p className="text-sm text-[#6E6B7B]">Importés</p>
                      </div>
                    </div>
                  )}

                  {/* Progress Bar */}
                  {sessionStats && sessionStats.progress_percentage > 0 && (
                    <div className="vx-card">
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium text-[#5E5873]">Progression</span>
                        <span className="text-sm font-medium text-[#7367F0]">
                          {sessionStats.progress_percentage.toFixed(0)}%
                        </span>
                      </div>
                      <div className="w-full bg-[#F3F2F7] rounded-full h-2">
                        <div
                          className="bg-gradient-to-r from-[#7367F0] to-[#9055FD] h-2 rounded-full transition-all duration-500"
                          style={{ width: `${sessionStats.progress_percentage}%` }}
                        />
                      </div>
                    </div>
                  )}

                  {/* Mapping Configuration - Smart Form */}
                  {selectedSession.status === "mapping" && selectedSession.detected_columns && (
                    <div className="vx-card">
                      <div className="mb-6">
                        <h3 className="text-lg font-semibold text-[#5E5873]">
                          Configuration du mapping
                        </h3>
                        <p className="text-sm text-[#6E6B7B] mt-1">
                          Associez les colonnes de votre fichier "{selectedSession.original_filename}" aux champs du système.
                          Utilisez la recherche pour trouver rapidement le bon champ.
                        </p>
                      </div>

                      <SmartMappingForm
                        columns={selectedSession.detected_columns || []}
                        availableFields={availableFields}
                        enhancedFields={enhancedFields}
                        aiSuggestions={selectedSession.ai_suggested_mappings || {}}
                        columnMappings={columnMappings}
                        onMappingChange={handleMappingChange}
                        onSubmit={submitMapping}
                      />
                    </div>
                  )}

                  {/* Actions */}
                  {selectedSession.status === "mapping" && selectedSession.mapping && (
                    <div className="vx-card">
                      <h3 className="text-lg font-semibold text-[#5E5873] mb-4">
                        Lancer l'import
                      </h3>
                      <p className="text-sm text-[#6E6B7B] mb-4">
                        Le mapping est configuré. Vous pouvez maintenant lancer l'analyse et l'import.
                      </p>
                      <button
                        onClick={startImport}
                        className="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-[#28C76F] to-[#48DA89] text-white rounded-xl font-semibold hover:shadow-lg hover:shadow-[#28C76F]/30 transition-all duration-200"
                      >
                        <Play size={18} />
                        Lancer l'import
                      </button>
                    </div>
                  )}

                  {/* Import Valid Rows */}
                  {selectedSession.status === "completed" && sessionStats && sessionStats.valid > 0 && (
                    <div className="vx-card">
                      <h3 className="text-lg font-semibold text-[#5E5873] mb-4">
                        Importer les lignes valides
                      </h3>
                      <p className="text-sm text-[#6E6B7B] mb-4">
                        {sessionStats.valid} ligne(s) prête(s) à être importée(s) comme nouveaux clients.
                      </p>
                      <button
                        onClick={importValidRows}
                        className="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white rounded-xl font-semibold hover:shadow-lg hover:shadow-[#7367F0]/30 transition-all duration-200"
                      >
                        <Users size={18} />
                        Importer {sessionStats.valid} client(s)
                      </button>
                    </div>
                  )}

                  {/* Processing Status */}
                  {selectedSession.status === "processing" && (
                    <div className="vx-card text-center py-8">
                      <RefreshCw size={48} className="mx-auto mb-4 text-[#7367F0] animate-spin" />
                      <p className="text-[#5E5873] font-semibold">Import en cours...</p>
                      <p className="text-sm text-[#6E6B7B] mt-2">
                        Cette opération peut prendre quelques minutes
                      </p>
                      <button
                        onClick={() => fetchSessionDetails(selectedSession)}
                        className="mt-4 text-[#7367F0] hover:underline text-sm"
                      >
                        Actualiser
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                <div className="vx-card text-center py-16">
                  <Eye size={48} className="mx-auto mb-4 text-[#B9B9C3]" />
                  <p className="text-[#6E6B7B]">Sélectionnez une session pour voir les détails</p>
                </div>
              )}
            </div>
          </div>
            </>
          )}

          {/* Database Import Tab */}
          {activeTab === "database" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Connections List */}
              <div className="lg:col-span-1">
                <div className="vx-card">
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold text-[#5E5873]">Connexions</h2>
                    <button
                      onClick={() => setShowConnectionForm(true)}
                      className="flex items-center gap-1 px-3 py-1.5 bg-[#7367F0] text-white text-sm rounded-lg hover:bg-[#5E50EE] transition-colors"
                    >
                      <Plus size={16} />
                      Nouvelle
                    </button>
                  </div>

                  {dbConnections.length === 0 ? (
                    <div className="text-center py-8 text-[#6E6B7B]">
                      <Database size={48} className="mx-auto mb-4 opacity-50" />
                      <p>Aucune connexion configurée</p>
                      <p className="text-sm mt-2">Ajoutez une connexion pour commencer</p>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {dbConnections.map((conn) => (
                        <div
                          key={conn.id}
                          onClick={() => selectDbConnection(conn)}
                          className={`p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 ${
                            selectedConnection?.id === conn.id
                              ? "border-[#7367F0] bg-[#7367F0]/5"
                              : "border-transparent bg-[#F8F8F8] hover:border-[#7367F0]/30"
                          }`}
                        >
                          <div className="flex items-start justify-between">
                            <div className="flex-1 min-w-0">
                              <p className="font-semibold text-[#5E5873] truncate">{conn.name}</p>
                              <p className="text-sm text-[#6E6B7B] mt-1">
                                {DRIVER_LABELS[conn.driver]} - {conn.database}
                              </p>
                            </div>
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                deleteDbConnection(conn.id);
                              }}
                              className="p-1 text-[#EA5455] hover:bg-[#EA5455]/10 rounded"
                            >
                              <Trash2 size={16} />
                            </button>
                          </div>
                          {conn.last_tested_at && (
                            <span className="inline-flex items-center gap-1 mt-2 text-xs text-[#28C76F]">
                              <CheckCircle size={12} />
                              Testé
                            </span>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>

              {/* Connection Details / Table Selection */}
              <div className="lg:col-span-2">
                {showConnectionForm ? (
                  <div className="vx-card">
                    <h3 className="text-lg font-semibold text-[#5E5873] mb-4">
                      Nouvelle connexion
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Nom de la connexion
                        </label>
                        <input
                          type="text"
                          value={connectionForm.name}
                          onChange={(e) => setConnectionForm({ ...connectionForm, name: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                          placeholder="Ex: Base Cabinet Durand"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Type de base
                        </label>
                        <select
                          value={connectionForm.driver}
                          onChange={(e) => setConnectionForm({ ...connectionForm, driver: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                        >
                          {Object.entries(DRIVER_LABELS).map(([key, label]) => (
                            <option key={key} value={key}>{label}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Hôte
                        </label>
                        <input
                          type="text"
                          value={connectionForm.host}
                          onChange={(e) => setConnectionForm({ ...connectionForm, host: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                          placeholder="localhost ou IP"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Port
                        </label>
                        <input
                          type="number"
                          value={connectionForm.port}
                          onChange={(e) => setConnectionForm({ ...connectionForm, port: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                          placeholder="3306"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Base de données
                        </label>
                        <input
                          type="text"
                          value={connectionForm.database}
                          onChange={(e) => setConnectionForm({ ...connectionForm, database: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                          placeholder="nom_base"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Utilisateur
                        </label>
                        <input
                          type="text"
                          value={connectionForm.username}
                          onChange={(e) => setConnectionForm({ ...connectionForm, username: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                          placeholder="root"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">
                          Mot de passe
                        </label>
                        <input
                          type="password"
                          value={connectionForm.password}
                          onChange={(e) => setConnectionForm({ ...connectionForm, password: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                        />
                      </div>
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                      <button
                        onClick={() => setShowConnectionForm(false)}
                        className="px-4 py-2 text-[#6E6B7B] hover:bg-[#F3F2F7] rounded-lg transition-colors"
                      >
                        Annuler
                      </button>
                      <button
                        onClick={() => testConnection(true)}
                        disabled={testingConnection}
                        className="flex items-center gap-2 px-4 py-2 border border-[#7367F0] text-[#7367F0] rounded-lg hover:bg-[#7367F0]/10 transition-colors"
                      >
                        {testingConnection ? (
                          <RefreshCw size={16} className="animate-spin" />
                        ) : (
                          <TestTube size={16} />
                        )}
                        Tester
                      </button>
                      <button
                        onClick={saveConnection}
                        className="flex items-center gap-2 px-4 py-2 bg-[#7367F0] text-white rounded-lg hover:bg-[#5E50EE] transition-colors"
                      >
                        <CheckCircle size={16} />
                        Enregistrer
                      </button>
                    </div>
                  </div>
                ) : selectedConnection ? (
                  <div className="space-y-6">
                    <div className="vx-card">
                      <div className="flex items-center justify-between mb-4">
                        <div>
                          <h3 className="text-lg font-semibold text-[#5E5873]">
                            {selectedConnection.name}
                          </h3>
                          <p className="text-sm text-[#6E6B7B]">
                            {DRIVER_LABELS[selectedConnection.driver]} - {selectedConnection.host}:{selectedConnection.port}
                          </p>
                        </div>
                        <button
                          onClick={() => testConnection(false)}
                          disabled={testingConnection}
                          className="flex items-center gap-2 px-3 py-1.5 border border-[#7367F0] text-[#7367F0] text-sm rounded-lg hover:bg-[#7367F0]/10 transition-colors"
                        >
                          {testingConnection ? (
                            <RefreshCw size={14} className="animate-spin" />
                          ) : (
                            <TestTube size={14} />
                          )}
                          Tester
                        </button>
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-2">
                          Sélectionner une table à importer
                        </label>
                        {dbTables.length === 0 ? (
                          <p className="text-sm text-[#6E6B7B]">Aucune table disponible</p>
                        ) : (
                          <select
                            value={selectedTable}
                            onChange={(e) => setSelectedTable(e.target.value)}
                            className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                          >
                            <option value="">-- Sélectionner une table --</option>
                            {dbTables.map((table) => (
                              <option key={table} value={table}>{table}</option>
                            ))}
                          </select>
                        )}
                      </div>

                      {selectedTable && (
                        <div className="mt-6">
                          <button
                            onClick={startDatabaseImport}
                            className="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white rounded-xl font-semibold hover:shadow-lg hover:shadow-[#7367F0]/30 transition-all duration-200"
                          >
                            <Play size={18} />
                            Importer depuis "{selectedTable}"
                          </button>
                        </div>
                      )}
                    </div>
                  </div>
                ) : (
                  <div className="vx-card text-center py-16">
                    <Link size={48} className="mx-auto mb-4 text-[#B9B9C3]" />
                    <p className="text-[#6E6B7B]">Sélectionnez une connexion pour voir les tables disponibles</p>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* RGPD Consent Modal */}
          {showConsentModal && (
            <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div className="p-6 border-b border-[#EBE9F1]">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-xl bg-[#7367F0]/10 flex items-center justify-center">
                      <Shield size={24} className="text-[#7367F0]" />
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-[#5E5873]">Conformité RGPD</h3>
                      <p className="text-sm text-[#6E6B7B]">Confirmation requise avant l'import</p>
                    </div>
                  </div>
                </div>

                <div className="p-6 space-y-5">
                  <div className="bg-[#FFF3E8] border border-[#FF9F43]/30 rounded-xl p-4">
                    <div className="flex gap-3">
                      <AlertTriangle size={20} className="text-[#FF9F43] flex-shrink-0 mt-0.5" />
                      <div className="text-sm text-[#5E5873]">
                        <p className="font-semibold mb-1">Information importante</p>
                        <p>
                          Vous vous apprêtez à importer des données personnelles. Conformément au RGPD,
                          vous devez justifier d'une base légale pour ce traitement.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-[#5E5873] mb-2">
                      Base légale du traitement *
                    </label>
                    <select
                      value={consentForm.legal_basis}
                      onChange={(e) => setConsentForm({ ...consentForm, legal_basis: e.target.value })}
                      className="w-full px-3 py-2.5 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                    >
                      <option value="">-- Sélectionner une base légale --</option>
                      {Object.entries(LEGAL_BASES).map(([key, label]) => (
                        <option key={key} value={key}>{label}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-[#5E5873] mb-2">
                      Justification détaillée *
                    </label>
                    <textarea
                      value={consentForm.legal_basis_details}
                      onChange={(e) => setConsentForm({ ...consentForm, legal_basis_details: e.target.value })}
                      rows={3}
                      className="w-full px-3 py-2.5 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0] resize-none"
                      placeholder="Ex: Les personnes concernées ont donné leur consentement lors de la signature du contrat de conseil..."
                    />
                  </div>

                  <div className="flex items-start gap-3 p-4 bg-[#F8F8F8] rounded-xl">
                    <input
                      type="checkbox"
                      id="confirm_authorization"
                      checked={consentForm.confirm_authorization}
                      onChange={(e) => setConsentForm({ ...consentForm, confirm_authorization: e.target.checked })}
                      className="w-5 h-5 mt-0.5 rounded border-[#D8D6DE] text-[#7367F0] focus:ring-[#7367F0]"
                    />
                    <label htmlFor="confirm_authorization" className="text-sm text-[#5E5873]">
                      Je confirme être autorisé à importer ces données et dispose d'une base légale valide
                      pour leur traitement conformément au RGPD.
                    </label>
                  </div>

                  <div className="text-xs text-[#6E6B7B] flex items-center gap-2">
                    <Lock size={14} />
                    <span>
                      Cette action sera enregistrée dans le journal d'audit. Les données seront conservées 90 jours.
                    </span>
                  </div>
                </div>

                <div className="p-6 border-t border-[#EBE9F1] flex justify-end gap-3">
                  <button
                    onClick={() => {
                      setShowConsentModal(false);
                      setConsentForm({ legal_basis: "", legal_basis_details: "", confirm_authorization: false });
                    }}
                    className="px-5 py-2.5 text-[#6E6B7B] hover:bg-[#F3F2F7] rounded-lg font-medium transition-colors"
                  >
                    Annuler
                  </button>
                  <button
                    onClick={submitConsent}
                    disabled={submittingConsent || !consentForm.legal_basis || !consentForm.legal_basis_details || !consentForm.confirm_authorization}
                    className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white rounded-lg font-semibold hover:shadow-lg hover:shadow-[#7367F0]/30 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {submittingConsent ? (
                      <RefreshCw size={18} className="animate-spin" />
                    ) : (
                      <FileCheck size={18} />
                    )}
                    Confirmer et lancer l'import
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default ImportPage;
