import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { Calendar, User, Mail, MapPin, Clock } from "lucide-react";

interface MIA {
  id: number;
  name: string;
  email: string;
}

const DerFormPage: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [mias, setMias] = useState<MIA[]>([]);
  const [formData, setFormData] = useState({
    charge_clientele_id: "",
    civilite: "",
    nom: "",
    prenom: "",
    email: "",
    lieu_rdv: "",
    date_rdv: "",
    heure_rdv: "",
  });

  useEffect(() => {
    fetchMias();
  }, []);

  const fetchMias = async () => {
    try {
      const res = await api.get("/der/create");
      setMias(res.data.mias || []);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement des chargés de clientèle");
    }
  };

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await api.post("/der", formData);
      toast.success("DER envoyé avec succès !");
      setTimeout(() => navigate("/clients"), 2000);
    } catch (err: any) {
      console.error(err);
      const errorMessage =
        err.response?.data?.message || "Erreur lors de l'envoi du DER";
      toast.error(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-12 px-4">
        <div className="max-w-2xl mx-auto">
          <div className="vx-card mb-6">
            <div className="flex items-center space-x-4 mb-6">
              <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white shadow-md shadow-purple-500/30">
                <Calendar size={24} />
              </div>
              <div>
                <h1 className="text-3xl font-bold text-[#5E5873]">
                  DER à envoyer
                </h1>
                <p className="text-[#6E6B7B] mt-1">
                  Document d'Entrée en Relation
                </p>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Section Responsable */}
              <div className="border-l-4 border-[#7367F0] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <User className="w-4 h-4 mr-2 text-[#7367F0]" />
                  Responsable
                </h4>
                <div>
                  <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                    Chargé de clientèle <span className="text-red-500">*</span>
                  </label>
                  <select
                    name="charge_clientele_id"
                    value={formData.charge_clientele_id}
                    onChange={handleChange}
                    required
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-all bg-white"
                  >
                    <option value="">Sélectionner un chargé de clientèle</option>
                    {mias.map((mia) => (
                      <option key={mia.id} value={mia.id}>
                        {mia.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Section Identité Client */}
              <div className="border-l-4 border-[#00CFE8] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#00CFE8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  Identité du client
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Civilité <span className="text-red-500">*</span>
                    </label>
                    <select
                      name="civilite"
                      value={formData.civilite}
                      onChange={handleChange}
                      required
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-all bg-white"
                    >
                      <option value="">Sélectionner</option>
                      <option value="Monsieur">Monsieur</option>
                      <option value="Madame">Madame</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Nom <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      name="nom"
                      value={formData.nom}
                      onChange={handleChange}
                      required
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-all"
                      placeholder="Dupont"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Prénom <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      name="prenom"
                      value={formData.prenom}
                      onChange={handleChange}
                      required
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-all"
                      placeholder="Jean"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Adresse mail <span className="text-red-500">*</span>
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Mail size={16} className="text-[#B9B9C3]" />
                      </div>
                      <input
                        type="email"
                        name="email"
                        value={formData.email}
                        onChange={handleChange}
                        required
                        className="w-full pl-10 pr-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-all"
                        placeholder="exemple@email.com"
                      />
                    </div>
                  </div>
                </div>
              </div>

              {/* Section Rendez-vous */}
              <div className="border-l-4 border-[#28C76F] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <Calendar className="w-4 h-4 mr-2 text-[#28C76F]" />
                  Rendez-vous
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="md:col-span-2">
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Lieu du rdv <span className="text-red-500">*</span>
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <MapPin size={16} className="text-[#B9B9C3]" />
                      </div>
                      <input
                        type="text"
                        name="lieu_rdv"
                        value={formData.lieu_rdv}
                        onChange={handleChange}
                        required
                        className="w-full pl-10 pr-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-all"
                        placeholder="10 rue de la République, Paris"
                      />
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Date du rdv <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="date"
                      name="date_rdv"
                      value={formData.date_rdv}
                      onChange={handleChange}
                      required
                      min={new Date().toISOString().split("T")[0]}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-all"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Heure du rdv <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="time"
                      name="heure_rdv"
                      value={formData.heure_rdv}
                      onChange={handleChange}
                      required
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-all"
                    />
                  </div>
                </div>
              </div>

              {/* Boutons */}
              <div className="flex space-x-4 pt-6">
                <button
                  type="button"
                  onClick={() => navigate("/clients")}
                  className="flex-1 px-6 py-3 border border-[#D8D6DE] rounded-lg text-[#5E5873] font-semibold hover:bg-[#F3F2F7] transition-all"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="flex-1 bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg font-semibold shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
                >
                  {loading ? (
                    <>
                      <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                      <span>Envoi en cours...</span>
                    </>
                  ) : (
                    <>
                      <svg
                        className="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M14 5l7 7m0 0l-7 7m7-7H3"
                        />
                      </svg>
                      <span>Envoyer</span>
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </>
  );
};

export default DerFormPage;
