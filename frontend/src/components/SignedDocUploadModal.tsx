import React, { useState } from 'react';
import { Upload, X, RefreshCw, Calendar, Tag } from 'lucide-react';
import { FileDropZone } from './FileDropZone';

interface SignedDocUploadModalProps {
  isOpen: boolean;
  onClose: () => void;
  onUpload: (data: {
    file: File;
    tags: string[];
    customLabel: string;
    expiresAt: string;
  }) => Promise<void>;
  availableTags: Record<string, string>;
  isUploading: boolean;
}

export const SignedDocUploadModal: React.FC<SignedDocUploadModalProps> = ({
  isOpen,
  onClose,
  onUpload,
  availableTags,
  isUploading,
}) => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [selectedTags, setSelectedTags] = useState<string[]>([]);
  const [customLabel, setCustomLabel] = useState('');
  const [expiresAt, setExpiresAt] = useState('');

  const handleClose = () => {
    setSelectedFile(null);
    setSelectedTags([]);
    setCustomLabel('');
    setExpiresAt('');
    onClose();
  };

  const toggleTag = (tag: string) => {
    setSelectedTags(prev =>
      prev.includes(tag) ? prev.filter(t => t !== tag) : [...prev, tag]
    );
  };

  const handleSubmit = async () => {
    if (!selectedFile || selectedTags.length === 0) return;

    await onUpload({
      file: selectedFile,
      tags: selectedTags,
      customLabel,
      expiresAt,
    });

    handleClose();
  };

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      style={{ backgroundColor: 'rgba(94, 88, 115, 0.4)', backdropFilter: 'blur(4px)' }}
      onClick={handleClose}
    >
      <div
        className="bg-white rounded-xl shadow-2xl w-full max-w-lg animate-modalSlideIn"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 border-b border-[#EBE9F1] flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-[#7367F0]/10 flex items-center justify-center text-[#7367F0]">
              <Upload size={20} />
            </div>
            <div>
              <h3 className="font-semibold text-[#5E5873]">Importer un document signe</h3>
              <p className="text-sm text-[#6E6B7B]">Document taggue par besoin</p>
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
        <div className="p-6 space-y-5">
          {/* Zone de drop */}
          <FileDropZone
            onFileSelect={(file) => setSelectedFile(file)}
            accept={['pdf', 'jpg', 'jpeg', 'png']}
            maxSize={10485760}
            label="Glissez un document signe ici ou cliquez pour selectionner"
            selectedFile={selectedFile}
            onClear={() => setSelectedFile(null)}
          />

          {/* Nom personnalise */}
          <div>
            <label className="block text-sm font-medium text-[#5E5873] mb-2">
              Nom du document (optionnel)
            </label>
            <input
              type="text"
              value={customLabel}
              onChange={(e) => setCustomLabel(e.target.value)}
              placeholder="Ex: Lettre de mission sante signee"
              className="w-full px-4 py-2.5 border border-[#EBE9F1] rounded-lg focus:ring-2 focus:ring-[#7367F0]/20 focus:border-[#7367F0] outline-none transition-all text-[#5E5873]"
            />
          </div>

          {/* Selection des tags */}
          <div>
            <label className="flex items-center gap-2 text-sm font-medium text-[#5E5873] mb-2">
              <Tag size={16} />
              Besoins concernes *
            </label>
            <div className="flex flex-wrap gap-2">
              {Object.entries(availableTags).map(([key, label]) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => toggleTag(key)}
                  className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                    selectedTags.includes(key)
                      ? 'bg-[#7367F0] text-white shadow-md'
                      : 'bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EBE9F1]'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
            {selectedTags.length === 0 && (
              <p className="text-xs text-[#EA5455] mt-2">
                Selectionnez au moins un besoin
              </p>
            )}
          </div>

          {/* Date d'expiration */}
          <div>
            <label className="flex items-center gap-2 text-sm font-medium text-[#5E5873] mb-2">
              <Calendar size={16} />
              Date d'expiration (optionnel)
            </label>
            <input
              type="date"
              value={expiresAt}
              onChange={(e) => setExpiresAt(e.target.value)}
              className="w-full px-4 py-2.5 border border-[#EBE9F1] rounded-lg focus:ring-2 focus:ring-[#7367F0]/20 focus:border-[#7367F0] outline-none transition-all text-[#5E5873]"
            />
          </div>
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
            disabled={!selectedFile || selectedTags.length === 0 || isUploading}
            className="px-4 py-2 rounded-lg bg-[#7367F0] text-white hover:bg-[#6558E8] transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {isUploading ? (
              <>
                <RefreshCw size={16} className="animate-spin" />
                Upload en cours...
              </>
            ) : (
              <>
                <Upload size={16} />
                Importer
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

export default SignedDocUploadModal;
