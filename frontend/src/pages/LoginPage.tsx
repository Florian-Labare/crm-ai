import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { LogIn, Mail, Lock } from 'lucide-react';

function GoogleIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
      <g fill="none" fillRule="evenodd">
        <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
        <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
        <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
        <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
      </g>
    </svg>
  );
}

function MicrosoftIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
      <rect x="0" y="0" width="8.5" height="8.5" fill="#F25022"/>
      <rect x="9.5" y="0" width="8.5" height="8.5" fill="#7FBA00"/>
      <rect x="0" y="9.5" width="8.5" height="8.5" fill="#00A4EF"/>
      <rect x="9.5" y="9.5" width="8.5" height="8.5" fill="#FFB900"/>
    </svg>
  );
}

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const { login, loginWithProvider } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await login(email, password);
      toast.success('Connexion réussie !');
      setTimeout(() => navigate('/'), 1000);
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur de connexion');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center py-12 px-4">
        <div className="max-w-md w-full animate-fadeIn">
          <div className="vx-card">
            {/* Header */}
            <div className="text-center mb-8">
              <div className="w-16 h-16 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-3xl font-bold shadow-lg shadow-purple-500/40 mx-auto mb-4">
                🎧
              </div>
              <h1 className="text-3xl font-bold text-[#5E5873] mb-2">
                Whisper CRM
              </h1>
              <h2 className="text-xl font-semibold text-[#5E5873]">Connexion</h2>
              <p className="text-[#6E6B7B] mt-2">Accédez à votre espace</p>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="space-y-5">
              <div>
                <label className="block text-sm font-semibold text-[#5E5873] mb-2">
                  Email
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Mail size={18} className="text-[#B9B9C3]" />
                  </div>
                  <input
                    type="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full pl-10 pr-4 py-3 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-all"
                    placeholder="votre@email.com"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-[#5E5873] mb-2">
                  Mot de passe
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Lock size={18} className="text-[#B9B9C3]" />
                  </div>
                  <input
                    type="password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="w-full pl-10 pr-4 py-3 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-all"
                    placeholder="••••••••"
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:shadow-lg disabled:opacity-50 transition-all flex items-center justify-center gap-2"
              >
                {loading ? (
                  <>
                    <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    Connexion...
                  </>
                ) : (
                  <>
                    <LogIn size={18} />
                    Se connecter
                  </>
                )}
              </button>
            </form>

            {/* SSO */}
            <div className="mt-6">
              <div className="relative flex items-center">
                <div className="flex-grow border-t border-[#EBE9F1]"></div>
                <span className="mx-4 text-sm text-[#B9B9C3] font-medium">ou</span>
                <div className="flex-grow border-t border-[#EBE9F1]"></div>
              </div>

              <div className="mt-4 space-y-3">
                <button
                  type="button"
                  onClick={() => loginWithProvider('google')}
                  className="w-full flex items-center justify-center gap-3 px-4 py-3 border border-[#D8D6DE] rounded-lg bg-white hover:bg-[#F8F8F8] text-[#5E5873] font-semibold text-sm transition-all shadow-sm hover:shadow"
                >
                  <GoogleIcon />
                  Continuer avec Google
                </button>
                <button
                  type="button"
                  onClick={() => loginWithProvider('azure')}
                  className="w-full flex items-center justify-center gap-3 px-4 py-3 border border-[#D8D6DE] rounded-lg bg-white hover:bg-[#F8F8F8] text-[#5E5873] font-semibold text-sm transition-all shadow-sm hover:shadow"
                >
                  <MicrosoftIcon />
                  Continuer avec Microsoft
                </button>
              </div>
            </div>

            {/* Footer */}
            <div className="mt-6 text-center">
              <p className="text-sm text-[#6E6B7B]">
                Pas encore de compte ?{' '}
                <Link
                  to="/register"
                  className="text-[#7367F0] hover:text-[#5E50EE] font-semibold transition-colors"
                >
                  S'inscrire
                </Link>
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
