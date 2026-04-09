import { useEffect, useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { usePage } from '../contexts/PageContext';
import api from '../api/apiClient';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { Building2, Users, Trash2, ToggleLeft, ToggleRight, Plus, X } from 'lucide-react';

interface AdminUser {
  id: number;
  name: string;
  firstname?: string;
  email: string;
  is_super_admin: boolean;
  teams_count: number;
}

interface AdminTeam {
  id: number;
  name: string;
  personal_team: boolean;
  owner: { id: number; name: string; email: string } | null;
  members_count: number;
  created_at: string;
}

export default function SuperAdminPage() {
  const { isSuperAdmin, user } = useAuth();
  const { setPage } = usePage();
  const [activeTab, setActiveTab] = useState<'teams' | 'users'>('teams');
  const [teams, setTeams] = useState<AdminTeam[]>([]);
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateTeam, setShowCreateTeam] = useState(false);
  const [newTeamName, setNewTeamName] = useState('');
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    setPage('Administration', [], [{ label: 'Administration' }]);
  }, [setPage]);

  useEffect(() => {
    if (!isSuperAdmin) return;
    fetchData();
  }, [isSuperAdmin]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [teamsRes, usersRes] = await Promise.all([
        api.get('/admin/teams'),
        api.get('/admin/users'),
      ]);
      setTeams(teamsRes.data.teams);
      setUsers(usersRes.data.users);
    } catch {
      toast.error('Erreur lors du chargement.');
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteTeam = async (teamId: number) => {
    if (!confirm('Supprimer définitivement ce cabinet et toutes ses données ?')) return;
    try {
      await api.delete(`/admin/teams/${teamId}`);
      setTeams((prev) => prev.filter((t) => t.id !== teamId));
      toast.success('Cabinet supprimé.');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Erreur lors de la suppression.');
    }
  };

  const handleToggleSuperAdmin = async (userId: number) => {
    try {
      const res = await api.put(`/admin/users/${userId}/super-admin`);
      setUsers((prev) =>
        prev.map((u) => u.id === userId ? { ...u, is_super_admin: res.data.is_super_admin } : u)
      );
      toast.success('Statut super admin mis à jour.');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Erreur.');
    }
  };

  const handleCreateTeam = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTeamName.trim()) return;
    setCreating(true);
    try {
      await api.post('/admin/teams', { name: newTeamName });
      toast.success('Cabinet créé.');
      setShowCreateTeam(false);
      setNewTeamName('');
      fetchData();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Erreur lors de la création.');
    } finally {
      setCreating(false);
    }
  };

  if (!isSuperAdmin) {
    return (
      <div className="p-8 text-center text-[#6E6B7B]">
        Accès réservé aux super administrateurs.
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="py-6 px-4 lg:px-6 w-full max-w-7xl mx-auto space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold text-[#5E5873]">Administration</h1>
          <span className="inline-block bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">
            Super Admin
          </span>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 bg-[#F3F2F7] p-1 rounded-xl w-fit">
          {(['teams', 'users'] as const).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-5 py-2 rounded-lg text-sm font-semibold transition-all ${
                activeTab === tab
                  ? 'bg-white text-[#7367F0] shadow-sm'
                  : 'text-[#6E6B7B] hover:text-[#5E5873]'
              }`}
            >
              {tab === 'teams' ? (
                <span className="flex items-center gap-2"><Building2 size={15} /> Cabinets</span>
              ) : (
                <span className="flex items-center gap-2"><Users size={15} /> Utilisateurs</span>
              )}
            </button>
          ))}
        </div>

        {loading ? (
          <div className="text-center py-12 text-[#B9B9C3]">Chargement...</div>
        ) : activeTab === 'teams' ? (
          <div className="vx-card">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-[#5E5873]">Cabinets ({teams.length})</h2>
              <button
                onClick={() => setShowCreateTeam(true)}
                className="flex items-center gap-2 px-4 py-2 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold text-sm rounded-lg transition-colors"
              >
                <Plus size={14} />
                Créer un cabinet
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[#EBE9F1]">
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Nom</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Owner</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Membres</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Créé le</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {teams.map((team) => (
                    <tr key={team.id} className="border-b border-[#F3F2F7] hover:bg-[#F8F8F8]">
                      <td className="px-3 py-3 font-medium text-[#5E5873]">
                        {team.name}
                        {team.personal_team && (
                          <span className="ml-2 text-xs text-[#B9B9C3]">(personnel)</span>
                        )}
                      </td>
                      <td className="px-3 py-3 text-[#6E6B7B]">{team.owner?.name ?? '—'}</td>
                      <td className="px-3 py-3 text-[#6E6B7B]">{team.members_count}</td>
                      <td className="px-3 py-3 text-[#6E6B7B]">
                        {new Date(team.created_at).toLocaleDateString('fr-FR')}
                      </td>
                      <td className="px-3 py-3">
                        {!team.personal_team && (
                          <button
                            onClick={() => handleDeleteTeam(team.id)}
                            className="text-[#EA5455] hover:text-red-700 p-1 rounded transition-colors"
                            title="Supprimer le cabinet"
                          >
                            <Trash2 size={15} />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
          <div className="vx-card">
            <h2 className="text-lg font-semibold text-[#5E5873] mb-4">Utilisateurs ({users.length})</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[#EBE9F1]">
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Nom</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Email</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Cabinets</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Super Admin</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id} className="border-b border-[#F3F2F7] hover:bg-[#F8F8F8]">
                      <td className="px-3 py-3 font-medium text-[#5E5873]">{u.firstname} {u.name}</td>
                      <td className="px-3 py-3 text-[#6E6B7B]">{u.email}</td>
                      <td className="px-3 py-3 text-[#6E6B7B]">{u.teams_count}</td>
                      <td className="px-3 py-3">
                        <button
                          onClick={() => handleToggleSuperAdmin(u.id)}
                          disabled={u.id === user?.id}
                          className={`flex items-center gap-1.5 text-sm font-medium transition-colors ${
                            u.is_super_admin ? 'text-[#7367F0]' : 'text-[#B9B9C3]'
                          } disabled:opacity-40`}
                          title={u.id === user?.id ? 'Vous ne pouvez pas modifier votre propre statut' : ''}
                        >
                          {u.is_super_admin ? <ToggleRight size={20} /> : <ToggleLeft size={20} />}
                          {u.is_super_admin ? 'Oui' : 'Non'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Modal créer cabinet */}
      {showCreateTeam && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div className="flex items-center justify-between px-6 py-4 border-b border-[#EBE9F1]">
              <h3 className="text-lg font-bold text-[#5E5873]">Créer un cabinet</h3>
              <button onClick={() => setShowCreateTeam(false)} className="text-[#B9B9C3] hover:text-[#5E5873]">
                <X size={20} />
              </button>
            </div>
            <form onSubmit={handleCreateTeam} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-semibold text-[#5E5873] mb-2">Nom du cabinet</label>
                <input
                  type="text"
                  required
                  value={newTeamName}
                  onChange={(e) => setNewTeamName(e.target.value)}
                  className="w-full px-4 py-2.5 border border-[#D8D6DE] rounded-lg text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
                  placeholder="Ex: Cabinet Dupont Conseil"
                />
              </div>
              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowCreateTeam(false)}
                  className="flex-1 px-4 py-2.5 border border-[#D8D6DE] text-[#6E6B7B] font-semibold rounded-lg hover:bg-[#F8F8F8] transition-colors"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  disabled={creating}
                  className="flex-1 px-4 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold rounded-lg disabled:opacity-50 transition-colors"
                >
                  {creating ? 'Création...' : 'Créer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
