import React, { useMemo, useState, useEffect, useRef } from "react";
import { NavLink, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "../contexts/AuthContext";
import {
  LayoutGrid,
  Users,
  UserPlus,
  Calendar,
  ChevronDown,
  Upload,
  ShieldCheck,
  Sliders,
  Menu,
  ChevronLeft,
  LogOut,
  User,
  ChevronUp,
  Shield,
  TrendingUp,
} from "lucide-react";

type MenuItem = {
  label: string;
  path?: string;
  icon?: React.ReactNode;
  children?: MenuItem[];
  adminOnly?: boolean;
  hideForRoles?: string[];
  isComingSoon?: boolean;
};

const SIDEBAR_COLLAPSED_KEY = "sidebar_collapsed";
const AVATAR_COLOR_KEY = "profile_avatar_color";

const AVATAR_GRADIENTS: Record<string, string> = {
  purple: "from-[#7367F0] to-[#9055FD]",
  blue:   "from-[#00CFE8] to-[#1E9BCE]",
  green:  "from-[#28C76F] to-[#48DA89]",
  orange: "from-[#FF9F43] to-[#FFBE76]",
  rose:   "from-[#EA5455] to-[#F08182]",
  slate:  "from-[#82868B] to-[#A8AAAE]",
};

export const VuexySidebar: React.FC = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const { isAdmin, isSuperAdmin, user, logout } = useAuth();
  const isAuthPage = ["/login", "/register"].includes(location.pathname);

  const [collapsed, setCollapsed] = useState(() => {
    const saved = localStorage.getItem(SIDEBAR_COLLAPSED_KEY);
    return saved === "true";
  });

  const [profileOpen, setProfileOpen] = useState(false);
  const profileRef = useRef<HTMLDivElement>(null);

  const [avatarColor, setAvatarColor] = useState<string>(
    () => localStorage.getItem(AVATAR_COLOR_KEY) ?? "purple"
  );
  const avatarGradient = AVATAR_GRADIENTS[avatarColor] ?? AVATAR_GRADIENTS.purple;

  // Sync avatar color if changed in ProfilePage (same tab via custom event)
  useEffect(() => {
    const handler = () => setAvatarColor(localStorage.getItem(AVATAR_COLOR_KEY) ?? "purple");
    window.addEventListener("avatar-color-changed", handler);
    return () => window.removeEventListener("avatar-color-changed", handler);
  }, []);

  useEffect(() => {
    localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(collapsed));
  }, [collapsed]);

  // Close profile popover on outside click
  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (profileRef.current && !profileRef.current.contains(e.target as Node)) {
        setProfileOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  const handleLogout = () => {
    logout();
    navigate("/login");
  };

  const menu: MenuItem[] = useMemo(
    () => [
      {
        label: "Tableau de bord",
        path: "/",
        icon: <LayoutGrid size={20} />,
      },
      {
        label: "Clients",
        icon: <Users size={20} />,
        children: [
          { label: "Liste des clients", path: "/clients", icon: <Users size={16} /> },
          { label: "Nouveau client", path: "/clients/new", icon: <UserPlus size={16} /> },
          { label: "Imports", path: "/import", adminOnly: true, icon: <Upload size={16} /> },
        ],
      },
      {
        label: "Rendez-vous",
        path: "/der/new",
        icon: <Calendar size={20} />,
      },
      {
        label: "Conformité",
        path: "/compliance-dashboard",
        icon: <ShieldCheck size={20} />,
      },
      {
        label: "Production",
        path: "/production",
        icon: <TrendingUp size={20} />,
        adminOnly: true,
      },
      {
        label: "Paramètres",
        icon: <Sliders size={20} />,
        children: [
          { label: "Mon cabinet", path: "/settings/cabinet" },
        ],
        adminOnly: true,
      },
    ],
    [isSuperAdmin]
  );

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    Clients: true,
    Paramètres: false,
  });

  if (isAuthPage) return null;

  const toggleSection = (label: string) => {
    if (collapsed) {
      setCollapsed(false);
      setOpenSections((prev) => ({ ...prev, [label]: true }));
    } else {
      setOpenSections((prev) => ({ ...prev, [label]: !prev[label] }));
    }
  };

  const renderLink = (item: MenuItem, depth = 0) => {
    if (item.adminOnly && !isAdmin) return null;
    if (item.hideForRoles && user?.team_role && item.hideForRoles.includes(user.team_role)) return null;
    if (!item.path) return null;
    return (
      <NavLink
        key={`${item.label}-${item.path}`}
        to={item.path}
        title={collapsed ? item.label : undefined}
        className={({ isActive }) =>
          [
            "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-all duration-200",
            depth === 0 ? "text-[#5E5873]" : "text-[#6E6B7B]",
            isActive
              ? "bg-[#7367F0]/10 text-[#7367F0]"
              : "hover:bg-[#F3F2F7] hover:text-[#7367F0]",
            collapsed && depth === 0 ? "justify-center" : "",
          ].join(" ")
        }
      >
        {item.icon && <span className="text-[#7367F0]">{item.icon}</span>}
        {!collapsed && <span>{item.label}</span>}
        {!collapsed && item.isComingSoon && (
          <span className="ml-auto rounded-full bg-[#F3F2F7] px-2 py-0.5 text-[10px] font-semibold text-[#6E6B7B]">
            Bientôt
          </span>
        )}
      </NavLink>
    );
  };

  const renderComingSoon = (item: MenuItem, depth = 0) => {
    if (item.adminOnly && !isAdmin) return null;
    if (item.hideForRoles && user?.team_role && item.hideForRoles.includes(user.team_role)) return null;
    if (collapsed && depth > 0) return null;
    return (
      <div
        key={`${item.label}-soon`}
        title={collapsed ? item.label : undefined}
        className={[
          "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-[#B9B9C3]",
          collapsed && depth === 0 ? "justify-center" : "",
        ].join(" ")}
      >
        {item.icon && <span className="text-[#B9B9C3]">{item.icon}</span>}
        {!collapsed && <span>{item.label}</span>}
        {!collapsed && (
          <span className="ml-auto rounded-full bg-[#F3F2F7] px-2 py-0.5 text-[10px] font-semibold text-[#6E6B7B]">
            Bientôt
          </span>
        )}
      </div>
    );
  };

  const initials = user
    ? `${user.firstname?.charAt(0) ?? ""}${user.name?.charAt(0) ?? ""}`.toUpperCase() || "U"
    : "U";
  const displayName = user ? `${user.firstname ?? ""} ${user.name ?? ""}`.trim() : "";
  const displayEmail = user?.email ?? "";

  return (
    <aside
      className={`hidden lg:flex lg:flex-col bg-white border-r border-[#EBE9F1] h-screen sticky top-0 transition-all duration-300 ${
        collapsed ? "lg:w-20" : "lg:w-64"
      }`}
    >
      {/* Logo */}
      <div className={`flex-shrink-0 border-b border-[#EBE9F1] ${collapsed ? "px-3 py-4" : "px-6 py-5"}`}>
        <div className="flex items-center gap-3">
          {user?.current_team_logo_url ? (
            <img
              src={user.current_team_logo_url}
              alt="Logo cabinet"
              className="w-9 h-9 object-contain rounded-lg flex-shrink-0"
            />
          ) : (
            <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-lg font-bold shadow-md shadow-purple-500/30 flex-shrink-0">
              🎧
            </div>
          )}
          {!collapsed && (
            <div>
              <div className="text-base font-bold text-[#5E5873] leading-tight">
                {user?.current_team_name ?? "Whisper CRM"}
              </div>
              <div className="text-xs text-[#B9B9C3]">CRM vocal intelligent</div>
            </div>
          )}
        </div>
      </div>

      {/* Bouton collapse */}
      <div className={`flex-shrink-0 border-b border-[#EBE9F1] px-3 py-2`}>
        <button
          onClick={() => setCollapsed(!collapsed)}
          className={`flex items-center gap-2 px-3 py-2 rounded-lg text-[#6E6B7B] hover:bg-[#F3F2F7] hover:text-[#7367F0] transition-all duration-200 w-full ${
            collapsed ? "justify-center" : ""
          }`}
          title={collapsed ? "Ouvrir le menu" : "Réduire le menu"}
        >
          {collapsed ? <Menu size={18} /> : <><ChevronLeft size={18} /><span className="text-sm font-medium">Réduire</span></>}
        </button>
      </div>

      {/* Menu — scrollable, limité à la hauteur disponible */}
      <div className={`flex-1 overflow-y-auto py-3 space-y-1 min-h-0 ${collapsed ? "px-2" : "px-3"}`}>
        {menu.map((section) => {
          if (section.adminOnly && !isAdmin) return null;
          if (section.hideForRoles && user?.team_role && section.hideForRoles.includes(user.team_role)) return null;

          if (!section.children?.length) {
            return (
              <div key={section.label}>
                {section.isComingSoon ? renderComingSoon(section) : renderLink(section)}
              </div>
            );
          }

          const isOpen = openSections[section.label] ?? false;

          if (collapsed) {
            return (
              <div key={section.label} className="relative group">
                <button
                  onClick={() => toggleSection(section.label)}
                  className="w-full flex items-center justify-center p-3 rounded-lg hover:bg-[#F3F2F7] transition-colors text-[#7367F0]"
                  title={section.label}
                >
                  {section.icon}
                </button>
                <div className="absolute left-full top-0 ml-2 hidden group-hover:block z-50">
                  <div className="bg-[#5E5873] text-white text-xs font-semibold px-3 py-2 rounded-lg shadow-lg whitespace-nowrap">
                    {section.label}
                  </div>
                </div>
              </div>
            );
          }

          return (
            <div key={section.label} className="space-y-0.5">
              <button
                onClick={() => toggleSection(section.label)}
                className="w-full flex items-center justify-between text-left px-3 py-2 rounded-lg hover:bg-[#F3F2F7] transition-colors"
              >
                <div className="flex items-center gap-3 text-sm font-semibold text-[#5E5873]">
                  <span className="text-[#7367F0]">{section.icon}</span>
                  <span>{section.label}</span>
                </div>
                <ChevronDown
                  size={15}
                  className={`text-[#B9B9C3] transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
              </button>

              {isOpen && (
                <div className="ml-4 space-y-0.5">
                  {section.children.map((child) =>
                    child.isComingSoon || !child.path ? renderComingSoon(child, 1) : renderLink(child, 1)
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Section Administration (super admin uniquement) */}
      {isSuperAdmin && (
        <div className="space-y-0.5">
          {collapsed ? (
            <div className="relative group">
              <NavLink
                to="/admin"
                title="Administration"
                className={({ isActive }) =>
                  `w-full flex items-center justify-center p-3 rounded-lg transition-colors ${isActive ? 'text-red-600 bg-red-50' : 'text-red-500 hover:bg-red-50'}`
                }
              >
                <Shield size={20} />
              </NavLink>
              <div className="absolute left-full top-0 ml-2 hidden group-hover:block z-50">
                <div className="bg-[#5E5873] text-white text-xs font-semibold px-3 py-2 rounded-lg shadow-lg whitespace-nowrap">
                  Administration
                </div>
              </div>
            </div>
          ) : (
            <>
              <div className="px-3 pt-3 pb-1">
                <div className="text-[10px] font-bold text-[#B9B9C3] uppercase tracking-widest">Administration</div>
              </div>
              <NavLink
                to="/admin"
                className={({ isActive }) =>
                  `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-all duration-200 ${
                    isActive ? 'bg-red-50 text-red-600' : 'text-red-500 hover:bg-red-50 hover:text-red-600'
                  }`
                }
              >
                <Shield size={16} className="text-red-500" />
                <span>Panneau admin</span>
              </NavLink>
            </>
          )}
        </div>
      )}

      {/* Profil utilisateur — sticky en bas */}
      <div className="flex-shrink-0 border-t border-[#EBE9F1] p-3 relative" ref={profileRef}>
        {/* Popover au-dessus */}
        {profileOpen && (
          <div className="absolute bottom-full mb-2 left-3 right-3 bg-white border border-[#EBE9F1] rounded-xl shadow-2xl z-50 overflow-hidden">
            {/* Header */}
            <div className="px-4 pt-4 pb-3">
              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl overflow-hidden flex-shrink-0 shadow-md ${!user?.avatar_url ? `bg-gradient-to-br ${avatarGradient}` : ''}`}>
                  {user?.avatar_url ? (
                    <img src={user.avatar_url} alt="Avatar" className="w-full h-full object-cover" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-white text-sm font-bold">{initials}</div>
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-sm font-semibold text-[#5E5873] truncate leading-tight">{displayName}</div>
                  <div className="text-xs text-[#B9B9C3] truncate">{displayEmail}</div>
                </div>
              </div>
              {user?.current_team_name && (
                <div className="mt-2.5 flex items-center gap-1.5 px-2.5 py-1.5 bg-[#F3F2F7] rounded-lg">
                  <div className="w-1.5 h-1.5 rounded-full bg-[#28C76F] flex-shrink-0" />
                  <span className="text-xs text-[#6E6B7B] font-medium truncate">{user.current_team_name}</span>
                  {user?.team_role && (
                    <span className="ml-auto text-[10px] font-semibold text-[#B9B9C3] uppercase tracking-wide flex-shrink-0">
                      {({ owner: 'Propriétaire', admin: 'Admin', mia: 'MIA', secretaire: 'Secrétaire' } as Record<string, string>)[user.team_role] ?? user.team_role}
                    </span>
                  )}
                </div>
              )}
            </div>

            <div className="border-t border-[#EBE9F1]" />

            <div className="py-1.5">
              <button
                onClick={() => { setProfileOpen(false); navigate('/profile'); }}
                className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#5E5873] hover:bg-[#F3F2F7] hover:text-[#7367F0] transition-colors font-medium"
              >
                <User size={15} className="text-[#7367F0]" />
                Mon profil
              </button>
              {isAdmin && (
                <button
                  onClick={() => { setProfileOpen(false); navigate('/settings/cabinet'); }}
                  className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#5E5873] hover:bg-[#F3F2F7] hover:text-[#7367F0] transition-colors font-medium"
                >
                  <ChevronDown size={15} className="text-[#7367F0] -rotate-90" />
                  Paramètres du cabinet
                </button>
              )}
            </div>

            <div className="border-t border-[#EBE9F1]" />

            <div className="py-1.5">
              <button
                onClick={handleLogout}
                className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#EA5455] hover:bg-[#EA5455]/10 transition-colors font-medium"
              >
                <LogOut size={15} />
                Déconnexion
              </button>
            </div>
          </div>
        )}

        {/* Trigger */}
        <button
          onClick={() => setProfileOpen((v) => !v)}
          className={`w-full flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-[#F3F2F7] transition-colors ${
            collapsed ? "justify-center" : ""
          } ${profileOpen ? "bg-[#F3F2F7]" : ""}`}
          title={collapsed ? displayName : undefined}
        >
          <div className={`w-8 h-8 rounded-lg overflow-hidden flex-shrink-0 ${!user?.avatar_url ? `bg-gradient-to-br ${avatarGradient}` : ''}`}>
            {user?.avatar_url ? (
              <img src={user.avatar_url} alt="Avatar" className="w-full h-full object-cover" />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-white text-xs font-bold">{initials}</div>
            )}
          </div>
          {!collapsed && (
            <>
              <div className="flex-1 text-left min-w-0">
                <div className="text-sm font-semibold text-[#5E5873] truncate leading-tight">{displayName}</div>
                <div className="text-xs text-[#B9B9C3] truncate">{displayEmail}</div>
              </div>
              <ChevronUp
                size={15}
                className={`text-[#B9B9C3] flex-shrink-0 transition-transform ${profileOpen ? "rotate-180" : ""}`}
              />
            </>
          )}
        </button>
      </div>
    </aside>
  );
};

export default VuexySidebar;
