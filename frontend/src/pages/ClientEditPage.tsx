import { useState, useEffect } from "react";
import type { ReactNode } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { LongRecorder } from "../components/LongRecorder";
import type {
  ClientRevenu,
  ClientPassif,
  ClientActifFinancier,
  ClientBienImmobilier,
  ClientAutreEpargne,
} from "../types/api";

export default function ClientEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  // État principal du client
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

  // États pour les relations
  const [revenus, setRevenus] = useState<ClientRevenu[]>([]);
  const [passifs, setPassifs] = useState<ClientPassif[]>([]);
  const [actifsFinanciers, setActifsFinanciers] = useState<ClientActifFinancier[]>([]);
  const [biensImmobiliers, setBiensImmobiliers] = useState<ClientBienImmobilier[]>([]);
  const [autresEpargnes, setAutresEpargnes] = useState<ClientAutreEpargne[]>([]);

  // États UI
  const [loading, setLoading] = useState(false);
  const [fetching, setFetching] = useState(true);
  const [expandedSections, setExpandedSections] = useState<string[]>([]);
  const [showModal, setShowModal] = useState<{type: string; data?: any} | null>(null);

  const showNomJeuneFille = form.civilite === "Madame" && form.situation_matrimoniale === "Marié(e)";

  useEffect(() => {
    fetchClient();
  }, [id]);

  const fetchClient = async () => {
    try {
      setFetching(true);
      const res = await api.get(`/clients/${id}`);
      const client = res.data.data || res.data;

      let formattedDate = "";
      if (client.date_naissance) {
        if (client.date_naissance.includes("T")) {
          formattedDate = client.date_naissance.split("T")[0];
        } else {
          formattedDate = client.date_naissance;
        }
      }

      setForm({
        civilite: client.civilite || "",
        nom: client.nom || "",
        nom_jeune_fille: client.nom_jeune_fille || "",
        prenom: client.prenom || "",
        date_naissance: formattedDate,
        lieu_naissance: client.lieu_naissance || "",
        nationalite: client.nationalite || "",
        telephone: client.telephone || "",
        email: client.email || "",
        adresse: client.adresse || "",
        code_postal: client.code_postal || "",
        ville: client.ville || "",
        situation_matrimoniale: client.situation_matrimoniale || "",
        profession: client.profession || "",
        revenus_annuels: client.revenus_annuels || "",
        nombre_enfants: client.nombre_enfants ?? "",
        chef_entreprise: Boolean(client.chef_entreprise),
        statut: client.statut || "",
        travailleur_independant: Boolean(client.travailleur_independant),
        mandataire_social: Boolean(client.mandataire_social),
      });

      setBesoins(client.besoins || []);
      setRevenus(client.revenus || []);
      setPassifs(client.passifs || []);
      setActifsFinanciers(client.actifs_financiers || []);
      setBiensImmobiliers(client.biens_immobiliers || []);
      setAutresEpargnes(client.autres_epargnes || []);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement du client");
      navigate("/");
    } finally {
      setFetching(false);
    }
  };

  const toggleSection = (section: string) => {
    setExpandedSections(prev =>
      prev.includes(section)
        ? prev.filter(s => s !== section)
        : [...prev, section]
    );
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

  // ===== CRUD REVENUS =====
  const handleAddRevenu = async (data: Partial<ClientRevenu>) => {
    try {
      const res = await api.post(`/clients/${id}/revenus`, data);
      setRevenus([...revenus, res.data]);
      setShowModal(null);
      toast.success("✅ Revenu ajouté");
    } catch (err) {
      toast.error("❌ Erreur lors de l'ajout");
    }
  };

  const handleUpdateRevenu = async (revenuId: number, data: Partial<ClientRevenu>) => {
    try {
      const res = await api.put(`/clients/${id}/revenus/${revenuId}`, data);
      setRevenus(revenus.map(r => r.id === revenuId ? res.data : r));
      setShowModal(null);
      toast.success("✅ Revenu modifié");
    } catch (err) {
      toast.error("❌ Erreur lors de la modification");
    }
  };

  const handleDeleteRevenu = async (revenuId: number) => {
    if (!confirm("Supprimer ce revenu ?")) return;
    try {
      await api.delete(`/clients/${id}/revenus/${revenuId}`);
      setRevenus(revenus.filter(r => r.id !== revenuId));
      toast.success("✅ Revenu supprimé");
    } catch (err) {
      toast.error("❌ Erreur lors de la suppression");
    }
  };

  // ===== CRUD PASSIFS =====
  const handleAddPassif = async (data: Partial<ClientPassif>) => {
    try {
      const res = await api.post(`/clients/${id}/passifs`, data);
      setPassifs([...passifs, res.data]);
      setShowModal(null);
      toast.success("✅ Passif ajouté");
    } catch (err) {
      toast.error("❌ Erreur lors de l'ajout");
    }
  };

  const handleUpdatePassif = async (passifId: number, data: Partial<ClientPassif>) => {
    try {
      const res = await api.put(`/clients/${id}/passifs/${passifId}`, data);
      setPassifs(passifs.map(p => p.id === passifId ? res.data : p));
      setShowModal(null);
      toast.success("✅ Passif modifié");
    } catch (err) {
      toast.error("❌ Erreur lors de la modification");
    }
  };

  const handleDeletePassif = async (passifId: number) => {
    if (!confirm("Supprimer ce passif ?")) return;
    try {
      await api.delete(`/clients/${id}/passifs/${passifId}`);
      setPassifs(passifs.filter(p => p.id !== passifId));
      toast.success("✅ Passif supprimé");
    } catch (err) {
      toast.error("❌ Erreur lors de la suppression");
    }
  };

  // ===== CRUD ACTIFS FINANCIERS =====
  const handleAddActifFinancier = async (data: Partial<ClientActifFinancier>) => {
    try {
      const res = await api.post(`/clients/${id}/actifs-financiers`, data);
      setActifsFinanciers([...actifsFinanciers, res.data]);
      setShowModal(null);
      toast.success("✅ Actif financier ajouté");
    } catch (err) {
      toast.error("❌ Erreur lors de l'ajout");
    }
  };

  const handleUpdateActifFinancier = async (actifId: number, data: Partial<ClientActifFinancier>) => {
    try {
      const res = await api.put(`/clients/${id}/actifs-financiers/${actifId}`, data);
      setActifsFinanciers(actifsFinanciers.map(a => a.id === actifId ? res.data : a));
      setShowModal(null);
      toast.success("✅ Actif financier modifié");
    } catch (err) {
      toast.error("❌ Erreur lors de la modification");
    }
  };

  const handleDeleteActifFinancier = async (actifId: number) => {
    if (!confirm("Supprimer cet actif financier ?")) return;
    try {
      await api.delete(`/clients/${id}/actifs-financiers/${actifId}`);
      setActifsFinanciers(actifsFinanciers.filter(a => a.id !== actifId));
      toast.success("✅ Actif financier supprimé");
    } catch (err) {
      toast.error("❌ Erreur lors de la suppression");
    }
  };

  // ===== CRUD BIENS IMMOBILIERS =====
  const handleAddBienImmobilier = async (data: Partial<ClientBienImmobilier>) => {
    try {
      const res = await api.post(`/clients/${id}/biens-immobiliers`, data);
      setBiensImmobiliers([...biensImmobiliers, res.data]);
      setShowModal(null);
      toast.success("✅ Bien immobilier ajouté");
    } catch (err) {
      toast.error("❌ Erreur lors de l'ajout");
    }
  };

  const handleUpdateBienImmobilier = async (bienId: number, data: Partial<ClientBienImmobilier>) => {
    try {
      const res = await api.put(`/clients/${id}/biens-immobiliers/${bienId}`, data);
      setBiensImmobiliers(biensImmobiliers.map(b => b.id === bienId ? res.data : b));
      setShowModal(null);
      toast.success("✅ Bien immobilier modifié");
    } catch (err) {
      toast.error("❌ Erreur lors de la modification");
    }
  };

  const handleDeleteBienImmobilier = async (bienId: number) => {
    if (!confirm("Supprimer ce bien immobilier ?")) return;
    try {
      await api.delete(`/clients/${id}/biens-immobiliers/${bienId}`);
      setBiensImmobiliers(biensImmobiliers.filter(b => b.id !== bienId));
      toast.success("✅ Bien immobilier supprimé");
    } catch (err) {
      toast.error("❌ Erreur lors de la suppression");
    }
  };

  // ===== CRUD AUTRES ÉPARGNES =====
  const handleAddAutreEpargne = async (data: Partial<ClientAutreEpargne>) => {
    try {
      const res = await api.post(`/clients/${id}/autres-epargnes`, data);
      setAutresEpargnes([...autresEpargnes, res.data]);
      setShowModal(null);
      toast.success("✅ Épargne ajoutée");
    } catch (err) {
      toast.error("❌ Erreur lors de l'ajout");
    }
  };

  const handleUpdateAutreEpargne = async (epargneId: number, data: Partial<ClientAutreEpargne>) => {
    try {
      const res = await api.put(`/clients/${id}/autres-epargnes/${epargneId}`, data);
      setAutresEpargnes(autresEpargnes.map(e => e.id === epargneId ? res.data : e));
      setShowModal(null);
      toast.success("✅ Épargne modifiée");
    } catch (err) {
      toast.error("❌ Erreur lors de la modification");
    }
  };

  const handleDeleteAutreEpargne = async (epargneId: number) => {
    if (!confirm("Supprimer cette épargne ?")) return;
    try {
      await api.delete(`/clients/${id}/autres-epargnes/${epargneId}`);
      setAutresEpargnes(autresEpargnes.filter(e => e.id !== epargneId));
      toast.success("✅ Épargne supprimée");
    } catch (err) {
      toast.error("❌ Erreur lors de la suppression");
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
        <div className="max-w-7xl mx-auto">
          {/* Header */}
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
                Mise à jour vocale ou manuelle des informations
              </p>
            </div>
          </div>

          {/* Mise à jour vocale */}
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
                <LongRecorder
                  clientId={parseInt(id!)}
                  onTranscriptionComplete={() => {
                    toast.success("✅ Fiche client mise à jour avec succès !");
                    setTimeout(() => navigate(`/clients/${id}`), 1500);
                  }}
                />
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

          {/* Formulaire manuel */}
          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Section Informations de base */}
            <div className="bg-white rounded-xl shadow-lg overflow-hidden border-l-4 border-indigo-500">
              <div className="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-800">Informations de base</h3>
              </div>
              <div className="p-6 space-y-6">
                {/* Identité */}
                <div className="border-l-4 border-indigo-400 pl-4">
                  <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Identité</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Civilité <span className="text-red-500">*</span>
                      </label>
                      <select
                        value={form.civilite}
                        onChange={(e) => setForm({ ...form, civilite: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
                        value={form.nom}
                        onChange={(e) => setForm({ ...form, nom: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                        required
                      />
                    </div>
                    {showNomJeuneFille && (
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nom de jeune fille</label>
                        <input
                          value={form.nom_jeune_fille}
                          onChange={(e) => setForm({ ...form, nom_jeune_fille: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                        />
                      </div>
                    )}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Prénom <span className="text-red-500">*</span>
                      </label>
                      <input
                        value={form.prenom}
                        onChange={(e) => setForm({ ...form, prenom: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                        required
                      />
                    </div>
                  </div>
                </div>

                {/* Coordonnées */}
                <div className="border-l-4 border-cyan-400 pl-4">
                  <h4 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-4">Coordonnées</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                      <input
                        type="tel"
                        value={form.telephone}
                        onChange={(e) => setForm({ ...form, telephone: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                      <input
                        type="email"
                        value={form.email}
                        onChange={(e) => setForm({ ...form, email: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div className="md:col-span-2">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                      <input
                        value={form.adresse}
                        onChange={(e) => setForm({ ...form, adresse: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Code postal</label>
                      <input
                        value={form.code_postal}
                        onChange={(e) => setForm({ ...form, code_postal: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                      <input
                        value={form.ville}
                        onChange={(e) => setForm({ ...form, ville: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                  </div>
                </div>

                {/* Informations personnelles */}
                <div className="border-l-4 border-blue-400 pl-4">
                  <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Informations personnelles</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
                      <input
                        type="date"
                        value={form.date_naissance}
                        onChange={(e) => setForm({ ...form, date_naissance: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Lieu de naissance</label>
                      <input
                        value={form.lieu_naissance}
                        onChange={(e) => setForm({ ...form, lieu_naissance: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Situation matrimoniale</label>
                      <select
                        value={form.situation_matrimoniale}
                        onChange={(e) => setForm({ ...form, situation_matrimoniale: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
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
                        value={form.nombre_enfants}
                        onChange={(e) => setForm({ ...form, nombre_enfants: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                  </div>
                </div>

                {/* Informations professionnelles */}
                <div className="border-l-4 border-green-400 pl-4">
                  <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Informations professionnelles</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Profession</label>
                      <input
                        value={form.profession}
                        onChange={(e) => setForm({ ...form, profession: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Revenus annuels (€)</label>
                      <input
                        type="number"
                        min="0"
                        value={form.revenus_annuels}
                        onChange={(e) => setForm({ ...form, revenus_annuels: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                  </div>
                </div>

                {/* Besoins */}
                <div className="border-l-4 border-purple-400 pl-4">
                  <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Besoins</h4>
                  {besoins.length > 0 && (
                    <div className="flex flex-wrap gap-2 mb-3">
                      {besoins.map((besoin, index) => (
                        <span
                          key={index}
                          className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-purple-100 text-purple-800"
                        >
                          {besoin}
                          <button
                            type="button"
                            onClick={() => handleRemoveBesoin(index)}
                            className="ml-2 text-purple-600 hover:text-purple-900"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </span>
                      ))}
                    </div>
                  )}
                  <div className="flex gap-2">
                    <input
                      type="text"
                      placeholder="Ex: mutuelle, prévoyance..."
                      value={newBesoin}
                      onChange={(e) => setNewBesoin(e.target.value)}
                      onKeyPress={(e) => {
                        if (e.key === "Enter") {
                          handleAddBesoin(e);
                        }
                      }}
                      className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    />
                    <button
                      type="button"
                      onClick={handleAddBesoin}
                      className="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-4 py-2 rounded-lg font-medium"
                    >
                      Ajouter
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Section Revenus - Collapsible */}
            <CollapsibleSection
              title="Revenus"
              icon="💰"
              color="emerald"
              count={revenus.length}
              isExpanded={expandedSections.includes('revenus')}
              onToggle={() => toggleSection('revenus')}
              onAdd={() => setShowModal({type: 'revenu'})}
            >
              {revenus.length === 0 ? (
                <p className="text-gray-500 text-sm italic">Aucun revenu enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nature</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Périodicité</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {revenus.map((revenu) => (
                        <tr key={revenu.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3 text-sm">{revenu.nature || '-'}</td>
                          <td className="px-4 py-3 text-sm">{revenu.periodicite || '-'}</td>
                          <td className="px-4 py-3 text-sm">{revenu.montant ? `${revenu.montant} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'revenu', data: revenu})}
                              className="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                              Éditer
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDeleteRevenu(revenu.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Supprimer
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CollapsibleSection>

            {/* Section Passifs - Collapsible */}
            <CollapsibleSection
              title="Passifs (Dettes)"
              icon="📉"
              color="red"
              count={passifs.length}
              isExpanded={expandedSections.includes('passifs')}
              onToggle={() => toggleSection('passifs')}
              onAdd={() => setShowModal({type: 'passif'})}
            >
              {passifs.length === 0 ? (
                <p className="text-gray-500 text-sm italic">Aucun passif enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nature</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Prêteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remboursement</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Capital restant</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {passifs.map((passif) => (
                        <tr key={passif.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3 text-sm">{passif.nature || '-'}</td>
                          <td className="px-4 py-3 text-sm">{passif.preteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{passif.montant_remboursement ? `${passif.montant_remboursement} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm">{passif.capital_restant_du ? `${passif.capital_restant_du} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'passif', data: passif})}
                              className="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                              Éditer
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDeletePassif(passif.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Supprimer
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CollapsibleSection>

            {/* Section Actifs Financiers - Collapsible */}
            <CollapsibleSection
              title="Actifs Financiers (Assurances, PEA...)"
              icon="📈"
              color="blue"
              count={actifsFinanciers.length}
              isExpanded={expandedSections.includes('actifs')}
              onToggle={() => toggleSection('actifs')}
              onAdd={() => setShowModal({type: 'actif'})}
            >
              {actifsFinanciers.length === 0 ? (
                <p className="text-gray-500 text-sm italic">Aucun actif financier enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nature</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Établissement</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Détenteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valeur actuelle</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {actifsFinanciers.map((actif) => (
                        <tr key={actif.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3 text-sm">{actif.nature || '-'}</td>
                          <td className="px-4 py-3 text-sm">{actif.etablissement || '-'}</td>
                          <td className="px-4 py-3 text-sm">{actif.detenteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{actif.valeur_actuelle ? `${actif.valeur_actuelle} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'actif', data: actif})}
                              className="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                              Éditer
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDeleteActifFinancier(actif.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Supprimer
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CollapsibleSection>

            {/* Section Biens Immobiliers - Collapsible */}
            <CollapsibleSection
              title="Biens Immobiliers"
              icon="🏠"
              color="amber"
              count={biensImmobiliers.length}
              isExpanded={expandedSections.includes('biens')}
              onToggle={() => toggleSection('biens')}
              onAdd={() => setShowModal({type: 'bien'})}
            >
              {biensImmobiliers.length === 0 ? (
                <p className="text-gray-500 text-sm italic">Aucun bien immobilier enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Détenteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Forme propriété</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valeur estimée</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {biensImmobiliers.map((bien) => (
                        <tr key={bien.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3 text-sm">{bien.designation || '-'}</td>
                          <td className="px-4 py-3 text-sm">{bien.detenteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{bien.forme_propriete || '-'}</td>
                          <td className="px-4 py-3 text-sm">{bien.valeur_actuelle_estimee ? `${bien.valeur_actuelle_estimee} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'bien', data: bien})}
                              className="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                              Éditer
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDeleteBienImmobilier(bien.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Supprimer
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CollapsibleSection>

            {/* Section Autres Épargnes - Collapsible */}
            <CollapsibleSection
              title="Autres Épargnes (Or, Crypto...)"
              icon="💎"
              color="violet"
              count={autresEpargnes.length}
              isExpanded={expandedSections.includes('epargnes')}
              onToggle={() => toggleSection('epargnes')}
              onAdd={() => setShowModal({type: 'epargne'})}
            >
              {autresEpargnes.length === 0 ? (
                <p className="text-gray-500 text-sm italic">Aucune autre épargne enregistrée</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Détenteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valeur</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {autresEpargnes.map((epargne) => (
                        <tr key={epargne.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3 text-sm">{epargne.designation || '-'}</td>
                          <td className="px-4 py-3 text-sm">{epargne.detenteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{epargne.valeur ? `${epargne.valeur} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'epargne', data: epargne})}
                              className="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                              Éditer
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDeleteAutreEpargne(epargne.id)}
                              className="text-red-600 hover:text-red-900"
                            >
                              Supprimer
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CollapsibleSection>

            {/* Boutons d'action */}
            <div className="flex gap-3 pt-4">
              <button
                type="submit"
                disabled={loading}
                className="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-semibold disabled:opacity-50 shadow-lg flex items-center justify-center space-x-2"
              >
                {loading ? (
                  <>
                    <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Mise à jour...</span>
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
                className="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>
      </div>

      {/* Modales */}
      {showModal && (
        <Modal
          type={showModal.type}
          data={showModal.data}
          onClose={() => setShowModal(null)}
          onSubmit={(data) => {
            if (showModal.type === 'revenu') {
              showModal.data ? handleUpdateRevenu(showModal.data.id, data) : handleAddRevenu(data);
            } else if (showModal.type === 'passif') {
              showModal.data ? handleUpdatePassif(showModal.data.id, data) : handleAddPassif(data);
            } else if (showModal.type === 'actif') {
              showModal.data ? handleUpdateActifFinancier(showModal.data.id, data) : handleAddActifFinancier(data);
            } else if (showModal.type === 'bien') {
              showModal.data ? handleUpdateBienImmobilier(showModal.data.id, data) : handleAddBienImmobilier(data);
            } else if (showModal.type === 'epargne') {
              showModal.data ? handleUpdateAutreEpargne(showModal.data.id, data) : handleAddAutreEpargne(data);
            }
          }}
        />
      )}
    </>
  );
}

// Composant Section Collapsible
interface CollapsibleSectionProps {
  title: string;
  icon: string;
  color: string;
  count: number;
  isExpanded: boolean;
  onToggle: () => void;
  onAdd: () => void;
  children: ReactNode;
}

function CollapsibleSection({ title, icon, color, count, isExpanded, onToggle, onAdd, children }: CollapsibleSectionProps) {
  const colorClasses = {
    emerald: 'border-emerald-500 bg-emerald-50',
    red: 'border-red-500 bg-red-50',
    blue: 'border-blue-500 bg-blue-50',
    amber: 'border-amber-500 bg-amber-50',
    violet: 'border-violet-500 bg-violet-50',
  };

  return (
    <div className={`bg-white rounded-xl shadow-lg overflow-hidden border-l-4 ${colorClasses[color as keyof typeof colorClasses] || 'border-gray-500 bg-gray-50'}`}>
      <div
        className="px-6 py-4 cursor-pointer hover:bg-gray-50 transition-colors flex items-center justify-between"
        onClick={onToggle}
      >
        <div className="flex items-center space-x-3">
          <span className="text-2xl">{icon}</span>
          <div>
            <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
            <p className="text-sm text-gray-500">{count} élément{count !== 1 ? 's' : ''}</p>
          </div>
        </div>
        <div className="flex items-center space-x-3">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onAdd();
            }}
            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
          >
            + Ajouter
          </button>
          <svg
            className={`w-5 h-5 text-gray-600 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>
      {isExpanded && (
        <div className="px-6 py-4 border-t border-gray-200">
          {children}
        </div>
      )}
    </div>
  );
}

// Composant Modal
interface ModalProps {
  type: string;
  data?: any;
  onClose: () => void;
  onSubmit: (data: any) => void;
}

function Modal({ type, data, onClose, onSubmit }: ModalProps) {
  const [formData, setFormData] = useState(data || {});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit(formData);
  };

  const renderForm = () => {
    switch (type) {
      case 'revenu':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nature</label>
              <input
                value={formData.nature || ''}
                onChange={(e) => setFormData({...formData, nature: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Salaire, rente, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Périodicité</label>
              <input
                value={formData.periodicite || ''}
                onChange={(e) => setFormData({...formData, periodicite: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Mensuel, annuel, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Montant (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant || ''}
                onChange={(e) => setFormData({...formData, montant: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </>
        );

      case 'passif':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nature</label>
              <input
                value={formData.nature || ''}
                onChange={(e) => setFormData({...formData, nature: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Crédit immobilier, personnel, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Prêteur</label>
              <input
                value={formData.preteur || ''}
                onChange={(e) => setFormData({...formData, preteur: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Périodicité</label>
              <input
                value={formData.periodicite || ''}
                onChange={(e) => setFormData({...formData, periodicite: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Montant remboursement (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant_remboursement || ''}
                onChange={(e) => setFormData({...formData, montant_remboursement: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Capital restant dû (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.capital_restant_du || ''}
                onChange={(e) => setFormData({...formData, capital_restant_du: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Durée restante (mois)</label>
              <input
                type="number"
                min="0"
                value={formData.duree_restante || ''}
                onChange={(e) => setFormData({...formData, duree_restante: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </>
        );

      case 'actif':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nature</label>
              <input
                value={formData.nature || ''}
                onChange={(e) => setFormData({...formData, nature: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Assurance-vie, PEA, PER, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Établissement</label>
              <input
                value={formData.etablissement || ''}
                onChange={(e) => setFormData({...formData, etablissement: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Détenteur</label>
              <input
                value={formData.detenteur || ''}
                onChange={(e) => setFormData({...formData, detenteur: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date ouverture/souscription</label>
              <input
                type="date"
                value={formData.date_ouverture_souscription || ''}
                onChange={(e) => setFormData({...formData, date_ouverture_souscription: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Valeur actuelle (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_actuelle || ''}
                onChange={(e) => setFormData({...formData, valeur_actuelle: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </>
        );

      case 'bien':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Désignation</label>
              <input
                value={formData.designation || ''}
                onChange={(e) => setFormData({...formData, designation: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Résidence principale, appartement locatif, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Détenteur</label>
              <input
                value={formData.detenteur || ''}
                onChange={(e) => setFormData({...formData, detenteur: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Forme propriété</label>
              <input
                value={formData.forme_propriete || ''}
                onChange={(e) => setFormData({...formData, forme_propriete: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Pleine propriété, indivision, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Valeur actuelle estimée (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_actuelle_estimee || ''}
                onChange={(e) => setFormData({...formData, valeur_actuelle_estimee: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Année acquisition</label>
              <input
                type="number"
                min="1900"
                max={new Date().getFullYear()}
                value={formData.annee_acquisition || ''}
                onChange={(e) => setFormData({...formData, annee_acquisition: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Valeur acquisition (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_acquisition || ''}
                onChange={(e) => setFormData({...formData, valeur_acquisition: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </>
        );

      case 'epargne':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Désignation</label>
              <input
                value={formData.designation || ''}
                onChange={(e) => setFormData({...formData, designation: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                placeholder="Or, crypto, œuvres d'art, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Détenteur</label>
              <input
                value={formData.detenteur || ''}
                onChange={(e) => setFormData({...formData, detenteur: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Valeur (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur || ''}
                onChange={(e) => setFormData({...formData, valeur: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </>
        );

      default:
        return null;
    }
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-xl font-bold text-gray-800">
            {data ? 'Modifier' : 'Ajouter'} {type === 'revenu' ? 'un revenu' : type === 'passif' ? 'un passif' : type === 'actif' ? 'un actif financier' : type === 'bien' ? 'un bien immobilier' : 'une épargne'}
          </h3>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          {renderForm()}
          <div className="flex gap-3 pt-4">
            <button
              type="submit"
              className="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-semibold"
            >
              {data ? 'Modifier' : 'Ajouter'}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold"
            >
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
