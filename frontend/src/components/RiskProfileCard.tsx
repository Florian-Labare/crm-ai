import React from 'react';

interface RiskProfileCardProps {
  score: number;
  profil: string;
  recommandation: string;
}

export const RiskProfileCard: React.FC<RiskProfileCardProps> = ({ score, profil, recommandation }) => {
  const getColorByProfile = (profil: string) => {
    switch (profil) {
      case 'Prudent':
        return { stroke: '#10b981', text: 'text-green-600' };
      case 'Modéré':
        return { stroke: '#3b82f6', text: 'text-blue-600' };
      case 'Dynamique':
        return { stroke: '#f97316', text: 'text-orange-600' };
      default:
        return { stroke: '#6b7280', text: 'text-gray-600' };
    }
  };

  const colors = getColorByProfile(profil);
  const radius = 70;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (score / 100) * circumference;

  return (
    <div className="bg-white rounded-lg shadow p-6 border border-gray-200">
      <h3 className="text-lg font-semibold mb-4 text-gray-800">Profil de risque</h3>

      <div className="flex flex-col items-center">
        <div className="relative w-48 h-48 mb-4">
          <svg className="w-full h-full transform -rotate-90">
            <circle
              cx="96"
              cy="96"
              r={radius}
              stroke="#e5e7eb"
              strokeWidth="12"
              fill="none"
            />
            <circle
              cx="96"
              cy="96"
              r={radius}
              stroke={colors.stroke}
              strokeWidth="12"
              fill="none"
              strokeDasharray={circumference}
              strokeDashoffset={offset}
              strokeLinecap="round"
              style={{ transition: 'stroke-dashoffset 1s ease-in-out' }}
            />
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <span className={`text-4xl font-bold ${colors.text}`}>{score}</span>
            <span className="text-sm text-gray-500">/ 100</span>
          </div>
        </div>

        <div className={`text-2xl font-semibold mb-3 ${colors.text}`}>
          {profil}
        </div>

        {recommandation && (
          <div className="text-sm text-gray-600 text-center leading-relaxed">
            <div dangerouslySetInnerHTML={{ __html: recommandation.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') }} />
          </div>
        )}
      </div>
    </div>
  );
};
