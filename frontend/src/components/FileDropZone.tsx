import React, { useState, useRef, useCallback } from 'react';
import { Upload, FileText, AlertCircle, X } from 'lucide-react';

interface FileDropZoneProps {
  onFileSelect: (file: File) => void;
  accept?: string[];
  maxSize?: number;
  label?: string;
  disabled?: boolean;
  selectedFile?: File | null;
  onClear?: () => void;
}

export const FileDropZone: React.FC<FileDropZoneProps> = ({
  onFileSelect,
  accept = ['pdf', 'jpg', 'jpeg', 'png'],
  maxSize = 10485760, // 10MB par défaut
  label = 'Glissez un fichier ici ou cliquez pour sélectionner',
  disabled = false,
  selectedFile = null,
  onClear,
}) => {
  const [isDragging, setIsDragging] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const acceptedExtensions = accept.map(ext => ext.toLowerCase());
  const acceptString = accept.map(ext => `.${ext}`).join(',');
  const maxSizeMB = Math.round(maxSize / 1048576);

  const validateFile = useCallback((file: File): string | null => {
    // Vérifier l'extension
    const fileName = file.name.toLowerCase();
    const extension = fileName.split('.').pop() || '';
    if (!acceptedExtensions.includes(extension)) {
      return `Format non accepté. Formats acceptés : ${accept.map(e => e.toUpperCase()).join(', ')}`;
    }

    // Vérifier la taille
    if (file.size > maxSize) {
      return `Fichier trop volumineux. Taille maximale : ${maxSizeMB} Mo`;
    }

    return null;
  }, [acceptedExtensions, accept, maxSize, maxSizeMB]);

  const handleFile = useCallback((file: File) => {
    setError(null);
    const validationError = validateFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }
    onFileSelect(file);
  }, [validateFile, onFileSelect]);

  const handleDragEnter = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (!disabled) {
      setIsDragging(true);
    }
  }, [disabled]);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  }, []);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);

    if (disabled) return;

    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
      handleFile(files[0]);
    }
  }, [disabled, handleFile]);

  const handleInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      handleFile(files[0]);
    }
    // Reset input pour permettre de re-sélectionner le même fichier
    if (inputRef.current) {
      inputRef.current.value = '';
    }
  }, [handleFile]);

  const handleClick = useCallback(() => {
    if (!disabled && inputRef.current) {
      inputRef.current.click();
    }
  }, [disabled]);

  const handleClear = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    setError(null);
    if (onClear) {
      onClear();
    }
  }, [onClear]);

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / 1048576).toFixed(1)} Mo`;
  };

  return (
    <div className="w-full">
      <div
        onClick={handleClick}
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        className={`
          relative border-2 border-dashed rounded-xl p-6 text-center transition-all duration-200 cursor-pointer
          ${disabled
            ? 'bg-[#F3F2F7] border-[#EBE9F1] cursor-not-allowed opacity-60'
            : isDragging
              ? 'border-[#00CFE8] bg-[#00CFE8]/5'
              : error
                ? 'border-[#EA5455] bg-[#EA5455]/5'
                : 'border-[#EBE9F1] hover:border-[#7367F0] hover:bg-[#7367F0]/5'
          }
        `}
      >
        <input
          ref={inputRef}
          type="file"
          accept={acceptString}
          onChange={handleInputChange}
          disabled={disabled}
          className="hidden"
        />

        {selectedFile ? (
          // Fichier sélectionné
          <div className="flex items-center justify-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-[#7367F0]/10 flex items-center justify-center text-[#7367F0]">
              <FileText size={24} />
            </div>
            <div className="text-left flex-1 min-w-0">
              <p className="text-[#5E5873] font-medium truncate">{selectedFile.name}</p>
              <p className="text-xs text-[#6E6B7B]">{formatFileSize(selectedFile.size)}</p>
            </div>
            {onClear && (
              <button
                onClick={handleClear}
                className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all"
                title="Supprimer"
              >
                <X size={16} />
              </button>
            )}
          </div>
        ) : (
          // Zone de drop vide
          <>
            <div className={`
              w-12 h-12 mx-auto mb-3 rounded-lg flex items-center justify-center
              ${isDragging ? 'bg-[#00CFE8]/10 text-[#00CFE8]' : 'bg-[#F3F2F7] text-[#B9B9C3]'}
            `}>
              <Upload size={24} />
            </div>
            <p className={`text-sm mb-1 ${isDragging ? 'text-[#00CFE8]' : 'text-[#6E6B7B]'}`}>
              {isDragging ? 'Déposez le fichier ici' : label}
            </p>
            <p className="text-xs text-[#B9B9C3]">
              Formats acceptés : {accept.map(e => e.toUpperCase()).join(', ')} (max {maxSizeMB} Mo)
            </p>
          </>
        )}
      </div>

      {/* Message d'erreur */}
      {error && (
        <div className="mt-2 flex items-center gap-2 text-[#EA5455]">
          <AlertCircle size={16} />
          <span className="text-sm">{error}</span>
        </div>
      )}
    </div>
  );
};

export default FileDropZone;
