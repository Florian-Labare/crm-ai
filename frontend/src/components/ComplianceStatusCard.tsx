import React from 'react';
import type { LucideIcon } from 'lucide-react';

interface ComplianceStatusCardProps {
  title: string;
  value: number;
  icon: LucideIcon;
  color: 'green' | 'orange' | 'red' | 'purple' | 'blue' | 'gray';
  subtitle?: string;
  onClick?: () => void;
  isActive?: boolean;
}

const colorConfig = {
  green: {
    bg: 'bg-[#28C76F]/10',
    text: 'text-[#28C76F]',
    iconBg: 'bg-[#28C76F]',
    border: 'border-[#28C76F]',
    shadow: 'shadow-[#28C76F]/20',
  },
  orange: {
    bg: 'bg-[#FF9F43]/10',
    text: 'text-[#FF9F43]',
    iconBg: 'bg-[#FF9F43]',
    border: 'border-[#FF9F43]',
    shadow: 'shadow-[#FF9F43]/20',
  },
  red: {
    bg: 'bg-[#EA5455]/10',
    text: 'text-[#EA5455]',
    iconBg: 'bg-[#EA5455]',
    border: 'border-[#EA5455]',
    shadow: 'shadow-[#EA5455]/20',
  },
  purple: {
    bg: 'bg-[#7367F0]/10',
    text: 'text-[#7367F0]',
    iconBg: 'bg-[#7367F0]',
    border: 'border-[#7367F0]',
    shadow: 'shadow-[#7367F0]/20',
  },
  blue: {
    bg: 'bg-[#00CFE8]/10',
    text: 'text-[#00CFE8]',
    iconBg: 'bg-[#00CFE8]',
    border: 'border-[#00CFE8]',
    shadow: 'shadow-[#00CFE8]/20',
  },
  gray: {
    bg: 'bg-[#F3F2F7]',
    text: 'text-[#6E6B7B]',
    iconBg: 'bg-[#B9B9C3]',
    border: 'border-[#EBE9F1]',
    shadow: 'shadow-gray-200',
  },
};

export const ComplianceStatusCard: React.FC<ComplianceStatusCardProps> = ({
  title,
  value,
  icon: Icon,
  color,
  subtitle,
  onClick,
  isActive = false,
}) => {
  const config = colorConfig[color];

  return (
    <div
      onClick={onClick}
      className={`
        bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)] p-5 transition-all duration-200
        ${onClick ? 'cursor-pointer hover:shadow-lg hover:-translate-y-0.5' : ''}
        ${isActive ? `ring-2 ring-offset-2 ${config.border.replace('border-', 'ring-')}` : ''}
      `}
    >
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-[#6E6B7B] mb-1">{title}</p>
          <p className={`text-3xl font-bold ${config.text}`}>{value}</p>
          {subtitle && (
            <p className="text-xs text-[#B9B9C3] mt-1">{subtitle}</p>
          )}
        </div>
        <div className={`w-12 h-12 rounded-xl ${config.iconBg} flex items-center justify-center text-white shadow-lg ${config.shadow}`}>
          <Icon size={24} />
        </div>
      </div>
    </div>
  );
};

export default ComplianceStatusCard;
