import { useEffect, useRef, useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { usePage } from '../contexts/PageContext';
import { useSearchParams } from 'react-router-dom';
import api from '../api/apiClient';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { Users, Mail, Trash2, Edit3, Send, X, Link, CheckCircle, Image } from 'lucide-react';

interface Member {
  id: number;
  name: string;
  email: string;
  role: 'owner' | 'admin' | 'mia' | 'secretaire';
}

interface Invitation {
  id: number;
  email: string;
  role: string;
  invited_by: string;
  expires_at: string;
}

const ROLE_COLORS: Record<string, string> = {
  owner:      'bg-purple-100 text-purple-700',
  admin:      'bg-blue-100 text-blue-700',
  mia:        'bg-green-100 text-green-700',
  secretaire: 'bg-gray-100 text-gray-600',
};

const ROLE_LABELS: Record<string, string> = {
  owner:      'Propriétaire',
  admin:      'Admin',
  mia:        'MIA',
  secretaire: 'Secrétaire',
};

export default function CabinetSettingsPage() {
  const { user, isAdmin, fetchUser } = useAuth();
  const { setPage } = usePage();
  const [searchParams, setSearchParams] = useSearchParams();
  const [teamName, setTeamName] = useState('');
  const [savingName, setSavingName] = useState(false);

  // Logo state
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const logoInputRef = useRef<HTMLInputElement>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [loading, setLoading] = useState(true);
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState<'admin' | 'mia' | 'secretaire'>('mia');
  const [inviting, setInviting] = useState(false);
  const [linkingProvider, setLinkingProvider] = useState<string | null>(null);

  const teamId = user?.current_team_id;
  const isOwner = user?.team_role === 'owner';
  const linkedProviders = user?.linked_providers ?? [];

  useEffect(() => {
    setPage('Paramètres du cabinet', [], [
      { label: 'Paramètres' },
      { label: 'Cabinet' },
    ]);
  }, [setPage]);

  // Gestion du retour OAuth (mode "link")
  useEffect(() => {
    const oauthResult = searchParams.get('oauth');
    const provider = searchParams.get('provider');
    if (oauthResult === 'linked' && provider) {
      const label = provider === 'google' ? 'Gmail' : 'Outlook';
      toast.success(`Compte ${label} connecté avec succès ! Vos DERs partiront désormais depuis cette adresse.`);
      fetchUser(); // Rafraîchir linked_providers
      setSearchParams({}, { replace: true });
    } else if (oauthResult === 'error') {
      toast.error('Erreur lors de la connexion du compte email.');
      setSearchParams({}, { replace: true });
    }
  }, []);

  const handleLinkProvider = async (provider: 'google' | 'azure') => {
    setLinkingProvider(provider);
    try {
      const res = await api.get(`/user/oauth/${provider}/initiate`);
      window.location.href = res.data.redirect_url;
    } catch {
      toast.error('Impossible d\'initier la connexion OAuth.');
      setLinkingProvider(null);
    }
  };

  useEffect(() => {
    if (!teamId) return;
    setTeamName(user?.current_team_name ?? '');
    fetchData();
  }, [teamId]);

  const fetchData = async () => {
    if (!teamId) return;
    setLoading(true);
    try {
      const [membersRes, invRes] = await Promise.all([
        api.get(`/teams/${teamId}/members`),
        api.get(`/teams/${teamId}/invitations`),
      ]);
      setMembers(membersRes.data.members);
      setInvitations(invRes.data.invitations);
    } catch {
      toast.error('Erreur lors du chargement des données.');
    } finally {
      setLoading(false);
    }
  };

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setLogoFile(file);
    setLogoPreview(URL.createObjectURL(file));
  };

  const handleLogoUpload = async () => {
    if (!teamId || !logoFile) return;
    setUploadingLogo(true);
    try {
      const formData = new FormData();
      formData.append('logo', logoFile);
      await api.post(`/teams/${teamId}/logo`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Logo mis à jour avec succès.');
      setLogoFile(null);
      await fetchUser();
    } catch {
      toast.error('Erreur lors de l\'upload du logo.');
    } finally {
      setUploadingLogo(false);
    }
  };

  const handleSaveName = async () => {
    if (!teamId || !teamName.trim()) return;
    setSavingName(true);
    try {
      await api.put(`/teams/${teamId}`, { name: teamName });
      toast.success('Nom du cabinet mis à jour.');
    } catch {
      toast.error('Erreur lors de la mise à jour.');
    } finally {
      setSavingName(false);
    }
  };

  const handleChangeRole = async (memberId: number, role: string) => {
    if (!teamId) return;
    try {
      await api.put(`/teams/${teamId}/members/${memberId}`, { role });
      setMembers((prev) => prev.map((m) => m.id === memberId ? { ...m, role: role as Member['role'] } : m));
      toast.success('Rôle mis à jour.');
    } catch {
      toast.error('Erreur lors de la mise à jour du rôle.');
    }
  };

  const handleRemoveMember = async (memberId: number) => {
    if (!teamId || !confirm('Retirer ce membre ?')) return;
    try {
      await api.delete(`/teams/${teamId}/members/${memberId}`);
      setMembers((prev) => prev.filter((m) => m.id !== memberId));
      toast.success('Membre retiré.');
    } catch {
      toast.error('Erreur lors de la suppression.');
    }
  };

  const handleCancelInvitation = async (invId: number) => {
    if (!teamId || !confirm('Annuler cette invitation ?')) return;
    try {
      await api.delete(`/teams/${teamId}/invitations/${invId}`);
      setInvitations((prev) => prev.filter((i) => i.id !== invId));
      toast.success('Invitation annulée.');
    } catch {
      toast.error('Erreur lors de l\'annulation.');
    }
  };

  const handleInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!teamId) return;
    setInviting(true);
    try {
      await api.post(`/teams/${teamId}/members`, { email: inviteEmail, role: inviteRole });
      toast.success('Invitation envoyée !');
      setShowInviteModal(false);
      setInviteEmail('');
      setInviteRole('mia');
      fetchData();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Erreur lors de l\'invitation.');
    } finally {
      setInviting(false);
    }
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={5000} />
      <div className="py-6 px-4 lg:px-6 w-full max-w-7xl mx-auto space-y-6">
        <h1 className="text-2xl font-bold text-[#5E5873]">Paramètres du cabinet</h1>

        {/* Section — Comptes email connectés (visible par tous) */}
        <div className="vx-card">
          <h2 className="text-lg font-semibold text-[#5E5873] mb-1 flex items-center gap-2">
            <Link size={18} className="text-[#7367F0]" />
            Mon compte email pour l'envoi des DERs
          </h2>
          <p className="text-sm text-[#6E6B7B] mb-4">
            Connectez votre compte Gmail ou Outlook pour que vos DERs soient envoyés directement depuis votre adresse professionnelle.
          </p>
          <div className="flex flex-wrap gap-3">
            {/* Google */}
            <div className="flex items-center gap-3 p-3 border border-[#EBE9F1] rounded-lg bg-[#F8F8F8] min-w-[240px]">
              <svg className="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-[#5E5873]">Gmail</p>
                {linkedProviders.includes('google') ? (
                  <p className="text-xs text-[#28C76F] flex items-center gap-1 mt-0.5">
                    <CheckCircle size={11} /> Connecté
                  </p>
                ) : (
                  <p className="text-xs text-[#B9B9C3] mt-0.5">Non connecté</p>
                )}
              </div>
              <button
                onClick={() => handleLinkProvider('google')}
                disabled={linkingProvider === 'google'}
                className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors ${
                  linkedProviders.includes('google')
                    ? 'bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EBE9F1]'
                    : 'bg-[#7367F0] hover:bg-[#5E50EE] text-white'
                } disabled:opacity-50`}
              >
                {linkingProvider === 'google' ? 'Redirection...' : linkedProviders.includes('google') ? 'Reconnecter' : 'Connecter'}
              </button>
            </div>

            {/* Outlook / Azure */}
            <div className="flex items-center gap-3 p-3 border border-[#EBE9F1] rounded-lg bg-[#F8F8F8] min-w-[240px]">
              <svg className="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="#0078D4">
                <path d="M24 12.003C24 5.376 18.629 0 12 0 5.371 0 0 5.376 0 12.003 0 17.625 3.875 22.347 9.125 23.647V15.75H6.375v-3.747h2.75v-2.374c0-3.126 1.625-4.876 4.5-4.876 1.375 0 2.875.25 2.875.25v3.126h-1.625c-1.5 0-1.875.876-1.875 1.875v2h3.25l-.5 3.747h-2.75v7.897C20.125 22.347 24 17.625 24 12.003z"/>
              </svg>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-[#5E5873]">Outlook / Microsoft</p>
                {linkedProviders.includes('azure') ? (
                  <p className="text-xs text-[#28C76F] flex items-center gap-1 mt-0.5">
                    <CheckCircle size={11} /> Connecté
                  </p>
                ) : (
                  <p className="text-xs text-[#B9B9C3] mt-0.5">Non connecté</p>
                )}
              </div>
              <button
                onClick={() => handleLinkProvider('azure')}
                disabled={linkingProvider === 'azure'}
                className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors ${
                  linkedProviders.includes('azure')
                    ? 'bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EBE9F1]'
                    : 'bg-[#7367F0] hover:bg-[#5E50EE] text-white'
                } disabled:opacity-50`}
              >
                {linkingProvider === 'azure' ? 'Redirection...' : linkedProviders.includes('azure') ? 'Reconnecter' : 'Connecter'}
              </button>
            </div>
          </div>
        </div>

        {!isAdmin && (
          <div className="p-4 bg-[#F8F8F8] rounded-lg text-sm text-[#6E6B7B] text-center">
            Les paramètres du cabinet sont réservés aux administrateurs.
          </div>
        )}

        {isAdmin && (<>

        {/* Section Logo */}
        <div className="vx-card">
          <h2 className="text-lg font-semibold text-[#5E5873] mb-4 flex items-center gap-2">
            <Image size={18} className="text-[#7367F0]" />
            Logo du cabinet
          </h2>
          <p className="text-sm text-[#6E6B7B] mb-4">
            Ce logo apparaîtra dans la barre latérale et dans les documents générés (DER, documents réglementaires).
          </p>
          <div className="flex items-center gap-6">
            {/* Aperçu */}
            <div className="w-24 h-24 rounded-xl border-2 border-dashed border-[#D8D6DE] flex items-center justify-center bg-[#F8F8F8] overflow-hidden flex-shrink-0">
              {(logoPreview || user?.current_team_logo_url) ? (
                <img
                  src={logoPreview ?? user!.current_team_logo_url!}
                  alt="Logo cabinet"
                  className="w-full h-full object-contain p-2"
                />
              ) : (
                <Image size={32} className="text-[#D8D6DE]" />
              )}
            </div>
            {/* Actions */}
            <div className="space-y-3">
              <input
                ref={logoInputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                className="hidden"
                onChange={handleLogoChange}
              />
              <button
                onClick={() => logoInputRef.current?.click()}
                className="px-4 py-2 border border-[#7367F0] text-[#7367F0] font-semibold text-sm rounded-lg hover:bg-[#7367F0]/5 transition-colors"
              >
                Choisir un logo
              </button>
              {logoFile && (
                <div className="flex items-center gap-3">
                  <span className="text-xs text-[#6E6B7B] truncate max-w-[160px]">{logoFile.name}</span>
                  <button
                    onClick={handleLogoUpload}
                    disabled={uploadingLogo}
                    className="px-4 py-2 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold text-sm rounded-lg disabled:opacity-50 transition-colors"
                  >
                    {uploadingLogo ? 'Upload...' : 'Enregistrer'}
                  </button>
                </div>
              )}
              <p className="text-xs text-[#B9B9C3]">PNG, JPG, WebP — max 2 Mo</p>
            </div>
          </div>
        </div>

        {/* Section A — Nom du cabinet */}
        <div className="vx-card">
          <h2 className="text-lg font-semibold text-[#5E5873] mb-4 flex items-center gap-2">
            <Edit3 size={18} className="text-[#7367F0]" />
            Informations du cabinet
          </h2>
          <div className="flex gap-3">
            <input
              type="text"
              value={teamName}
              onChange={(e) => setTeamName(e.target.value)}
              className="flex-1 px-4 py-2.5 border border-[#D8D6DE] rounded-lg text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
              placeholder="Nom du cabinet"
            />
            <button
              onClick={handleSaveName}
              disabled={savingName || !teamName.trim()}
              className="px-5 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold rounded-lg disabled:opacity-50 transition-colors"
            >
              {savingName ? 'Sauvegarde...' : 'Sauvegarder'}
            </button>
          </div>
        </div>

        {/* Section B — Membres */}
        <div className="vx-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-[#5E5873] flex items-center gap-2">
              <Users size={18} className="text-[#7367F0]" />
              Membres ({members.length})
            </h2>
            <button
              onClick={() => setShowInviteModal(true)}
              className="flex items-center gap-2 px-4 py-2 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold text-sm rounded-lg transition-colors"
            >
              <Send size={14} />
              + Inviter un membre
            </button>
          </div>

          {loading ? (
            <div className="text-center py-8 text-[#B9B9C3]">Chargement...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[#EBE9F1]">
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Nom</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Email</th>
                    <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Rôle</th>
                    {isAdmin && <th className="text-left px-3 py-2 text-[#5E5873] font-semibold">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {members.map((member) => {
                    // Un admin ne peut agir que sur les membres/viewers.
                    // L'owner peut agir sur tout le monde (sauf lui-même).
                    const canAct = member.role !== 'owner' && member.id !== user?.id
                      && (isOwner || member.role !== 'admin');
                    return (
                    <tr key={member.id} className="border-b border-[#F3F2F7] hover:bg-[#F8F8F8]">
                      <td className="px-3 py-3 font-medium text-[#5E5873]">{member.name}</td>
                      <td className="px-3 py-3 text-[#6E6B7B]">{member.email}</td>
                      <td className="px-3 py-3">
                        <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ${ROLE_COLORS[member.role]}`}>
                          {ROLE_LABELS[member.role]}
                        </span>
                      </td>
                      {isAdmin && (
                        <td className="px-3 py-3">
                          {canAct ? (
                            <div className="flex items-center gap-2">
                              <select
                                value={member.role}
                                onChange={(e) => handleChangeRole(member.id, e.target.value)}
                                className="text-xs border border-[#D8D6DE] rounded px-2 py-1 text-[#5E5873]"
                              >
                                {isOwner && <option value="admin">Admin</option>}
                                <option value="mia">MIA</option>
                                <option value="secretaire">Secrétaire</option>
                              </select>
                              <button
                                onClick={() => handleRemoveMember(member.id)}
                                className="text-[#EA5455] hover:text-red-700 p-1 rounded transition-colors"
                                title="Retirer"
                              >
                                <Trash2 size={14} />
                              </button>
                            </div>
                          ) : (
                            <span className="text-xs text-[#B9B9C3] italic">—</span>
                          )}
                        </td>
                      )}
                    </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Section C — Invitations en attente */}
        {invitations.length > 0 && (
          <div className="vx-card">
            <h2 className="text-lg font-semibold text-[#5E5873] mb-4 flex items-center gap-2">
              <Mail size={18} className="text-[#7367F0]" />
              Invitations en attente ({invitations.length})
            </h2>
            <div className="space-y-2">
              {invitations.map((inv) => (
                <div key={inv.id} className="flex items-center justify-between p-3 bg-[#F8F8F8] rounded-lg">
                  <div>
                    <p className="font-medium text-[#5E5873] text-sm">{inv.email}</p>
                    <p className="text-xs text-[#B9B9C3] mt-0.5">
                      {ROLE_LABELS[inv.role] ?? inv.role} · Expire le{' '}
                      {new Date(inv.expires_at).toLocaleDateString('fr-FR')}
                    </p>
                  </div>
                  <button
                    onClick={() => handleCancelInvitation(inv.id)}
                    className="text-[#EA5455] hover:text-red-700 p-1.5 rounded transition-colors"
                    title="Annuler l'invitation"
                  >
                    <X size={16} />
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Fin section admin */}
        </>)}

        {/* Modal d'invitation */}
        {showInviteModal && (
          <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-md">
              <div className="flex items-center justify-between px-6 py-4 border-b border-[#EBE9F1]">
                <h3 className="text-lg font-bold text-[#5E5873]">Inviter un membre</h3>
                <button onClick={() => setShowInviteModal(false)} className="text-[#B9B9C3] hover:text-[#5E5873]">
                  <X size={20} />
                </button>
              </div>
              <form onSubmit={handleInvite} className="p-6 space-y-4">
                <div>
                  <label className="block text-sm font-semibold text-[#5E5873] mb-2">Email</label>
                  <input
                    type="email"
                    required
                    value={inviteEmail}
                    onChange={(e) => setInviteEmail(e.target.value)}
                    className="w-full px-4 py-2.5 border border-[#D8D6DE] rounded-lg text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
                    placeholder="membre@exemple.com"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-[#5E5873] mb-2">Rôle</label>
                  <select
                    value={inviteRole}
                    onChange={(e) => setInviteRole(e.target.value as typeof inviteRole)}
                    className="w-full px-4 py-2.5 border border-[#D8D6DE] rounded-lg text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] transition-all"
                  >
                    <option value="admin">Admin</option>
                    <option value="mia">MIA</option>
                    <option value="secretaire">Secrétaire</option>
                  </select>
                </div>
                <div className="flex gap-3 pt-2">
                  <button
                    type="button"
                    onClick={() => setShowInviteModal(false)}
                    className="flex-1 px-4 py-2.5 border border-[#D8D6DE] text-[#6E6B7B] font-semibold rounded-lg hover:bg-[#F8F8F8] transition-colors"
                  >
                    Annuler
                  </button>
                  <button
                    type="submit"
                    disabled={inviting}
                    className="flex-1 px-4 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold rounded-lg disabled:opacity-50 transition-colors"
                  >
                    {inviting ? 'Envoi...' : 'Envoyer l\'invitation'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
