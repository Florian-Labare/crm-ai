import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { LogIn, Mail, Lock } from 'lucide-react';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
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
