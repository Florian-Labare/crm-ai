import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";

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
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 to-purple-50 py-12 px-4">
        <div className="max-w-2xl mx-auto">
          <div className="bg-white rounded-xl shadow-xl p-8">
            <h1 className="text-3xl font-bold text-gray-800 mb-2">
              DER à envoyer
            </h1>
            <p className="text-gray-600 mb-8">
              Document d'Entrée en Relation
            </p>

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Chargé de clientèle */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Chargé de clientèle
                </label>
                <select
                  name="charge_clientele_id"
                  value={formData.charge_clientele_id}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                >
                  <option value="">Sélectionner un chargé de clientèle</option>
                  {mias.map((mia) => (
                    <option key={mia.id} value={mia.id}>
                      {mia.name}
                    </option>
                  ))}
                </select>
              </div>

              {/* Civilité */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Monsieur/Madame <span className="text-red-500">*</span>
                </label>
                <select
                  name="civilite"
                  value={formData.civilite}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                >
                  <option value="">Sélectionner</option>
                  <option value="Monsieur">Monsieur</option>
                  <option value="Madame">Madame</option>
                </select>
              </div>

              {/* Nom */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Nom <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="nom"
                  value={formData.nom}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
              </div>

              {/* Prénom */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Prénom <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="prenom"
                  value={formData.prenom}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
              </div>

              {/* Email */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Adresse mail <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
              </div>

              {/* Lieu du rdv */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Lieu du rdv <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="lieu_rdv"
                  value={formData.lieu_rdv}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
              </div>

              {/* Date du rdv */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Date du rdv <span className="text-red-500">*</span>
                </label>
                <input
                  type="date"
                  name="date_rdv"
                  value={formData.date_rdv}
                  onChange={handleChange}
                  required
                  min={new Date().toISOString().split("T")[0]}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
              </div>

              {/* Heure du rdv */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Heure du rdv <span className="text-red-500">*</span>
                </label>
                <input
                  type="time"
                  name="heure_rdv"
                  value={formData.heure_rdv}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                />
              </div>

              {/* Boutons */}
              <div className="flex space-x-4 pt-6">
                <button
                  type="button"
                  onClick={() => navigate("/clients")}
                  className="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-all"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium shadow-lg transform hover:scale-105 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center space-x-2"
                >
                  {loading ? (
                    <>
                      <svg
                        className="animate-spin h-5 w-5 text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                      >
                        <circle
                          className="opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          strokeWidth="4"
                        ></circle>
                        <path
                          className="opacity-75"
                          fill="currentColor"
                          d="M4 12a8 8 0 018-8v8H4z"
                        ></path>
                      </svg>
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
