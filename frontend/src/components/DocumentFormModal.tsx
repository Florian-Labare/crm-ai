import React, { useMemo } from "react";
import { X, FileText, Link as LinkIcon, Info, Save, Loader2 } from "lucide-react";

interface FormField {
  variable: string;
  label: string;
  required?: boolean;
}

interface DocumentFormModalProps {
  templateName: string;
  fields: FormField[];
  values: Record<string, string>;
  onChange: (variable: string, value: string) => void;
  onClose: () => void;
  onSave: () => void;
  onSaveAndGenerate: () => void;
  isLoading?: boolean;
  isSaving?: boolean;
  isGenerating?: boolean;
  error?: string | null;
}

export const DocumentFormModal: React.FC<DocumentFormModalProps> = ({
  templateName,
  fields,
  values,
  onChange,
  onClose,
  onSave,
  onSaveAndGenerate,
  isLoading = false,
  isSaving = false,
  isGenerating = false,
  error,
}) => {
  // Grouper les champs par section (basé sur le préfixe de variable)
  const groupedFields = useMemo(() => {
    const groups: Record<string, FormField[]> = {};

    fields.forEach((field) => {
      // Extraire la section du nom de variable (ex: "clients.nom" -> "clients")
      const parts = field.variable.split('.');
      const section = parts.length > 1 ? parts[0] : 'general';

      if (!groups[section]) {
        groups[section] = [];
      }
      groups[section].push(field);
    });

    return groups;
  }, [fields]);

  // Labels pour les sections
  const sectionLabels: Record<string, string> = {
    clients: "Client",
    conjoints: "Conjoint",
    enfants: "Enfants",
    patrimoine: "Patrimoine",
    revenus: "Revenus",
    charges: "Charges",
    objectifs: "Objectifs",
    general: "Informations",
  };

  // Calculer le nombre de champs remplis
  const filledCount = fields.filter((f) => values[f.variable]?.trim()).length;
  const progressPercent = fields.length > 0 ? Math.round((filledCount / fields.length) * 100) : 0;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-8"
      style={{ backgroundColor: 'rgba(94, 88, 115, 0.4)', backdropFilter: 'blur(4px)' }}
      onClick={onClose}
    >
      {/* Modal */}
      <div
        className="bg-white rounded-xl shadow-2xl w-full max-w-[1200px] max-h-[90vh] flex flex-col animate-modalSlideIn"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 md:p-8 border-b border-[#EBE9F1] flex items-start justify-between flex-shrink-0">
          <div className="flex-1">
            <h2 className="text-2xl font-bold text-[#5E5873] mb-1.5">{templateName}</h2>
            <p className="text-[15px] text-[#6E6B7B]">
              Renseignez les champs liés aux variables du template.
            </p>
          </div>
          <button
            onClick={onClose}
            className="w-9 h-9 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200 hover:rotate-90 flex-shrink-0 ml-4"
          >
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="p-6 md:p-8 overflow-y-auto flex-1 custom-scrollbar">
          {/* Error Message */}
          {error && (
            <div className="mb-6 rounded-lg border-2 border-[#EA5455]/30 bg-[#EA5455]/10 text-[#EA5455] px-4 py-3 text-sm font-medium">
              {error}
            </div>
          )}

          {/* Loading State */}
          {isLoading ? (
            <div className="py-16 text-center">
              <div className="w-12 h-12 mx-auto mb-4 rounded-full bg-[#E8E7FD] flex items-center justify-center">
                <Loader2 size={24} className="text-[#7367F0] animate-spin" />
              </div>
              <p className="text-[#6E6B7B]">Chargement du formulaire...</p>
            </div>
          ) : (
            <div className="space-y-8">
              {Object.entries(groupedFields).map(([section, sectionFields], sectionIndex) => (
                <div key={section}>
                  {/* Section Divider (sauf pour la première section) */}
                  {sectionIndex > 0 && (
                    <div className="flex items-center gap-4 mb-6">
                      <div className="flex-1 h-px bg-[#EBE9F1]" />
                      <span className="text-sm font-semibold text-[#6E6B7B] uppercase tracking-wide px-4 bg-white">
                        {sectionLabels[section] || section}
                      </span>
                      <div className="flex-1 h-px bg-[#EBE9F1]" />
                    </div>
                  )}

                  {/* Section Header pour la première section */}
                  {sectionIndex === 0 && Object.keys(groupedFields).length > 1 && (
                    <div className="flex items-center gap-4 mb-6">
                      <div className="flex-1 h-px bg-[#EBE9F1]" />
                      <span className="text-sm font-semibold text-[#6E6B7B] uppercase tracking-wide px-4 bg-white">
                        {sectionLabels[section] || section}
                      </span>
                      <div className="flex-1 h-px bg-[#EBE9F1]" />
                    </div>
                  )}

                  {/* Fields Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {sectionFields.map((field, index) => {
                      const value = values[field.variable] ?? '';
                      const useTextarea = value.length > 120 || field.label.length > 50;

                      return (
                        <div
                          key={field.variable}
                          className="flex flex-col gap-2"
                          style={{
                            animation: `fadeInUp 0.3s ease-out ${index * 0.05}s both`,
                          }}
                        >
                          <label className="text-sm font-semibold text-[#5E5873] flex items-center gap-1.5">
                            {field.label}
                            {field.required && (
                              <span className="text-[#EA5455]">*</span>
                            )}
                          </label>

                          {useTextarea ? (
                            <textarea
                              value={value}
                              onChange={(e) => onChange(field.variable, e.target.value)}
                              className="w-full min-h-[96px] px-4 py-3.5 border border-[#EBE9F1] rounded-lg text-[#5E5873] bg-white transition-all duration-200 focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)] placeholder:text-[#B9B9C3] resize-none"
                              placeholder="Saisir une réponse"
                            />
                          ) : (
                            <input
                              type="text"
                              value={value}
                              onChange={(e) => onChange(field.variable, e.target.value)}
                              className="w-full px-4 py-3.5 border border-[#EBE9F1] rounded-lg text-[#5E5873] bg-white transition-all duration-200 focus:outline-none focus:border-[#7367F0] focus:ring-[3px] focus:ring-[rgba(115,103,240,0.1)] placeholder:text-[#B9B9C3]"
                              placeholder="Saisir une réponse"
                            />
                          )}

                          <span className="text-[13px] text-[#6E6B7B] flex items-center gap-1.5">
                            <LinkIcon size={12} />
                            {field.variable}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}

              {/* Empty State */}
              {fields.length === 0 && !isLoading && (
                <div className="py-16 text-center">
                  <div className="w-16 h-16 mx-auto mb-4 rounded-xl bg-[#F3F2F7] flex items-center justify-center">
                    <FileText size={32} className="text-[#B9B9C3]" />
                  </div>
                  <h3 className="text-lg font-semibold text-[#5E5873] mb-2">
                    Aucun champ à remplir
                  </h3>
                  <p className="text-[#6E6B7B] text-[15px]">
                    Ce template ne nécessite pas de variables supplémentaires.
                  </p>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-4 md:p-6 border-t border-[#EBE9F1] bg-[#F3F2F7] flex flex-col md:flex-row items-center justify-between gap-4 flex-shrink-0">
          {/* Footer Info */}
          <div className="flex items-center gap-4 text-sm text-[#6E6B7B]">
            <div className="flex items-center gap-2">
              <Info size={16} className="text-[#00CFE8]" />
              <span>
                Les champs marqués d'un <span className="text-[#EA5455]">*</span> sont obligatoires
              </span>
            </div>
            {fields.length > 0 && (
              <div className="hidden md:flex items-center gap-2">
                <div className="w-[120px] h-1.5 bg-[#EBE9F1] rounded-full overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-[#7367F0] to-[#9055FD] transition-all duration-300"
                    style={{ width: `${progressPercent}%` }}
                  />
                </div>
                <span className="text-xs">{filledCount}/{fields.length}</span>
              </div>
            )}
          </div>

          {/* Footer Actions */}
          <div className="flex items-center gap-3 w-full md:w-auto">
            <button
              onClick={onClose}
              className="flex-1 md:flex-initial px-6 py-2.5 text-[#6E6B7B] font-semibold hover:text-[#5E5873] transition-colors"
            >
              Annuler
            </button>
            <button
              onClick={onSave}
              disabled={isSaving || isLoading}
              className={`
                flex-1 md:flex-initial inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg font-semibold transition-all duration-200 border-2
                ${!isSaving && !isLoading
                  ? 'border-[#7367F0] text-[#7367F0] hover:bg-[#E8E7FD]'
                  : 'border-[#EBE9F1] text-[#B9B9C3] cursor-not-allowed'
                }
              `}
            >
              {isSaving ? (
                <Loader2 size={16} className="animate-spin" />
              ) : (
                <Save size={16} />
              )}
              Enregistrer
            </button>
            <button
              onClick={onSaveAndGenerate}
              disabled={isSaving || isLoading || isGenerating}
              className={`
                flex-1 md:flex-initial inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg font-semibold transition-all duration-200
                ${!isSaving && !isLoading && !isGenerating
                  ? 'bg-[#7367F0] text-white shadow-[0_2px_8px_rgba(115,103,240,0.3)] hover:bg-[#5E50EE] hover:shadow-[0_4px_12px_rgba(115,103,240,0.4)] hover:-translate-y-0.5'
                  : 'bg-[#F3F2F7] text-[#B9B9C3] cursor-not-allowed'
                }
              `}
            >
              {(isSaving || isGenerating) ? (
                <>
                  <Loader2 size={16} className="animate-spin" />
                  Traitement...
                </>
              ) : (
                <>
                  <FileText size={16} />
                  Enregistrer et générer
                </>
              )}
            </button>
          </div>
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

export default DocumentFormModal;
