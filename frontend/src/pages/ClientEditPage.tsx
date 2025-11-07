import { useState, useEffect } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import AudioRecorder from "../components/AudioRecorder";

export default function ClientEditPage() {
  const { id } = useParams<{ id: string }>();
  const [form, setForm] = useState({
    civilite: "",
    nom: "",
    nom_jeune_fille: "",
    prenom: "",
    datedenaissance: "",
    lieudenaissance: "",
    nationalite: "",
    telephone: "",
    email: "",
    adresse: "",
    code_postal: "",
    ville: "",
    situationmatrimoniale: "",
    profession: "",
    revenusannuels: "",
    nombreenfants: "",
  });
  const [besoins, setBesoins] = useState<string[]>([]);
  const [newBesoin, setNewBesoin] = useState("");
  const [loading, setLoading] = useState(false);
  const [fetching, setFetching] = useState(true);
  const navigate = useNavigate();

  // Afficher le champ nom de jeune fille si Madame et Marié(e)
  const showNomJeuneFille = form.civilite === "Madame" && form.situationmatrimoniale === "Marié(e)";

  useEffect(() => {
    fetchClient();
  }, [id]);

  const fetchClient = async () => {
    try {
      setFetching(true);
      const res = await api.get(`/clients/${id}`);
      const client = res.data;

      // Convertir la date de naissance au format YYYY-MM-DD pour l'input HTML
      let formattedDate = "";
      if (client.datedenaissance) {
        // Si c'est une date ISO (avec T), extraire juste la partie date
        if (client.datedenaissance.includes("T")) {
          formattedDate = client.datedenaissance.split("T")[0];
        } else {
          formattedDate = client.datedenaissance;
        }
      }

      setForm({
        civilite: client.civilite || "",
        nom: client.nom || "",
        nom_jeune_fille: client.nom_jeune_fille || "",
        prenom: client.prenom || "",
        datedenaissance: formattedDate,
        lieudenaissance: client.lieudenaissance || "",
        nationalite: client.nationalite || "",
        telephone: client.telephone || "",
        email: client.email || "",
        adresse: client.adresse || "",
        code_postal: client.code_postal || "",
        ville: client.ville || "",
        situationmatrimoniale: client.situationmatrimoniale || "",
        profession: client.profession || "",
        revenusannuels: client.revenusannuels || "",
        nombreenfants: client.nombreenfants || "",
      });

      setBesoins(client.besoins || []);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement du client");
      navigate("/");
    } finally {
      setFetching(false);
    }
  };

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
      await api.put(`/clients/${id}`, payload);

      toast.success("✅ Client mis à jour avec succès !");

      // Rediriger vers la page de détail du client
      setTimeout(() => {
        navigate(`/clients/${id}`);
      }, 1000);
    } catch (err) {
      console.error(err);
      toast.error("❌ Erreur lors de la mise à jour du client");
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateClient = (updatedClient: any) => {
    // Afficher un toast de succès
    toast.success("✅ Fiche client mise à jour avec succès !");

    // Rediriger vers la page de détail du client
    setTimeout(() => {
      navigate(`/clients/${id}`);
    }, 1500);
  };

  if (fetching) {
    return (
      <div className="flex justify-center items-center h-screen">
        <div className="flex flex-col items-center space-y-4">
          <svg
            className="animate-spin h-12 w-12 text-indigo-600"
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
          <p className="text-gray-600 font-medium">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8 px-4">
        <div className="max-w-5xl mx-auto">
          {/* Header avec bouton retour */}
          <div className="mb-6 flex items-center space-x-4">
            <button
              onClick={() => navigate(`/clients/${id}`)}
              className="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-md hover:shadow-lg transition-shadow text-gray-600 hover:text-indigo-600"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </button>
            <div>
              <h1 className="text-3xl font-bold text-gray-800">Éditer le client</h1>
              <p className="text-gray-600 mt-1">
                Mettez à jour les informations par vocal ou manuellement
              </p>
            </div>
          </div>

          {/* Option 1 : Mise à jour vocale */}
          <div className="bg-white rounded-xl shadow-lg p-6 mb-6 border-l-4 border-purple-500">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-indigo-500 rounded-lg flex items-center justify-center">
                  <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                  </svg>
                </div>
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-gray-800 mb-1">
                  Mise à jour vocale
                </h3>
                <p className="text-sm text-gray-600 mb-4">
                  Enregistrez une conversation pour mettre à jour automatiquement la fiche client
                </p>
                <AudioRecorder clientId={parseInt(id!)} onUpdateClient={handleUpdateClient} />
              </div>
            </div>
          </div>

          {/* Séparateur OR */}
          <div className="relative mb-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t-2 border-gray-200"></div>
            </div>
            <div className="relative flex justify-center">
              <span className="bg-gradient-to-br from-gray-50 to-gray-100 px-4 py-1 text-sm font-semibold text-gray-500 rounded-full border-2 border-gray-200">
                OU
              </span>
            </div>
          </div>

          {/* Option 2 : Formulaire manuel */}
          <div className="bg-white rounded-xl shadow-lg overflow-hidden border-l-4 border-indigo-500">
            <div className="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-4 border-b border-gray-200">
              <div className="flex items-center space-x-3">
                <div className="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center">
                  <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-gray-800">
                    Modification manuelle
                  </h3>
                  <p className="text-xs text-gray-600">
                    Modifiez les champs ci-dessous
                  </p>
                </div>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-6">
              {/* Section Identité */}
              <div className="border-l-4 border-indigo-400 pl-4">
                <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  Identité
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Civilité <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={form.civilite}
                      onChange={(e) => setForm({ ...form, civilite: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors bg-white"
                      required
                    >
                      <option value="">Sélectionner...</option>
                      <option value="Monsieur">Monsieur</option>
                      <option value="Madame">Madame</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Nom <span className="text-red-500">*</span>
                    </label>
                    <input
                      placeholder="Dupont"
                      value={form.nom}
                      onChange={(e) => setForm({ ...form, nom: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                      required
                    />
                  </div>
                  {showNomJeuneFille && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nom de jeune fille
                      </label>
                      <input
                        placeholder="Nom avant mariage"
                        value={form.nom_jeune_fille}
                        onChange={(e) => setForm({ ...form, nom_jeune_fille: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                      />
                    </div>
                  )}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Prénom <span className="text-red-500">*</span>
                    </label>
                    <input
                      placeholder="Jean"
                      value={form.prenom}
                      onChange={(e) => setForm({ ...form, prenom: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                      required
                    />
                  </div>
                </div>
              </div>

              {/* Section Coordonnées */}
              <div className="border-l-4 border-cyan-400 pl-4">
                <h4 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                  Coordonnées
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                    <input
                      type="tel"
                      placeholder="06 12 34 56 78"
                      value={form.telephone}
                      onChange={(e) => setForm({ ...form, telephone: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                      type="email"
                      placeholder="exemple@email.com"
                      value={form.email}
                      onChange={(e) => setForm({ ...form, email: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input
                      placeholder="10 rue de la République"
                      value={form.adresse}
                      onChange={(e) => setForm({ ...form, adresse: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Code postal</label>
                    <input
                      placeholder="75001"
                      value={form.code_postal}
                      onChange={(e) => setForm({ ...form, code_postal: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                    <input
                      placeholder="Paris"
                      value={form.ville}
                      onChange={(e) => setForm({ ...form, ville: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nationalité</label>
                    <input
                      placeholder="Française"
                      value={form.nationalite}
                      onChange={(e) => setForm({ ...form, nationalite: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Section Informations personnelles */}
              <div className="border-l-4 border-blue-400 pl-4">
                <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Informations personnelles
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
                    <input
                      type="date"
                      value={form.datedenaissance}
                      onChange={(e) => setForm({ ...form, datedenaissance: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Lieu de naissance</label>
                    <input
                      placeholder="Paris, France"
                      value={form.lieudenaissance}
                      onChange={(e) => setForm({ ...form, lieudenaissance: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Situation matrimoniale</label>
                    <select
                      value={form.situationmatrimoniale}
                      onChange={(e) => setForm({ ...form, situationmatrimoniale: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors bg-white"
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
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nombre d'enfants</label>
                    <input
                      type="number"
                      min="0"
                      placeholder="0"
                      value={form.nombreenfants}
                      onChange={(e) => setForm({ ...form, nombreenfants: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Section Professionnel */}
              <div className="border-l-4 border-green-400 pl-4">
                <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                  Informations professionnelles
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Profession</label>
                    <input
                      placeholder="Ingénieur, Médecin..."
                      value={form.profession}
                      onChange={(e) => setForm({ ...form, profession: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Revenus annuels (€)</label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="45000"
                      value={form.revenusannuels}
                      onChange={(e) => setForm({ ...form, revenusannuels: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                    />
                  </div>
                </div>
              </div>

              {/* Section Besoins */}
              <div className="border-l-4 border-purple-400 pl-4">
                <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-purple-100 text-purple-800 border border-purple-200 transition-all hover:bg-purple-200"
                      >
                        {besoin}
                        <button
                          type="button"
                          onClick={() => handleRemoveBesoin(index)}
                          className="ml-2 text-purple-600 hover:text-purple-900 focus:outline-none"
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
                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
              />
              <button
                type="button"
                onClick={handleAddBesoin}
                className="bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white px-4 py-2 rounded-lg transition-all font-medium flex items-center space-x-1"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                <span>Ajouter</span>
              </button>
            </div>
            <p className="text-xs text-gray-500 mt-2">
              Ajoutez les besoins un par un et appuyez sur "Ajouter" ou "Entrée"
            </p>
          </div>

          {/* Boutons d'action */}
          <div className="flex gap-3 pt-4 border-t border-gray-200">
            <button
              type="submit"
              disabled={loading}
              className="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl flex items-center justify-center space-x-2"
            >
              {loading ? (
                <>
                  <svg className="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                  </svg>
                  <span>Mise à jour en cours...</span>
                </>
              ) : (
                <>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  <span>Enregistrer les modifications</span>
                </>
              )}
            </button>
            <button
              type="button"
              onClick={() => navigate(`/clients/${id}`)}
              className="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold transition-all"
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
