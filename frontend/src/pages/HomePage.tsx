import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { ConfirmDialog } from "../components/ConfirmDialog";
import { extractCollection } from "../utils/apiHelpers";
import type { Client } from "../types/api";
import { Users, UserPlus, ClipboardList, Eye, Edit, Trash2, Mail, Phone, LogOut, Upload } from "lucide-react";
import { VuexyStatCard } from "../components/VuexyStatCard";
import { PendingChangesBadge } from "../components/PendingChangesBadge";
import { ReviewChangesModal } from "../components/ReviewChangesModal";
import { useAuth } from "../contexts/AuthContext";

interface ExtendedClient extends Client {
  situation_matrimoniale?: string;
  besoins?: string[];
}

const FILTER_STORAGE_KEY = "home_clients_filters_v1";
const COLUMN_STORAGE_KEY = "home_clients_columns_v1";

const HomePage: React.FC = () => {
  const [clients, setClients] = useState<ExtendedClient[]>([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    total: 0,
    nouveaux: 0,
    avecBesoins: 0,
  });
  const navigate = useNavigate();
  const { user, logout, isAdmin } = useAuth();
  const [selectedPendingChangeId, setSelectedPendingChangeId] = useState<number | null>(null);
  const [searchText, setSearchText] = useState("");
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
  const [visibleColumns, setVisibleColumns] = useState({
    contact: true,
    profession: true,
    situation: true,
    besoins: true,
    created: false,
  });

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const [confirmDialog, setConfirmDialog] = useState<{
    isOpen: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
    type?: 'danger' | 'warning' | 'info';
  }>({
    isOpen: false,
    title: '',
    message: '',
    onConfirm: () => {},
    type: 'danger',
  });

  useEffect(() => {
    fetchClients();
  }, []);

  useEffect(() => {
    try {
      const rawFilters = localStorage.getItem(FILTER_STORAGE_KEY);
      if (rawFilters) {
        const saved = JSON.parse(rawFilters);
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
    } catch (err) {
      console.warn("Filtres non restaurés", err);
    }

    try {
      const rawColumns = localStorage.getItem(COLUMN_STORAGE_KEY);
      if (rawColumns) {
        const savedColumns = JSON.parse(rawColumns);
        setVisibleColumns((prev) => ({
          ...prev,
          ...savedColumns,
        }));
      }
    } catch (err) {
      console.warn("Colonnes non restaurées", err);
    }
  }, []);

  useEffect(() => {
    const payload = {
      searchText,
      besoinFilter,
      situationFilter,
      professionFilter,
      emailFilter,
      contractFilter,
      companyFilter,
      createdFrom,
      createdTo,
      sortKey,
      filtersOpen,
    };
    localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(payload));
  }, [
    searchText,
    besoinFilter,
    situationFilter,
    professionFilter,
    emailFilter,
    contractFilter,
    companyFilter,
    createdFrom,
    createdTo,
    sortKey,
    pageSize,
    filtersOpen,
  ]);

  useEffect(() => {
    localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(visibleColumns));
  }, [visibleColumns]);

  const fetchClients = async () => {
    try {
      setLoading(true);
      const res = await api.get("/clients");
      const clientsData = extractCollection<ExtendedClient>(res);
      setClients(clientsData);

      const total = clientsData.length;
      const nouveaux = clientsData.filter((c: ExtendedClient) => {
        if (!c.created_at) return false;
        const createdDate = new Date(c.created_at);
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        return createdDate > weekAgo;
      }).length;
      const avecBesoins = clientsData.filter(
        (c: ExtendedClient) => c.besoins && c.besoins.length > 0
      ).length;

      setStats({ total, nouveaux, avecBesoins });
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
    if (lower.includes("prévoyance") || lower.includes("prevoyance") || lower.includes("décès") || lower.includes("deces")) return "Prévoyance";
    if (lower.includes("santé") || lower.includes("sante") || lower.includes("mutuelle")) return "Santé";
    if (lower.includes("emprunt") || lower.includes("emprunteur") || lower.includes("crédit") || lower.includes("credit")) return "Emprunteur";
    if (lower.includes("épargne") || lower.includes("epargne") || lower.includes("assurance vie") || lower.includes("livret") || lower.includes("pea") || lower.includes("invest")) return "Épargne";
    return "Autre";
  };

  const filteredClients = useMemo(() => {
    const term = searchText.trim().toLowerCase();
    const professionTerm = professionFilter.trim().toLowerCase();
    const emailTerm = emailFilter.trim().toLowerCase();
    const contractTerm = contractFilter.trim().toLowerCase();
    const companyTerm = companyFilter.trim().toLowerCase();
    const fromDate = createdFrom ? new Date(createdFrom) : null;
    const toDate = createdTo ? new Date(createdTo) : null;

    const matchesSearch = (client: ExtendedClient) => {
      if (!term) return true;
      const parts = [
        client.nom_complet,
        client.nom,
        client.prenom,
        client.email,
        client.telephone,
        client.profession,
        client.situation_matrimoniale,
        ...(client.besoins || []),
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
      return parts.includes(term);
    };

    const matchesContracts = (client: ExtendedClient) => {
      if (!contractTerm && !companyTerm) return true;
      const extra = client as any;
      const contractCandidates = [
        extra?.bae_retraite?.contrat_en_place,
        extra?.bae_prevoyance?.contrat_en_place,
        extra?.sante_souhait?.contrat_en_place,
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

      const companyCandidates = [
        extra?.bae_retraite?.designation_etablissement,
        extra?.bae_prevoyance?.designation_etablissement,
        extra?.sante_souhait?.designation_etablissement,
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

      const contractMatch = contractTerm ? contractCandidates.includes(contractTerm) : true;
      const companyMatch = companyTerm ? companyCandidates.includes(companyTerm) : true;
      return contractMatch && companyMatch;
    };

    const inDateRange = (client: ExtendedClient) => {
      if (!fromDate && !toDate) return true;
      if (!client.created_at) return false;
      const created = new Date(client.created_at);
      if (fromDate && created < fromDate) return false;
      if (toDate) {
        const end = new Date(toDate);
        end.setHours(23, 59, 59, 999);
        if (created > end) return false;
      }
      return true;
    };

    let filtered = clients.filter((client) => {
      const besoinMatches =
        besoinFilter === "all"
          ? true
          : (client.besoins || []).some((besoin) => normalizeBesoin(besoin) === besoinFilter);

      const situationMatches =
        situationFilter === "all"
          ? true
          : (client.situation_matrimoniale || "").toLowerCase() === situationFilter.toLowerCase();

      const professionMatches = professionTerm
        ? (client.profession || "").toLowerCase().includes(professionTerm)
        : true;

      const emailMatches = emailTerm ? (client.email || "").toLowerCase().includes(emailTerm) : true;

      return (
        matchesSearch(client) &&
        besoinMatches &&
        situationMatches &&
        professionMatches &&
        emailMatches &&
        matchesContracts(client) &&
        inDateRange(client)
      );
    });

    switch (sortKey) {
      case "created_asc":
        filtered = filtered.sort((a, b) => new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime());
        break;
      case "name_asc":
        filtered = filtered.sort((a, b) => (a.nom_complet || "").localeCompare(b.nom_complet || ""));
        break;
      case "name_desc":
        filtered = filtered.sort((a, b) => (b.nom_complet || "").localeCompare(a.nom_complet || ""));
        break;
      case "besoins_desc":
        filtered = filtered.sort((a, b) => (b.besoins?.length || 0) - (a.besoins?.length || 0));
        break;
      case "besoins_asc":
        filtered = filtered.sort((a, b) => (a.besoins?.length || 0) - (b.besoins?.length || 0));
        break;
      case "created_desc":
      default:
        filtered = filtered.sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime());
        break;
    }

    return filtered;
  }, [
    clients,
    searchText,
    besoinFilter,
    situationFilter,
    professionFilter,
    emailFilter,
    contractFilter,
    companyFilter,
    createdFrom,
    createdTo,
    sortKey,
  ]);

  const situationOptions = useMemo(() => {
    const set = new Set<string>();
    clients.forEach((client) => {
      if (client.situation_matrimoniale) {
        set.add(client.situation_matrimoniale);
      }
    });
    return Array.from(set).sort();
  }, [clients]);

  const resetFilters = () => {
    setSearchText("");
    setBesoinFilter("all");
    setSituationFilter("all");
    setProfessionFilter("");
    setEmailFilter("");
    setContractFilter("");
    setCompanyFilter("");
    setCreatedFrom("");
    setCreatedTo("");
    setSortKey("created_desc");
    setCurrentPage(1);
  };

  const activeFilterChips = useMemo(() => {
    const chips: { key: string; label: string; onClear: () => void }[] = [];

    if (searchText.trim()) {
      chips.push({ key: "search", label: `Recherche : ${searchText}`, onClear: () => setSearchText("") });
    }
    if (besoinFilter !== "all") {
      chips.push({ key: "besoin", label: `Besoin : ${besoinFilter}`, onClear: () => setBesoinFilter("all") });
    }
    if (situationFilter !== "all") {
      chips.push({ key: "situation", label: `Situation : ${situationFilter}`, onClear: () => setSituationFilter("all") });
    }
    if (professionFilter.trim()) {
      chips.push({ key: "profession", label: `Métier : ${professionFilter}`, onClear: () => setProfessionFilter("") });
    }
    if (emailFilter.trim()) {
      chips.push({ key: "email", label: `Email : ${emailFilter}`, onClear: () => setEmailFilter("") });
    }
    if (contractFilter.trim()) {
      chips.push({ key: "contrat", label: `Contrat : ${contractFilter}`, onClear: () => setContractFilter("") });
    }
    if (companyFilter.trim()) {
      chips.push({ key: "compagnie", label: `Compagnie : ${companyFilter}`, onClear: () => setCompanyFilter("") });
    }
    if (createdFrom || createdTo) {
      const label = `Créé${createdFrom ? ` du ${createdFrom}` : ""}${createdTo ? ` au ${createdTo}` : ""}`;
      chips.push({ key: "created", label, onClear: () => { setCreatedFrom(""); setCreatedTo(""); } });
    }
    if (sortKey !== "created_desc") {
      const labelMap: Record<string, string> = {
        created_asc: "Tri : création (ancien)",
        name_asc: "Tri : nom (A → Z)",
        name_desc: "Tri : nom (Z → A)",
        besoins_desc: "Tri : besoins (plus)",
        besoins_asc: "Tri : besoins (moins)",
      };
      chips.push({ key: "sort", label: labelMap[sortKey] || "Tri personnalisé", onClear: () => setSortKey("created_desc") });
    }

    return chips;
  }, [
    searchText,
    besoinFilter,
    situationFilter,
    professionFilter,
    emailFilter,
    contractFilter,
    companyFilter,
    createdFrom,
    createdTo,
    sortKey,
  ]);

  const filtersSummary = useMemo(() => {
    const parts: string[] = [];
    if (searchText.trim()) parts.push(`Recherche: ${searchText}`);
    if (besoinFilter !== "all") parts.push(`Besoin: ${besoinFilter}`);
    if (situationFilter !== "all") parts.push(`Situation: ${situationFilter}`);
    if (professionFilter.trim()) parts.push(`Métier: ${professionFilter}`);
    if (emailFilter.trim()) parts.push(`Email: ${emailFilter}`);
    if (contractFilter.trim()) parts.push(`Contrat: ${contractFilter}`);
    if (companyFilter.trim()) parts.push(`Compagnie: ${companyFilter}`);
    if (createdFrom || createdTo) {
      parts.push(`Créé${createdFrom ? ` du ${createdFrom}` : ""}${createdTo ? ` au ${createdTo}` : ""}`);
    }
    if (sortKey !== "created_desc") {
      const labelMap: Record<string, string> = {
        created_asc: "Tri: ancien",
        name_asc: "Tri: nom A→Z",
        name_desc: "Tri: nom Z→A",
        besoins_desc: "Tri: besoins +",
        besoins_asc: "Tri: besoins -",
      };
      parts.push(labelMap[sortKey] || "Tri custom");
    }
    return parts;
  }, [
    searchText,
    besoinFilter,
    situationFilter,
    professionFilter,
    emailFilter,
    contractFilter,
    companyFilter,
    createdFrom,
    createdTo,
    sortKey,
  ]);

  useEffect(() => {
    setCurrentPage(1);
  }, [
    searchText,
    besoinFilter,
    situationFilter,
    professionFilter,
    emailFilter,
    contractFilter,
    companyFilter,
    createdFrom,
    createdTo,
    sortKey,
  ]);

  const totalPages = Math.max(1, Math.ceil(filteredClients.length / pageSize));
  const safePage = Math.min(currentPage, totalPages);
  const paginatedClients = useMemo(() => {
    const start = (safePage - 1) * pageSize;
    return filteredClients.slice(start, start + pageSize);
  }, [filteredClients, pageSize, safePage]);

  const paginationItems = useMemo(() => {
    const pages: (number | string)[] = [];
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i += 1) pages.push(i);
      return pages;
    }
    pages.push(1);
    if (safePage > 3) pages.push("...");
    const start = Math.max(2, safePage - 1);
    const end = Math.min(totalPages - 1, safePage + 1);
    for (let i = start; i <= end; i += 1) pages.push(i);
    if (safePage < totalPages - 2) pages.push("...");
    pages.push(totalPages);
    return pages;
  }, [safePage, totalPages]);

  const handleDelete = (id: number, nom: string, prenom: string) => {
    setConfirmDialog({
      isOpen: true,
      title: 'Supprimer le client',
      message: `Êtes-vous sûr de vouloir supprimer ${prenom} ${nom} ? Cette action est irréversible.`,
      type: 'danger',
      onConfirm: async () => {
        try {
          await api.delete(`/clients/${id}`);
          setClients((prev) => prev.filter((c) => c.id !== id));
          toast.success("Client supprimé avec succès");
          setStats((prev) => ({ ...prev, total: prev.total - 1 }));
        } catch (err) {
          console.error(err);
          toast.error("Erreur lors de la suppression du client");
        }
      },
    });
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-screen bg-[#F8F8F8]">
        <div className="flex flex-col items-center space-y-4">
          <div className="w-16 h-16 border-4 border-[#7367F0] border-t-transparent rounded-full animate-spin"></div>
          <p className="text-[#6E6B7B] font-semibold">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-7xl mx-auto space-y-8">
          {/* Header */}
          <div className="vx-card">
            <div className="flex justify-between items-start">
              <div>
                <h1 className="text-3xl font-bold text-[#5E5873] mb-2">Tableau de bord</h1>
                <p className="text-[#6E6B7B]">
                  Gérez vos clients et leurs besoins en un clin d'œil
                </p>
              </div>

              {/* User Menu & Pending Changes */}
              {user && (
                <div className="flex items-center gap-4">
                  {/* Import Button - Admin Only */}
                  {isAdmin && (
                    <button
                      onClick={() => navigate("/import")}
                      className="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#FF9F43] text-[#FF9F43] hover:bg-[#FF9F43] hover:text-white font-semibold transition-all duration-200"
                    >
                      <Upload size={18} />
                      Importer
                    </button>
                  )}

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

          {/* Statistiques */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <VuexyStatCard
              label="Total Clients"
              value={stats.total}
              icon={<Users size={20} />}
              color="blue"
              delay={0.1}
            />
            <VuexyStatCard
              label="Nouveaux (7j)"
              value={stats.nouveaux}
              icon={<UserPlus size={20} />}
              color="green"
              delay={0.2}
            />
            <VuexyStatCard
              label="Avec besoins"
              value={stats.avecBesoins}
              icon={<ClipboardList size={20} />}
              color="purple"
              delay={0.3}
            />
          </div>

          {/* En-tête de la liste */}
          <div className="flex justify-between items-center">
            <h2 className="text-2xl font-bold text-[#5E5873]">Mes clients</h2>
            <button
              onClick={() => navigate("/clients/new")}
              className="bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg font-semibold shadow-md hover:shadow-lg transition-all flex items-center gap-2"
            >
              <UserPlus size={20} />
              Nouveau client
            </button>
          </div>

          {/* Bloc combiné filtres + liste */}
          {paginatedClients.length === 0 ? (
            <div className="vx-card text-center py-12">
              <Users className="mx-auto h-16 w-16 text-[#B9B9C3] mb-4" />
              <h3 className="text-xl font-semibold text-[#5E5873] mb-2">
                Aucun client correspondant
              </h3>
              <p className="text-[#6E6B7B] mb-6">
                Ajustez les filtres ou créez un nouveau client
              </p>
              <button
                onClick={() => navigate("/clients/new")}
                className="bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg font-semibold inline-flex items-center gap-2 shadow-md hover:shadow-lg transition-all"
              >
                <UserPlus size={20} />
                Créer un client
              </button>
            </div>
          ) : (
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
                    <div className="flex flex-wrap items-center gap-2">
                      {filtersSummary.length > 0 && (
                        <div className="hidden md:flex items-center gap-2 text-xs text-[#6E6B7B] bg-white border border-[#EFEFF5] rounded-full px-3 py-1">
                          {filtersSummary.join(" · ")}
                        </div>
                      )}
                      <button
                        onClick={() => setFiltersOpen((prev) => !prev)}
                        className="px-3 py-2 text-sm font-semibold rounded-lg border border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7] transition-colors"
                      >
                        {filtersOpen ? "Masquer filtres" : "Afficher filtres"}
                      </button>
                      <button
                        onClick={resetFilters}
                        className="px-4 py-2 text-sm font-semibold rounded-lg border border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7] transition-colors"
                      >
                        Réinitialiser
                      </button>
                      <div className="flex items-center gap-2 text-sm text-[#6E6B7B]">
                        <span className="text-xs font-semibold uppercase tracking-wide text-[#6E6B7B]">Par page</span>
                        <select
                          value={pageSize}
                          onChange={(e) => setPageSize(Number(e.target.value))}
                          className="rounded-lg border border-[#D8D6DE] bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        >
                          {[10, 20, 50].map((size) => (
                            <option key={size} value={size}>
                              {size}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>

                  {activeFilterChips.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                      {activeFilterChips.map((chip) => (
                        <button
                          key={chip.key}
                          onClick={chip.onClear}
                          className="inline-flex items-center gap-2 rounded-full border border-[#E7E5F7] bg-white px-3 py-1 text-xs font-semibold text-[#6E6B7B] hover:border-[#7367F0] hover:text-[#7367F0] transition-colors"
                        >
                          {chip.label}
                          <span className="text-[#B9B9C3]">×</span>
                        </button>
                      ))}
                    </div>
                  )}
                </div>

                {filtersOpen && (
                  <div className="px-6 pb-5">
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Recherche globale
                        </label>
                        <input
                          value={searchText}
                          onChange={(e) => setSearchText(e.target.value)}
                          placeholder="Nom, email, besoins..."
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Besoin
                        </label>
                        <select
                          value={besoinFilter}
                          onChange={(e) => setBesoinFilter(e.target.value)}
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        >
                          <option value="all">Tous</option>
                          <option value="Retraite">Retraite</option>
                          <option value="Épargne">Épargne</option>
                          <option value="Prévoyance">Prévoyance</option>
                          <option value="Santé">Santé</option>
                          <option value="Emprunteur">Emprunteur</option>
                          <option value="Autre">Autre</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Situation matrimoniale
                        </label>
                        <select
                          value={situationFilter}
                          onChange={(e) => setSituationFilter(e.target.value)}
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        >
                          <option value="all">Toutes</option>
                          {situationOptions.map((option) => (
                            <option key={option} value={option}>
                              {option}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Tri
                        </label>
                        <select
                          value={sortKey}
                          onChange={(e) => setSortKey(e.target.value)}
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        >
                          <option value="created_desc">Création (récent)</option>
                          <option value="created_asc">Création (ancien)</option>
                          <option value="name_asc">Nom (A → Z)</option>
                          <option value="name_desc">Nom (Z → A)</option>
                          <option value="besoins_desc">Besoins (plus)</option>
                          <option value="besoins_asc">Besoins (moins)</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Profession
                        </label>
                        <input
                          value={professionFilter}
                          onChange={(e) => setProfessionFilter(e.target.value)}
                          placeholder="Ex: médecin, dirigeant..."
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Email
                        </label>
                        <input
                          value={emailFilter}
                          onChange={(e) => setEmailFilter(e.target.value)}
                          placeholder="Filtrer par email"
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Contrat
                        </label>
                        <input
                          value={contractFilter}
                          onChange={(e) => setContractFilter(e.target.value)}
                          placeholder="Nom du contrat"
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Compagnie / Établissement
                        </label>
                        <input
                          value={companyFilter}
                          onChange={(e) => setCompanyFilter(e.target.value)}
                          placeholder="Ex: AXA, Generali..."
                          className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-2">
                          Créé entre
                        </label>
                        <div className="flex items-center gap-2">
                          <input
                            type="date"
                            value={createdFrom}
                            onChange={(e) => setCreatedFrom(e.target.value)}
                            className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                          />
                          <span className="text-xs text-[#B9B9C3]">→</span>
                          <input
                            type="date"
                            value={createdTo}
                            onChange={(e) => setCreatedTo(e.target.value)}
                            className="w-full rounded-lg border border-[#D8D6DE] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7367F0]/30"
                          />
                        </div>
                      </div>
                    </div>

                    <div className="mt-6 border-t border-[#F1F0F5] pt-5">
                      <div className="text-xs font-semibold text-[#6E6B7B] uppercase tracking-wide mb-3">
                        Colonnes visibles
                      </div>
                      <div className="flex flex-wrap gap-3">
                        {[
                          { key: "contact", label: "Contact" },
                          { key: "profession", label: "Profession" },
                          { key: "situation", label: "Situation" },
                          { key: "besoins", label: "Besoins" },
                          { key: "created", label: "Créé le" },
                        ].map((item) => (
                          <label
                            key={item.key}
                            className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold cursor-pointer transition-colors ${
                              visibleColumns[item.key as keyof typeof visibleColumns]
                                ? "border-[#7367F0] bg-[#F2F0FF] text-[#7367F0]"
                                : "border-[#E7E5F7] bg-white text-[#6E6B7B]"
                            }`}
                          >
                            <input
                              type="checkbox"
                              checked={visibleColumns[item.key as keyof typeof visibleColumns]}
                              onChange={() =>
                                setVisibleColumns((prev) => ({
                                  ...prev,
                                  [item.key]: !prev[item.key as keyof typeof visibleColumns],
                                }))
                              }
                              className="accent-[#7367F0]"
                            />
                            {item.label}
                          </label>
                        ))}
                      </div>
                    </div>
                  </div>
                )}
              </div>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-[#EBE9F1]">
                  <thead className="bg-[#F8F8F8]">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Client
                      </th>
                      {visibleColumns.contact && (
                        <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                          Contact
                        </th>
                      )}
                      {visibleColumns.profession && (
                        <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                          Profession
                        </th>
                      )}
                      {visibleColumns.situation && (
                        <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                          Situation
                        </th>
                      )}
                      {visibleColumns.besoins && (
                        <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                          Besoins
                        </th>
                      )}
                      {visibleColumns.created && (
                        <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                          Créé le
                        </th>
                      )}
                      <th className="px-6 py-4 text-right text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-[#EBE9F1]">
                    {paginatedClients.map((client) => (
                      <tr
                        key={client.id}
                        className="hover:bg-[#F8F8F8] transition-colors"
                      >
                        {/* Client */}
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white font-semibold text-sm shadow-md shadow-purple-500/30">
                              {client.prenom?.charAt(0) || ''}
                              {client.nom?.charAt(0) || ''}
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-semibold text-[#5E5873]">
                                {client.nom_complet}
                              </div>
                              <div className="text-xs text-[#B9B9C3]">
                                ID: {client.id}
                              </div>
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
                              {client.profession || (
                                <span className="text-[#B9B9C3] italic">Non renseignée</span>
                              )}
                            </div>
                          </td>
                        )}

                        {/* Situation */}
                        {visibleColumns.situation && (
                          <td className="px-6 py-4">
                            <div className="text-sm text-[#5E5873] font-medium">
                              {client.situation_matrimoniale || (
                                <span className="text-[#B9B9C3] italic">Non renseignée</span>
                              )}
                            </div>
                          </td>
                        )}

                        {/* Besoins */}
                        {visibleColumns.besoins && (
                          <td className="px-6 py-4">
                            {client.besoins && client.besoins.length > 0 ? (
                              <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#7367F0]/10 text-[#7367F0] text-xs font-semibold">
                                {client.besoins.length} besoin{client.besoins.length > 1 ? 's' : ''}
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#B9B9C3]/10 text-[#B9B9C3] text-xs font-semibold">
                                Aucun
                              </span>
                            )}
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
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => navigate(`/clients/${client.id}`)}
                              className="p-2 rounded-lg border border-[#7367F0] text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-all"
                              title="Voir"
                            >
                              <Eye size={16} />
                            </button>
                            <button
                              onClick={() => navigate(`/clients/${client.id}/edit`)}
                              className="p-2 rounded-lg border border-[#00CFE8] text-[#00CFE8] hover:bg-[#00CFE8] hover:text-white transition-all"
                              title="Éditer"
                            >
                              <Edit size={16} />
                            </button>
                            <button
                              onClick={() => handleDelete(client.id, client.nom || '', client.prenom || '')}
                              className="p-2 rounded-lg border border-[#EA5455] text-[#EA5455] hover:bg-[#EA5455] hover:text-white transition-all"
                              title="Supprimer"
                            >
                              <Trash2 size={16} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="border-t border-[#EBE9F1] px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div className="text-sm text-[#6E6B7B]">
                  {filteredClients.length === 0
                    ? "0 résultat"
                    : `Affichage ${(safePage - 1) * pageSize + 1}-${Math.min(safePage * pageSize, filteredClients.length)} sur ${filteredClients.length}`}
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
                    disabled={safePage === 1}
                    className="px-3 py-1.5 rounded-lg border border-[#D8D6DE] text-sm font-semibold text-[#6E6B7B] hover:bg-[#F3F2F7] disabled:opacity-50"
                  >
                    Précédent
                  </button>
                  <div className="flex items-center gap-1">
                    {paginationItems.map((item, index) => {
                      if (item === "...") {
                        return (
                          <span key={`dots-${index}`} className="px-2 text-[#B9B9C3]">
                            ...
                          </span>
                        );
                      }
                      const page = item as number;
                      const active = page === safePage;
                      return (
                        <button
                          key={page}
                          onClick={() => setCurrentPage(page)}
                          className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${
                            active
                              ? "bg-[#7367F0] text-white"
                              : "border border-[#D8D6DE] text-[#6E6B7B] hover:bg-[#F3F2F7]"
                          }`}
                        >
                          {page}
                        </button>
                      );
                    })}
                  </div>
                  <button
                    onClick={() => setCurrentPage((prev) => Math.min(totalPages, prev + 1))}
                    disabled={safePage === totalPages}
                    className="px-3 py-1.5 rounded-lg border border-[#D8D6DE] text-sm font-semibold text-[#6E6B7B] hover:bg-[#F3F2F7] disabled:opacity-50"
                  >
                    Suivant
                  </button>
                </div>
              </div>
            </div>
          )}
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

      {/* Review Changes Modal */}
      {selectedPendingChangeId && (
        <ReviewChangesModal
          pendingChangeId={selectedPendingChangeId}
          onClose={() => setSelectedPendingChangeId(null)}
          onApplied={() => {
            setSelectedPendingChangeId(null);
            fetchClients(); // Refresh clients list after applying changes
          }}
        />
      )}
    </>
  );
};

export default HomePage;
