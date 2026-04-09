import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";

export default function ClientForm() {
  const [form, setForm] = useState({
    civilite: "",
    nom: "",
    nom_jeune_fille: "",
    prenom: "",
    date_naissance: "",
    lieu_naissance: "",
    nationalite: "",
    telephone: "",
    email: "",
    adresse: "",
    code_postal: "",
    ville: "",
    situation_matrimoniale: "",
    nombre_enfants: "",
  });
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  // Afficher le champ nom de jeune fille si Madame et Marié(e)
  const showNomJeuneFille = form.civilite === "Madame" && form.situation_matrimoniale === "Marié(e)";

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      setLoading(true);
      const response = await api.post("/clients", form);

      toast.success("Client créé avec succès !");

      // Rediriger vers la page de détail du client créé
      setTimeout(() => {
        navigate(`/clients/${response.data.id}`);
      }, 1000);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la création du client");
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-3xl mx-auto">
          {/* Header */}
          <div className="mb-6">
            <h1 className="text-3xl font-bold text-[#5E5873] mb-2">
              Créer un nouveau client
            </h1>
            <p className="text-[#6E6B7B]">
              Renseignez les informations du client
            </p>
          </div>

          {/* Formulaire */}
          <div className="vx-card overflow-hidden border-l-4 border-[#7367F0]">
            <form onSubmit={handleSubmit} className="p-6 space-y-6">
              {/* Section Identité */}
              <div className="border-l-4 border-[#7367F0] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#7367F0]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  Identité
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Civilité <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={form.civilite}
                      onChange={(e) => setForm({ ...form, civilite: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-colors bg-white"
                      required
                    >
                      <option value="">Sélectionner...</option>
                      <option value="Monsieur">Monsieur</option>
                      <option value="Madame">Madame</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Nom <span className="text-red-500">*</span>
                    </label>
                    <input
                      placeholder="Dupont"
                      value={form.nom}
                      onChange={(e) => setForm({ ...form, nom: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                      Prénom <span className="text-red-500">*</span>
                    </label>
                    <input
                      placeholder="Jean"
                      value={form.prenom}
                      onChange={(e) => setForm({ ...form, prenom: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                      required
                    />
                  </div>
                  {showNomJeuneFille && (
                    <div>
                      <label className="block text-sm font-semibold text-[#5E5873] mb-1">
                        Nom de jeune fille
                      </label>
                      <input
                        placeholder="Nom avant mariage"
                        value={form.nom_jeune_fille}
                        onChange={(e) => setForm({ ...form, nom_jeune_fille: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                      />
                    </div>
                  )}
                </div>
              </div>

              {/* Section Coordonnées */}
              <div className="border-l-4 border-[#00CFE8] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#00CFE8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                  Coordonnées
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Téléphone</label>
                    <input
                      type="tel"
                      placeholder="06 12 34 56 78"
                      value={form.telephone}
                      onChange={(e) => setForm({ ...form, telephone: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Email</label>
                    <input
                      type="email"
                      placeholder="exemple@email.com"
                      value={form.email}
                      onChange={(e) => setForm({ ...form, email: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Adresse</label>
                    <input
                      placeholder="10 rue de la République"
                      value={form.adresse}
                      onChange={(e) => setForm({ ...form, adresse: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Code postal</label>
                    <input
                      placeholder="75001"
                      value={form.code_postal}
                      onChange={(e) => setForm({ ...form, code_postal: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Ville</label>
                    <input
                      placeholder="Paris"
                      value={form.ville}
                      onChange={(e) => setForm({ ...form, ville: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Nationalité</label>
                    <input
                      placeholder="Française"
                      value={form.nationalite}
                      onChange={(e) => setForm({ ...form, nationalite: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Section Informations personnelles */}
              <div className="border-l-4 border-[#28C76F] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#28C76F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Informations personnelles
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Date de naissance</label>
                    <input
                      type="date"
                      value={form.date_naissance}
                      onChange={(e) => setForm({ ...form, date_naissance: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Lieu de naissance</label>
                    <input
                      placeholder="Paris, France"
                      value={form.lieu_naissance}
                      onChange={(e) => setForm({ ...form, lieu_naissance: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Situation matrimoniale</label>
                    <select
                      value={form.situation_matrimoniale}
                      onChange={(e) => setForm({ ...form, situation_matrimoniale: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] transition-colors bg-white"
                    >
                      <option value="">Sélectionner...</option>
                      <option value="Célibataire">Célibataire</option>
                      <option value="Marié(e)">Marié(e)</option>
                      <option value="Divorcé(e)">Divorcé(e)</option>
                      <option value="Veuf(ve)">Veuf(ve)</option>
                      <option value="Pacsé(e)">Pacsé(e)</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Nombre d'enfants</label>
                    <input
                      type="number"
                      min="0"
                      placeholder="0"
                      value={form.nombre_enfants}
                      onChange={(e) => setForm({ ...form, nombre_enfants: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Boutons d'action */}
              <div className="flex gap-3 pt-4 border-t border-[#EBE9F1]">
                <button
                  type="submit"
                  disabled={loading}
                  className="flex-1 bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg flex items-center justify-center space-x-2"
                >
                  {loading ? (
                    <>
                      <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                      <span>Création en cours...</span>
                    </>
                  ) : (
                    <>
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                      </svg>
                      <span>Créer le client</span>
                    </>
                  )}
                </button>
                <button
                  type="button"
                  onClick={() => navigate("/")}
                  className="px-6 py-3 bg-[#F3F2F7] hover:bg-[#EBE9F1] text-[#5E5873] rounded-lg font-semibold transition-all"
                >
                  Annuler
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </>
  );
}
