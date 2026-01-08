import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import AudioRecorder from "./AudioRecorder";
import type { AudioResponse } from "../types/api";

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
    profession: "",
    revenus_annuels: "",
    nombre_enfants: "",
    chef_entreprise: false,
    statut: "",
    travailleur_independant: false,
    mandataire_social: false,
  });
  const [besoins, setBesoins] = useState<string[]>([]);
  const [newBesoin, setNewBesoin] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  // Afficher le champ nom de jeune fille si Madame et Marié(e)
  const showNomJeuneFille = form.civilite === "Madame" && form.situation_matrimoniale === "Marié(e)";

  const handleAddBesoin = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmedBesoin = newBesoin.trim();
    if (trimmedBesoin && !besoins.includes(trimmedBesoin)) {
      setBesoins([...besoins, trimmedBesoin]);
      setNewBesoin("");
    }
  };

  const handleRemoveBesoin = (index: number) => {
    setBesoins(besoins.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      setLoading(true);
      const payload = {
        ...form,
        besoins: besoins.length > 0 ? besoins : null,
      };
      const response = await api.post("/clients", payload);

      toast.success("✅ Client créé avec succès !");

      // Rediriger vers la page de détail du client créé
      setTimeout(() => {
        navigate(`/clients/${response.data.id}`);
      }, 1000);
    } catch (err) {
      console.error(err);
      toast.error("❌ Erreur lors de la création du client");
    } finally {
      setLoading(false);
    }
  };

  const handleVoiceCreation = (data: AudioResponse) => {
    toast.success("✅ Client créé avec succès via audio !");

    // Rediriger vers la page de détail du client créé
    if (data.client?.id) {
      setTimeout(() => {
        navigate(`/clients/${data.client.id}`);
      }, 1500);
    }
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-5xl mx-auto">
          {/* Header */}
          <div className="mb-6">
            <h1 className="text-3xl font-bold text-[#5E5873] mb-2">
              Créer un nouveau client
            </h1>
            <p className="text-[#6E6B7B]">
              Utilisez l'enregistrement vocal ou saisissez les informations manuellement
            </p>
          </div>

          {/* Option 1 : Création vocale */}
          <div className="vx-card mb-6 border-l-4 border-[#7367F0]">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <div className="w-12 h-12 bg-gradient-to-br from-[#7367F0] to-[#9055FD] rounded-lg flex items-center justify-center shadow-md shadow-purple-500/30">
                  <svg
                    className="w-6 h-6 text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                    />
                  </svg>
                </div>
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-[#5E5873] mb-1">
                  Création vocale
                </h3>
                <p className="text-sm text-[#6E6B7B] mb-4">
                  Enregistrez une conversation pour créer automatiquement la fiche client
                </p>
                <AudioRecorder onUploadSuccess={handleVoiceCreation} />
              </div>
            </div>
          </div>

          {/* Séparateur OR */}
          <div className="relative mb-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t-2 border-[#EBE9F1]"></div>
            </div>
            <div className="relative flex justify-center">
              <span className="bg-[#F8F8F8] px-4 py-1 text-sm font-semibold text-[#B9B9C3] rounded-full border-2 border-[#EBE9F1]">
                OU
              </span>
            </div>
          </div>

          {/* Option 2 : Formulaire manuel */}
          <div className="vx-card overflow-hidden border-l-4 border-[#7367F0]">
            <div className="bg-[#F3F2F7] px-6 py-4 border-b border-[#EBE9F1]">
              <div className="flex items-center space-x-3">
                <div className="w-10 h-10 bg-gradient-to-br from-[#7367F0] to-[#9055FD] rounded-lg flex items-center justify-center shadow-md shadow-purple-500/30">
                  <svg
                    className="w-5 h-5 text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-[#5E5873]">
                    Saisie manuelle
                  </h3>
                  <p className="text-xs text-[#6E6B7B]">
                    Remplissez les champs ci-dessous
                  </p>
                </div>
              </div>
            </div>

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

              {/* Section Professionnel */}
              <div className="border-l-4 border-[#FF9F43] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#FF9F43]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                  Informations professionnelles
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Profession</label>
                    <input
                      placeholder="Ingénieur, Médecin..."
                      value={form.profession}
                      onChange={(e) => setForm({ ...form, profession: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Revenus annuels (€)</label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="45000"
                      value={form.revenus_annuels}
                      onChange={(e) => setForm({ ...form, revenus_annuels: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Section Entreprise */}
              <div className="border-l-4 border-[#EA5455] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#EA5455]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7l9-4 9 4-9 4-9-4zm0 6l9 4 9-4m-9 4v6" />
                  </svg>
                  Informations entreprise
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label className="flex items-center justify-between rounded-lg border border-[#EA5455]/30 bg-[#EA5455]/10 px-3 py-2 text-sm font-semibold text-[#5E5873] cursor-pointer">
                    <span>Chef d'entreprise</span>
                    <input
                      type="checkbox"
                      checked={form.chef_entreprise}
                      onChange={(e) => setForm({ ...form, chef_entreprise: e.target.checked })}
                      className="h-5 w-5 rounded text-[#EA5455] focus:ring-[#EA5455]"
                    />
                  </label>
                  <label className="flex items-center justify-between rounded-lg border border-[#EA5455]/30 bg-[#EA5455]/10 px-3 py-2 text-sm font-semibold text-[#5E5873] cursor-pointer">
                    <span>Travailleur indépendant</span>
                    <input
                      type="checkbox"
                      checked={form.travailleur_independant}
                      onChange={(e) => setForm({ ...form, travailleur_independant: e.target.checked })}
                      className="h-5 w-5 rounded text-[#EA5455] focus:ring-[#EA5455]"
                    />
                  </label>
                  <label className="flex items-center justify-between rounded-lg border border-[#EA5455]/30 bg-[#EA5455]/10 px-3 py-2 text-sm font-semibold text-[#5E5873] cursor-pointer">
                    <span>Mandataire social</span>
                    <input
                      type="checkbox"
                      checked={form.mandataire_social}
                      onChange={(e) => setForm({ ...form, mandataire_social: e.target.checked })}
                      className="h-5 w-5 rounded text-[#EA5455] focus:ring-[#EA5455]"
                    />
                  </label>
                  <div>
                    <label className="block text-sm font-semibold text-[#5E5873] mb-1">Statut (SARL, SAS...)</label>
                    <input
                      placeholder="Ex : SAS"
                      value={form.statut}
                      onChange={(e) => setForm({ ...form, statut: e.target.value })}
                      className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Section Besoins */}
              <div className="border-l-4 border-[#9055FD] pl-4">
                <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-[#9055FD]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                  </svg>
                  Besoins
                </h4>

                {/* Affichage des besoins existants sous forme de badges */}
                {besoins.length > 0 && (
                  <div className="flex flex-wrap gap-2 mb-3">
                    {besoins.map((besoin, index) => (
                      <span
                        key={index}
                        className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-[#9055FD]/10 text-[#9055FD] border border-[#9055FD]/30 transition-all hover:bg-[#9055FD]/20"
                      >
                        {besoin}
                        <button
                          type="button"
                          onClick={() => handleRemoveBesoin(index)}
                          className="ml-2 text-[#9055FD] hover:text-[#7367F0] focus:outline-none"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </span>
                    ))}
                  </div>
                )}

                {/* Formulaire d'ajout d'un nouveau besoin */}
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="Ex: mutuelle, prévoyance, assurance habitation..."
                    value={newBesoin}
                    onChange={(e) => setNewBesoin(e.target.value)}
                    onKeyPress={(e) => {
                      if (e.key === "Enter") {
                        handleAddBesoin(e);
                      }
                    }}
                    className="flex-1 px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0] text-[#5E5873] placeholder-[#B9B9C3] transition-colors"
                  />
                  <button
                    type="button"
                    onClick={handleAddBesoin}
                    className="bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-4 py-2 rounded-lg transition-all font-semibold flex items-center space-x-1 shadow-md hover:shadow-lg"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Ajouter</span>
                  </button>
                </div>
                <p className="text-xs text-[#B9B9C3] mt-2">
                  Ajoutez les besoins un par un et appuyez sur "Ajouter" ou "Entrée"
                </p>
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
                      <span>Enregistrer</span>
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
