import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { usePage } from '../contexts/PageContext';
import api from '../api/apiClient';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { Lock, User, Building2, CheckCircle, Eye, EyeOff, Camera, Trash2 } from 'lucide-react';

const AVATAR_COLORS = [
  { id: 'purple', gradient: 'from-[#7367F0] to-[#9055FD]', label: 'Violet' },
  { id: 'blue',   gradient: 'from-[#00CFE8] to-[#1E9BCE]', label: 'Bleu' },
  { id: 'green',  gradient: 'from-[#28C76F] to-[#48DA89]', label: 'Vert' },
  { id: 'orange', gradient: 'from-[#FF9F43] to-[#FFBE76]', label: 'Orange' },
  { id: 'rose',   gradient: 'from-[#EA5455] to-[#F08182]', label: 'Rose' },
  { id: 'slate',  gradient: 'from-[#82868B] to-[#A8AAAE]', label: 'Gris' },
];

const ROLE_META: Record<string, { label: string; color: string }> = {
  owner:  { label: 'Propriétaire', color: 'bg-purple-100 text-purple-700' },
  admin:  { label: 'Administrateur', color: 'bg-blue-100 text-blue-700' },
  member: { label: 'Membre', color: 'bg-green-100 text-green-700' },
  viewer: { label: 'Observateur', color: 'bg-gray-100 text-gray-600' },
};

const AVATAR_COLOR_KEY = 'profile_avatar_color';

export default function ProfilePage() {
  const { user, fetchUser } = useAuth();
  const { setPage } = usePage();
  const navigate = useNavigate();
  const avatarInputRef = useRef<HTMLInputElement>(null);

  // Avatar
  const [avatarColor, setAvatarColor] = useState<string>(
    () => localStorage.getItem(AVATAR_COLOR_KEY) ?? 'purple'
  );
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [removingAvatar, setRemovingAvatar] = useState(false);

  // Profile form
  const [firstname, setFirstname] = useState('');
  const [name, setName] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);

  // Password form
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);

  useEffect(() => {
    setPage('Mon profil', [], [{ label: 'Profil' }]);
  }, [setPage]);

  useEffect(() => {
    if (user) {
      setFirstname(user.firstname ?? '');
      setName(user.name ?? '');
    }
  }, [user]);

  const currentGradient = AVATAR_COLORS.find(c => c.id === avatarColor)?.gradient ?? AVATAR_COLORS[0].gradient;
  const initials = user
    ? `${user.firstname?.charAt(0) ?? ''}${user.name?.charAt(0) ?? ''}`.toUpperCase() || 'U'
    : 'U';
  const displayName = user ? `${user.firstname ?? ''} ${user.name ?? ''}`.trim() : '';
  const roleMeta = ROLE_META[user?.team_role ?? ''] ?? null;

  // Current avatar to display: local preview > remote URL > initials
  const displayAvatarUrl = avatarPreview ?? user?.avatar_url ?? null;

  const handleAvatarColor = (colorId: string) => {
    setAvatarColor(colorId);
    localStorage.setItem(AVATAR_COLOR_KEY, colorId);
    window.dispatchEvent(new Event('avatar-color-changed'));
  };

  const handleAvatarFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setAvatarFile(file);
    setAvatarPreview(URL.createObjectURL(file));
  };

  const handleAvatarUpload = async () => {
    if (!avatarFile) return;
    setUploadingAvatar(true);
    try {
      const formData = new FormData();
      formData.append('avatar', avatarFile);
      await api.post('/user/avatar', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
      await fetchUser();
      setAvatarFile(null);
      setAvatarPreview(null);
      toast.success('Photo de profil mise à jour.');
      window.dispatchEvent(new Event('avatar-color-changed'));
    } catch {
      toast.error('Erreur lors de l\'upload de la photo.');
    } finally {
      setUploadingAvatar(false);
    }
  };

  const handleRemoveAvatar = async () => {
    if (!confirm('Supprimer la photo de profil ?')) return;
    setRemovingAvatar(true);
    try {
      await api.delete('/user/avatar');
      await fetchUser();
      setAvatarFile(null);
      setAvatarPreview(null);
      toast.success('Photo supprimée.');
      window.dispatchEvent(new Event('avatar-color-changed'));
    } catch {
      toast.error('Erreur lors de la suppression.');
    } finally {
      setRemovingAvatar(false);
    }
  };

  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingProfile(true);
    try {
      await api.put('/user/profile', { firstname, name });
      await fetchUser();
      toast.success('Profil mis à jour.');
    } catch {
      toast.error('Erreur lors de la mise à jour du profil.');
    } finally {
      setSavingProfile(false);
    }
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword !== confirmPassword) {
      toast.error('Les mots de passe ne correspondent pas.');
      return;
    }
    setSavingPassword(true);
    try {
      await api.put('/user/password', {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      toast.success('Mot de passe mis à jour.');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Erreur lors du changement de mot de passe.');
    } finally {
      setSavingPassword(false);
    }
  };

  const passwordStrength = (() => {
    if (!newPassword) return null;
    if (newPassword.length < 8) return { label: 'Trop court', color: 'bg-red-400', width: 'w-1/4' };
    const score = [/[A-Z]/.test(newPassword), /[0-9]/.test(newPassword), /[^A-Za-z0-9]/.test(newPassword)].filter(Boolean).length;
    if (score === 0) return { label: 'Faible', color: 'bg-orange-400', width: 'w-1/3' };
    if (score === 1) return { label: 'Moyen', color: 'bg-yellow-400', width: 'w-2/3' };
    return { label: 'Fort', color: 'bg-green-500', width: 'w-full' };
  })();

  return (
    <>
      <ToastContainer position="top-right" autoClose={4000} />
      <div className="py-6 px-4 lg:px-6 w-full max-w-7xl mx-auto space-y-6">

        {/* Header card — avatar + identité */}
        <div className="vx-card">
          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-6">

            {/* Avatar interactif */}
            <div className="relative flex-shrink-0">
              <input
                ref={avatarInputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                className="hidden"
                onChange={handleAvatarFileChange}
              />
              <div
                className="w-24 h-24 rounded-2xl overflow-hidden cursor-pointer group relative shadow-lg"
                onClick={() => avatarInputRef.current?.click()}
                title="Changer la photo"
              >
                {displayAvatarUrl ? (
                  <img src={displayAvatarUrl} alt="Avatar" className="w-full h-full object-cover" />
                ) : (
                  <div className={`w-full h-full bg-gradient-to-br ${currentGradient} flex items-center justify-center text-white text-3xl font-bold`}>
                    {initials}
                  </div>
                )}
                {/* Overlay au hover */}
                <div className="absolute inset-0 bg-black/40 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl">
                  <Camera size={20} className="text-white" />
                  <span className="text-white text-[10px] font-semibold mt-1">Modifier</span>
                </div>
              </div>

              {/* Bouton supprimer — si photo uploadée */}
              {(user?.avatar_url || avatarPreview) && (
                <button
                  onClick={handleRemoveAvatar}
                  disabled={removingAvatar}
                  title="Supprimer la photo"
                  className="absolute -top-2 -right-2 w-6 h-6 bg-[#EA5455] hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition-colors disabled:opacity-50"
                >
                  <Trash2 size={11} />
                </button>
              )}
            </div>

            {/* Infos + actions */}
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-[#5E5873] leading-tight">{displayName || '—'}</h1>
              <p className="text-sm text-[#B9B9C3] mt-0.5">{user?.email}</p>
              <div className="flex items-center gap-2 mt-3 flex-wrap">
                {roleMeta && (
                  <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${roleMeta.color}`}>
                    {roleMeta.label}
                  </span>
                )}
                {user?.current_team_name && (
                  <button
                    onClick={() => navigate('/settings/cabinet')}
                    className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EBE9F1] transition-colors"
                  >
                    <Building2 size={11} />
                    {user.current_team_name}
                  </button>
                )}
              </div>

              {/* Actions avatar */}
              <div className="flex items-center gap-3 mt-4">
                <button
                  onClick={() => avatarInputRef.current?.click()}
                  className="px-3 py-1.5 border border-[#7367F0] text-[#7367F0] text-xs font-semibold rounded-lg hover:bg-[#7367F0]/5 transition-colors"
                >
                  {user?.avatar_url ? 'Changer la photo' : 'Ajouter une photo'}
                </button>
                {avatarFile && (
                  <button
                    onClick={handleAvatarUpload}
                    disabled={uploadingAvatar}
                    className="px-3 py-1.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white text-xs font-semibold rounded-lg disabled:opacity-50 transition-colors"
                  >
                    {uploadingAvatar ? 'Upload...' : 'Enregistrer'}
                  </button>
                )}
                {avatarFile && (
                  <button
                    onClick={() => { setAvatarFile(null); setAvatarPreview(null); }}
                    className="text-xs text-[#B9B9C3] hover:text-[#6E6B7B] transition-colors"
                  >
                    Annuler
                  </button>
                )}
              </div>
              {avatarFile && (
                <p className="text-xs text-[#B9B9C3] mt-1.5">{avatarFile.name}</p>
              )}
            </div>
          </div>
        </div>

        {/* Grille 2 colonnes sur grand écran */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

          {/* Apparence — couleur de l'avatar */}
          <div className="vx-card">
            <h2 className="text-base font-semibold text-[#5E5873] mb-1">Couleur de l'avatar</h2>
            <p className="text-sm text-[#B9B9C3] mb-4">
              Utilisée quand vous n'avez pas de photo de profil.
            </p>
            <div className="flex items-center gap-3 flex-wrap">
              {AVATAR_COLORS.map(color => (
                <button
                  key={color.id}
                  onClick={() => handleAvatarColor(color.id)}
                  title={color.label}
                  className={`w-10 h-10 rounded-xl bg-gradient-to-br ${color.gradient} flex items-center justify-center transition-all ${
                    avatarColor === color.id
                      ? 'ring-2 ring-offset-2 ring-[#7367F0] scale-110'
                      : 'hover:scale-105 opacity-60 hover:opacity-100'
                  }`}
                >
                  {avatarColor === color.id && (
                    <CheckCircle size={16} className="text-white drop-shadow" />
                  )}
                </button>
              ))}
            </div>
            <p className="text-xs text-[#B9B9C3] mt-3">
              La couleur choisie s'applique aussi dans la barre latérale.
            </p>
          </div>

          {/* Informations personnelles */}
          <div className="vx-card">
            <h2 className="text-base font-semibold text-[#5E5873] mb-1 flex items-center gap-2">
              <User size={16} className="text-[#7367F0]" />
              Informations personnelles
            </h2>
            <p className="text-sm text-[#B9B9C3] mb-4">Votre nom tel qu'il apparaît dans l'application.</p>
            <form onSubmit={handleSaveProfile} className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-[#5E5873] mb-1.5 uppercase tracking-wide">Prénom</label>
                  <input
                    type="text"
                    value={firstname}
                    onChange={e => setFirstname(e.target.value)}
                    className="w-full px-3 py-2.5 border border-[#D8D6DE] rounded-lg text-sm text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
                    placeholder="Prénom"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-[#5E5873] mb-1.5 uppercase tracking-wide">Nom</label>
                  <input
                    type="text"
                    value={name}
                    onChange={e => setName(e.target.value)}
                    className="w-full px-3 py-2.5 border border-[#D8D6DE] rounded-lg text-sm text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
                    placeholder="Nom"
                    required
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-[#5E5873] mb-1.5 uppercase tracking-wide">Email</label>
                <input
                  type="email"
                  value={user?.email ?? ''}
                  disabled
                  className="w-full px-3 py-2.5 border border-[#EBE9F1] rounded-lg text-sm text-[#B9B9C3] bg-[#F8F8F8] cursor-not-allowed"
                />
                <p className="text-xs text-[#B9B9C3] mt-1">L'email ne peut pas être modifié.</p>
              </div>
              <div className="flex justify-end pt-1">
                <button
                  type="submit"
                  disabled={savingProfile}
                  className="px-5 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white text-sm font-semibold rounded-lg disabled:opacity-50 transition-colors"
                >
                  {savingProfile ? 'Sauvegarde...' : 'Sauvegarder'}
                </button>
              </div>
            </form>
          </div>

        </div>

        {/* Sécurité — pleine largeur */}
        <div className="vx-card">
          <h2 className="text-base font-semibold text-[#5E5873] mb-1 flex items-center gap-2">
            <Lock size={16} className="text-[#7367F0]" />
            Sécurité
          </h2>
          <p className="text-sm text-[#B9B9C3] mb-5">Changez votre mot de passe de connexion.</p>
          <form onSubmit={handleChangePassword}>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
              {/* Mot de passe actuel */}
              <div>
                <label className="block text-xs font-semibold text-[#5E5873] mb-1.5 uppercase tracking-wide">Mot de passe actuel</label>
                <div className="relative">
                  <input
                    type={showCurrent ? 'text' : 'password'}
                    value={currentPassword}
                    onChange={e => setCurrentPassword(e.target.value)}
                    className="w-full px-3 py-2.5 pr-10 border border-[#D8D6DE] rounded-lg text-sm text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
                    placeholder="••••••••"
                    required
                  />
                  <button type="button" onClick={() => setShowCurrent(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-[#B9B9C3] hover:text-[#6E6B7B]">
                    {showCurrent ? <EyeOff size={15} /> : <Eye size={15} />}
                  </button>
                </div>
              </div>

              {/* Nouveau mot de passe */}
              <div>
                <label className="block text-xs font-semibold text-[#5E5873] mb-1.5 uppercase tracking-wide">Nouveau mot de passe</label>
                <div className="relative">
                  <input
                    type={showNew ? 'text' : 'password'}
                    value={newPassword}
                    onChange={e => setNewPassword(e.target.value)}
                    className="w-full px-3 py-2.5 pr-10 border border-[#D8D6DE] rounded-lg text-sm text-[#5E5873] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
                    placeholder="••••••••"
                    required
                    minLength={8}
                  />
                  <button type="button" onClick={() => setShowNew(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-[#B9B9C3] hover:text-[#6E6B7B]">
                    {showNew ? <EyeOff size={15} /> : <Eye size={15} />}
                  </button>
                </div>
                {passwordStrength && (
                  <div className="mt-1.5 space-y-0.5">
                    <div className="h-1 w-full bg-[#EBE9F1] rounded-full overflow-hidden">
                      <div className={`h-full rounded-full transition-all ${passwordStrength.color} ${passwordStrength.width}`} />
                    </div>
                    <p className="text-[11px] text-[#B9B9C3]">Force : <span className="font-medium text-[#6E6B7B]">{passwordStrength.label}</span></p>
                  </div>
                )}
              </div>

              {/* Confirmation */}
              <div>
                <label className="block text-xs font-semibold text-[#5E5873] mb-1.5 uppercase tracking-wide">Confirmer</label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={e => setConfirmPassword(e.target.value)}
                  className={`w-full px-3 py-2.5 border rounded-lg text-sm text-[#5E5873] focus:ring-2 transition-all ${
                    confirmPassword && confirmPassword !== newPassword
                      ? 'border-[#EA5455] focus:ring-[#EA5455]'
                      : 'border-[#D8D6DE] focus:ring-[#7367F0] focus:border-[#7367F0]'
                  }`}
                  placeholder="••••••••"
                  required
                />
                {confirmPassword && confirmPassword !== newPassword && (
                  <p className="text-[11px] text-[#EA5455] mt-1">Ne correspond pas.</p>
                )}
              </div>
            </div>

            <div className="flex justify-end mt-5">
              <button
                type="submit"
                disabled={savingPassword || (!!confirmPassword && confirmPassword !== newPassword)}
                className="px-5 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white text-sm font-semibold rounded-lg disabled:opacity-50 transition-colors"
              >
                {savingPassword ? 'Mise à jour...' : 'Changer le mot de passe'}
              </button>
            </div>
          </form>
        </div>

      </div>
    </>
  );
}
