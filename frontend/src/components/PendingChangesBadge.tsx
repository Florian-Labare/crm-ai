import React, { useState, useEffect } from 'react';
import { Bell, AlertTriangle } from 'lucide-react';
import api from '../api/apiClient';

interface PendingChangesData {
  pending_changes: Array<{
    id: number;
    client: {
      id: number;
      full_name: string;
    };
    changes_count: number;
    conflicts_count: number;
    created_at: string;
  }>;
  total_count: number;
  conflicts_total: number;
}

interface PendingChangesBadgeProps {
  onClick?: () => void;
  showDropdown?: boolean;
  onSelectChange?: (id: number) => void;
}

export const PendingChangesBadge: React.FC<PendingChangesBadgeProps> = ({
  onClick,
  showDropdown = true,
  onSelectChange,
}) => {
  const [data, setData] = useState<PendingChangesData | null>(null);
  const [loading, setLoading] = useState(true);
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    loadPendingChanges();
    // Refresh every 30 seconds
    const interval = setInterval(loadPendingChanges, 30000);
    return () => clearInterval(interval);
  }, []);

  const loadPendingChanges = async () => {
    try {
      const response = await api.get('/pending-changes');
      setData(response.data);
    } catch (err) {
      console.error('Erreur chargement pending changes:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleClick = () => {
    if (onClick) {
      onClick();
    } else if (showDropdown) {
      setIsOpen(!isOpen);
    }
  };

  const handleSelectChange = (id: number) => {
    setIsOpen(false);
    if (onSelectChange) {
      onSelectChange(id);
    }
  };

  if (loading || !data || data.total_count === 0) {
    return null;
  }

  return (
    <div className="relative">
      <button
        onClick={handleClick}
        className="relative p-2 hover:bg-[#F8F8F8] rounded-lg transition-colors"
        title={`${data.total_count} modification(s) en attente`}
      >
        <Bell size={20} className="text-[#6E6B7B]" />

        {/* Badge count */}
        <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center px-1 text-xs font-bold text-white bg-[#EA5455] rounded-full">
          {data.total_count > 9 ? '9+' : data.total_count}
        </span>

        {/* Conflict indicator */}
        {data.conflicts_total > 0 && (
          <span className="absolute -bottom-1 -right-1 w-3 h-3 bg-[#FF9F43] rounded-full border-2 border-white" />
        )}
      </button>

      {/* Dropdown */}
      {showDropdown && isOpen && (
        <>
          {/* Overlay to close dropdown */}
          <div
            className="fixed inset-0 z-40"
            onClick={() => setIsOpen(false)}
          />

          <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-[#EBE9F1] z-50 overflow-hidden">
            <div className="px-4 py-3 bg-[#F8F8F8] border-b border-[#EBE9F1]">
              <h3 className="font-semibold text-[#5E5873]">
                Modifications en attente
              </h3>
              <p className="text-xs text-[#6E6B7B] mt-1">
                {data.total_count} modification(s)
                {data.conflicts_total > 0 && (
                  <span className="text-[#FF9F43]">
                    {' '}&bull; {data.conflicts_total} conflit(s)
                  </span>
                )}
              </p>
            </div>

            <div className="max-h-64 overflow-y-auto">
              {data.pending_changes.map((change) => (
                <button
                  key={change.id}
                  onClick={() => handleSelectChange(change.id)}
                  className="w-full px-4 py-3 hover:bg-[#F8F8F8] transition-colors text-left border-b border-[#EBE9F1] last:border-b-0"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-[#5E5873]">
                        {change.client?.full_name || 'Client inconnu'}
                      </p>
                      <p className="text-xs text-[#6E6B7B] mt-0.5">
                        {change.changes_count} champ(s) modifié(s)
                      </p>
                    </div>
                    {change.conflicts_count > 0 && (
                      <div className="flex items-center gap-1 text-[#FF9F43]">
                        <AlertTriangle size={14} />
                        <span className="text-xs font-medium">
                          {change.conflicts_count}
                        </span>
                      </div>
                    )}
                  </div>
                </button>
              ))}
            </div>

            {data.pending_changes.length === 0 && (
              <div className="px-4 py-6 text-center text-[#6E6B7B]">
                Aucune modification en attente
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
};

export default PendingChangesBadge;
