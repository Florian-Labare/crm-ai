import React, { useState } from 'react';
import { ChevronDown, Plus } from 'lucide-react';

interface VuexyCollapsibleSectionProps {
  title: string;
  subtitle?: string;
  count: number;
  icon: React.ReactNode;
  iconGradient: string; // ex: "from-[#FF9F43] to-[#FFB976]"
  borderColor: string; // ex: "#FF9F43"
  children: React.ReactNode;
  defaultOpen?: boolean;
  onAdd?: () => void;
}

export const VuexyCollapsibleSection: React.FC<VuexyCollapsibleSectionProps> = ({
  title,
  subtitle,
  count,
  icon,
  iconGradient,
  borderColor,
  children,
  defaultOpen = false,
  onAdd,
}) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <div
      className="bg-white rounded-xl shadow-md overflow-hidden mb-4"
      style={{ borderLeft: `4px solid ${borderColor}` }}
    >
      {/* Header */}
      <div
        className="px-6 py-4 flex items-center justify-between cursor-pointer hover:bg-[#F3F2F7] transition-colors"
        onClick={() => setIsOpen(!isOpen)}
      >
        <div className="flex items-center gap-4">
          {/* Icon */}
          <div
            className={`w-12 h-12 rounded-lg bg-gradient-to-br ${iconGradient} flex items-center justify-center text-white shadow-md`}
          >
            {icon}
          </div>
          {/* Title & Count */}
          <div>
            <h3 className="text-lg font-semibold text-[#5E5873]">{title}</h3>
            <div className="text-sm text-[#6E6B7B]">
              {count} {count > 1 ? 'éléments' : 'élément'}
              {subtitle && <span className="ml-2 text-xs">({subtitle})</span>}
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-3">
          {onAdd && (
            <button
              className="bg-[#7367F0] hover:bg-[#5E50EE] text-white px-4 py-2 rounded-lg font-semibold text-sm flex items-center gap-2 transition-all hover:-translate-y-0.5"
              onClick={(e) => {
                e.stopPropagation();
                onAdd();
              }}
            >
              <Plus size={16} />
              Ajouter
            </button>
          )}
          <div
            className={`w-8 h-8 rounded-lg bg-[#F3F2F7] flex items-center justify-center transition-transform duration-200 ${
              isOpen ? 'rotate-180' : ''
            }`}
          >
            <ChevronDown size={18} className="text-[#5E5873]" />
          </div>
        </div>
      </div>

      {/* Content */}
      <div
        className={`overflow-hidden transition-all duration-300 ease-in-out ${
          isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'
        }`}
      >
        <div className="px-6 pb-6 pt-2">{children}</div>
      </div>
    </div>
  );
};

export default VuexyCollapsibleSection;
