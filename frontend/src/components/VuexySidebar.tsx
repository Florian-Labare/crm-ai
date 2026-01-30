import React, { useMemo, useState } from "react";
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
} from "lucide-react";

type MenuItem = {
  label: string;
  path?: string;
  icon?: React.ReactNode;
  children?: MenuItem[];
  adminOnly?: boolean;
  isComingSoon?: boolean;
};

export const VuexySidebar: React.FC = () => {
  const location = useLocation();
  const { isAdmin } = useAuth();
  const isAuthPage = ["/login", "/register"].includes(location.pathname);

  const menu: MenuItem[] = useMemo(
    () => [
      {
        label: "Tableau de bord",
        path: "/",
        icon: <LayoutGrid size={18} />,
      },
      {
        label: "Clients",
        icon: <Users size={18} />,
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
        icon: <Calendar size={18} />,
        children: [
          { label: "Historique des RDV", isComingSoon: true, icon: <History size={16} /> },
          { label: "Enregistrements audio", isComingSoon: true, icon: <Mic size={16} /> },
          { label: "Résumés de rendez-vous", isComingSoon: true, icon: <ListChecks size={16} /> },
          { label: "Nouveau RDV", path: "/der/new" },
        ],
      },
      {
        label: "Documents",
        icon: <FileText size={18} />,
        children: [
          { label: "Génération de documents", isComingSoon: true, icon: <FileStack size={16} /> },
          { label: "Documents envoyés", isComingSoon: true, icon: <FolderOpen size={16} /> },
          { label: "Templates", isComingSoon: true, adminOnly: true },
        ],
      },
      {
        label: "Questionnaires",
        icon: <ClipboardList size={18} />,
        children: [
          { label: "Risque (MiFID)", path: "/clients/1/questionnaire-risque", isComingSoon: true },
          { label: "Autres questionnaires", isComingSoon: true },
        ],
      },
      {
        label: "Patrimoine",
        icon: <Landmark size={18} />,
        children: [
          { label: "Épargne & placements", isComingSoon: true, icon: <Coins size={16} /> },
          { label: "Immobilier", isComingSoon: true, icon: <Building2 size={16} /> },
          { label: "Passifs", isComingSoon: true, icon: <Database size={16} /> },
        ],
      },
      {
        label: "Conformité",
        icon: <ShieldCheck size={18} />,
        children: [
          { label: "RGPD / consentements", isComingSoon: true },
          { label: "Logs d'audit", isComingSoon: true },
        ],
      },
      {
        label: "Paramètres",
        icon: <Sliders size={18} />,
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
    setOpenSections((prev) => ({ ...prev, [label]: !prev[label] }));
  };

  const renderLink = (item: MenuItem, depth = 0) => {
    if (item.adminOnly && !isAdmin) return null;
    if (!item.path) return null;
    return (
      <NavLink
        key={`${item.label}-${item.path}`}
        to={item.path}
        className={({ isActive }) =>
          [
            "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-all duration-200",
            depth === 0 ? "text-[#5E5873]" : "text-[#6E6B7B]",
            isActive
              ? "bg-[#7367F0]/10 text-[#7367F0]"
              : "hover:bg-[#F3F2F7] hover:text-[#7367F0]",
          ].join(" ")
        }
      >
        {item.icon && <span className="text-[#7367F0]">{item.icon}</span>}
        <span>{item.label}</span>
        {item.isComingSoon && (
          <span className="ml-auto rounded-full bg-[#F3F2F7] px-2 py-0.5 text-[10px] font-semibold text-[#6E6B7B]">
            Bientôt
          </span>
        )}
      </NavLink>
    );
  };

  const renderComingSoon = (item: MenuItem, depth = 0) => {
    if (item.adminOnly && !isAdmin) return null;
    return (
      <div
        key={`${item.label}-soon`}
        className={[
          "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-[#B9B9C3]",
          depth === 0 ? "" : "",
        ].join(" ")}
      >
        {item.icon && <span className="text-[#B9B9C3]">{item.icon}</span>}
        <span>{item.label}</span>
        <span className="ml-auto rounded-full bg-[#F3F2F7] px-2 py-0.5 text-[10px] font-semibold text-[#6E6B7B]">
          Bientôt
        </span>
      </div>
    );
  };

  return (
    <aside className="hidden lg:flex lg:flex-col lg:w-64 bg-white border-r border-[#EBE9F1] min-h-screen sticky top-0">
      <div className="px-6 py-6 border-b border-[#EBE9F1]">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-xl font-bold shadow-md shadow-purple-500/30">
            🎧
          </div>
          <div>
            <div className="text-lg font-bold text-[#5E5873]">Whisper CRM</div>
            <div className="text-xs text-[#6E6B7B]">CRM vocal intelligent</div>
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
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
          return (
            <div key={section.label} className="space-y-2">
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
