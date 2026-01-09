import React, { useState, useMemo } from "react";
import { Search, X, FileText, Check, File } from "lucide-react";

interface Template {
  id: number;
  name: string;
  description?: string;
  category?: string;
}

interface TemplateSelectModalProps {
  templates: Template[];
  onClose: () => void;
  onConfirm: (templateId: number, format: 'docx' | 'pdf') => void;
  isLoading?: boolean;
}

export const TemplateSelectModal: React.FC<TemplateSelectModalProps> = ({
  templates,
  onClose,
  onConfirm,
  isLoading = false,
}) => {
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null);
  const [selectedFormat, setSelectedFormat] = useState<'docx' | 'pdf'>('docx');

  // Filtrer les templates selon la recherche
  const filteredTemplates = useMemo(() => {
    if (!searchQuery.trim()) return templates;

    const query = searchQuery.toLowerCase().trim();
    return templates.filter(
      (template) =>
        template.name.toLowerCase().includes(query) ||
        (template.description && template.description.toLowerCase().includes(query)) ||
        (template.category && template.category.toLowerCase().includes(query))
    );
  }, [templates, searchQuery]);

  const handleConfirm = () => {
    if (selectedTemplateId) {
      onConfirm(selectedTemplateId, selectedFormat);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-8"
      style={{ backgroundColor: 'rgba(94, 88, 115, 0.4)', backdropFilter: 'blur(4px)' }}
      onClick={onClose}
    >
      {/* Modal */}
      <div
        className="bg-white rounded-xl shadow-2xl w-full max-w-[800px] max-h-[90vh] flex flex-col animate-modalSlideIn"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 md:p-8 border-b border-[#EBE9F1] flex items-center justify-between flex-shrink-0">
          <h2 className="text-2xl font-bold text-[#5E5873]">Sélectionner un template</h2>
          <button
            onClick={onClose}
            className="w-9 h-9 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200 hover:rotate-90"
          >
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="p-6 md:p-8 overflow-y-auto flex-1 custom-scrollbar">
          {/* Search Box */}
          <div className="relative mb-6">
            <Search
              size={18}
              className="absolute left-4 top-1/2 -translate-y-1/2 text-[#6E6B7B] pointer-events-none"
            />
            <input
              type="text"
              placeholder="Rechercher un template..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-12 pr-4 py-3.5 border border-[#EBE9F1] rounded-lg text-[#5E5873] bg-white transition-all duration-200 focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)]"
            />
          </div>

          {/* Templates List */}
          {filteredTemplates.length > 0 ? (
            <div className="space-y-3">
              {filteredTemplates.map((template, index) => (
                <div
                  key={template.id}
                  onClick={() => setSelectedTemplateId(template.id)}
                  className={`
                    border-2 rounded-xl p-5 cursor-pointer transition-all duration-200 relative
                    ${selectedTemplateId === template.id
                      ? 'border-[#7367F0] bg-[#E8E7FD] shadow-[0_4px_12px_rgba(115,103,240,0.2)]'
                      : 'border-[#EBE9F1] hover:border-[#7367F0] hover:shadow-md hover:-translate-y-0.5'
                    }
                  `}
                  style={{
                    animation: `fadeInUp 0.3s ease-out ${index * 0.05}s both`,
                  }}
                >
                  <div className="flex items-start gap-4">
                    {/* Radio Button */}
                    <div
                      className={`
                        w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 mt-0.5 transition-all duration-200
                        ${selectedTemplateId === template.id
                          ? 'border-[#7367F0] bg-[#7367F0] shadow-[0_0_0_4px_rgba(115,103,240,0.2)]'
                          : 'border-[#EBE9F1]'
                        }
                      `}
                    >
                      {selectedTemplateId === template.id && (
                        <div className="w-2.5 h-2.5 bg-white rounded-full" />
                      )}
                    </div>

                    {/* Template Info */}
                    <div className="flex-1 min-w-0">
                      <h3 className="text-lg font-semibold text-[#5E5873] mb-1.5 leading-tight">
                        {template.name}
                      </h3>
                      {template.description && (
                        <p className="text-[15px] text-[#6E6B7B] leading-relaxed mb-3">
                          {template.description}
                        </p>
                      )}
                      <span
                        className={`
                          inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide
                          ${selectedTemplateId === template.id
                            ? 'bg-[#7367F0] text-white'
                            : 'bg-[rgba(115,103,240,0.12)] text-[#7367F0]'
                          }
                        `}
                      >
                        <FileText size={12} />
                        {template.category || 'réglementaire'}
                      </span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            /* Empty State */
            <div className="text-center py-12">
              <div className="w-16 h-16 mx-auto mb-4 rounded-xl bg-[#F3F2F7] flex items-center justify-center">
                <Search size={32} className="text-[#B9B9C3]" />
              </div>
              <h3 className="text-lg font-semibold text-[#5E5873] mb-2">
                Aucun template trouvé
              </h3>
              <p className="text-[#6E6B7B] text-[15px]">
                Essayez avec d'autres mots-clés
              </p>
            </div>
          )}

          {/* Format Selection */}
          {filteredTemplates.length > 0 && (
            <div className="mt-6 pt-6 border-t border-[#EBE9F1]">
              <label className="block text-sm font-semibold text-[#5E5873] mb-3">
                Format du document
              </label>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setSelectedFormat('docx')}
                  className={`
                    flex-1 px-4 py-3.5 rounded-lg border-2 transition-all duration-200
                    ${selectedFormat === 'docx'
                      ? 'border-[#7367F0] bg-[#E8E7FD] text-[#7367F0]'
                      : 'border-[#EBE9F1] hover:border-[#7367F0]/50 text-[#6E6B7B]'
                    }
                  `}
                >
                  <div className="flex items-center justify-center gap-2">
                    <File size={18} />
                    <span className="font-semibold">DOCX</span>
                  </div>
                  <p className="text-xs mt-1 opacity-75">Modifiable avec Word</p>
                </button>
                <button
                  type="button"
                  onClick={() => setSelectedFormat('pdf')}
                  className={`
                    flex-1 px-4 py-3.5 rounded-lg border-2 transition-all duration-200
                    ${selectedFormat === 'pdf'
                      ? 'border-[#7367F0] bg-[#E8E7FD] text-[#7367F0]'
                      : 'border-[#EBE9F1] hover:border-[#7367F0]/50 text-[#6E6B7B]'
                    }
                  `}
                >
                  <div className="flex items-center justify-center gap-2">
                    <FileText size={18} />
                    <span className="font-semibold">PDF</span>
                  </div>
                  <p className="text-xs mt-1 opacity-75">Prêt à imprimer</p>
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-6 md:p-8 border-t border-[#EBE9F1] flex items-center justify-end gap-3 flex-shrink-0">
          <button
            onClick={onClose}
            className="px-6 py-2.5 text-[#6E6B7B] font-semibold hover:text-[#5E5873] transition-colors"
          >
            Annuler
          </button>
          <button
            onClick={handleConfirm}
            disabled={!selectedTemplateId || isLoading}
            className={`
              inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-semibold transition-all duration-200
              ${selectedTemplateId && !isLoading
                ? 'bg-[#7367F0] text-white shadow-[0_2px_8px_rgba(115,103,240,0.3)] hover:bg-[#5E50EE] hover:shadow-[0_4px_12px_rgba(115,103,240,0.4)] hover:-translate-y-0.5'
                : 'bg-[#F3F2F7] text-[#B9B9C3] cursor-not-allowed'
              }
            `}
          >
            {isLoading ? (
              <>
                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                Chargement...
              </>
            ) : (
              <>
                <Check size={18} />
                Confirmer la sélection
              </>
            )}
          </button>
        </div>
      </div>

      {/* Animations CSS */}
      <style>{`
        @keyframes modalSlideIn {
          from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
          }
          to {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }

        @keyframes fadeInUp {
          from {
            opacity: 0;
            transform: translateY(10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .animate-modalSlideIn {
          animation: modalSlideIn 0.3s ease-out;
        }

        .custom-scrollbar::-webkit-scrollbar {
          width: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
          background: #F3F2F7;
          border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: #EBE9F1;
          border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
          background: #7367F0;
        }
      `}</style>
    </div>
  );
};

export default TemplateSelectModal;
