import React, { useState } from 'react';
import { Link2, X, RefreshCw, FileText, Check } from 'lucide-react';

interface AvailableSignedDoc {
  id: number;
  file_name: string;
  custom_label: string | null;
  display_label: string;
  tags: string[];
  uploaded_at: string;
}

interface ChecklistItem {
  requirement_id: number;
  document_type: string;
  label: string;
  besoin: string;
  besoin_label: string;
  available_signed_docs?: AvailableSignedDoc[];
}

interface SignedDocLinkModalProps {
  isOpen: boolean;
  onClose: () => void;
  requirement: ChecklistItem | null;
  onLink: (documentId: number, requirementIds: number[]) => Promise<void>;
  isLinking: boolean;
}

export const SignedDocLinkModal: React.FC<SignedDocLinkModalProps> = ({
  isOpen,
  onClose,
  requirement,
  onLink,
  isLinking,
}) => {
  const [selectedDocId, setSelectedDocId] = useState<number | null>(null);

  const handleClose = () => {
    setSelectedDocId(null);
    onClose();
  };

  const handleSubmit = async () => {
    if (!selectedDocId || !requirement) return;

    await onLink(selectedDocId, [requirement.requirement_id]);
    handleClose();
  };

  if (!isOpen || !requirement) return null;

  const availableDocs = requirement.available_signed_docs || [];

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      style={{ backgroundColor: 'rgba(94, 88, 115, 0.4)', backdropFilter: 'blur(4px)' }}
      onClick={handleClose}
    >
      <div
        className="bg-white rounded-xl shadow-2xl w-full max-w-md animate-modalSlideIn"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 border-b border-[#EBE9F1] flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-[#28C76F]/10 flex items-center justify-center text-[#28C76F]">
              <Link2 size={20} />
            </div>
            <div>
              <h3 className="font-semibold text-[#5E5873]">Lier un document signe</h3>
              <p className="text-sm text-[#6E6B7B] truncate max-w-[250px]">{requirement.label}</p>
            </div>
          </div>
          <button
            onClick={handleClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all"
          >
            <X size={18} />
          </button>
        </div>

        {/* Body */}
        <div className="p-6">
          {availableDocs.length === 0 ? (
            <div className="text-center py-8 text-[#6E6B7B]">
              <FileText size={40} className="mx-auto mb-3 text-[#B9B9C3]" />
              <p>Aucun document signe disponible avec le tag "{requirement.besoin_label}"</p>
            </div>
          ) : (
            <>
              <p className="text-sm text-[#6E6B7B] mb-4">
                Selectionnez le document a lier a cette exigence :
              </p>
              <div className="space-y-2 max-h-[300px] overflow-y-auto">
                {availableDocs.map((doc) => (
                  <button
                    key={doc.id}
                    onClick={() => setSelectedDocId(doc.id)}
                    className={`w-full p-4 rounded-lg border-2 text-left transition-all flex items-center gap-3 ${
                      selectedDocId === doc.id
                        ? 'border-[#7367F0] bg-[#7367F0]/5'
                        : 'border-[#EBE9F1] hover:border-[#7367F0]/50 hover:bg-[#F8F8F8]'
                    }`}
                  >
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                      selectedDocId === doc.id ? 'bg-[#7367F0] text-white' : 'bg-[#F3F2F7] text-[#6E6B7B]'
                    }`}>
                      {selectedDocId === doc.id ? <Check size={20} /> : <FileText size={20} />}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-[#5E5873] truncate">
                        {doc.display_label}
                      </p>
                      <p className="text-xs text-[#6E6B7B]">
                        Importe le {new Date(doc.uploaded_at).toLocaleDateString('fr-FR')}
                      </p>
                      <div className="flex flex-wrap gap-1 mt-1">
                        {doc.tags.map((tag) => (
                          <span
                            key={tag}
                            className="px-2 py-0.5 rounded text-[10px] font-semibold bg-[#7367F0]/10 text-[#7367F0] capitalize"
                          >
                            {tag}
                          </span>
                        ))}
                      </div>
                    </div>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="p-6 border-t border-[#EBE9F1] flex justify-end gap-3">
          <button
            onClick={handleClose}
            className="px-4 py-2 rounded-lg bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EBE9F1] transition-colors font-medium"
          >
            Annuler
          </button>
          <button
            onClick={handleSubmit}
            disabled={!selectedDocId || isLinking}
            className="px-4 py-2 rounded-lg bg-[#28C76F] text-white hover:bg-[#24B263] transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {isLinking ? (
              <>
                <RefreshCw size={16} className="animate-spin" />
                Liaison en cours...
              </>
            ) : (
              <>
                <Link2 size={16} />
                Lier
              </>
            )}
          </button>
        </div>
      </div>

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

export default SignedDocLinkModal;
