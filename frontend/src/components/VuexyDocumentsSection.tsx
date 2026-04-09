import React from "react";
import { Download, Mail, Trash2, Plus, FileText, Calendar, User } from "lucide-react";

interface Document {
  id: number;
  document_template?: {
    name: string;
    description: string;
  };
  created_at: string;
  user?: {
    name: string;
  };
  sent_by_email?: boolean;
  format?: string;
}

interface VuexyDocumentsSectionProps {
  documents: Document[];
  onGenerateClick: () => void;
  onDownload: (documentId: number) => void;
  onSendEmail: (documentId: number) => void;
  onDelete: (documentId: number) => void;
}

export const VuexyDocumentsSection: React.FC<VuexyDocumentsSectionProps> = ({
  documents,
  onGenerateClick,
  onDownload,
  onSendEmail,
  onDelete,
}) => {
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("fr-FR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  };

  return (
    <div className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)]">
      {/* Section Header */}
      <div className="p-6 border-b border-[#EBE9F1]">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold text-[#5E5873]">
            Documents réglementaires
          </h2>
          <button
            onClick={onGenerateClick}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white rounded-lg font-semibold text-sm transition-all shadow-[0_2px_4px_rgba(115,103,240,0.4)] hover:shadow-[0_4px_8px_rgba(115,103,240,0.5)] hover:-translate-y-0.5"
          >
            <Plus size={18} />
            <span>Générer un document réglementaire</span>
          </button>
        </div>
      </div>

      {/* Documents List */}
      <div className="p-6">
        {documents.length === 0 ? (
          /* Empty State */
          <div className="text-center py-12">
            <div className="w-20 h-20 mx-auto mb-4 rounded-xl bg-[#F3F2F7] flex items-center justify-center">
              <FileText size={40} className="text-[#B9B9C3]" />
            </div>
            <h3 className="text-lg font-semibold text-[#5E5873] mb-2">
              Aucun document
            </h3>
            <p className="text-[#6E6B7B] text-sm">
              Cliquez sur le bouton ci-dessus pour générer votre premier document réglementaire.
            </p>
          </div>
        ) : (
          /* Document Cards */
          <div className="space-y-4">
            {documents.map((doc, index) => (
              <div
                key={doc.id}
                className="group border border-[#EBE9F1] rounded-xl p-5 transition-all duration-200 hover:border-[#7367F0] hover:shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:-translate-y-0.5"
                style={{
                  animation: `fadeIn 0.3s ease-out ${index * 0.05}s both`,
                }}
              >
                <div className="flex items-center justify-between gap-4">
                  {/* Document Info */}
                  <div className="flex-1 min-w-0">
                    <h3 className="text-lg font-semibold text-[#5E5873] mb-1 truncate">
                      {doc.document_template?.name || "Document sans nom"}
                    </h3>
                    <p className="text-sm text-[#6E6B7B] mb-3 line-clamp-2">
                      {doc.document_template?.description || "Aucune description"}
                    </p>
                    <div className="flex flex-wrap items-center gap-4 text-xs text-[#B9B9C3]">
                      <span className="inline-flex items-center gap-1.5">
                        <Calendar size={14} />
                        Généré le {formatDate(doc.created_at)}
                      </span>
                      <span className="inline-flex items-center gap-1.5">
                        <User size={14} />
                        Par {doc.user?.name || "Utilisateur"}
                      </span>
                      {doc.sent_by_email && (
                        <span className="inline-flex items-center gap-1.5 text-[#28C76F]">
                          <Mail size={14} />
                          Envoyé par email
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Action Buttons */}
                  <div className="flex items-center gap-2 flex-shrink-0">
                    {/* Download Button */}
                    <button
                      onClick={() => onDownload(doc.id)}
                      className="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-200 bg-[rgba(0,207,232,0.12)] text-[#00CFE8] hover:bg-[#00CFE8] hover:text-white hover:scale-105"
                      title="Télécharger"
                    >
                      <Download size={18} />
                    </button>

                    {/* Email Button */}
                    {!doc.sent_by_email && (
                      <button
                        onClick={() => onSendEmail(doc.id)}
                        className="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-200 bg-[rgba(40,199,111,0.12)] text-[#28C76F] hover:bg-[#28C76F] hover:text-white hover:scale-105"
                        title="Envoyer par email"
                      >
                        <Mail size={18} />
                      </button>
                    )}

                    {/* Delete Button */}
                    <button
                      onClick={() => onDelete(doc.id)}
                      className="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-200 bg-[rgba(234,84,85,0.12)] text-[#EA5455] hover:bg-[#EA5455] hover:text-white hover:scale-105"
                      title="Supprimer"
                    >
                      <Trash2 size={18} />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Animation keyframes */}
      <style>{`
        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: translateY(10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>
    </div>
  );
};

export default VuexyDocumentsSection;
