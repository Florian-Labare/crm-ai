import React, { useState, useRef, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ChevronRight, Home, MoreHorizontal } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { usePage } from '../contexts/PageContext';
import { PendingChangesBadge } from './PendingChangesBadge';
import { ReviewChangesModal } from './ReviewChangesModal';

export const VuexyNavigation: React.FC = () => {
  const location = useLocation();
  const { user } = useAuth();
  const { title, actions, dropdownActions, breadcrumbs } = usePage();
  const isAuthPage = ['/login', '/register'].includes(location.pathname);
  const [selectedPendingChangeId, setSelectedPendingChangeId] = useState<number | null>(null);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Fermer le dropdown sur clic extérieur
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // Fermer le dropdown lors d'un changement de route
  useEffect(() => {
    setDropdownOpen(false);
  }, [location.pathname]);

  if (isAuthPage) return null;

  return (
    <>
      <header className="bg-white border-b border-[#EBE9F1] sticky top-0 z-40 h-14 flex items-center px-6 gap-4">

        {/* Breadcrumbs */}
        <nav className="flex items-center gap-1 text-sm min-w-0">
          <Link to="/" className="flex-shrink-0 text-[#B9B9C3] hover:text-[#7367F0] transition-colors">
            <Home size={15} />
          </Link>

          {breadcrumbs.map((crumb, i) => (
            <React.Fragment key={i}>
              <ChevronRight size={13} className="flex-shrink-0 text-[#D8D6DE]" />
              {crumb.path ? (
                <Link
                  to={crumb.path}
                  className="flex-shrink-0 text-[#6E6B7B] hover:text-[#7367F0] transition-colors font-medium truncate max-w-[200px]"
                >
                  {crumb.label}
                </Link>
              ) : (
                <span className="font-semibold text-[#5E5873] truncate max-w-[240px]">
                  {crumb.label}
                </span>
              )}
            </React.Fragment>
          ))}

          {breadcrumbs.length === 0 && title && (
            <>
              <ChevronRight size={13} className="flex-shrink-0 text-[#D8D6DE]" />
              <span className="font-semibold text-[#5E5873] truncate max-w-[300px]">{title}</span>
            </>
          )}
        </nav>

        {/* Actions */}
        <div className="ml-auto flex items-center gap-2 flex-shrink-0">
          {actions.map((action, i) => {
            if (action.variant === 'primary') {
              return (
                <button
                  key={i}
                  onClick={action.onClick}
                  className="flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white text-sm font-semibold shadow-sm hover:shadow-md hover:from-[#5E50EE] hover:to-[#7E3FF2] transition-all"
                >
                  {action.icon}
                  {action.label}
                </button>
              );
            }
            if (action.variant === 'danger') {
              return (
                <button
                  key={i}
                  onClick={action.onClick}
                  className="flex items-center gap-1.5 px-4 py-1.5 rounded-lg border border-[#EA5455] text-[#EA5455] text-sm font-semibold hover:bg-[#EA5455]/10 transition-all"
                >
                  {action.icon}
                  {action.label}
                </button>
              );
            }
            return (
              <button
                key={i}
                onClick={action.onClick}
                className="flex items-center gap-1.5 px-4 py-1.5 rounded-lg border border-[#D8D6DE] text-[#6E6B7B] text-sm font-semibold hover:bg-[#F3F2F7] hover:text-[#7367F0] hover:border-[#7367F0] transition-all"
              >
                {action.icon}
                {action.label}
              </button>
            );
          })}

          {/* Dropdown "..." pour actions secondaires */}
          {dropdownActions.length > 0 && (
            <div className="relative" ref={dropdownRef}>
              <button
                onClick={() => setDropdownOpen((v) => !v)}
                className={`p-1.5 rounded-lg border text-sm transition-all ${
                  dropdownOpen
                    ? 'border-[#7367F0] bg-[#F3F2F7] text-[#7367F0]'
                    : 'border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7] hover:border-[#7367F0] hover:text-[#7367F0]'
                }`}
                title="Plus d'actions"
              >
                <MoreHorizontal size={16} />
              </button>

              {dropdownOpen && (
                <div className="absolute right-0 top-full mt-1.5 bg-white border border-[#EBE9F1] rounded-xl shadow-xl z-50 min-w-[180px] py-1 overflow-hidden">
                  {dropdownActions.map((action, i) => (
                    <React.Fragment key={i}>
                      {action.separator && i > 0 && (
                        <div className="my-1 border-t border-[#F1F0F5]" />
                      )}
                      <button
                        onClick={() => { action.onClick(); setDropdownOpen(false); }}
                        className={`w-full flex items-center gap-2.5 px-4 py-2 text-sm font-medium transition-colors ${
                          action.danger
                            ? 'text-[#EA5455] hover:bg-[#EA5455]/5'
                            : 'text-[#5E5873] hover:bg-[#F3F2F7] hover:text-[#7367F0]'
                        }`}
                      >
                        {action.icon}
                        {action.label}
                      </button>
                    </React.Fragment>
                  ))}
                </div>
              )}
            </div>
          )}

          {user && <PendingChangesBadge onSelectChange={(id) => setSelectedPendingChangeId(id)} />}
        </div>
      </header>

      {selectedPendingChangeId && (
        <ReviewChangesModal
          pendingChangeId={selectedPendingChangeId}
          onClose={() => setSelectedPendingChangeId(null)}
          onApplied={() => setSelectedPendingChangeId(null)}
        />
      )}
    </>
  );
};
