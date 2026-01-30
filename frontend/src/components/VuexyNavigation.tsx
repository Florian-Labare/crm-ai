import React, { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { LogOut } from 'lucide-react';
import { PendingChangesBadge } from './PendingChangesBadge';
import { ReviewChangesModal } from './ReviewChangesModal';

export const VuexyNavigation: React.FC = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const isHomePage = location.pathname === '/';
  const isAuthPage = ['/login', '/register'].includes(location.pathname);
  const [selectedPendingChangeId, setSelectedPendingChangeId] = useState<number | null>(null);

  if (isHomePage || isAuthPage) return null;

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="bg-white shadow-sm sticky top-0 z-50 border-b border-[#EBE9F1]">
      <div className="max-w-7xl mx-auto px-6 py-4">
        <div className="flex justify-between items-center">
          {/* Logo */}
          <a href="/" className="flex items-center space-x-3 group">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-xl font-bold shadow-md shadow-purple-500/30">
              🎧
            </div>
            <span className="text-xl font-bold text-[#5E5873] group-hover:text-[#7367F0] transition-colors">
              Whisper CRM
            </span>
          </a>

          {/* User Menu */}
          {user && (
            <div className="flex items-center gap-4">
              {/* Pending Changes Badge */}
              <PendingChangesBadge
                onSelectChange={(id) => setSelectedPendingChangeId(id)}
              />

              <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-[#F3F2F7]">
                <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-sm font-semibold">
                  {user.name?.charAt(0).toUpperCase() || 'U'}
                </div>
                <span className="text-sm font-semibold text-[#5E5873]">{user.name}</span>
              </div>

              <button
                onClick={handleLogout}
                className="flex items-center gap-2 px-4 py-2.5 rounded-lg text-[#EA5455] hover:bg-[#EA5455]/10 font-semibold transition-all duration-200"
              >
                <LogOut size={18} />
                Déconnexion
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Review Changes Modal */}
      {selectedPendingChangeId && (
        <ReviewChangesModal
          pendingChangeId={selectedPendingChangeId}
          onClose={() => setSelectedPendingChangeId(null)}
          onApplied={() => {
            setSelectedPendingChangeId(null);
            // Optionally refresh the page or navigate to client
          }}
        />
      )}
    </header>
  );
};
