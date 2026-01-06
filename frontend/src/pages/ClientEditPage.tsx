import { useState, useEffect } from "react";
import type { ReactNode } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { LongRecorder } from "../components/LongRecorder";
import { Mic, Coins, CreditCard, TrendingUp, Home, Gem, Heart, Shield, Users, Wallet } from "lucide-react";
import type {
  ClientRevenu,
  ClientPassif,
  ClientActifFinancier,
  ClientBienImmobilier,
  ClientAutreEpargne,
  SanteSouhait,
  BaePrevoyance,
  BaeRetraite,
  BaeEpargne,
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
  const [conjoint, setConjoint] = useState<any | null>(null);
  const [enfants, setEnfants] = useState<any[]>([]);
  const [revenus, setRevenus] = useState<ClientRevenu[]>([]);
  const [passifs, setPassifs] = useState<ClientPassif[]>([]);
  const [actifsFinanciers, setActifsFinanciers] = useState<ClientActifFinancier[]>([]);
  const [biensImmobiliers, setBiensImmobiliers] = useState<ClientBienImmobilier[]>([]);
  const [autresEpargnes, setAutresEpargnes] = useState<ClientAutreEpargne[]>([]);

  // États pour les BAE
  const [santeSouhait, setSanteSouhait] = useState<SanteSouhait | null>(null);
  const [baePrevoyance, setBaePrevoyance] = useState<BaePrevoyance | null>(null);
  const [baeRetraite, setBaeRetraite] = useState<BaeRetraite | null>(null);
  const [baeEpargne, setBaeEpargne] = useState<BaeEpargne | null>(null);

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
      setConjoint(client.conjoint || null);
      setEnfants(client.enfants || []);
      setRevenus(client.revenus || []);
      setPassifs(client.passifs || []);
      setActifsFinanciers(client.actifs_financiers || []);
      setBiensImmobiliers(client.biens_immobiliers || []);
      setAutresEpargnes(client.autres_epargnes || []);

      // Charger les BAE
      setSanteSouhait(client.sante_souhait || null);
      setBaePrevoyance(client.bae_prevoyance || null);
      setBaeRetraite(client.bae_retraite || null);
      setBaeEpargne(client.bae_epargne || null);
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

  // ===== CRUD SANTÉ SOUHAIT =====
  const handleSaveSanteSouhait = async (data: Partial<SanteSouhait>) => {
    try {
      if (santeSouhait) {
        const res = await api.put(`/clients/${id}/sante-souhait`, data);
        setSanteSouhait(res.data);
        toast.success("✅ Santé/Souhait mis à jour");
      } else {
        const res = await api.post(`/clients/${id}/sante-souhait`, data);
        setSanteSouhait(res.data);
        toast.success("✅ Santé/Souhait créé");
      }
      setShowModal(null);
    } catch (err) {
      toast.error("❌ Erreur lors de l'enregistrement");
    }
  };

  // ===== CRUD BAE PRÉVOYANCE =====
  const handleSaveBaePrevoyance = async (data: Partial<BaePrevoyance>) => {
    try {
      if (baePrevoyance) {
        const res = await api.put(`/clients/${id}/bae-prevoyance`, data);
        setBaePrevoyance(res.data);
        toast.success("✅ Prévoyance mise à jour");
      } else {
        const res = await api.post(`/clients/${id}/bae-prevoyance`, data);
        setBaePrevoyance(res.data);
        toast.success("✅ Prévoyance créée");
      }
      setShowModal(null);
    } catch (err) {
      toast.error("❌ Erreur lors de l'enregistrement");
    }
  };

  // ===== CRUD BAE RETRAITE =====
  const handleSaveBaeRetraite = async (data: Partial<BaeRetraite>) => {
    try {
      if (baeRetraite) {
        const res = await api.put(`/clients/${id}/bae-retraite`, data);
        setBaeRetraite(res.data);
        toast.success("✅ Retraite mise à jour");
      } else {
        const res = await api.post(`/clients/${id}/bae-retraite`, data);
        setBaeRetraite(res.data);
        toast.success("✅ Retraite créée");
      }
      setShowModal(null);
    } catch (err) {
      toast.error("❌ Erreur lors de l'enregistrement");
    }
  };

  // ===== CRUD BAE ÉPARGNE =====
  const handleSaveBaeEpargne = async (data: Partial<BaeEpargne>) => {
    try {
      if (baeEpargne) {
        const res = await api.put(`/clients/${id}/bae-epargne`, data);
        setBaeEpargne(res.data);
        toast.success("✅ Épargne mise à jour");
      } else {
        const res = await api.post(`/clients/${id}/bae-epargne`, data);
        setBaeEpargne(res.data);
        toast.success("✅ Épargne créée");
      }
      setShowModal(null);
    } catch (err) {
      toast.error("❌ Erreur lors de l'enregistrement");
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

  const handleRemoveBesoin = async (index: number) => {
    const besoinToRemove = besoins[index].toLowerCase();

    // Map besoins to BAE sections
    const besoinToBAEMap: Record<string, string> = {
      'santé': 'sante-souhait',
      'prévoyance': 'bae-prevoyance',
      'retraite': 'bae-retraite',
      'épargne': 'bae-epargne',
    };

    // If besoin corresponds to a BAE section, delete it
    const baeEndpoint = besoinToBAEMap[besoinToRemove];
    if (baeEndpoint) {
      try {
        await api.delete(`/clients/${id}/${baeEndpoint}`);
        // Update local state
        if (besoinToRemove === 'santé') setSanteSouhait(null);
        if (besoinToRemove === 'prévoyance') setBaePrevoyance(null);
        if (besoinToRemove === 'retraite') setBaeRetraite(null);
        if (besoinToRemove === 'épargne') setBaeEpargne(null);

        toast.success(`Section ${besoins[index]} supprimée avec succès`);
      } catch (err) {
        console.error('Error deleting BAE section:', err);
        toast.error(`Erreur lors de la suppression de la section ${besoins[index]}`);
      }
    }

    setBesoins(besoins.filter((_, i) => i !== index));
  };

  if (fetching) {
    return (
      <div className="flex justify-center items-center h-screen">
        <div className="flex flex-col items-center space-y-4">
          <svg
            className="animate-spin h-12 w-12 text-[#7367F0]"
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
          <p className="text-[#6E6B7B] font-medium">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-8 px-4">
        <div className="max-w-7xl mx-auto">
          {/* Header */}
          <div className="mb-6 flex items-center space-x-4">
            <button
              onClick={() => navigate(`/clients/${id}`)}
              className="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-md hover:shadow-lg transition-shadow text-[#6E6B7B] hover:text-[#7367F0]"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </button>
            <div>
              <h1 className="text-3xl font-bold text-[#5E5873]">Éditer le client</h1>
              <p className="text-[#6E6B7B] mt-1">
                Mise à jour vocale ou manuelle des informations
              </p>
            </div>
          </div>

          {/* Mise à jour vocale */}
          <div className="vx-card mb-6 border-l-4 border-[#7367F0]">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <div className="w-14 h-14 bg-gradient-to-br from-[#7367F0] to-[#9055FD] rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                  <Mic size={28} className="text-white" />
                </div>
              </div>
              <div className="flex-1">
                <h3 className="text-xl font-semibold text-[#5E5873] mb-1">
                  Mise à jour vocale
                </h3>
                <p className="text-sm text-[#6E6B7B] mb-4">
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
              <div className="w-full border-t-2 border-[#EBE9F1]"></div>
            </div>
            <div className="relative flex justify-center">
              <span className="bg-[#F8F8F8] px-4 py-1 text-sm font-semibold text-[#B9B9C3] rounded-full border-2 border-[#EBE9F1]">
                OU
              </span>
            </div>
          </div>

          {/* Formulaire manuel */}
          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Section Informations de base */}
            <div className="vx-card overflow-hidden border-l-4 border-[#7367F0]">
              <div className="bg-[#F3F2F7] px-6 py-4 border-b border-[#EBE9F1]">
                <h3 className="text-lg font-semibold text-[#5E5873]">Informations de base</h3>
              </div>
              <div className="p-6 space-y-6">
                {/* Identité */}
                <div className="border-l-4 border-[#7367F0] pl-4">
                  <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4">Identité</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">
                        Civilité <span className="text-red-500">*</span>
                      </label>
                      <select
                        value={form.civilite}
                        onChange={(e) => setForm({ ...form, civilite: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0] focus:border-[#7367F0]"
                        required
                      >
                        <option value="">Sélectionner...</option>
                        <option value="Monsieur">Monsieur</option>
                        <option value="Madame">Madame</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">
                        Nom <span className="text-red-500">*</span>
                      </label>
                      <input
                        value={form.nom}
                        onChange={(e) => setForm({ ...form, nom: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                        required
                      />
                    </div>
                    {showNomJeuneFille && (
                      <div>
                        <label className="block text-sm font-medium text-[#5E5873] mb-1">Nom de jeune fille</label>
                        <input
                          value={form.nom_jeune_fille}
                          onChange={(e) => setForm({ ...form, nom_jeune_fille: e.target.value })}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                        />
                      </div>
                    )}
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">
                        Prénom <span className="text-red-500">*</span>
                      </label>
                      <input
                        value={form.prenom}
                        onChange={(e) => setForm({ ...form, prenom: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                        required
                      />
                    </div>
                  </div>
                </div>

                {/* Coordonnées */}
                <div className="border-l-4 border-[#00CFE8] pl-4">
                  <h4 className="text-sm font-medium text-[#5E5873] uppercase tracking-wide mb-4">Coordonnées</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Téléphone</label>
                      <input
                        type="tel"
                        value={form.telephone}
                        onChange={(e) => setForm({ ...form, telephone: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Email</label>
                      <input
                        type="email"
                        value={form.email}
                        onChange={(e) => setForm({ ...form, email: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div className="md:col-span-2">
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Adresse</label>
                      <input
                        value={form.adresse}
                        onChange={(e) => setForm({ ...form, adresse: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Code postal</label>
                      <input
                        value={form.code_postal}
                        onChange={(e) => setForm({ ...form, code_postal: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Ville</label>
                      <input
                        value={form.ville}
                        onChange={(e) => setForm({ ...form, ville: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                  </div>
                </div>

                {/* Informations personnelles */}
                <div className="border-l-4 border-[#28C76F] pl-4">
                  <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4">Informations personnelles</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Date de naissance</label>
                      <input
                        type="date"
                        value={form.date_naissance}
                        onChange={(e) => setForm({ ...form, date_naissance: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Lieu de naissance</label>
                      <input
                        value={form.lieu_naissance}
                        onChange={(e) => setForm({ ...form, lieu_naissance: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Situation matrimoniale</label>
                      <select
                        value={form.situation_matrimoniale}
                        onChange={(e) => setForm({ ...form, situation_matrimoniale: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
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
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Nombre d'enfants</label>
                      <input
                        type="number"
                        min="0"
                        value={form.nombre_enfants}
                        onChange={(e) => setForm({ ...form, nombre_enfants: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                  </div>
                </div>

                {/* Informations professionnelles */}
                <div className="border-l-4 border-[#FF9F43] pl-4">
                  <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4">Informations professionnelles</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Profession</label>
                      <input
                        value={form.profession}
                        onChange={(e) => setForm({ ...form, profession: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-[#5E5873] mb-1">Revenus annuels (€)</label>
                      <input
                        type="number"
                        min="0"
                        value={form.revenus_annuels}
                        onChange={(e) => setForm({ ...form, revenus_annuels: e.target.value })}
                        className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                      />
                    </div>
                  </div>
                </div>

                {/* Besoins */}
                <div className="border-l-4 border-[#9055FD] pl-4">
                  <h4 className="text-sm font-semibold text-[#5E5873] uppercase tracking-wide mb-4">Besoins</h4>
                  {besoins.length > 0 && (
                    <div className="flex flex-wrap gap-2 mb-3">
                      {besoins.map((besoin, index) => (
                        <span
                          key={index}
                          className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-[#9055FD]/10 text-[#9055FD] border border-[#9055FD]/30"
                        >
                          {besoin}
                          <button
                            type="button"
                            onClick={() => handleRemoveBesoin(index)}
                            className="ml-2 text-[#9055FD] hover:text-[#7367F0]"
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
                      className="flex-1 px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                    />
                    <button
                      type="button"
                      onClick={handleAddBesoin}
                      className="bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white px-4 py-2 rounded-lg font-medium"
                    >
                      Ajouter
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Section Conjoint - Collapsible */}
            <CollapsibleSection
              title="Conjoint"
              icon={<Heart className="w-6 h-6" />}
              color="pink"
              count={conjoint ? 1 : 0}
              isExpanded={expandedSections.includes('conjoint')}
              onToggle={() => toggleSection('conjoint')}
              onAdd={() => setShowModal({type: 'conjoint', data: conjoint})}
              buttonLabel={conjoint ? "Modifier" : "+ Ajouter"}
            >
              {!conjoint ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucun conjoint enregistré</p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 bg-white p-4 rounded-lg border border-[#EBE9F1]">
                  <div>
                    <span className="text-xs text-[#B9B9C3] uppercase font-semibold">Nom complet</span>
                    <p className="text-[#5E5873] font-medium mt-1">
                      {conjoint.prenom || conjoint.nom ? `${conjoint.prenom || ''} ${conjoint.nom?.toUpperCase() || ''}`.trim() : 'Non renseigné'}
                    </p>
                  </div>
                  <div>
                    <span className="text-xs text-[#B9B9C3] uppercase font-semibold">Nom de jeune fille</span>
                    <p className="text-[#5E5873] font-medium mt-1">{conjoint.nom_jeune_fille || 'Non renseigné'}</p>
                  </div>
                  <div>
                    <span className="text-xs text-[#B9B9C3] uppercase font-semibold">Date de naissance</span>
                    <p className="text-[#5E5873] font-medium mt-1">{conjoint.date_naissance || 'Non renseigné'}</p>
                  </div>
                  <div>
                    <span className="text-xs text-[#B9B9C3] uppercase font-semibold">Profession</span>
                    <p className="text-[#5E5873] font-medium mt-1">{conjoint.profession || 'Non renseigné'}</p>
                  </div>
                  <div>
                    <span className="text-xs text-[#B9B9C3] uppercase font-semibold">Téléphone</span>
                    <p className="text-[#5E5873] font-medium mt-1">{conjoint.telephone || 'Non renseigné'}</p>
                  </div>
                  <div>
                    <span className="text-xs text-[#B9B9C3] uppercase font-semibold">Adresse</span>
                    <p className="text-[#5E5873] font-medium mt-1">{conjoint.adresse || 'Non renseigné'}</p>
                  </div>
                </div>
              )}
            </CollapsibleSection>

            {/* Section Enfants - Collapsible */}
            <CollapsibleSection
              title="Enfants"
              icon={<Users className="w-6 h-6" />}
              color="purple"
              count={enfants.length}
              isExpanded={expandedSections.includes('enfants')}
              onToggle={() => toggleSection('enfants')}
              onAdd={() => setShowModal({type: 'enfant'})}
            >
              {enfants.length === 0 ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucun enfant enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Prénom</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Date de naissance</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">À charge</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-[#B9B9C3] uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {enfants.map((enfant, idx) => (
                        <tr key={enfant.id || idx} className="hover:bg-[#F8F8F8]">
                          <td className="px-4 py-3 text-sm text-[#5E5873] font-medium">{enfant.prenom || 'Non renseigné'}</td>
                          <td className="px-4 py-3 text-sm text-[#6E6B7B]">{enfant.date_naissance || 'Non renseigné'}</td>
                          <td className="px-4 py-3 text-sm text-[#6E6B7B]">
                            {enfant.fiscalement_a_charge ? (
                              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Oui
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                Non
                              </span>
                            )}
                          </td>
                          <td className="px-4 py-3 text-right text-sm space-x-2">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'enfant', data: enfant})}
                              className="text-[#7367F0] hover:text-[#5E50EE] font-medium"
                            >
                              Modifier
                            </button>
                            <button
                              type="button"
                              onClick={async () => {
                                if (confirm('Êtes-vous sûr de vouloir supprimer cet enfant ?')) {
                                  try {
                                    await api.delete(`/clients/${id}/enfants/${enfant.id}`);
                                    setEnfants(enfants.filter(e => e.id !== enfant.id));
                                    toast.success('Enfant supprimé');
                                  } catch (err) {
                                    toast.error('Erreur lors de la suppression');
                                  }
                                }
                              }}
                              className="text-[#EA5455] hover:text-[#E63C3D] font-medium"
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

            {/* Section Revenus - Collapsible */}
            <CollapsibleSection
              title="Revenus"
              icon={<Coins className="w-6 h-6" />}
              color="amber"
              count={revenus.length}
              isExpanded={expandedSections.includes('revenus')}
              onToggle={() => toggleSection('revenus')}
              onAdd={() => setShowModal({type: 'revenu'})}
            >
              {revenus.length === 0 ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucun revenu enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Nature</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Périodicité</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Montant</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-[#B9B9C3] uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {revenus.map((revenu) => (
                        <tr key={revenu.id} className="hover:bg-[#F8F8F8]">
                          <td className="px-4 py-3 text-sm">{revenu.nature || '-'}</td>
                          <td className="px-4 py-3 text-sm">{revenu.periodicite || '-'}</td>
                          <td className="px-4 py-3 text-sm">{revenu.montant ? `${revenu.montant} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'revenu', data: revenu})}
                              className="text-[#7367F0] hover:text-[#5E50EE] mr-3"
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
              icon={<CreditCard className="w-6 h-6" />}
              color="red"
              count={passifs.length}
              isExpanded={expandedSections.includes('passifs')}
              onToggle={() => toggleSection('passifs')}
              onAdd={() => setShowModal({type: 'passif'})}
            >
              {passifs.length === 0 ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucun passif enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Nature</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Prêteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Remboursement</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Capital restant</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-[#B9B9C3] uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {passifs.map((passif) => (
                        <tr key={passif.id} className="hover:bg-[#F8F8F8]">
                          <td className="px-4 py-3 text-sm">{passif.nature || '-'}</td>
                          <td className="px-4 py-3 text-sm">{passif.preteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{passif.montant_remboursement ? `${passif.montant_remboursement} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm">{passif.capital_restant_du ? `${passif.capital_restant_du} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'passif', data: passif})}
                              className="text-[#7367F0] hover:text-[#5E50EE] mr-3"
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
              icon={<TrendingUp className="w-6 h-6" />}
              color="blue"
              count={actifsFinanciers.length}
              isExpanded={expandedSections.includes('actifs')}
              onToggle={() => toggleSection('actifs')}
              onAdd={() => setShowModal({type: 'actif'})}
            >
              {actifsFinanciers.length === 0 ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucun actif financier enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Nature</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Établissement</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Détenteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Valeur actuelle</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-[#B9B9C3] uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {actifsFinanciers.map((actif) => (
                        <tr key={actif.id} className="hover:bg-[#F8F8F8]">
                          <td className="px-4 py-3 text-sm">{actif.nature || '-'}</td>
                          <td className="px-4 py-3 text-sm">{actif.etablissement || '-'}</td>
                          <td className="px-4 py-3 text-sm">{actif.detenteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{actif.valeur_actuelle ? `${actif.valeur_actuelle} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'actif', data: actif})}
                              className="text-[#7367F0] hover:text-[#5E50EE] mr-3"
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
              icon={<Home className="w-6 h-6" />}
              color="amber"
              count={biensImmobiliers.length}
              isExpanded={expandedSections.includes('biens')}
              onToggle={() => toggleSection('biens')}
              onAdd={() => setShowModal({type: 'bien'})}
            >
              {biensImmobiliers.length === 0 ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucun bien immobilier enregistré</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Désignation</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Détenteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Forme propriété</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Valeur estimée</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-[#B9B9C3] uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {biensImmobiliers.map((bien) => (
                        <tr key={bien.id} className="hover:bg-[#F8F8F8]">
                          <td className="px-4 py-3 text-sm">{bien.designation || '-'}</td>
                          <td className="px-4 py-3 text-sm">{bien.detenteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{bien.forme_propriete || '-'}</td>
                          <td className="px-4 py-3 text-sm">{bien.valeur_actuelle_estimee ? `${bien.valeur_actuelle_estimee} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'bien', data: bien})}
                              className="text-[#7367F0] hover:text-[#5E50EE] mr-3"
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
              icon={<Gem className="w-6 h-6" />}
              color="violet"
              count={autresEpargnes.length}
              isExpanded={expandedSections.includes('epargnes')}
              onToggle={() => toggleSection('epargnes')}
              onAdd={() => setShowModal({type: 'epargne'})}
            >
              {autresEpargnes.length === 0 ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucune autre épargne enregistrée</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Désignation</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Détenteur</th>
                        <th className="px-4 py-2 text-left text-xs font-medium text-[#B9B9C3] uppercase">Valeur</th>
                        <th className="px-4 py-2 text-right text-xs font-medium text-[#B9B9C3] uppercase">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {autresEpargnes.map((epargne) => (
                        <tr key={epargne.id} className="hover:bg-[#F8F8F8]">
                          <td className="px-4 py-3 text-sm">{epargne.designation || '-'}</td>
                          <td className="px-4 py-3 text-sm">{epargne.detenteur || '-'}</td>
                          <td className="px-4 py-3 text-sm">{epargne.valeur ? `${epargne.valeur} €` : '-'}</td>
                          <td className="px-4 py-3 text-sm text-right">
                            <button
                              type="button"
                              onClick={() => setShowModal({type: 'epargne', data: epargne})}
                              className="text-[#7367F0] hover:text-[#5E50EE] mr-3"
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

            {/* Section Santé / Souhait - Collapsible */}
            <CollapsibleSection
              title="Santé / Souhait"
              icon={<Heart className="w-6 h-6" />}
              color="teal"
              count={santeSouhait ? 1 : 0}
              isExpanded={expandedSections.includes('sante')}
              onToggle={() => toggleSection('sante')}
              onAdd={() => setShowModal({type: 'sante', data: santeSouhait})}
              buttonLabel="Modifier"
            >
              {!santeSouhait ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucune donnée de santé/souhait enregistrée</p>
              ) : (
                <div className="space-y-3 text-sm">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <span className="font-semibold text-[#5E5873]">Contrat en place:</span>
                      <p className="text-[#6E6B7B]">{santeSouhait.contrat_en_place || '-'}</p>
                    </div>
                    <div>
                      <span className="font-semibold text-[#5E5873]">Budget mensuel max:</span>
                      <p className="text-[#6E6B7B]">{santeSouhait.budget_mensuel_maximum ? `${santeSouhait.budget_mensuel_maximum} €` : '-'}</p>
                    </div>
                  </div>
                  <div className="border-t border-[#EBE9F1] pt-3">
                    <h5 className="font-semibold text-[#5E5873] mb-2">Niveaux de garantie (0-10)</h5>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                      <div>
                        <span className="text-[#6E6B7B]">Hospitalisation:</span> {santeSouhait.niveau_hospitalisation ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Chambre particulière:</span> {santeSouhait.niveau_chambre_particuliere ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Médecin généraliste:</span> {santeSouhait.niveau_medecin_generaliste ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Analyses/Imagerie:</span> {santeSouhait.niveau_analyses_imagerie ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Auxiliaires médicaux:</span> {santeSouhait.niveau_auxiliaires_medicaux ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Pharmacie:</span> {santeSouhait.niveau_pharmacie ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Dentaire:</span> {santeSouhait.niveau_dentaire ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Optique:</span> {santeSouhait.niveau_optique ?? '-'}
                      </div>
                      <div>
                        <span className="text-[#6E6B7B]">Prothèses auditives:</span> {santeSouhait.niveau_protheses_auditives ?? '-'}
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </CollapsibleSection>

            {/* Section BAE Prévoyance - Collapsible */}
            <CollapsibleSection
              title="BAE Prévoyance"
              icon={<Shield className="w-6 h-6" />}
              color="indigo"
              count={baePrevoyance ? 1 : 0}
              isExpanded={expandedSections.includes('prevoyance')}
              onToggle={() => toggleSection('prevoyance')}
              onAdd={() => setShowModal({type: 'prevoyance', data: baePrevoyance})}
              buttonLabel="Modifier"
            >
              {!baePrevoyance ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucune donnée de prévoyance enregistrée</p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  <div>
                    <span className="font-semibold text-[#5E5873]">Contrat en place:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.contrat_en_place || '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Date effet:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.date_effet || '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Cotisations:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.cotisations ? `${baePrevoyance.cotisations} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Couverture invalidité:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.souhaite_couverture_invalidite ? 'Oui' : 'Non'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Revenu à garantir:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.revenu_a_garantir ? `${baePrevoyance.revenu_a_garantir} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Capital décès souhaité:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.capital_deces_souhaite ? `${baePrevoyance.capital_deces_souhaite} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Garanties obsèques:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.garanties_obseques || '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Payeur:</span>
                    <p className="text-[#6E6B7B]">{baePrevoyance.payeur || '-'}</p>
                  </div>
                </div>
              )}
            </CollapsibleSection>

            {/* Section BAE Retraite - Collapsible */}
            <CollapsibleSection
              title="BAE Retraite"
              icon={<Users className="w-6 h-6" />}
              color="purple"
              count={baeRetraite ? 1 : 0}
              isExpanded={expandedSections.includes('retraite')}
              onToggle={() => toggleSection('retraite')}
              onAdd={() => setShowModal({type: 'retraite', data: baeRetraite})}
              buttonLabel="Modifier"
            >
              {!baeRetraite ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucune donnée de retraite enregistrée</p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  <div>
                    <span className="font-semibold text-[#5E5873]">Revenus annuels:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.revenus_annuels ? `${baeRetraite.revenus_annuels} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Revenus annuels foyer:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.revenus_annuels_foyer ? `${baeRetraite.revenus_annuels_foyer} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Impôt revenu:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.impot_revenu ? `${baeRetraite.impot_revenu} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Nombre parts fiscales:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.nombre_parts_fiscales ?? '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">TMI:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.tmi || '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Âge départ retraite:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.age_depart_retraite ?? '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">% revenu à maintenir:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.pourcentage_revenu_a_maintenir ? `${baeRetraite.pourcentage_revenu_a_maintenir}%` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Cotisations annuelles:</span>
                    <p className="text-[#6E6B7B]">{baeRetraite.cotisations_annuelles ? `${baeRetraite.cotisations_annuelles} €` : '-'}</p>
                  </div>
                </div>
              )}
            </CollapsibleSection>

            {/* Section BAE Épargne - Collapsible */}
            <CollapsibleSection
              title="BAE Épargne"
              icon={<Wallet className="w-6 h-6" />}
              color="green"
              count={baeEpargne ? 1 : 0}
              isExpanded={expandedSections.includes('bae_epargne')}
              onToggle={() => toggleSection('bae_epargne')}
              onAdd={() => setShowModal({type: 'bae_epargne', data: baeEpargne})}
              buttonLabel="Modifier"
            >
              {!baeEpargne ? (
                <p className="text-[#B9B9C3] text-sm italic">Aucune donnée d'épargne BAE enregistrée</p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  <div>
                    <span className="font-semibold text-[#5E5873]">Épargne disponible:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.epargne_disponible ? 'Oui' : 'Non'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Montant épargne disponible:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.montant_epargne_disponible ? `${baeEpargne.montant_epargne_disponible} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Capacité épargne estimée:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.capacite_epargne_estimee ? `${baeEpargne.capacite_epargne_estimee} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Actifs financiers total:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.actifs_financiers_total ? `${baeEpargne.actifs_financiers_total} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Actifs immo total:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.actifs_immo_total ? `${baeEpargne.actifs_immo_total} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Passifs total emprunts:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.passifs_total_emprunts ? `${baeEpargne.passifs_total_emprunts} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Charges totales:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.charges_totales ? `${baeEpargne.charges_totales} €` : '-'}</p>
                  </div>
                  <div>
                    <span className="font-semibold text-[#5E5873]">Situation financière:</span>
                    <p className="text-[#6E6B7B]">{baeEpargne.situation_financiere_revenus_charges || '-'}</p>
                  </div>
                </div>
              )}
            </CollapsibleSection>

            {/* Boutons d'action */}
            <div className="flex gap-3 pt-4">
              <button
                type="submit"
                disabled={loading}
                className="flex-1 bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg font-semibold disabled:opacity-50 shadow-lg flex items-center justify-center space-x-2"
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
                className="px-6 py-3 bg-[#F3F2F7] hover:bg-[#F3F2F7] text-[#5E5873] rounded-lg font-semibold"
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
            } else if (showModal.type === 'sante') {
              handleSaveSanteSouhait(data);
            } else if (showModal.type === 'prevoyance') {
              handleSaveBaePrevoyance(data);
            } else if (showModal.type === 'retraite') {
              handleSaveBaeRetraite(data);
            } else if (showModal.type === 'bae_epargne') {
              handleSaveBaeEpargne(data);
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
  icon: ReactNode;
  color: string;
  count: number;
  isExpanded: boolean;
  onToggle: () => void;
  onAdd: () => void;
  buttonLabel?: string;
  children: ReactNode;
}

function CollapsibleSection({ title, icon, color, count, isExpanded, onToggle, onAdd, buttonLabel = "+ Ajouter", children }: CollapsibleSectionProps) {
  const colorClasses = {
    emerald: 'border-emerald-500 bg-emerald-50',
    red: 'border-red-500 bg-red-50',
    blue: 'border-blue-500 bg-blue-50',
    amber: 'border-amber-500 bg-amber-50',
    violet: 'border-violet-500 bg-violet-50',
    teal: 'border-teal-500 bg-teal-50',
    indigo: 'border-indigo-500 bg-indigo-50',
    purple: 'border-purple-500 bg-purple-50',
    green: 'border-green-500 bg-green-50',
    pink: 'border-pink-500 bg-pink-50',
  };

  const gradientClasses = {
    emerald: 'bg-gradient-to-br from-[#FF9F43] to-[#FFB976]',
    red: 'bg-gradient-to-br from-[#EA5455] to-[#EF6E6F]',
    blue: 'bg-gradient-to-br from-[#00CFE8] to-[#00E5FF]',
    amber: 'bg-gradient-to-br from-[#FF9F43] to-[#FFB976]',
    violet: 'bg-gradient-to-br from-[#9055FD] to-[#B085FF]',
    teal: 'bg-gradient-to-br from-[#14B8A6] to-[#2DD4BF]',
    indigo: 'bg-gradient-to-br from-[#7367F0] to-[#9055FD]',
    purple: 'bg-gradient-to-br from-[#9055FD] to-[#B085FF]',
    green: 'bg-gradient-to-br from-[#28C76F] to-[#48DA89]',
    pink: 'bg-gradient-to-br from-[#E91E63] to-[#F06292]',
  };

  return (
    <div className={`vx-card overflow-hidden border-l-4 ${colorClasses[color as keyof typeof colorClasses] || 'border-gray-500 bg-[#F8F8F8]'}`}>
      <div
        className="px-6 py-4 cursor-pointer hover:bg-[#F8F8F8] transition-colors flex items-center justify-between"
        onClick={onToggle}
      >
        <div className="flex items-center space-x-4">
          <div className={`w-12 h-12 rounded-lg flex items-center justify-center text-white shadow-sm ${gradientClasses[color as keyof typeof gradientClasses]}`}>
            {icon}
          </div>
          <div>
            <h3 className="text-lg font-semibold text-[#5E5873]">{title}</h3>
            <p className="text-sm text-[#B9B9C3]">{count} élément{count !== 1 ? 's' : ''}</p>
          </div>
        </div>
        <div className="flex items-center space-x-3">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onAdd();
            }}
            className="px-4 py-2 bg-[#7367F0] hover:bg-[#5E50EE] text-white rounded-lg text-sm font-medium transition-colors"
          >
            {buttonLabel}
          </button>
          <svg
            className={`w-5 h-5 text-[#6E6B7B] transition-transform ${isExpanded ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>
      {isExpanded && (
        <div className="px-6 py-4 border-t border-[#EBE9F1]">
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
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Nature</label>
              <input
                value={formData.nature || ''}
                onChange={(e) => setFormData({...formData, nature: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Salaire, rente, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Périodicité</label>
              <input
                value={formData.periodicite || ''}
                onChange={(e) => setFormData({...formData, periodicite: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Mensuel, annuel, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Montant (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant || ''}
                onChange={(e) => setFormData({...formData, montant: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
          </>
        );

      case 'passif':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Nature</label>
              <input
                value={formData.nature || ''}
                onChange={(e) => setFormData({...formData, nature: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Crédit immobilier, personnel, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Prêteur</label>
              <input
                value={formData.preteur || ''}
                onChange={(e) => setFormData({...formData, preteur: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Périodicité</label>
              <input
                value={formData.periodicite || ''}
                onChange={(e) => setFormData({...formData, periodicite: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Montant remboursement (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant_remboursement || ''}
                onChange={(e) => setFormData({...formData, montant_remboursement: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Capital restant dû (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.capital_restant_du || ''}
                onChange={(e) => setFormData({...formData, capital_restant_du: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Durée restante (mois)</label>
              <input
                type="number"
                min="0"
                value={formData.duree_restante || ''}
                onChange={(e) => setFormData({...formData, duree_restante: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
          </>
        );

      case 'actif':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Nature</label>
              <input
                value={formData.nature || ''}
                onChange={(e) => setFormData({...formData, nature: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Assurance-vie, PEA, PER, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Établissement</label>
              <input
                value={formData.etablissement || ''}
                onChange={(e) => setFormData({...formData, etablissement: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Détenteur</label>
              <input
                value={formData.detenteur || ''}
                onChange={(e) => setFormData({...formData, detenteur: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Date ouverture/souscription</label>
              <input
                type="date"
                value={formData.date_ouverture_souscription || ''}
                onChange={(e) => setFormData({...formData, date_ouverture_souscription: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Valeur actuelle (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_actuelle || ''}
                onChange={(e) => setFormData({...formData, valeur_actuelle: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
          </>
        );

      case 'bien':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Désignation</label>
              <input
                value={formData.designation || ''}
                onChange={(e) => setFormData({...formData, designation: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Résidence principale, appartement locatif, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Détenteur</label>
              <input
                value={formData.detenteur || ''}
                onChange={(e) => setFormData({...formData, detenteur: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Forme propriété</label>
              <input
                value={formData.forme_propriete || ''}
                onChange={(e) => setFormData({...formData, forme_propriete: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Pleine propriété, indivision, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Valeur actuelle estimée (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_actuelle_estimee || ''}
                onChange={(e) => setFormData({...formData, valeur_actuelle_estimee: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Année acquisition</label>
              <input
                type="number"
                min="1900"
                max={new Date().getFullYear()}
                value={formData.annee_acquisition || ''}
                onChange={(e) => setFormData({...formData, annee_acquisition: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Valeur acquisition (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_acquisition || ''}
                onChange={(e) => setFormData({...formData, valeur_acquisition: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
          </>
        );

      case 'epargne':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Désignation</label>
              <input
                value={formData.designation || ''}
                onChange={(e) => setFormData({...formData, designation: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Or, crypto, œuvres d'art, etc."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Détenteur</label>
              <input
                value={formData.detenteur || ''}
                onChange={(e) => setFormData({...formData, detenteur: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Valeur (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur || ''}
                onChange={(e) => setFormData({...formData, valeur: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
          </>
        );

      case 'sante':
        return (
          <>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Contrat en place</label>
              <input
                value={formData.contrat_en_place || ''}
                onChange={(e) => setFormData({...formData, contrat_en_place: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Budget mensuel maximum (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.budget_mensuel_maximum || ''}
                onChange={(e) => setFormData({...formData, budget_mensuel_maximum: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div className="md:col-span-2 border-t border-[#EBE9F1] pt-3">
              <h5 className="font-semibold text-[#5E5873] mb-3">Niveaux de garantie (échelle 0-10)</h5>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Hospitalisation</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_hospitalisation ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_hospitalisation: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Chambre particulière</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_chambre_particuliere ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_chambre_particuliere: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Médecin généraliste</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_medecin_generaliste ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_medecin_generaliste: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Analyses/Imagerie</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_analyses_imagerie ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_analyses_imagerie: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Auxiliaires médicaux</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_auxiliaires_medicaux ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_auxiliaires_medicaux: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Pharmacie</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_pharmacie ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_pharmacie: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Dentaire</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_dentaire ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_dentaire: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Optique</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_optique ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_optique: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#6E6B7B] mb-1">Prothèses auditives</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={formData.niveau_protheses_auditives ?? ''}
                    onChange={(e) => setFormData({...formData, niveau_protheses_auditives: e.target.value})}
                    className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                  />
                </div>
              </div>
            </div>
          </>
        );

      case 'prevoyance':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Contrat en place</label>
              <input
                value={formData.contrat_en_place || ''}
                onChange={(e) => setFormData({...formData, contrat_en_place: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Date effet</label>
              <input
                type="date"
                value={formData.date_effet || ''}
                onChange={(e) => setFormData({...formData, date_effet: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Cotisations (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.cotisations || ''}
                onChange={(e) => setFormData({...formData, cotisations: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Revenu à garantir (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.revenu_a_garantir || ''}
                onChange={(e) => setFormData({...formData, revenu_a_garantir: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Capital décès souhaité (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.capital_deces_souhaite || ''}
                onChange={(e) => setFormData({...formData, capital_deces_souhaite: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Garanties obsèques</label>
              <input
                value={formData.garanties_obseques || ''}
                onChange={(e) => setFormData({...formData, garanties_obseques: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Rente enfants</label>
              <input
                value={formData.rente_enfants || ''}
                onChange={(e) => setFormData({...formData, rente_enfants: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Rente conjoint</label>
              <input
                value={formData.rente_conjoint || ''}
                onChange={(e) => setFormData({...formData, rente_conjoint: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Payeur</label>
              <input
                value={formData.payeur || ''}
                onChange={(e) => setFormData({...formData, payeur: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div className="flex items-center">
              <input
                type="checkbox"
                checked={formData.souhaite_couverture_invalidite || false}
                onChange={(e) => setFormData({...formData, souhaite_couverture_invalidite: e.target.checked})}
                className="mr-2"
              />
              <label className="text-sm text-[#5E5873]">Souhaite couverture invalidité</label>
            </div>
          </>
        );

      case 'retraite':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Revenus annuels (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.revenus_annuels || ''}
                onChange={(e) => setFormData({...formData, revenus_annuels: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Revenus annuels foyer (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.revenus_annuels_foyer || ''}
                onChange={(e) => setFormData({...formData, revenus_annuels_foyer: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Impôt revenu (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.impot_revenu || ''}
                onChange={(e) => setFormData({...formData, impot_revenu: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Nombre parts fiscales</label>
              <input
                type="number"
                min="0"
                step="0.5"
                value={formData.nombre_parts_fiscales || ''}
                onChange={(e) => setFormData({...formData, nombre_parts_fiscales: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">TMI (Tranche marginale d'imposition)</label>
              <input
                value={formData.tmi || ''}
                onChange={(e) => setFormData({...formData, tmi: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                placeholder="Ex: 30%"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Âge départ retraite</label>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.age_depart_retraite || ''}
                onChange={(e) => setFormData({...formData, age_depart_retraite: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Âge départ retraite conjoint</label>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.age_depart_retraite_conjoint || ''}
                onChange={(e) => setFormData({...formData, age_depart_retraite_conjoint: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">% revenu à maintenir</label>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.pourcentage_revenu_a_maintenir || ''}
                onChange={(e) => setFormData({...formData, pourcentage_revenu_a_maintenir: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Cotisations annuelles (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.cotisations_annuelles || ''}
                onChange={(e) => setFormData({...formData, cotisations_annuelles: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Contrat en place</label>
              <input
                value={formData.contrat_en_place || ''}
                onChange={(e) => setFormData({...formData, contrat_en_place: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
          </>
        );

      case 'bae_epargne':
        return (
          <>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Montant épargne disponible (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant_epargne_disponible || ''}
                onChange={(e) => setFormData({...formData, montant_epargne_disponible: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Capacité épargne estimée (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.capacite_epargne_estimee || ''}
                onChange={(e) => setFormData({...formData, capacite_epargne_estimee: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Actifs financiers total (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.actifs_financiers_total || ''}
                onChange={(e) => setFormData({...formData, actifs_financiers_total: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Actifs financiers %</label>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.actifs_financiers_pourcentage || ''}
                onChange={(e) => setFormData({...formData, actifs_financiers_pourcentage: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Actifs immo total (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.actifs_immo_total || ''}
                onChange={(e) => setFormData({...formData, actifs_immo_total: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Actifs immo %</label>
              <input
                type="number"
                min="0"
                max="100"
                value={formData.actifs_immo_pourcentage || ''}
                onChange={(e) => setFormData({...formData, actifs_immo_pourcentage: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Passifs total emprunts (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.passifs_total_emprunts || ''}
                onChange={(e) => setFormData({...formData, passifs_total_emprunts: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Charges totales (€)</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.charges_totales || ''}
                onChange={(e) => setFormData({...formData, charges_totales: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
              />
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-[#5E5873] mb-1">Situation financière revenus/charges</label>
              <textarea
                value={formData.situation_financiere_revenus_charges || ''}
                onChange={(e) => setFormData({...formData, situation_financiere_revenus_charges: e.target.value})}
                className="w-full px-3 py-2 border border-[#D8D6DE] rounded-lg focus:ring-2 focus:ring-[#7367F0]"
                rows={3}
              />
            </div>
            <div className="flex items-center">
              <input
                type="checkbox"
                checked={formData.epargne_disponible || false}
                onChange={(e) => setFormData({...formData, epargne_disponible: e.target.checked})}
                className="mr-2"
              />
              <label className="text-sm text-[#5E5873]">Épargne disponible</label>
            </div>
          </>
        );

      default:
        return null;
    }
  };

  return (
    <div className="fixed inset-0 backdrop-blur-md bg-black/20 flex items-center justify-center z-50" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-xl font-bold text-[#5E5873]">
            {data ? 'Modifier' : 'Ajouter'} {
              type === 'revenu' ? 'un revenu' :
              type === 'passif' ? 'un passif' :
              type === 'actif' ? 'un actif financier' :
              type === 'bien' ? 'un bien immobilier' :
              type === 'epargne' ? 'une épargne' :
              type === 'sante' ? 'Santé / Souhait' :
              type === 'prevoyance' ? 'BAE Prévoyance' :
              type === 'retraite' ? 'BAE Retraite' :
              type === 'bae_epargne' ? 'BAE Épargne' :
              'un élément'
            }
          </h3>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-[#6E6B7B]"
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
              className="flex-1 bg-gradient-to-r from-[#7367F0] to-[#9055FD] hover:from-[#5E50EE] hover:to-[#7E3FF2] text-white px-6 py-3 rounded-lg font-semibold"
            >
              {data ? 'Modifier' : 'Ajouter'}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="px-6 py-3 bg-[#F3F2F7] hover:bg-[#F3F2F7] text-[#5E5873] rounded-lg font-semibold"
            >
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
