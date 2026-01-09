import React from 'react';

interface VuexyStatCardProps {
  label: string;
  value: React.ReactNode;
  footer?: React.ReactNode;
  icon: React.ReactNode;
  color: 'blue' | 'green' | 'purple' | 'orange';
  delay?: number;
}

export const VuexyStatCard: React.FC<VuexyStatCardProps> = ({
  label,
  value,
  footer,
  icon,
  color,
  delay = 0,
}) => {
  const isCompactValue = typeof value === 'string' && value.length > 22;
  const valueClass = isCompactValue
    ? 'text-base md:text-lg font-semibold text-[#5E5873] leading-tight'
    : 'text-3xl font-bold text-[#5E5873]';
  const colorClasses = {
    blue: {
      bg: 'vx-gradient-bg blue',
      icon: 'bg-gradient-to-br from-[#7367F0] to-[#9055FD]',
    },
    green: {
      bg: 'vx-gradient-bg green',
      icon: 'bg-gradient-to-br from-[#28C76F] to-[#48DA89]',
    },
    purple: {
      bg: 'vx-gradient-bg purple',
      icon: 'bg-gradient-to-br from-[#9055FD] to-[#B085FF]',
    },
    orange: {
      bg: 'vx-gradient-bg orange',
      icon: 'bg-gradient-to-br from-[#FF9F43] to-[#FFB976]',
    },
  };

  return (
    <div
      className={`vx-card vx-card-hover ${colorClasses[color].bg} vx-fade-in`}
      style={{ animationDelay: `${delay}s` }}
    >
      <div className="flex items-center justify-between mb-4">
        <span className="text-sm font-medium text-[#6E6B7B]">{label}</span>
        <div
          className={`w-11 h-11 rounded-lg flex items-center justify-center text-white ${colorClasses[color].icon}`}
        >
          {icon}
        </div>
      </div>
      <div className={valueClass}>{value}</div>
      {footer && <div className="mt-3">{footer}</div>}
    </div>
  );
};
