import React, { type ReactNode } from 'react';

interface InfoCardProps {
  title: string;
  icon: ReactNode;
  children: ReactNode;
  color?: 'blue' | 'purple' | 'green' | 'orange' | 'pink' | 'indigo' | 'teal' | 'cyan';
  badge?: string;
  actions?: ReactNode;
}

const colorClasses = {
  blue: {
    bg: 'bg-gradient-to-br from-blue-50 to-blue-100/50',
    iconBg: 'bg-blue-500',
    border: 'border-blue-200',
    text: 'text-blue-700',
  },
  purple: {
    bg: 'bg-gradient-to-br from-purple-50 to-purple-100/50',
    iconBg: 'bg-purple-500',
    border: 'border-purple-200',
    text: 'text-purple-700',
  },
  green: {
    bg: 'bg-gradient-to-br from-emerald-50 to-emerald-100/50',
    iconBg: 'bg-emerald-500',
    border: 'border-emerald-200',
    text: 'text-emerald-700',
  },
  orange: {
    bg: 'bg-gradient-to-br from-orange-50 to-orange-100/50',
    iconBg: 'bg-orange-500',
    border: 'border-orange-200',
    text: 'text-orange-700',
  },
  pink: {
    bg: 'bg-gradient-to-br from-pink-50 to-pink-100/50',
    iconBg: 'bg-pink-500',
    border: 'border-pink-200',
    text: 'text-pink-700',
  },
  indigo: {
    bg: 'bg-gradient-to-br from-indigo-50 to-indigo-100/50',
    iconBg: 'bg-indigo-500',
    border: 'border-indigo-200',
    text: 'text-indigo-700',
  },
  teal: {
    bg: 'bg-gradient-to-br from-teal-50 to-teal-100/50',
    iconBg: 'bg-teal-500',
    border: 'border-teal-200',
    text: 'text-teal-700',
  },
  cyan: {
    bg: 'bg-gradient-to-br from-cyan-50 to-cyan-100/50',
    iconBg: 'bg-cyan-500',
    border: 'border-cyan-200',
    text: 'text-cyan-700',
  },
};

export const InfoCard: React.FC<InfoCardProps> = ({
  title,
  icon,
  children,
  color = 'blue',
  badge,
  actions,
}) => {
  const colors = colorClasses[color];

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden transition-all hover:shadow-md">
      {/* Header */}
      <div className={`${colors.bg} border-b ${colors.border} px-6 py-4`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className={`${colors.iconBg} rounded-lg p-2.5 shadow-sm`}>
              <div className="text-white">
                {icon}
              </div>
            </div>
            <div>
              <h3 className={`text-lg font-semibold ${colors.text}`}>{title}</h3>
              {badge && (
                <span className="inline-block mt-1 px-2 py-0.5 text-xs font-medium bg-white/60 rounded-full text-gray-700">
                  {badge}
                </span>
              )}
            </div>
          </div>
          {actions && <div className="flex items-center space-x-2">{actions}</div>}
        </div>
      </div>

      {/* Content */}
      <div className="px-6 py-5">
        {children}
      </div>
    </div>
  );
};

interface InfoItemProps {
  label: string;
  value: string | number | ReactNode;
  icon?: ReactNode;
  fullWidth?: boolean;
}

export const InfoItem: React.FC<InfoItemProps> = ({ label, value, icon, fullWidth = false }) => (
  <div className={fullWidth ? 'col-span-full' : ''}>
    <dt className="text-sm font-medium text-gray-500 mb-1 flex items-center space-x-1.5">
      {icon && <span className="text-gray-400">{icon}</span>}
      <span>{label}</span>
    </dt>
    <dd className="text-base text-gray-900 font-medium">
      {value || <span className="text-gray-400 font-normal text-sm">Non renseigné</span>}
    </dd>
  </div>
);

interface StatCardProps {
  label: string;
  value: string | number;
  icon: ReactNode;
  color?: 'blue' | 'purple' | 'green' | 'orange';
  trend?: {
    value: string;
    isPositive: boolean;
  };
}

export const StatCard: React.FC<StatCardProps> = ({ label, value, icon, color = 'blue', trend }) => {
  const colors = colorClasses[color];

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-all">
      <div className="flex items-center justify-between">
        <div className="flex-1">
          <p className="text-sm font-medium text-gray-600 mb-1">{label}</p>
          <p className="text-2xl font-bold text-gray-900">{value}</p>
          {trend && (
            <p className={`text-xs mt-2 ${trend.isPositive ? 'text-green-600' : 'text-red-600'}`}>
              {trend.isPositive ? '↑' : '↓'} {trend.value}
            </p>
          )}
        </div>
        <div className={`${colors.iconBg} rounded-xl p-3 shadow-sm`}>
          <div className="text-white">
            {icon}
          </div>
        </div>
      </div>
    </div>
  );
};
