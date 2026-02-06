import React, { useMemo, useState, useEffect } from "react";
import { NavLink, useLocation } from "react-router-dom";
import { useAuth } from "../contexts/AuthContext";
import {
  LayoutGrid,
  Users,
  UserPlus,
  Calendar,
  ChevronDown,
  Upload,
  FileText,
  FileStack,
  FolderOpen,
  ClipboardList,
  ShieldCheck,
  Sliders,
  Landmark,
  Building2,
  Coins,
  Database,
  History,
  Mic,
  ListChecks,
  Menu,
  ChevronLeft,
} from "lucide-react";

type MenuItem = {
  label: string;
  path?: string;
  icon?: React.ReactNode;
  children?: MenuItem[];
  adminOnly?: boolean;
  isComingSoon?: boolean;
};

const SIDEBAR_COLLAPSED_KEY = "sidebar_collapsed";

export const VuexySidebar: React.FC = () => {
  const location = useLocation();
  const { isAdmin } = useAuth();
  const isAuthPage = ["/login", "/register"].includes(location.pathname);

  // Etat pour le mode collapsed
  const [collapsed, setCollapsed] = useState(() => {
    const saved = localStorage.getItem(SIDEBAR_COLLAPSED_KEY);
    return saved === "true";
  });

  // Sauvegarder l'etat collapsed dans localStorage
  useEffect(() => {
    localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(collapsed));
  }, [collapsed]);

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
          { label: "Liste des clients", path: "/" },
          { label: "Prospects", isComingSoon: true },
          { label: "Tags / Segments", isComingSoon: true },
          { label: "Nouveau client", path: "/clients/new", icon: <UserPlus size={16} /> },
          { label: "Imports", path: "/import", adminOnly: true, icon: <Upload size={16} /> },
        ],
      },
      {
        label: "Rendez-vous",
        icon: <Calendar size={20} />,
        children: [
          { label: "Historique des RDV", isComingSoon: true, icon: <History size={16} /> },
          { label: "Enregistrements audio", isComingSoon: true, icon: <Mic size={16} /> },
          { label: "Résumés de rendez-vous", isComingSoon: true, icon: <ListChecks size={16} /> },
          { label: "Nouveau RDV", path: "/der/new" },
        ],
      },
      {
        label: "Documents",
        icon: <FileText size={20} />,
        children: [
          { label: "Génération de documents", isComingSoon: true, icon: <FileStack size={16} /> },
          { label: "Documents envoyés", isComingSoon: true, icon: <FolderOpen size={16} /> },
          { label: "Templates", isComingSoon: true, adminOnly: true },
        ],
      },
      {
        label: "Questionnaires",
        icon: <ClipboardList size={20} />,
        children: [
          { label: "Risque (MiFID)", path: "/clients/1/questionnaire-risque", isComingSoon: true },
          { label: "Autres questionnaires", isComingSoon: true },
        ],
      },
      {
        label: "Patrimoine",
        icon: <Landmark size={20} />,
        children: [
          { label: "Épargne & placements", isComingSoon: true, icon: <Coins size={16} /> },
          { label: "Immobilier", isComingSoon: true, icon: <Building2 size={16} /> },
          { label: "Passifs", isComingSoon: true, icon: <Database size={16} /> },
        ],
      },
      {
        label: "Conformité",
        icon: <ShieldCheck size={20} />,
        children: [
          { label: "Dashboard conformité", path: "/compliance-dashboard" },
          { label: "RGPD / consentements", isComingSoon: true },
          { label: "Logs d'audit", isComingSoon: true },
        ],
      },
      {
        label: "Paramètres",
        icon: <Sliders size={20} />,
        children: [
          { label: "Utilisateurs & rôles", isComingSoon: true, adminOnly: true },
          { label: "Équipe / Cabinet", isComingSoon: true },
          { label: "Intégrations", isComingSoon: true },
          { label: "IA / Transcription", isComingSoon: true, adminOnly: true },
        ],
      },
    ],
    []
  );

  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    Clients: true,
    "Rendez-vous": true,
    Documents: false,
    Questionnaires: false,
    Patrimoine: false,
    Conformité: false,
    Paramètres: false,
  });

  if (isAuthPage) return null;

  const toggleSection = (label: string) => {
    if (collapsed) {
      // En mode collapsed, on expand d'abord la sidebar
      setCollapsed(false);
      setOpenSections((prev) => ({ ...prev, [label]: true }));
    } else {
      setOpenSections((prev) => ({ ...prev, [label]: !prev[label] }));
    }
  };

  const renderLink = (item: MenuItem, depth = 0) => {
    if (item.adminOnly && !isAdmin) return null;
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

  return (
    <aside
      className={`hidden lg:flex lg:flex-col bg-white border-r border-[#EBE9F1] min-h-screen sticky top-0 transition-all duration-300 ${
        collapsed ? "lg:w-20" : "lg:w-64"
      }`}
    >
      {/* Header avec bouton menu */}
      <div className={`px-4 py-4 border-b border-[#EBE9F1] ${collapsed ? "px-3" : "px-6 py-6"}`}>
        <div className="flex items-center justify-between">
          <div className={`flex items-center gap-3 ${collapsed ? "justify-center w-full" : ""}`}>
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-xl font-bold shadow-md shadow-purple-500/30 flex-shrink-0">
              🎧
            </div>
            {!collapsed && (
              <div>
                <div className="text-lg font-bold text-[#5E5873]">Whisper CRM</div>
                <div className="text-xs text-[#6E6B7B]">CRM vocal intelligent</div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Bouton collapse/expand */}
      <div className={`px-3 py-3 border-b border-[#EBE9F1] ${collapsed ? "flex justify-center" : ""}`}>
        <button
          onClick={() => setCollapsed(!collapsed)}
          className={`flex items-center gap-2 px-3 py-2 rounded-lg text-[#6E6B7B] hover:bg-[#F3F2F7] hover:text-[#7367F0] transition-all duration-200 ${
            collapsed ? "justify-center w-full" : "w-full"
          }`}
          title={collapsed ? "Ouvrir le menu" : "Reduire le menu"}
        >
          {collapsed ? (
            <Menu size={20} />
          ) : (
            <>
              <ChevronLeft size={20} />
              <span className="text-sm font-medium">Reduire</span>
            </>
          )}
        </button>
      </div>

      <div className={`flex-1 overflow-y-auto py-4 space-y-2 ${collapsed ? "px-2" : "px-4"}`}>
        {menu.map((section) => {
          if (section.adminOnly && !isAdmin) return null;

          if (!section.children?.length) {
            return (
              <div key={section.label}>
                {section.isComingSoon ? renderComingSoon(section) : renderLink(section)}
              </div>
            );
          }

          const isOpen = openSections[section.label] ?? false;

          // Mode collapsed : afficher seulement l'icone avec tooltip
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
                {/* Tooltip au hover */}
                <div className="absolute left-full top-0 ml-2 hidden group-hover:block z-50">
                  <div className="bg-[#5E5873] text-white text-xs font-semibold px-3 py-2 rounded-lg shadow-lg whitespace-nowrap">
                    {section.label}
                  </div>
                </div>
              </div>
            );
          }

          // Mode expanded : afficher le menu complet
          return (
            <div key={section.label} className="space-y-1">
              <button
                onClick={() => toggleSection(section.label)}
                className="w-full flex items-center justify-between text-left px-3 py-2 rounded-lg hover:bg-[#F3F2F7] transition-colors"
              >
                <div className="flex items-center gap-3 text-sm font-semibold text-[#5E5873]">
                  <span className="text-[#7367F0]">{section.icon}</span>
                  <span>{section.label}</span>
                </div>
                <ChevronDown
                  size={16}
                  className={`text-[#6E6B7B] transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
              </button>

              {isOpen && (
                <div className="ml-4 space-y-1">
                  {section.children.map((child) =>
                    child.isComingSoon || !child.path ? renderComingSoon(child, 1) : renderLink(child, 1)
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </aside>
  );
};

export default VuexySidebar;
