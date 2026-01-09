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
  ArrowRight,
} from "lucide-react";

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

const STATUS_LABELS: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  pending: { label: "En attente", color: "bg-gray-100 text-gray-600", icon: <RefreshCw size={14} /> },
  analyzing: { label: "Analyse...", color: "bg-blue-100 text-blue-600", icon: <RefreshCw size={14} className="animate-spin" /> },
  mapping: { label: "Mapping requis", color: "bg-yellow-100 text-yellow-600", icon: <AlertTriangle size={14} /> },
  processing: { label: "En cours...", color: "bg-purple-100 text-purple-600", icon: <RefreshCw size={14} className="animate-spin" /> },
  completed: { label: "Terminé", color: "bg-green-100 text-green-600", icon: <CheckCircle size={14} /> },
  failed: { label: "Échec", color: "bg-red-100 text-red-600", icon: <XCircle size={14} /> },
};

const ImportPage: React.FC = () => {
  const navigate = useNavigate();
  const { user, isAdmin } = useAuth();
  const [sessions, setSessions] = useState<ImportSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedSession, setSelectedSession] = useState<ImportSession | null>(null);
  const [sessionStats, setSessionStats] = useState<SessionStats | null>(null);
  const [columnMappings, setColumnMappings] = useState<Record<string, string>>({});
  const [availableFields, setAvailableFields] = useState<{ client: string[]; conjoint: string[]; enfant: string[] }>({
    client: [],
    conjoint: [],
    enfant: [],
  });

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
      setAvailableFields(response.data.data);
    } catch (error) {
      console.error("Error fetching fields:", error);
    }
  }, []);

  useEffect(() => {
    fetchSessions();
    fetchAvailableFields();
  }, [fetchSessions, fetchAvailableFields]);

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const allowedTypes = [
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "application/vnd.ms-excel",
      "text/csv",
    ];

    if (!allowedTypes.includes(file.type) && !file.name.endsWith(".csv")) {
      toast.error("Format de fichier non supporté. Utilisez Excel (.xlsx, .xls) ou CSV.");
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

  const startImport = async () => {
    if (!selectedSession) return;

    try {
      await api.post(`/import/sessions/${selectedSession.id}/start`);
      toast.success("Import lancé");
      fetchSessionDetails(selectedSession);
    } catch (error: any) {
      toast.error(error.response?.data?.message || "Erreur lors du lancement");
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

  const allFields = [...availableFields.client, ...availableFields.conjoint, ...availableFields.enfant];

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
                  Importez vos fichiers Excel ou CSV pour ajouter des clients en masse
                </p>
              </div>

              {/* Upload Button */}
              <label className="relative cursor-pointer">
                <input
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  onChange={handleFileUpload}
                  className="hidden"
                  disabled={uploading}
                />
                <div
                  className={`flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white transition-all duration-200 ${
                    uploading
                      ? "bg-gray-400 cursor-not-allowed"
                      : "bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:shadow-lg hover:shadow-[#7367F0]/30"
                  }`}
                >
                  {uploading ? (
                    <RefreshCw size={20} className="animate-spin" />
                  ) : (
                    <Upload size={20} />
                  )}
                  {uploading ? "Upload en cours..." : "Importer un fichier"}
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

                  {/* Mapping Configuration */}
                  {selectedSession.status === "mapping" && selectedSession.detected_columns && (
                    <div className="vx-card">
                      <h3 className="text-lg font-semibold text-[#5E5873] mb-4">
                        Configuration du mapping
                      </h3>
                      <p className="text-sm text-[#6E6B7B] mb-4">
                        Associez les colonnes de votre fichier aux champs du système
                      </p>
                      <div className="space-y-3 max-h-96 overflow-y-auto">
                        {selectedSession.detected_columns.map((column) => (
                          <div key={column} className="flex items-center gap-4">
                            <div className="flex-1">
                              <label className="block text-sm font-medium text-[#5E5873] mb-1">
                                {column}
                              </label>
                              <select
                                value={columnMappings[column] || ""}
                                onChange={(e) => handleMappingChange(column, e.target.value)}
                                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]"
                              >
                                <option value="">-- Ignorer --</option>
                                <optgroup label="Client">
                                  {availableFields.client.map((field) => (
                                    <option key={field} value={field}>
                                      {field}
                                    </option>
                                  ))}
                                </optgroup>
                                <optgroup label="Conjoint">
                                  {availableFields.conjoint.map((field) => (
                                    <option key={field} value={field}>
                                      {field}
                                    </option>
                                  ))}
                                </optgroup>
                                <optgroup label="Enfant">
                                  {availableFields.enfant.map((field) => (
                                    <option key={field} value={field}>
                                      {field}
                                    </option>
                                  ))}
                                </optgroup>
                              </select>
                            </div>
                            {selectedSession.ai_suggested_mappings?.[column]?.confidence && (
                              <span className="text-xs text-[#28C76F]">
                                {(selectedSession.ai_suggested_mappings[column].confidence * 100).toFixed(0)}%
                              </span>
                            )}
                          </div>
                        ))}
                      </div>
                      <div className="mt-6 flex justify-end">
                        <button
                          onClick={submitMapping}
                          className="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white rounded-xl font-semibold hover:shadow-lg hover:shadow-[#7367F0]/30 transition-all duration-200"
                        >
                          <ArrowRight size={18} />
                          Valider le mapping
                        </button>
                      </div>
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
        </div>
      </div>
    </>
  );
};

export default ImportPage;
