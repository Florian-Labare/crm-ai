import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { toast } from 'react-toastify';
import api from '../api/apiClient';

export default function AuthCallbackPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { fetchUser } = useAuth();

  useEffect(() => {
    // Token is passed via URL fragment (#token=...) to prevent server-side logging.
    // URL fragments are never sent to the server (no Referer leakage, no access log exposure).
    const hash = window.location.hash.slice(1); // remove leading '#'
    const hashParams = new URLSearchParams(hash);
    const token = hashParams.get('token');

    // Errors are still passed as query params since they contain no sensitive data
    const error = searchParams.get('error');

    if (error) {
      toast.error('Erreur lors de la connexion SSO. Veuillez réessayer.');
      navigate('/login');
      return;
    }

    if (!token) {
      navigate('/login');
      return;
    }

    localStorage.setItem('token', token);
    fetchUser().then(() => {
      api.get('/user').then((res) => {
        navigate(res.data.has_team ? '/' : '/onboarding');
      }).catch(() => navigate('/'));
    });
  }, []);

  return (
    <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center">
      <div className="text-center">
        <div className="w-12 h-12 border-4 border-[#7367F0] border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p className="text-[#5E5873] font-semibold text-lg">Connexion en cours…</p>
        <p className="text-[#B9B9C3] text-sm mt-2">Veuillez patienter</p>
      </div>
    </div>
  );
}
