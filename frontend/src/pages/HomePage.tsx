import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { ConfirmDialog } from "../components/ConfirmDialog";
import { extractCollection } from "../utils/apiHelpers";
import type { Client } from "../types/api";
import { Users, UserPlus, ClipboardList, Eye, Edit, Trash2, Mail, Phone, LogOut } from "lucide-react";
import { VuexyStatCard } from "../components/VuexyStatCard";
import { PendingChangesBadge } from "../components/PendingChangesBadge";
import { ReviewChangesModal } from "../components/ReviewChangesModal";
import { useAuth } from "../contexts/AuthContext";

interface ExtendedClient extends Client {
  situation_matrimoniale?: string;
  besoins?: string[];
}

const HomePage: React.FC = () => {
  const [clients, setClients] = useState<ExtendedClient[]>([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    total: 0,
    nouveaux: 0,
    avecBesoins: 0,
  });
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const [selectedPendingChangeId, setSelectedPendingChangeId] = useState<number | null>(null);

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

          {/* Tableau des clients */}
          {clients.length === 0 ? (
            <div className="vx-card text-center py-12">
              <Users className="mx-auto h-16 w-16 text-[#B9B9C3] mb-4" />
              <h3 className="text-xl font-semibold text-[#5E5873] mb-2">
                Aucun client pour le moment
              </h3>
              <p className="text-[#6E6B7B] mb-6">
                Commencez par créer votre premier client
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
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-[#EBE9F1]">
                  <thead className="bg-[#F8F8F8]">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Client
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Contact
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Profession
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Situation
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Besoins
                      </th>
                      <th className="px-6 py-4 text-right text-xs font-semibold text-[#5E5873] uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-[#EBE9F1]">
                    {clients.map((client) => (
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

                        {/* Profession */}
                        <td className="px-6 py-4">
                          <div className="text-sm text-[#5E5873] font-medium">
                            {client.profession || (
                              <span className="text-[#B9B9C3] italic">Non renseignée</span>
                            )}
                          </div>
                        </td>

                        {/* Situation */}
                        <td className="px-6 py-4">
                          <div className="text-sm text-[#5E5873] font-medium">
                            {client.situation_matrimoniale || (
                              <span className="text-[#B9B9C3] italic">Non renseignée</span>
                            )}
                          </div>
                        </td>

                        {/* Besoins */}
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
