import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { usePage } from '../contexts/PageContext';
import api from '../api/apiClient';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { Building2, UserCheck, LogIn } from 'lucide-react';

interface InvitationData {
  token: string;
  email: string;
  role: string;
  team: { id: number; name: string };
  invited_by: string;
  expires_at: string;
}

const ROLE_LABELS: Record<string, string> = {
  admin: 'Administrateur',
  member: 'Membre',
  viewer: 'Observateur',
};

export default function InvitationAcceptPage() {
  const { token } = useParams<{ token: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { setPage } = usePage();

  const [invitation, setInvitation] = useState<InvitationData | null>(null);
  const [loading, setLoading] = useState(true);
  const [accepting, setAccepting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setPage('Invitation', [], [{ label: 'Invitation' }]);
  }, [setPage]);

  useEffect(() => {
    if (!token) return;
    api.get(`/invitations/${token}`)
      .then((res) => setInvitation(res.data))
      .catch((err) => {
        const msg = err.response?.data?.message ?? 'Invitation invalide ou expirée.';
        setError(msg);
      })
      .finally(() => setLoading(false));
  }, [token]);

  const handleAccept = async () => {
    if (!token) return;
    setAccepting(true);
    try {
      const res = await api.post(`/invitations/${token}/accept`);
      toast.success(res.data.message ?? 'Invitation acceptée !');
      setTimeout(() => navigate('/'), 1200);
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Erreur lors de l\'acceptation.');
    } finally {
      setAccepting(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center">
        <div className="w-10 h-10 border-4 border-[#7367F0] border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center py-12 px-4">
        <div className="max-w-md w-full vx-card text-center">
          <div className="text-5xl mb-4">⚠️</div>
          <h2 className="text-xl font-bold text-[#5E5873] mb-2">Invitation invalide</h2>
          <p className="text-[#6E6B7B]">{error}</p>
          <button
            onClick={() => navigate('/login')}
            className="mt-6 px-6 py-2.5 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold rounded-lg transition-colors"
          >
            Retour à la connexion
          </button>
        </div>
      </div>
    );
  }

  if (!invitation) return null;

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center py-12 px-4">
        <div className="max-w-md w-full animate-fadeIn">
          <div className="vx-card text-center">
            <div className="w-16 h-16 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-3xl font-bold shadow-lg shadow-purple-500/40 mx-auto mb-6">
              🎧
            </div>

            <h1 className="text-2xl font-bold text-[#5E5873] mb-2">Invitation à rejoindre</h1>

            <div className="flex items-center justify-center gap-2 mb-4">
              <Building2 size={20} className="text-[#7367F0]" />
              <span className="text-xl font-bold text-[#7367F0]">{invitation.team.name}</span>
            </div>

            <div className="bg-[#F3F2F7] rounded-lg px-4 py-3 mb-6 text-sm text-[#6E6B7B]">
              <p>Invité par <strong className="text-[#5E5873]">{invitation.invited_by}</strong></p>
              <p className="mt-1">
                Rôle proposé :{' '}
                <span className="inline-block bg-[#7367F0]/10 text-[#7367F0] font-semibold px-2 py-0.5 rounded-full text-xs">
                  {ROLE_LABELS[invitation.role] ?? invitation.role}
                </span>
              </p>
            </div>

            {user ? (
              <button
                onClick={handleAccept}
                disabled={accepting}
                className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:shadow-lg disabled:opacity-50 transition-all"
              >
                {accepting ? (
                  <>
                    <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    Rejoindre...
                  </>
                ) : (
                  <>
                    <UserCheck size={18} />
                    Rejoindre {invitation.team.name}
                  </>
                )}
              </button>
            ) : (
              <div className="space-y-3">
                <p className="text-sm text-[#6E6B7B] mb-2">Connectez-vous pour accepter l'invitation.</p>
                <button
                  onClick={() => navigate(`/login?invitation=${token}`)}
                  className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:shadow-lg transition-all"
                >
                  <LogIn size={18} />
                  Se connecter
                </button>
                <button
                  onClick={() => navigate('/register')}
                  className="w-full flex items-center justify-center gap-2 border border-[#7367F0] text-[#7367F0] font-semibold py-3 px-4 rounded-lg hover:bg-[#7367F0]/5 transition-all"
                >
                  Créer un compte
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
