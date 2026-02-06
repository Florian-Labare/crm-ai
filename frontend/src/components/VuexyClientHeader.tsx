import React, { useState } from 'react';
import { Edit, FileText, FileDown, Trash2, Hash, Clock, CheckCircle, User, Users, Archive } from 'lucide-react';
import { ComplianceBadge } from './ComplianceBadge';
import { ConfirmClientModal } from './ConfirmClientModal';

interface VuexyClientHeaderProps {
  client: any;
  onEdit: () => void;
  onExportPDF?: () => void;
  onExportWord?: () => void;
  onDelete: () => void;
  showEditButton?: boolean;
  showExportQuestionnaireButton?: boolean;
  onExportQuestionnairePDF?: () => void;
  onStatusChange?: (isClient: boolean) => Promise<void>;
  onArchive?: () => Promise<void>;
  onRestore?: () => Promise<void>;
}

export const VuexyClientHeader: React.FC<VuexyClientHeaderProps> = ({
  client,
  onEdit,
  onExportPDF,
  onExportWord,
  onDelete,
  showEditButton = true,
  showExportQuestionnaireButton = false,
  onExportQuestionnairePDF,
  onStatusChange,
  onArchive,
  onRestore,
}) => {
  const [showClientModal, setShowClientModal] = useState(false);
  const [isStatusLoading, setIsStatusLoading] = useState(false);

  const getInitials = (prenom?: string, nom?: string): string => {
    const p = prenom?.charAt(0)?.toUpperCase() || '';
    const n = nom?.charAt(0)?.toUpperCase() || '';
    return p + n || '?';
  };

  const handleConvertToClient = async () => {
    if (!onStatusChange) return;
    setIsStatusLoading(true);
    try {
      await onStatusChange(true);
      setShowClientModal(false);
    } finally {
      setIsStatusLoading(false);
    }
  };

  const formatDate = (date?: string): string => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('fr-FR');
  };

  const civilite = client.civilite || 'Monsieur';
  const fullName = `${civilite} ${client.prenom || ''} ${client.nom || ''}`.trim();
  const initials = getInitials(client.prenom, client.nom);
  const lastUpdate = formatDate(client.updated_at);

  return (
    <div className="vx-card vx-slide-in mb-8">
      <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        {/* Client Info */}
        <div className="flex items-center gap-6">
          {/* Avatar */}
          <div className="w-20 h-20 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-2xl font-semibold shadow-lg shadow-purple-500/30">
            {initials}
          </div>

          {/* Details */}
          <div>
            <h1 className="text-2xl font-semibold text-[#5E5873] mb-2">
              {fullName}
            </h1>
            <div className="flex flex-wrap gap-4 text-sm text-[#6E6B7B]">
              <span className="flex items-center gap-1.5">
                <Hash size={16} />
                Client #{client.id}
              </span>
              <span className="flex items-center gap-1.5">
                <Clock size={16} />
                Dernière mise à jour: {lastUpdate}
              </span>
              {/* Badge Prospect/Client/Archive */}
              {client.is_archived ? (
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 text-gray-600 font-semibold text-xs uppercase tracking-wider">
                  <Archive size={14} />
                  Archive
                </span>
              ) : client.is_client ? (
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#28C76F]/10 text-[#28C76F] font-semibold text-xs uppercase tracking-wider">
                  <Users size={14} />
                  Client
                </span>
              ) : (
                <button
                  onClick={() => setShowClientModal(true)}
                  className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#00CFE8]/10 text-[#00CFE8] font-semibold text-xs uppercase tracking-wider hover:bg-[#7367F0]/10 hover:text-[#7367F0] transition-colors cursor-pointer"
                  title="Cliquez pour passer en client"
                >
                  <User size={14} />
                  Prospect
                </button>
              )}
              <ComplianceBadge clientId={client.id} variant="badge" />
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap gap-3">
          {showEditButton && (
            <button
              onClick={onEdit}
              className="px-4 py-2.5 rounded-lg border border-[#EBE9F1] bg-white text-[#5E5873] font-semibold hover:bg-[#F3F2F7] hover:border-[#7367F0] hover:text-[#7367F0] transition-all duration-200 flex items-center gap-2"
            >
              <Edit size={18} />
              Éditer
            </button>
          )}
          {onExportPDF && (
            <button
              onClick={onExportPDF}
              className="px-4 py-2.5 rounded-lg border border-[#EBE9F1] bg-white text-[#5E5873] font-semibold hover:bg-[#F3F2F7] hover:border-[#7367F0] hover:text-[#7367F0] transition-all duration-200 flex items-center gap-2"
            >
              <FileText size={18} />
              PDF
            </button>
          )}
          {onExportWord && (
            <button
              onClick={onExportWord}
              className="px-4 py-2.5 rounded-lg border border-[#EBE9F1] bg-white text-[#5E5873] font-semibold hover:bg-[#F3F2F7] hover:border-[#7367F0] hover:text-[#7367F0] transition-all duration-200 flex items-center gap-2"
            >
              <FileDown size={18} />
              Word
            </button>
          )}
          {showExportQuestionnaireButton && onExportQuestionnairePDF && (
            <button
              onClick={onExportQuestionnairePDF}
              className="px-4 py-2.5 rounded-lg border border-[#EBE9F1] bg-white text-[#5E5873] font-semibold hover:bg-[#F3F2F7] hover:border-[#FF9F43] hover:text-[#FF9F43] transition-all duration-200 flex items-center gap-2"
            >
              <FileText size={18} />
              Export questionnaire PDF
            </button>
          )}
          {/* Bouton Archiver/Restaurer */}
          {!client.is_archived && onArchive && (
            <button
              onClick={onArchive}
              className="px-4 py-2.5 rounded-lg border border-[#EBE9F1] bg-white text-[#6E6B7B] font-semibold hover:bg-gray-100 hover:border-gray-400 transition-all duration-200 flex items-center gap-2"
            >
              <Archive size={18} />
              Archiver
            </button>
          )}
          {client.is_archived && onRestore && (
            <button
              onClick={onRestore}
              className="px-4 py-2.5 rounded-lg border border-[#28C76F] bg-white text-[#28C76F] font-semibold hover:bg-[#28C76F]/10 transition-all duration-200 flex items-center gap-2"
            >
              <CheckCircle size={18} />
              Restaurer
            </button>
          )}
          <button
            onClick={onDelete}
            className="px-4 py-2.5 rounded-lg border border-[#EA5455] bg-white text-[#EA5455] font-semibold hover:bg-[#EA5455]/10 transition-all duration-200 flex items-center gap-2"
          >
            <Trash2 size={18} />
            Supprimer
          </button>
        </div>
      </div>

      {/* Modal de confirmation passage en client */}
      <ConfirmClientModal
        isOpen={showClientModal}
        onClose={() => setShowClientModal(false)}
        onConfirm={handleConvertToClient}
        clientName={`${client.prenom || ''} ${(client.nom || '').toUpperCase()}`}
        isLoading={isStatusLoading}
      />
    </div>
  );
};
