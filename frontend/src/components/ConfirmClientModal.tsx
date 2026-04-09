import React from "react";
import { AlertTriangle, CheckCircle } from "lucide-react";

interface ConfirmClientModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  clientName: string;
  isLoading?: boolean;
}

/**
 * Modal de confirmation pour le passage Prospect -> Client
 * Design Vuexy coherent avec ConfirmDialog
 */
export const ConfirmClientModal: React.FC<ConfirmClientModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  clientName,
  isLoading = false,
}) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop avec blur */}
      <div
        className="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-all duration-300"
        onClick={onClose}
      />

      {/* Modale */}
      <div className="relative bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100 animate-slideIn">
        <div className="p-6">
          {/* Icone */}
          <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] mb-4">
            <AlertTriangle size={24} />
          </div>

          {/* Titre et message */}
          <div className="text-center">
            <h3 className="text-lg font-semibold text-[#5E5873] mb-1">
              Confirmer le passage en client
            </h3>
            <p className="text-sm text-[#6E6B7B] mb-4">
              {clientName}
            </p>
          </div>

          {/* Checklist */}
          <div className="bg-[#F8F8F8] rounded-lg p-4 mb-4">
            <p className="text-sm font-medium text-[#5E5873] mb-3">
              Avant de confirmer, assurez-vous que :
            </p>
            <ul className="space-y-2">
              <li className="flex items-start gap-2 text-sm text-[#6E6B7B]">
                <CheckCircle size={18} className="text-[#FF9F43] flex-shrink-0 mt-0.5" />
                <span>Un mandat ou contrat d'assurance a été signé</span>
              </li>
              <li className="flex items-start gap-2 text-sm text-[#6E6B7B]">
                <CheckCircle size={18} className="text-[#FF9F43] flex-shrink-0 mt-0.5" />
                <span>Le document signé a été importé dans le dossier</span>
              </li>
            </ul>
          </div>

          {/* Boutons */}
          <div className="flex space-x-3">
            <button
              onClick={onClose}
              disabled={isLoading}
              className="flex-1 px-4 py-2.5 border border-[#EBE9F1] rounded-lg text-[#6E6B7B] font-medium hover:bg-[#F3F2F7] transition-colors disabled:opacity-50"
            >
              Annuler
            </button>
            <button
              onClick={onConfirm}
              disabled={isLoading}
              className="flex-1 px-4 py-2.5 rounded-lg text-white font-medium bg-[#7367F0] hover:bg-[#5E50EE] transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {isLoading ? (
                <>
                  <svg
                    className="animate-spin h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    />
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  <span>Confirmation...</span>
                </>
              ) : (
                "Confirmer"
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ConfirmClientModal;
