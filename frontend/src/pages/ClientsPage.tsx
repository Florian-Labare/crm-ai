import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { ConfirmDialog } from "../components/ConfirmDialog";
import { ComplianceBadge } from "../components/ComplianceBadge";
import { ConfirmClientModal } from "../components/ConfirmClientModal";
import { extractCollection } from "../utils/apiHelpers";
import type { Client } from "../types/api";
import {
  Users, UserPlus, Eye, Edit, Trash2, Mail, Phone,
  Upload, User, Archive, Filter, MoreHorizontal, UserCheck, ArchiveRestore,
} from "lucide-react";
import { useAuth } from "../contexts/AuthContext";
import { usePage } from "../contexts/PageContext";
import type { PageAction } from "../contexts/PageContext";

interface ExtendedClient extends Client {
  situation_matrimoniale?: string;
  besoins?: string[];
  besoins_count?: number;
  is_client?: boolean;
  is_archived?: boolean;
}

type StatusFilterType = "prospects" | "clients" | "archived";

const FILTER_STORAGE_KEY = "clients_list_filters_v1";
const COLUMN_STORAGE_KEY = "clients_list_columns_v1";

const ClientsPage: React.FC = () => {
  const [clients, setClients] = useState<ExtendedClient[]>([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { isAdmin } = useAuth();
  const { setPage } = usePage();

  const [searchText, setSearchText] = useState("");
  const [statusFilters, setStatusFilters] = useState<StatusFilterType[]>(["prospects", "clients"]);
  const [besoinFilter, setBesoinFilter] = useState("all");
  const [situationFilter, setSituationFilter] = useState("all");
  const [professionFilter, setProfessionFilter] = useState("");
  const [emailFilter, setEmailFilter] = useState("");
  const [contractFilter, setContractFilter] = useState("");
  const [companyFilter, setCompanyFilter] = useState("");
  const [createdFrom, setCreatedFrom] = useState("");
  const [createdTo, setCreatedTo] = useState("");
  const [sortKey, setSortKey] = useState("created_desc");
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [filtersOpen, setFiltersOpen] = useState(true);
  const [openDropdownId, setOpenDropdownId] = useState<number | null>(null);
  const [visibleColumns, setVisibleColumns] = useState({
    contact: true,
    profession: true,
    situation: true,
    besoins: true,
    dossier: true,
    created: false,
  });

  const [confirmDialog, setConfirmDialog] = useState<{
    isOpen: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
    type?: "danger" | "warning" | "info";
  }>({
    isOpen: false,
    title: "",
    message: "",
    onConfirm: () => {},
    type: "danger",
  });

  const [clientModal, setClientModal] = useState<{
    isOpen: boolean;
    client: ExtendedClient | null;
    isLoading: boolean;
  }>({ isOpen: false, client: null, isLoading: false });

  // Page header actions
  useEffect(() => {
    const actions: PageAction[] = [
      {
        label: "Nouveau client",
        icon: <UserPlus size={15} />,
        onClick: () => navigate("/clients/new"),
        variant: "primary" as const,
      },
    ];
    if (isAdmin) {
      actions.push({
        label: "Importer",
        icon: <Upload size={15} />,
        onClick: () => navigate("/import"),
        variant: "outline" as const,
      });
    }
    setPage("Liste des clients", actions, [{ label: "Liste des clients" }]);
  }, [isAdmin, navigate, setPage]);

  // Fetch
  useEffect(() => {
    fetchClients();
  }, []);

  // Restore saved filters
  useEffect(() => {
    try {
      const raw = localStorage.getItem(FILTER_STORAGE_KEY);
      if (raw) {
        const saved = JSON.parse(raw);
        setSearchText(saved.searchText || "");
        setBesoinFilter(saved.besoinFilter || "all");
        setSituationFilter(saved.situationFilter || "all");
        setProfessionFilter(saved.professionFilter || "");
        setEmailFilter(saved.emailFilter || "");
        setContractFilter(saved.contractFilter || "");
        setCompanyFilter(saved.companyFilter || "");
        setCreatedFrom(saved.createdFrom || "");
        setCreatedTo(saved.createdTo || "");
        setSortKey(saved.sortKey || "created_desc");
        setFiltersOpen(saved.filtersOpen ?? true);
      }
    } catch {}
    try {
      const rawCols = localStorage.getItem(COLUMN_STORAGE_KEY);
      if (rawCols) setVisibleColumns((prev) => ({ ...prev, ...JSON.parse(rawCols) }));
    } catch {}
  }, []);

  // Persist filters
  useEffect(() => {
    localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify({
      searchText, besoinFilter, situationFilter, professionFilter,
      emailFilter, contractFilter, companyFilter, createdFrom, createdTo, sortKey, filtersOpen,
    }));
  }, [searchText, besoinFilter, situationFilter, professionFilter, emailFilter,
      contractFilter, companyFilter, createdFrom, createdTo, sortKey, pageSize, filtersOpen]);

  useEffect(() => {
    localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(visibleColumns));
  }, [visibleColumns]);

  // Reset page on filter change
  useEffect(() => {
    setCurrentPage(1);
  }, [searchText, statusFilters, besoinFilter, situationFilter, professionFilter,
      emailFilter, contractFilter, companyFilter, createdFrom, createdTo, sortKey]);

  const fetchClients = async () => {
    try {
      setLoading(true);
      const res = await api.get("/clients?type=all");
      setClients(extractCollection<ExtendedClient>(res));
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement des clients");
    } finally {
      setLoading(false);
    }
  };

  const normalizeBesoin = (besoin: string): string => {
    const lower = besoin.toLowerCase();
    if (lower.includes("retraite") || /\bper\b/.test(lower)) return "Retraite";
    if (lower.includes("prévoyance") || lower.includes("prevoyance") || lower.includes("décès")) return "Prévoyance";
    if (lower.includes("santé") || lower.includes("sante") || lower.includes("mutuelle")) return "Santé";
    if (lower.includes("emprunt") || lower.includes("crédit") || lower.includes("credit")) return "Emprunteur";
    if (lower.includes("épargne") || lower.includes("epargne") || lower.includes("assurance vie")) return "Épargne";
    return "Autre";
  };

  const toggleStatusFilter = (filter: StatusFilterType) => {
    setStatusFilters((prev) =>
      prev.includes(filter) ? prev.filter((f) => f !== filter) : [...prev, filter]
    );
  };

  const statusCounts = useMemo(() => ({
    prospects: clients.filter((c) => !c.is_client && !c.is_archived).length,
    clients:   clients.filter((c) =>  c.is_client && !c.is_archived).length,
    archived:  clients.filter((c) =>  c.is_archived).length,
  }), [clients]);

  const getStatusBadge = (client: ExtendedClient) => {
    if (client.is_archived) return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
        <Archive size={10} /> Archive
      </span>
    );
    if (client.is_client) return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#28C76F]/10 text-[#28C76F]">
        <Users size={10} /> Client
      </span>
    );
    return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#00CFE8]/10 text-[#00CFE8]">
        <User size={10} /> Prospect
      </span>
    );
  };

  const filteredClients = useMemo(() => {
    const term = searchText.trim().toLowerCase();
    const profTerm = professionFilter.trim().toLowerCase();
    const emailTerm = emailFilter.trim().toLowerCase();
    const contractTerm = contractFilter.trim().toLowerCase();
    const companyTerm = companyFilter.trim().toLowerCase();
    const fromDate = createdFrom ? new Date(createdFrom) : null;
    const toDate   = createdTo   ? new Date(createdTo)   : null;

    const matchesSearch = (c: ExtendedClient) => {
      if (!term) return true;
      const extra = c as any;
      const contratsText = (extra?.contrats || [])
        .flatMap((ct: any) => [ct?.assureur?.nom, ct?.type]).filter(Boolean).join(" ");
      return [c.nom_complet, c.nom, c.prenom, c.email, c.telephone, c.profession,
              c.situation_matrimoniale, contratsText, ...(c.besoins || [])]
        .filter(Boolean).join(" ").toLowerCase().includes(term);
    };

    const matchesContracts = (c: ExtendedClient) => {
      if (!contractTerm && !companyTerm) return true;
      const extra = c as any;
      const contratsNouveaux = (extra?.contrats || []) as any[];
      const contractCandidates = [
        extra?.bae_retraite?.contrat_en_place,
        extra?.bae_prevoyance?.contrat_en_place,
        extra?.sante_souhait?.contrat_en_place,
        ...contratsNouveaux.flatMap((ct: any) => [ct?.assureur?.nom, ct?.type]),
      ].filter(Boolean).join(" ").toLowerCase();
      const companyCandidates = [
        extra?.bae_retraite?.designation_etablissement,
        extra?.bae_prevoyance?.designation_etablissement,
        extra?.sante_souhait?.designation_etablissement,
        ...contratsNouveaux.map((ct: any) => ct?.assureur?.nom),
      ].filter(Boolean).join(" ").toLowerCase();
      return (!contractTerm || contractCandidates.includes(contractTerm))
          && (!companyTerm  || companyCandidates.includes(companyTerm));
    };

    const inDateRange = (c: ExtendedClient) => {
      if (!fromDate && !toDate) return true;
      if (!c.created_at) return false;
      const created = new Date(c.created_at);
      if (fromDate && created < fromDate) return false;
      if (toDate) { const end = new Date(toDate); end.setHours(23,59,59,999); if (created > end) return false; }
      return true;
    };

    let filtered = clients.filter((c) => {
      const statusMatches = statusFilters.length === 0 || statusFilters.length === 3 ? true : (() => {
        if (c.is_archived) return statusFilters.includes("archived");
        if (c.is_client)   return statusFilters.includes("clients");
        return statusFilters.includes("prospects");
      })();
      const besoinMatches = besoinFilter === "all" ? true :
        (c.besoins || []).some((b) => normalizeBesoin(b) === besoinFilter);
      const situationMatches = situationFilter === "all" ? true :
        (c.situation_matrimoniale || "").toLowerCase() === situationFilter.toLowerCase();
      const profMatch  = profTerm  ? (c.profession || "").toLowerCase().includes(profTerm)  : true;
      const emailMatch = emailTerm ? (c.email || "").toLowerCase().includes(emailTerm) : true;
      return statusMatches && matchesSearch(c) && besoinMatches && situationMatches
          && profMatch && emailMatch && matchesContracts(c) && inDateRange(c);
    });

    switch (sortKey) {
      case "created_asc":  filtered.sort((a,b) => new Date(a.created_at||0).getTime() - new Date(b.created_at||0).getTime()); break;
      case "name_asc":     filtered.sort((a,b) => (a.nom_complet||"").localeCompare(b.nom_complet||"")); break;
      case "name_desc":    filtered.sort((a,b) => (b.nom_complet||"").localeCompare(a.nom_complet||"")); break;
      case "besoins_desc": filtered.sort((a,b) => (b.besoins?.length||0) - (a.besoins?.length||0)); break;
      case "besoins_asc":  filtered.sort((a,b) => (a.besoins?.length||0) - (b.besoins?.length||0)); break;
      default:             filtered.sort((a,b) => new Date(b.created_at||0).getTime() - new Date(a.created_at||0).getTime());
    }
    return filtered;
  }, [clients, searchText, statusFilters, besoinFilter, situationFilter, professionFilter,
      emailFilter, contractFilter, companyFilter, createdFrom, createdTo, sortKey]);

  const situationOptions = useMemo(() => {
    const set = new Set<string>();
    clients.forEach((c) => { if (c.situation_matrimoniale) set.add(c.situation_matrimoniale); });
    return Array.from(set).sort();
  }, [clients]);

  const resetFilters = () => {
    setSearchText(""); setStatusFilters(["prospects", "clients"]); setBesoinFilter("all");
    setSituationFilter("all"); setProfessionFilter(""); setEmailFilter("");
    setContractFilter(""); setCompanyFilter(""); setCreatedFrom(""); setCreatedTo("");
    setSortKey("created_desc"); setCurrentPage(1);
  };

  const activeFilterChips = useMemo(() => {
    const chips: { key: string; label: string; onClear: () => void }[] = [];
    if (searchText.trim()) chips.push({ key: "search", label: `Recherche : ${searchText}`, onClear: () => setSearchText("") });
    if (besoinFilter !== "all") chips.push({ key: "besoin", label: `Besoin : ${besoinFilter}`, onClear: () => setBesoinFilter("all") });
    if (situationFilter !== "all") chips.push({ key: "situation", label: `Situation : ${situationFilter}`, onClear: () => setSituationFilter("all") });
    if (professionFilter.trim()) chips.push({ key: "profession", label: `Métier : ${professionFilter}`, onClear: () => setProfessionFilter("") });
    if (emailFilter.trim()) chips.push({ key: "email", label: `Email : ${emailFilter}`, onClear: () => setEmailFilter("") });
    if (contractFilter.trim()) chips.push({ key: "contrat", label: `Contrat : ${contractFilter}`, onClear: () => setContractFilter("") });
    if (companyFilter.trim()) chips.push({ key: "compagnie", label: `Compagnie : ${companyFilter}`, onClear: () => setCompanyFilter("") });
    if (createdFrom || createdTo) chips.push({ key: "created", label: `Créé${createdFrom ? ` du ${createdFrom}` : ""}${createdTo ? ` au ${createdTo}` : ""}`, onClear: () => { setCreatedFrom(""); setCreatedTo(""); } });
    if (sortKey !== "created_desc") {
      const m: Record<string,string> = { created_asc: "Tri : création (ancien)", name_asc: "Tri : nom (A→Z)", name_desc: "Tri : nom (Z→A)", besoins_desc: "Tri : besoins (plus)", besoins_asc: "Tri : besoins (moins)" };
      chips.push({ key: "sort", label: m[sortKey] || "Tri personnalisé", onClear: () => setSortKey("created_desc") });
    }
    return chips;
  }, [searchText, besoinFilter, situationFilter, professionFilter, emailFilter, contractFilter, companyFilter, createdFrom, createdTo, sortKey]);

  const filtersSummary = useMemo(() => {
    const parts: string[] = [];
    if (searchText.trim()) parts.push(`Recherche: ${searchText}`);
    if (besoinFilter !== "all") parts.push(`Besoin: ${besoinFilter}`);
    if (situationFilter !== "all") parts.push(`Situation: ${situationFilter}`);
    if (professionFilter.trim()) parts.push(`Métier: ${professionFilter}`);
    if (emailFilter.trim()) parts.push(`Email: ${emailFilter}`);
    if (contractFilter.trim()) parts.push(`Contrat: ${contractFilter}`);
    if (companyFilter.trim()) parts.push(`Compagnie: ${companyFilter}`);
    if (createdFrom || createdTo) parts.push(`Créé${createdFrom ? ` du ${createdFrom}` : ""}${createdTo ? ` au ${createdTo}` : ""}`);
    if (sortKey !== "created_desc") {
      const m: Record<string,string> = { created_asc: "Tri: ancien", name_asc: "Tri: A→Z", name_desc: "Tri: Z→A", besoins_desc: "Tri: besoins +", besoins_asc: "Tri: besoins -" };
      parts.push(m[sortKey] || "Tri custom");
    }
    return parts;
  }, [searchText, besoinFilter, situationFilter, professionFilter, emailFilter, contractFilter, companyFilter, createdFrom, createdTo, sortKey]);

  const totalPages = Math.max(1, Math.ceil(filteredClients.length / pageSize));
  const safePage = Math.min(currentPage, totalPages);
  const paginatedClients = useMemo(() => {
    const start = (safePage - 1) * pageSize;
    return filteredClients.slice(start, start + pageSize);
  }, [filteredClients, pageSize, safePage]);

  const paginationItems = useMemo(() => {
    const pages: (number | string)[] = [];
    if (totalPages <= 7) { for (let i = 1; i <= totalPages; i++) pages.push(i); return pages; }
    pages.push(1);
    if (safePage > 3) pages.push("...");
    for (let i = Math.max(2, safePage - 1); i <= Math.min(totalPages - 1, safePage + 1); i++) pages.push(i);
    if (safePage < totalPages - 2) pages.push("...");
    pages.push(totalPages);
    return pages;
  }, [safePage, totalPages]);

  const handleDelete = (id: number, nom: string, prenom: string) => {
    setConfirmDialog({
      isOpen: true, title: "Supprimer le client", type: "danger",
      message: `Êtes-vous sûr de vouloir supprimer ${prenom} ${nom} ? Cette action est irréversible.`,
      onConfirm: async () => {
        try {
          await api.delete(`/clients/${id}`);
          setClients((prev) => prev.filter((c) => c.id !== id));
          toast.success("Client supprimé avec succès");
        } catch (err) {
          console.error(err);
          toast.error("Erreur lors de la suppression du client");
        }
      },
    });
  };

  const handleConvertToClient = (client: ExtendedClient) => {
    setClientModal({ isOpen: true, client, isLoading: false });
  };

  const confirmConvertToClient = async () => {
    if (!clientModal.client) return;
    setClientModal((prev) => ({ ...prev, isLoading: true }));
    try {
      await api.patch(`/clients/${clientModal.client.id}/status`, { is_client: true });
      toast.success("Le prospect a été converti en client");
      setClientModal({ isOpen: false, client: null, isLoading: false });
      fetchClients();
    } catch {
      toast.error("Erreur lors de la conversion en client");
      setClientModal((prev) => ({ ...prev, isLoading: false }));
    }
  };

  const handleArchive = async (client: ExtendedClient) => {
    try {
      await api.post(`/clients/${client.id}/archive`);
      toast.success("Le contact a été archivé");
      fetchClients();
    } catch { toast.error("Erreur lors de l'archivage"); }
  };

  const handleRestore = async (client: ExtendedClient) => {
    try {
      await api.post(`/clients/${client.id}/restore`);
      toast.success("Le contact a été restauré");
      fetchClients();
    } catch { toast.error("Erreur lors de la restauration"); }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-screen bg-[#F8F8F8]">
        <div className="flex flex-col items-center space-y-4">
          <div className="w-16 h-16 border-4 border-[#7367F0] border-t-transparent rounded-full animate-spin" />
          <p className="text-[#6E6B7B] font-semibold">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-6 px-4 lg:px-6">
        <div className="w-full max-w-7xl mx-auto">

          {/* Bloc filtres + liste */}
          <div className="vx-card p-0 overflow-hidden">
            <div className="sticky top-0 z-10 bg-[#FCFCFF]/95 backdrop-blur border-b border-[#F1F0F5]">
              <div className="px-6 py-4">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                  <div>
                    <h3 className="text-lg font-semibold text-[#5E5873]">Recherche & filtres</h3>
                    <p className="text-sm text-[#6E6B7B]">
                      {filteredClients.length} résultat{filteredClients.length > 1 ? "s" : ""} sur {clients.length}
                    </p>
                  </div>

                  {/* Filtres par statut */}
                  <div className="flex items-center gap-2">
                    <Filter size={16} className="text-[#6E6B7B]" />
                    {[
                      { key: "prospects" as const, label: "Prospects", count: statusCounts.prospects, activeColor: "bg-[#00CFE8]/10 text-[#00CFE8] border-[#00CFE8]/30", icon: <User size={12} /> },
                      { key: "clients"   as const, label: "Clients",   count: statusCounts.clients,   activeColor: "bg-[#28C76F]/10 text-[#28C76F] border-[#28C76F]/30", icon: <Users size={12} /> },
                      { key: "archived"  as const, label: "Archives",  count: statusCounts.archived,  activeColor: "bg-gray-200 text-gray-700 border-gray-300",         icon: <Archive size={12} /> },
                    ].map((f) => {
                      const active = statusFilters.includes(f.key);
                      return (
                        <button key={f.key} onClick={() => toggleStatusFilter(f.key)}
                          className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border ${active ? f.activeColor : "bg-[#F3F2F7] text-[#6E6B7B] border-transparent hover:bg-[#EBE9F1]"}`}>
                          {f.icon}{f.label}
                          <span className={`ml-0.5 px-1.5 py-0.5 rounded text-[10px] ${active ? "bg-white/40" : "bg-[#EBE9F1]"}`}>{f.count}</span>
                        </button>
                      );
                    })}
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    {filtersSummary.length > 0 && (
                      <div className="hidden md:flex items-center gap-2 text-xs text-[#6E6B7B] bg-white border border-[#EFEFF5] rounded-full px-3 py-1">
                        {filtersSummary.join(" · ")}
                      </div>
                    )}
                    <button onClick={() => setFiltersOpen((p) => !p)}
                      className="px-3 py-2 text-sm font-semibold rounded-lg border border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7] transition-colors">
                      {filtersOpen ? "Masquer filtres" : "Afficher filtres"}
                    </button>
                    <button onClick={resetFilters}
                      className="px-4 py-2 text-sm font-semibold rounded-lg border border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7] transition-colors">
                      Réinitialiser
                    </button>
                    <div className="flex items-center gap-2 text-sm text-[#6E6B7B]">
                      <span className="text-xs font-semibold uppercase tracking-wide">Par page</span>
                      <select value={pageSize} onChange={(e) => setPageSize(Number(e.target.value))}
                        className="rounded-lg border border-[#D8D6DE] bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30">
                        {[10, 20, 50].map((s) => <option key={s} value={s}>{s}</option>)}
                      </select>
                    </div>
                  </div>
                </div>

                {activeFilterChips.length > 0 && (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {activeFilterChips.map((chip) => (
                      <button key={chip.key} onClick={chip.onClear}
                        className="inline-flex items-center gap-2 rounded-full border border-[#E7E5F7] bg-white px-3 py-1 text-xs font-semibold text-[#6E6B7B] hover:border-[#7367F0] hover:text-[#7367F0] transition-colors">
                        {chip.label}<span className="text-[#B9B9C3]">×</span>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {filtersOpen && (
                <div className="px-6 pb-5">
                  <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    {[
                      { label: "Recherche globale", value: searchText, setter: setSearchText, placeholder: "Nom, email, besoins..." },
                      { label: "Profession", value: professionFilter, setter: setProfessionFilter, placeholder: "Ex: médecin, dirigeant..." },
                      { label: "Email", value: emailFilter, setter: setEmailFilter, placeholder: "Filtrer par email" },
                      { label: "Contrat", value: contractFilter, setter: setContractFilter, placeholder: "Nom du contrat" },
                      { label: "Compagnie / Établissement", value: companyFilter, setter: setCompanyFilter, placeholder: "Ex: AXA, Generali..." },
                    ].map((f) => (
                      <div key={f.label}>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">{f.label}</label>
                        <input value={f.value} onChange={(e) => f.setter(e.target.value)} placeholder={f.placeholder}
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30" />
                      </div>
                    ))}
                    <div>
                      <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">Besoin</label>
                      <select value={besoinFilter} onChange={(e) => setBesoinFilter(e.target.value)}
                        className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30">
                        <option value="all">Tous</option>
                        {["Retraite","Épargne","Prévoyance","Santé","Emprunteur","Autre"].map((v) => <option key={v} value={v}>{v}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">Situation matrimoniale</label>
                      <select value={situationFilter} onChange={(e) => setSituationFilter(e.target.value)}
                        className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30">
                        <option value="all">Toutes</option>
                        {situationOptions.map((o) => <option key={o} value={o}>{o}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">Tri</label>
                      <select value={sortKey} onChange={(e) => setSortKey(e.target.value)}
                        className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30">
                        <option value="created_desc">Création (récent)</option>
                        <option value="created_asc">Création (ancien)</option>
                        <option value="name_asc">Nom (A → Z)</option>
                        <option value="name_desc">Nom (Z → A)</option>
                        <option value="besoins_desc">Besoins (plus)</option>
                        <option value="besoins_asc">Besoins (moins)</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">Créé entre</label>
                      <div className="flex items-center gap-2">
                        <input type="date" value={createdFrom} onChange={(e) => setCreatedFrom(e.target.value)}
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30" />
                        <span className="text-xs text-[#B9B9C3]">→</span>
                        <input type="date" value={createdTo} onChange={(e) => setCreatedTo(e.target.value)}
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30" />
                      </div>
                    </div>
                  </div>

                  {/* Colonnes visibles */}
                  <div className="mt-6 border-t border-[#F1F0F5] pt-5">
                    <div className="text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-3">Colonnes visibles</div>
                    <div className="flex flex-wrap gap-3">
                      {[
                        { key: "contact", label: "Contact" }, { key: "profession", label: "Profession" },
                        { key: "situation", label: "Situation" }, { key: "besoins", label: "Besoins" },
                        { key: "dossier", label: "Dossier" }, { key: "created", label: "Créé le" },
                      ].map((item) => (
                        <label key={item.key} className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold cursor-pointer transition-colors ${
                          visibleColumns[item.key as keyof typeof visibleColumns]
                            ? "border-[#7367F0] bg-[#F2F0FF] text-[#7367F0]"
                            : "border-[#E7E5F7] bg-white text-[#6E6B7B]"}`}>
                          <input type="checkbox"
                            checked={visibleColumns[item.key as keyof typeof visibleColumns]}
                            onChange={() => setVisibleColumns((prev) => ({ ...prev, [item.key]: !prev[item.key as keyof typeof visibleColumns] }))}
                            className="accent-[#7367F0]" />
                          {item.label}
                        </label>
                      ))}
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Table */}
            {filteredClients.length === 0 ? (
              <div className="p-12 text-center">
                <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-[#F3F2F7] flex items-center justify-center">
                  <Users size={32} className="text-[#B9B9C3]" />
                </div>
                <p className="text-[#6E6B7B]">Aucun contact trouvé.</p>
                <p className="text-sm text-[#B9B9C3] mt-1">Ajustez les filtres ou votre recherche.</p>
              </div>
            ) : (
              <>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-[#EBE9F1]">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Client</th>
                        {visibleColumns.contact    && <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Contact</th>}
                        {visibleColumns.profession && <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Profession</th>}
                        {visibleColumns.situation  && <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Situation</th>}
                        {visibleColumns.besoins    && <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Besoins</th>}
                        {visibleColumns.dossier    && <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Dossier</th>}
                        {visibleColumns.created    && <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Créé le</th>}
                        <th className="px-6 py-4 text-right text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-[#EBE9F1]">
                      {paginatedClients.map((client) => (
                        <tr key={client.id} className="hover:bg-[#F8F8F8] transition-colors">
                          {/* Client */}
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white font-semibold text-sm shadow-md shadow-purple-500/30 flex-shrink-0">
                                {client.prenom?.charAt(0) || ""}{client.nom?.charAt(0) || ""}
                              </div>
                              <div className="ml-4">
                                <div className="flex items-center gap-2">
                                  <span className="text-sm font-semibold text-[#5E5873]">{client.nom_complet}</span>
                                  {getStatusBadge(client)}
                                </div>
                                <div className="text-xs text-[#B9B9C3]">ID: {client.id}</div>
                              </div>
                            </div>
                          </td>

                          {/* Contact */}
                          {visibleColumns.contact && (
                            <td className="px-6 py-4">
                              <div className="text-sm">
                                {client.email && (
                                  <div className="flex items-center gap-2 mb-1 text-[#6E6B7B]">
                                    <Mail size={14} className="text-[#7367F0]" />
                                    <span className="truncate max-w-[200px]">{client.email}</span>
                                  </div>
                                )}
                                {client.telephone && (
                                  <div className="flex items-center gap-2 text-[#6E6B7B]">
                                    <Phone size={14} className="text-[#28C76F]" />
                                    {client.telephone}
                                  </div>
                                )}
                                {!client.email && !client.telephone && (
                                  <span className="text-[#B9B9C3] italic">Non renseigné</span>
                                )}
                              </div>
                            </td>
                          )}

                          {/* Profession */}
                          {visibleColumns.profession && (
                            <td className="px-6 py-4">
                              <div className="text-sm text-[#5E5873] font-medium">
                                {client.profession || <span className="text-[#B9B9C3] italic">Non renseignée</span>}
                              </div>
                            </td>
                          )}

                          {/* Situation */}
                          {visibleColumns.situation && (
                            <td className="px-6 py-4">
                              <div className="text-sm text-[#5E5873] font-medium">
                                {client.situation_matrimoniale || <span className="text-[#B9B9C3] italic">Non renseignée</span>}
                              </div>
                            </td>
                          )}

                          {/* Besoins */}
                          {visibleColumns.besoins && (
                            <td className="px-6 py-4">
                              {client.besoins && client.besoins.length > 0 ? (
                                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#7367F0]/10 text-[#7367F0] text-xs font-semibold">
                                  {client.besoins.length} besoin{client.besoins.length > 1 ? "s" : ""}
                                </span>
                              ) : (
                                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#B9B9C3]/10 text-[#B9B9C3] text-xs font-semibold">Aucun</span>
                              )}
                            </td>
                          )}

                          {/* Dossier */}
                          {visibleColumns.dossier && (
                            <td className="px-6 py-4">
                              <ComplianceBadge clientId={client.id} variant="badge" />
                            </td>
                          )}

                          {/* Créé le */}
                          {visibleColumns.created && (
                            <td className="px-6 py-4">
                              <div className="text-sm text-[#5E5873] font-medium">
                                {client.created_at
                                  ? new Date(client.created_at).toLocaleDateString("fr-FR")
                                  : <span className="text-[#B9B9C3] italic">—</span>}
                              </div>
                            </td>
                          )}

                          {/* Actions */}
                          <td className="px-6 py-4 text-right whitespace-nowrap">
                            <div className="flex items-center justify-end gap-1.5">
                              {/* Groupe principal : Voir / Éditer / Supprimer */}
                              <div className="inline-flex rounded-lg border border-[#EBE9F1] overflow-hidden">
                                <button
                                  onClick={() => navigate(`/clients/${client.id}`)}
                                  className="p-2 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-all border-r border-[#EBE9F1]"
                                  title="Voir"
                                >
                                  <Eye size={15} />
                                </button>
                                <button
                                  onClick={() => navigate(`/clients/${client.id}/edit`)}
                                  className="p-2 text-[#00CFE8] hover:bg-[#00CFE8] hover:text-white transition-all border-r border-[#EBE9F1]"
                                  title="Éditer"
                                >
                                  <Edit size={15} />
                                </button>
                                <button
                                  onClick={() => handleDelete(client.id, client.nom || "", client.prenom || "")}
                                  className="p-2 text-[#EA5455] hover:bg-[#EA5455] hover:text-white transition-all"
                                  title="Supprimer"
                                >
                                  <Trash2 size={15} />
                                </button>
                              </div>

                              {/* Menu secondaire : actions contextuelles */}
                              <div className="relative">
                                <button
                                  onClick={() => setOpenDropdownId(openDropdownId === client.id ? null : client.id)}
                                  className="p-2 rounded-lg border border-[#EBE9F1] text-[#6E6B7B] hover:bg-[#F3F2F7] transition-all"
                                  title="Plus d'actions"
                                >
                                  <MoreHorizontal size={15} />
                                </button>
                                {openDropdownId === client.id && (
                                  <div
                                    className="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-lg border border-[#EBE9F1] z-20 py-1"
                                    onMouseLeave={() => setOpenDropdownId(null)}
                                  >
                                    {!client.is_client && !client.is_archived && (
                                      <button
                                        onClick={() => { handleConvertToClient(client); setOpenDropdownId(null); }}
                                        className="flex items-center gap-2 w-full px-4 py-2 text-sm text-[#7367F0] hover:bg-[#F3F2F7] transition-colors"
                                      >
                                        <UserCheck size={14} />
                                        Passer en client
                                      </button>
                                    )}
                                    {!client.is_archived ? (
                                      <button
                                        onClick={() => { handleArchive(client); setOpenDropdownId(null); }}
                                        className="flex items-center gap-2 w-full px-4 py-2 text-sm text-[#6E6B7B] hover:bg-[#F3F2F7] transition-colors"
                                      >
                                        <Archive size={14} />
                                        Archiver
                                      </button>
                                    ) : (
                                      <button
                                        onClick={() => { handleRestore(client); setOpenDropdownId(null); }}
                                        className="flex items-center gap-2 w-full px-4 py-2 text-sm text-[#28C76F] hover:bg-[#F3F2F7] transition-colors"
                                      >
                                        <ArchiveRestore size={14} />
                                        Restaurer
                                      </button>
                                    )}
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Pagination */}
                <div className="border-t border-[#EBE9F1] px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                  <div className="text-sm text-[#6E6B7B]">
                    {filteredClients.length === 0 ? "0 résultat" :
                      `Affichage ${(safePage - 1) * pageSize + 1}–${Math.min(safePage * pageSize, filteredClients.length)} sur ${filteredClients.length}`}
                  </div>
                  <div className="flex items-center gap-2">
                    <button onClick={() => setCurrentPage((p) => Math.max(1, p - 1))} disabled={safePage === 1}
                      className="px-3 py-1.5 rounded-lg border border-[#D8D6DE] text-sm font-semibold text-[#6E6B7B] hover:bg-[#F3F2F7] disabled:opacity-50">
                      Précédent
                    </button>
                    <div className="flex items-center gap-1">
                      {paginationItems.map((item, idx) =>
                        item === "..." ? (
                          <span key={`dots-${idx}`} className="px-2 text-[#B9B9C3]">...</span>
                        ) : (
                          <button key={item} onClick={() => setCurrentPage(item as number)}
                            className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${
                              item === safePage ? "bg-[#7367F0] text-white" : "border border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7]"}`}>
                            {item}
                          </button>
                        )
                      )}
                    </div>
                    <button onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))} disabled={safePage === totalPages}
                      className="px-3 py-1.5 rounded-lg border border-[#D8D6DE] text-sm font-semibold text-[#6E6B7B] hover:bg-[#F3F2F7] disabled:opacity-50">
                      Suivant
                    </button>
                  </div>
                </div>
              </>
            )}
          </div>

        </div>
      </div>

      <ConfirmDialog
        isOpen={confirmDialog.isOpen}
        onClose={() => setConfirmDialog({ ...confirmDialog, isOpen: false })}
        onConfirm={confirmDialog.onConfirm}
        title={confirmDialog.title}
        message={confirmDialog.message}
        type={confirmDialog.type}
      />

      <ConfirmClientModal
        isOpen={clientModal.isOpen}
        onClose={() => setClientModal({ isOpen: false, client: null, isLoading: false })}
        onConfirm={confirmConvertToClient}
        clientName={clientModal.client ? `${clientModal.client.prenom} ${clientModal.client.nom?.toUpperCase()}` : ""}
        isLoading={clientModal.isLoading}
      />
    </>
  );
};

export default ClientsPage;
