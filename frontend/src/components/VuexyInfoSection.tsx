import React from 'react';

interface VuexyInfoSectionProps {
  title: string;
  icon: React.ReactNode;
  children: React.ReactNode;
}

export const VuexyInfoSection: React.FC<VuexyInfoSectionProps> = ({
  title,
  icon,
  children,
}) => {
  return (
    <div className="vx-card vx-fade-in">
      {/* Section Header */}
      <div className="flex items-center gap-3 mb-6 pb-4 border-b border-[#EBE9F1]">
        <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white">
          {icon}
        </div>
        <h3 className="text-lg font-semibold text-[#5E5873]">{title}</h3>
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
