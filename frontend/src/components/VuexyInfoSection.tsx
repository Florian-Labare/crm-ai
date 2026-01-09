import React from 'react';
import { Pencil, Plus } from 'lucide-react';

interface VuexyInfoSectionProps {
  title: string;
  icon: React.ReactNode;
  children: React.ReactNode;
  onEdit?: () => void;
  onAdd?: () => void;
  addLabel?: string;
}

export const VuexyInfoSection: React.FC<VuexyInfoSectionProps> = ({
  title,
  icon,
  children,
  onEdit,
  onAdd,
  addLabel = "Ajouter",
}) => {
  return (
    <div className="vx-card vx-fade-in">
      {/* Section Header */}
      <div className="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-[#EBE9F1]">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white">
            {icon}
          </div>
          <h3 className="text-lg font-semibold text-[#5E5873]">{title}</h3>
        </div>
        <div className="flex items-center gap-2">
          {onAdd && (
            <button
              onClick={onAdd}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-[#7367F0]/10 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
              title={addLabel}
            >
              <Plus size={16} />
              <span className="hidden sm:inline">{addLabel}</span>
            </button>
          )}
          {onEdit && (
            <button
              onClick={onEdit}
              className="w-8 h-8 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
              title="Modifier cette section"
            >
              <Pencil size={16} />
            </button>
          )}
        </div>
      </div>

      {/* Section Content */}
      <div className="space-y-5">
        {children}
      </div>
    </div>
  );
};

interface VuexyInfoRowProps {
  label: string;
  value?: string | React.ReactNode;
  empty?: boolean;
}

export const VuexyInfoRow: React.FC<VuexyInfoRowProps> = ({
  label,
  value,
  empty = false,
}) => {
  return (
    <div className="grid grid-cols-[140px_1fr] gap-4">
      <div className="text-sm font-medium text-[#6E6B7B]">{label}</div>
      <div
        className={`text-[15px] font-medium ${
          empty || !value
            ? 'text-[#B9B9C3] italic'
            : 'text-[#5E5873]'
        }`}
      >
        {empty || !value ? 'Non renseigné' : value}
      </div>
    </div>
  );
};

// Sous-section Vuexy avec design cohérent
interface VuexySubSectionProps {
  title: string;
  icon: React.ReactNode;
  count?: number;
  color: 'cyan' | 'orange' | 'purple' | 'red' | 'green' | 'blue';
  children: React.ReactNode;
  defaultOpen?: boolean;
}

const colorConfig = {
  cyan: {
    gradient: 'from-[#00CFE8] to-[#1DE9B6]',
    bg: 'bg-[#00CFE8]/5',
    border: 'border-[#00CFE8]/20',
    text: 'text-[#00CFE8]',
  },
  orange: {
    gradient: 'from-[#FF9F43] to-[#FFB976]',
    bg: 'bg-[#FF9F43]/5',
    border: 'border-[#FF9F43]/20',
    text: 'text-[#FF9F43]',
  },
  purple: {
    gradient: 'from-[#9055FD] to-[#C084FC]',
    bg: 'bg-[#9055FD]/5',
    border: 'border-[#9055FD]/20',
    text: 'text-[#9055FD]',
  },
  red: {
    gradient: 'from-[#EA5455] to-[#FF6B6B]',
    bg: 'bg-[#EA5455]/5',
    border: 'border-[#EA5455]/20',
    text: 'text-[#EA5455]',
  },
  green: {
    gradient: 'from-[#28C76F] to-[#48DA89]',
    bg: 'bg-[#28C76F]/5',
    border: 'border-[#28C76F]/20',
    text: 'text-[#28C76F]',
  },
  blue: {
    gradient: 'from-[#7367F0] to-[#9055FD]',
    bg: 'bg-[#7367F0]/5',
    border: 'border-[#7367F0]/20',
    text: 'text-[#7367F0]',
  },
};

export const VuexySubSection: React.FC<VuexySubSectionProps> = ({
  title,
  icon,
  count,
  color,
  children,
  defaultOpen = true,
}) => {
  const [isOpen, setIsOpen] = React.useState(defaultOpen);
  const config = colorConfig[color];

  return (
    <div className={`rounded-xl ${config.bg} border ${config.border} overflow-hidden`}>
      {/* Sub-section Header */}
      <div
        className="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-white/50 transition-colors"
        onClick={() => setIsOpen(!isOpen)}
      >
        <div className="flex items-center gap-3">
          <div className={`w-8 h-8 rounded-lg bg-gradient-to-br ${config.gradient} flex items-center justify-center text-white shadow-sm`}>
            {icon}
          </div>
          <span className="font-semibold text-[#5E5873]">{title}</span>
          {count !== undefined && (
            <span className={`text-xs font-medium ${config.text} bg-white px-2 py-0.5 rounded-full`}>
              {count}
            </span>
          )}
        </div>
        <svg
          className={`w-5 h-5 text-[#6E6B7B] transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </div>

      {/* Sub-section Content */}
      <div className={`overflow-hidden transition-all duration-300 ${isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'}`}>
        <div className="px-4 pb-4 pt-2 bg-white/30">
          {children}
        </div>
      </div>
    </div>
  );
};
