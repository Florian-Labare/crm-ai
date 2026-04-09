import React, { useState } from 'react';
import { Hash, Clock, User, Users, Archive } from 'lucide-react';
import { ComplianceBadge } from './ComplianceBadge';
import { ConfirmClientModal } from './ConfirmClientModal';

interface VuexyClientHeaderProps {
  client: any;
  onStatusChange?: (isClient: boolean) => Promise<void>;
}

export const VuexyClientHeader: React.FC<VuexyClientHeaderProps> = ({
  client,
  onStatusChange,
}) => {
  const [showClientModal, setShowClientModal] = useState(false);
  const [isStatusLoading, setIsStatusLoading] = useState(false);

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

  const getInitials = (prenom?: string, nom?: string): string => {
    return (prenom?.charAt(0)?.toUpperCase() || '') + (nom?.charAt(0)?.toUpperCase() || '') || '?';
  };

  const formatDate = (date?: string): string => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('fr-FR');
  };

  const initials = getInitials(client.prenom, client.nom);
  const fullName = `${client.civilite ? client.civilite + '\u00a0' : ''}${client.prenom || ''} ${(client.nom || '').toUpperCase()}`.trim();

  return (
    <div className="bg-white border-b border-[#EBE9F1] px-6 py-4">
      <div className="max-w-7xl mx-auto flex items-center gap-5">
        {/* Avatar */}
        <div className="w-14 h-14 flex-shrink-0 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-lg font-bold shadow-md shadow-purple-500/20">
          {initials}
        </div>

        {/* Identité */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2.5 flex-wrap">
            <h2 className="text-lg font-bold text-[#5E5873] truncate">{fullName}</h2>

            {/* Badge statut — cliquable pour les prospects */}
            {client.is_archived ? (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-500 font-semibold text-xs">
                <Archive size={11} />
                Archivé
              </span>
            ) : client.is_client ? (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#28C76F]/10 text-[#28C76F] font-semibold text-xs">
                <Users size={11} />
                Client
              </span>
            ) : (
              <button
                onClick={() => setShowClientModal(true)}
                title="Convertir en client"
                className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#00CFE8]/10 text-[#00CFE8] font-semibold text-xs hover:bg-[#7367F0]/10 hover:text-[#7367F0] transition-colors"
              >
                <User size={11} />
                Prospect
              </button>
            )}

            <ComplianceBadge clientId={client.id} variant="badge" />
          </div>

          {/* Métadonnées */}
          <div className="flex items-center gap-4 mt-1 text-xs text-[#B9B9C3]">
            <span className="flex items-center gap-1">
              <Hash size={11} />
              #{client.id}
            </span>
            {client.profession && (
              <span className="truncate max-w-[220px]">{client.profession}</span>
            )}
            <span className="flex items-center gap-1">
              <Clock size={11} />
              Màj {formatDate(client.updated_at)}
            </span>
          </div>
        </div>
      </div>

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
