import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api/apiClient';
import { Building2 } from 'lucide-react';

export default function OnboardingPage() {
  const { fetchUser } = useAuth();
  const navigate = useNavigate();
  const [cabinetName, setCabinetName] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!cabinetName.trim()) return;

    setLoading(true);
    setError('');
    try {
      await api.post('/teams', { name: cabinetName.trim() });
      await fetchUser();
      navigate('/');
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Erreur lors de la création du cabinet.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#F8F8F8] flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">
        <div className="text-center mb-8">
          <div className="w-16 h-16 bg-[#EEE9FD] rounded-2xl flex items-center justify-center mx-auto mb-4">
            <Building2 size={32} className="text-[#7367F0]" />
          </div>
          <h1 className="text-2xl font-bold text-[#5E5873]">Créez votre cabinet</h1>
          <p className="text-[#6E6B7B] mt-2 text-sm">
            Donnez un nom à votre cabinet pour commencer à gérer votre portefeuille clients.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-sm font-semibold text-[#5E5873] mb-2">
              Nom du cabinet
            </label>
            <input
              type="text"
              value={cabinetName}
              onChange={(e) => setCabinetName(e.target.value)}
              placeholder="Ex : Cabinet Dupont Conseil"
              autoFocus
              className="w-full px-4 py-3 border border-[#D8D6DE] rounded-xl text-[#5E5873] placeholder-[#B9B9C3] focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] transition-all"
            />
          </div>

          {error && (
            <p className="text-sm text-[#EA5455] bg-[#FFF5F5] border border-[#FFBDBD] rounded-lg px-4 py-2">
              {error}
            </p>
          )}

          <button
            type="submit"
            disabled={loading || !cabinetName.trim()}
            className="w-full py-3 bg-[#7367F0] hover:bg-[#5E50EE] text-white font-semibold rounded-xl disabled:opacity-50 transition-colors"
          >
            {loading ? 'Création en cours…' : 'Créer mon cabinet'}
          </button>
        </form>
      </div>
    </div>
  );
}
