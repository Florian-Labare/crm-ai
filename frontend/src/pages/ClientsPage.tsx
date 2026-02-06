import React, { useEffect, useState, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { ConfirmDialog } from "../components/ConfirmDialog";
import { ComplianceBadge } from "../components/ComplianceBadge";
import { ConfirmClientModal } from "../components/ConfirmClientModal";
import { Users, User, Archive, Plus, Search, Filter } from "lucide-react";

interface Client {
  id: number;
  nom: string;
  prenom: string;
  profession?: string;
  is_client: boolean;
  is_archived: boolean;
  type_label: string;
}

type FilterType = "prospects" | "clients" | "archived";

const ClientsPage: React.FC = () => {
  const [allClients, setAllClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState("");
  const [activeFilters, setActiveFilters] = useState<FilterType[]>(["prospects", "clients"]);
  const navigate = useNavigate();

  // Etat pour le dialogue de confirmation de suppression
  const [confirmDialog, setConfirmDialog] = useState<{
    isOpen: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
  }>({
    isOpen: false,
    title: "",
    message: "",
    onConfirm: () => {},
  });

  // Etat pour le modal de passage en client
  const [clientModal, setClientModal] = useState<{
    isOpen: boolean;
    client: Client | null;
    isLoading: boolean;
  }>({
    isOpen: false,
    client: null,
    isLoading: false,
  });

  useEffect(() => {
    fetchAllClients();
  }, []);

  const fetchAllClients = async () => {
    try {
      setLoading(true);
      const res = await api.get("/clients?type=all");
      setAllClients(res.data);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement des clients");
    } finally {
      setLoading(false);
    }
  };

  // Filtrage cote frontend
  const filteredClients = useMemo(() => {
    let result = allClients;

    // Filtre par type
    if (activeFilters.length > 0 && activeFilters.length < 3) {
      result = result.filter((client) => {
        if (client.is_archived) return activeFilters.includes("archived");
        if (client.is_client) return activeFilters.includes("clients");
        return activeFilters.includes("prospects");
      });
    }

    // Filtre par recherche
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      result = result.filter(
        (client) =>
          client.nom?.toLowerCase().includes(query) ||
          client.prenom?.toLowerCase().includes(query) ||
          client.profession?.toLowerCase().includes(query)
      );
    }

    return result;
  }, [allClients, activeFilters, searchQuery]);

  // Compteurs
  const counts = useMemo(() => {
    return {
      prospects: allClients.filter((c) => !c.is_client && !c.is_archived).length,
      clients: allClients.filter((c) => c.is_client && !c.is_archived).length,
      archived: allClients.filter((c) => c.is_archived).length,
    };
  }, [allClients]);

  const toggleFilter = (filter: FilterType) => {
    setActiveFilters((prev) =>
      prev.includes(filter)
        ? prev.filter((f) => f !== filter)
        : [...prev, filter]
    );
  };

  const handleDelete = (id: number) => {
    const client = allClients.find((c) => c.id === id);
    setConfirmDialog({
      isOpen: true,
      title: "Supprimer le client",
      message: `Etes-vous sur de vouloir supprimer ${client?.prenom} ${client?.nom} ? Cette action est irreversible.`,
      onConfirm: async () => {
        try {
          await api.delete(`/clients/${id}`);
          setAllClients((prev) => prev.filter((c) => c.id !== id));
          toast.success("Client supprime avec succes");
        } catch (err) {
          console.error(err);
          toast.error("Erreur lors de la suppression du client");
        }
      },
    });
  };

  const handleConvertToClient = (client: Client) => {
    setClientModal({
      isOpen: true,
      client,
      isLoading: false,
    });
  };

  const confirmConvertToClient = async () => {
    if (!clientModal.client) return;

    setClientModal((prev) => ({ ...prev, isLoading: true }));

    try {
      await api.patch(`/clients/${clientModal.client.id}/status`, {
        is_client: true,
      });
      toast.success("Le prospect a ete converti en client");
      setClientModal({ isOpen: false, client: null, isLoading: false });
      fetchAllClients();
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la conversion en client");
      setClientModal((prev) => ({ ...prev, isLoading: false }));
    }
  };

  const handleArchive = async (client: Client) => {
    try {
      await api.post(`/clients/${client.id}/archive`);
      toast.success("Le contact a ete archive");
      fetchAllClients();
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de l'archivage");
    }
  };

  const handleRestore = async (client: Client) => {
    try {
      await api.post(`/clients/${client.id}/restore`);
      toast.success("Le contact a ete restaure");
      fetchAllClients();
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la restauration");
    }
  };

  const getTypeBadge = (client: Client) => {
    if (client.is_archived) {
      return (
        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
          <Archive size={12} />
          Archive
        </span>
      );
    }
    if (client.is_client) {
      return (
        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#28C76F]/10 text-[#28C76F]">
          <Users size={12} />
          Client
        </span>
      );
    }
    return (
      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#00CFE8]/10 text-[#00CFE8]">
        <User size={12} />
        Prospect
      </span>
    );
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center">
        <div className="text-[#6E6B7B]">Chargement des clients...</div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-6xl mx-auto">
          {/* Header */}
          <div className="vx-card p-6 mb-6">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
              <div>
                <h1 className="text-2xl font-semibold text-[#5E5873]">
                  Liste des contacts
                </h1>
                <p className="text-sm text-[#6E6B7B] mt-1">
                  {filteredClients.length} contact{filteredClients.length > 1 ? "s" : ""} affiche{filteredClients.length > 1 ? "s" : ""}
                </p>
              </div>
              <button
                onClick={() => navigate("/clients/new")}
                className="inline-flex items-center gap-2 px-5 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold rounded-lg transition-colors"
              >
                <Plus size={18} />
                Nouveau contact
              </button>
            </div>
          </div>

          {/* Filtres */}
          <div className="vx-card p-4 mb-6">
            <div className="flex flex-col md:flex-row md:items-center gap-4">
              {/* Barre de recherche */}
              <div className="relative flex-1">
                <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#B9B9C3]" />
                <input
                  type="text"
                  placeholder="Rechercher par nom, prenom ou profession..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-[#EBE9F1] rounded-lg text-sm text-[#5E5873] placeholder-[#B9B9C3] focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0] outline-none transition-colors"
                />
              </div>

              {/* Filtres par type */}
              <div className="flex items-center gap-2">
                <Filter size={18} className="text-[#6E6B7B]" />
                <div className="flex gap-2">
                  <button
                    onClick={() => toggleFilter("prospects")}
                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      activeFilters.includes("prospects")
                        ? "bg-[#00CFE8]/10 text-[#00CFE8] border border-[#00CFE8]/30"
                        : "bg-[#F3F2F7] text-[#6E6B7B] border border-transparent hover:bg-[#EBE9F1]"
                    }`}
                  >
                    <User size={14} />
                    Prospects
                    <span className={`ml-1 px-1.5 py-0.5 rounded text-xs ${
                      activeFilters.includes("prospects")
                        ? "bg-[#00CFE8]/20"
                        : "bg-[#EBE9F1]"
                    }`}>
                      {counts.prospects}
                    </span>
                  </button>

                  <button
                    onClick={() => toggleFilter("clients")}
                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      activeFilters.includes("clients")
                        ? "bg-[#28C76F]/10 text-[#28C76F] border border-[#28C76F]/30"
                        : "bg-[#F3F2F7] text-[#6E6B7B] border border-transparent hover:bg-[#EBE9F1]"
                    }`}
                  >
                    <Users size={14} />
                    Clients
                    <span className={`ml-1 px-1.5 py-0.5 rounded text-xs ${
                      activeFilters.includes("clients")
                        ? "bg-[#28C76F]/20"
                        : "bg-[#EBE9F1]"
                    }`}>
                      {counts.clients}
                    </span>
                  </button>

                  <button
                    onClick={() => toggleFilter("archived")}
                    className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      activeFilters.includes("archived")
                        ? "bg-gray-200 text-gray-700 border border-gray-300"
                        : "bg-[#F3F2F7] text-[#6E6B7B] border border-transparent hover:bg-[#EBE9F1]"
                    }`}
                  >
                    <Archive size={14} />
                    Archives
                    <span className={`ml-1 px-1.5 py-0.5 rounded text-xs ${
                      activeFilters.includes("archived")
                        ? "bg-gray-300"
                        : "bg-[#EBE9F1]"
                    }`}>
                      {counts.archived}
                    </span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Liste */}
          <div className="vx-card">
            {filteredClients.length === 0 ? (
              <div className="p-12 text-center">
                <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-[#F3F2F7] flex items-center justify-center">
                  <Users size={32} className="text-[#B9B9C3]" />
                </div>
                <p className="text-[#6E6B7B]">Aucun contact trouve.</p>
                <p className="text-sm text-[#B9B9C3] mt-1">
                  Essayez de modifier vos filtres ou votre recherche.
                </p>
              </div>
            ) : (
              <ul className="divide-y divide-[#EBE9F1]">
                {filteredClients.map((client) => (
                  <li
                    key={client.id}
                    className="p-4 hover:bg-[#F8F8F8] transition-colors cursor-pointer"
                    onClick={() => navigate(`/clients/${client.id}`)}
                  >
                    <div className="flex items-center justify-between">
                      {/* Info client */}
                      <div className="flex items-center gap-4 flex-1 min-w-0">
                        {/* Avatar */}
                        <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                          {client.prenom?.charAt(0)?.toUpperCase() || ""}
                          {client.nom?.charAt(0)?.toUpperCase() || ""}
                        </div>

                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <p className="font-semibold text-[#5E5873] truncate">
                              {client.prenom} {client.nom?.toUpperCase()}
                            </p>
                            {getTypeBadge(client)}
                          </div>
                          <p className="text-sm text-[#6E6B7B] truncate mt-0.5">
                            {client.profession || "Profession non renseignee"}
                          </p>
                        </div>
                      </div>

                      {/* Compliance badge */}
                      <div className="hidden sm:block mx-4">
                        <ComplianceBadge
                          clientId={client.id}
                          variant="badge"
                        />
                      </div>

                      {/* Actions */}
                      <div className="flex items-center gap-2 flex-shrink-0">
                        {/* Bouton Passer en client (pour prospects non archives) */}
                        {!client.is_client && !client.is_archived && (
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleConvertToClient(client);
                            }}
                            className="hidden md:inline-flex text-sm px-3 py-1.5 bg-[#7367F0]/10 text-[#7367F0] rounded-lg hover:bg-[#7367F0]/20 transition-colors font-medium"
                          >
                            Passer en client
                          </button>
                        )}

                        {/* Bouton Archiver (pour non-archives) */}
                        {!client.is_archived && (
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleArchive(client);
                            }}
                            className="hidden md:inline-flex text-sm px-3 py-1.5 bg-[#F3F2F7] text-[#6E6B7B] rounded-lg hover:bg-[#EBE9F1] transition-colors font-medium"
                          >
                            Archiver
                          </button>
                        )}

                        {/* Bouton Restaurer (pour archives) */}
                        {client.is_archived && (
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleRestore(client);
                            }}
                            className="hidden md:inline-flex text-sm px-3 py-1.5 bg-[#28C76F]/10 text-[#28C76F] rounded-lg hover:bg-[#28C76F]/20 transition-colors font-medium"
                          >
                            Restaurer
                          </button>
                        )}

                        {/* Bouton Supprimer */}
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            handleDelete(client.id);
                          }}
                          className="text-sm px-3 py-1.5 text-[#EA5455] hover:bg-[#EA5455]/10 rounded-lg transition-colors font-medium"
                        >
                          Supprimer
                        </button>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>

      {/* Dialogue de confirmation de suppression */}
      <ConfirmDialog
        isOpen={confirmDialog.isOpen}
        onClose={() => setConfirmDialog({ ...confirmDialog, isOpen: false })}
        onConfirm={confirmDialog.onConfirm}
        title={confirmDialog.title}
        message={confirmDialog.message}
        type="danger"
      />

      {/* Modal de confirmation passage en client */}
      <ConfirmClientModal
        isOpen={clientModal.isOpen}
        onClose={() =>
          setClientModal({ isOpen: false, client: null, isLoading: false })
        }
        onConfirm={confirmConvertToClient}
        clientName={
          clientModal.client
            ? `${clientModal.client.prenom} ${clientModal.client.nom?.toUpperCase()}`
            : ""
        }
        isLoading={clientModal.isLoading}
      />
    </>
  );
};

export default ClientsPage;
