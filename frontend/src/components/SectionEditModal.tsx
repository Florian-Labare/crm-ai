import React, { useState, useEffect } from "react";
import { X, Save, Loader2, User, MapPin, Briefcase, Heart, Building2, Users, Baby, Coins, Stethoscope, Shield, Clock, FileText, ExternalLink } from "lucide-react";
import api from "../api/apiClient";
import { toast } from "react-toastify";

export type SectionType =
  | "etat_civil"
  | "coordonnees"
  | "professionnel"
  | "mode_vie"
  | "entreprise"
  | "conjoint"
  | "enfant"
  | "revenu"
  | "passif"
  | "actif"
  | "bien"
  | "epargne"
  | "sante"
  | "prevoyance"
  | "retraite"
  | "contrat";

interface SectionEditModalProps {
  sectionType: SectionType;
  initialData?: any;
  clientId: number;
  onClose: () => void;
  onSaved: () => void;
  onRefresh?: () => void; // Refresh sans fermer la modal
  isNew?: boolean;
}

const sectionConfig: Record<SectionType, { title: string; icon: React.ReactNode }> = {
  etat_civil: { title: "État Civil", icon: <User size={20} /> },
  coordonnees: { title: "Coordonnées", icon: <MapPin size={20} /> },
  professionnel: { title: "Informations Professionnelles", icon: <Briefcase size={20} /> },
  mode_vie: { title: "Mode de Vie", icon: <Heart size={20} /> },
  entreprise: { title: "Informations Entreprise", icon: <Building2 size={20} /> },
  conjoint: { title: "Conjoint", icon: <Users size={20} /> },
  enfant: { title: "Enfant", icon: <Baby size={20} /> },
  revenu: { title: "Revenu", icon: <Coins size={20} /> },
  passif: { title: "Passif / Emprunt", icon: <Coins size={20} /> },
  actif: { title: "Actif Financier", icon: <Coins size={20} /> },
  bien: { title: "Bien Immobilier", icon: <Building2 size={20} /> },
  epargne: { title: "Autre Épargne", icon: <Coins size={20} /> },
  sante: { title: "Santé", icon: <Stethoscope size={20} /> },
  prevoyance: { title: "Prévoyance", icon: <Shield size={20} /> },
  retraite: { title: "Retraite", icon: <Clock size={20} /> },
  contrat: { title: "Contrat", icon: <FileText size={20} /> },
};

interface Assureur {
  id: number;
  nom: string;
  lien_espace_client: string | null;
}

export const SectionEditModal: React.FC<SectionEditModalProps> = ({
  sectionType,
  initialData,
  clientId,
  onClose,
  onSaved,
  onRefresh,
  isNew = false,
}) => {
  const [formData, setFormData] = useState<any>(initialData || {});
  const [saving, setSaving] = useState(false);
  const [assureurs, setAssureurs] = useState<Assureur[]>([]);
  const [lienEspaceClient, setLienEspaceClient] = useState('');
  const [savingLien, setSavingLien] = useState(false);

  useEffect(() => {
    setFormData(initialData || {});
  }, [initialData]);

  useEffect(() => {
    if (sectionType === "contrat") {
      api.get("/assureurs").then((res) => {
        setAssureurs(res.data?.data || []);
      }).catch(() => {});
    }
  }, [sectionType]);

  // Sync lienEspaceClient when assureur changes
  useEffect(() => {
    if (sectionType === "contrat") {
      const selected = assureurs.find((a) => a.id === Number(formData.assureur_id));
      setLienEspaceClient(selected?.lien_espace_client || '');
    }
  }, [formData.assureur_id, assureurs, sectionType]);

  const handleSaveLien = async (assureurId: number) => {
    setSavingLien(true);
    try {
      const res = await api.patch(`/assureurs/${assureurId}/lien`, { lien_espace_client: lienEspaceClient || null });
      // Update local assureurs list so the link is immediately reflected
      setAssureurs((prev) => prev.map((a) => a.id === assureurId ? { ...a, lien_espace_client: res.data.data.lien_espace_client } : a));
      onRefresh?.();
      toast.success('Lien mis à jour');
    } catch {
      toast.error('Erreur lors de la mise à jour du lien');
    } finally {
      setSavingLien(false);
    }
  };

  const handleChange = (field: string, value: any) => {
    setFormData((prev: any) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async () => {
    setSaving(true);
    try {
      // Construire les données selon le type de section
      let endpoint = "";
      let method: "put" | "post" = "put";
      let payload = formData;

      switch (sectionType) {
        case "etat_civil":
        case "coordonnees":
        case "professionnel":
        case "mode_vie":
        case "entreprise":
          endpoint = `/clients/${clientId}`;
          break;
        case "conjoint":
          endpoint = `/clients/${clientId}/conjoint`;
          method = initialData?.id ? "put" : "post";
          break;
        case "enfant":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/enfants`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/enfants/${initialData.id}`;
            method = "put";
          }
          break;
        case "revenu":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/revenus`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/revenus/${initialData.id}`;
            method = "put";
          }
          break;
        case "passif":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/passifs`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/passifs/${initialData.id}`;
            method = "put";
          }
          break;
        case "actif":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/actifs-financiers`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/actifs-financiers/${initialData.id}`;
            method = "put";
          }
          break;
        case "bien":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/biens-immobiliers`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/biens-immobiliers/${initialData.id}`;
            method = "put";
          }
          break;
        case "epargne":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/autres-epargnes`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/autres-epargnes/${initialData.id}`;
            method = "put";
          }
          break;
        case "sante":
          endpoint = `/clients/${clientId}/sante-souhait`;
          method = initialData?.id ? "put" : "post";
          break;
        case "prevoyance":
          endpoint = `/clients/${clientId}/bae-prevoyance`;
          method = initialData?.id ? "put" : "post";
          break;
        case "retraite":
          endpoint = `/clients/${clientId}/bae-retraite`;
          method = initialData?.id ? "put" : "post";
          break;
        case "contrat":
          if (isNew || !initialData?.id) {
            endpoint = `/clients/${clientId}/contrats`;
            method = "post";
          } else {
            endpoint = `/clients/${clientId}/contrats/${initialData.id}`;
            method = "put";
          }
          break;
      }

      if (method === "post") {
        await api.post(endpoint, payload);
      } else {
        await api.put(endpoint, payload);
      }

      // Pour les contrats : sauvegarder aussi le lien espace client de l'assureur
      if (sectionType === "contrat" && formData.assureur_id) {
        await api.patch(`/assureurs/${formData.assureur_id}/lien`, {
          lien_espace_client: lienEspaceClient || null,
        });
      }

      toast.success("Modifications enregistrées");
      onSaved();
      onClose();
    } catch (error: any) {
      console.error("Erreur lors de la sauvegarde:", error);
      toast.error(error.response?.data?.message || "Erreur lors de l'enregistrement");
    } finally {
      setSaving(false);
    }
  };

  const config = sectionConfig[sectionType];

  const renderForm = () => {
    switch (sectionType) {
      case "etat_civil":
        return (
          <>
            <FormField label="Civilité">
              <select
                value={formData.civilite || ""}
                onChange={(e) => handleChange("civilite", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="Monsieur">Monsieur</option>
                <option value="Madame">Madame</option>
              </select>
            </FormField>
            <FormField label="Nom">
              <input
                type="text"
                value={formData.nom || ""}
                onChange={(e) => handleChange("nom", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Nom de jeune fille">
              <input
                type="text"
                value={formData.nom_jeune_fille || ""}
                onChange={(e) => handleChange("nom_jeune_fille", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Prénom">
              <input
                type="text"
                value={formData.prenom || ""}
                onChange={(e) => handleChange("prenom", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Date de naissance">
              <input
                type="date"
                value={formData.date_naissance?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_naissance", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Lieu de naissance">
              <input
                type="text"
                value={formData.lieu_naissance || ""}
                onChange={(e) => handleChange("lieu_naissance", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Nationalité">
              <input
                type="text"
                value={formData.nationalite || ""}
                onChange={(e) => handleChange("nationalite", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Situation matrimoniale">
              <select
                value={formData.situation_matrimoniale || ""}
                onChange={(e) => handleChange("situation_matrimoniale", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="Célibataire">Célibataire</option>
                <option value="Marié(e)">Marié(e)</option>
                <option value="Pacsé(e)">Pacsé(e)</option>
                <option value="Divorcé(e)">Divorcé(e)</option>
                <option value="Veuf(ve)">Veuf(ve)</option>
                <option value="Concubinage">Concubinage</option>
              </select>
            </FormField>
            <FormField label="Date de situation matrimoniale">
              <input
                type="date"
                value={formData.date_situation_matrimoniale?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_situation_matrimoniale", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "coordonnees":
        return (
          <>
            <FormField label="Adresse" fullWidth>
              <input
                type="text"
                value={formData.adresse || ""}
                onChange={(e) => handleChange("adresse", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Code postal">
              <input
                type="text"
                value={formData.code_postal || ""}
                onChange={(e) => handleChange("code_postal", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Ville">
              <input
                type="text"
                value={formData.ville || ""}
                onChange={(e) => handleChange("ville", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Téléphone">
              <input
                type="tel"
                value={formData.telephone || ""}
                onChange={(e) => handleChange("telephone", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Email">
              <input
                type="email"
                value={formData.email || ""}
                onChange={(e) => handleChange("email", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Résidence fiscale">
              <input
                type="text"
                value={formData.residence_fiscale || ""}
                onChange={(e) => handleChange("residence_fiscale", e.target.value)}
                className="form-input"
                placeholder="France"
              />
            </FormField>
          </>
        );

      case "professionnel":
        return (
          <>
            <FormField label="Profession">
              <input
                type="text"
                value={formData.profession || ""}
                onChange={(e) => handleChange("profession", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Situation actuelle">
              <select
                value={formData.situation_actuelle || ""}
                onChange={(e) => handleChange("situation_actuelle", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="En activité">En activité</option>
                <option value="Sans emploi">Sans emploi</option>
                <option value="Retraité">Retraité</option>
                <option value="Étudiant">Étudiant</option>
              </select>
            </FormField>
            <FormField label="Statut">
              <select
                value={formData.statut || ""}
                onChange={(e) => handleChange("statut", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="Salarié">Salarié</option>
                <option value="TNS">TNS</option>
                <option value="Fonctionnaire">Fonctionnaire</option>
                <option value="Profession libérale">Profession libérale</option>
                <option value="Dirigeant">Dirigeant</option>
                <option value="Retraité">Retraité</option>
              </select>
            </FormField>
            <FormField label="Revenus annuels (€)">
              <input
                type="number"
                min="0"
                value={formData.revenus_annuels || ""}
                onChange={(e) => handleChange("revenus_annuels", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Date événement professionnel">
              <input
                type="date"
                value={formData.date_evenement_professionnel?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_evenement_professionnel", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Risques professionnels">
              <div className="flex items-center gap-4 h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.risques_professionnels || false}
                    onChange={(e) => handleChange("risques_professionnels", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Oui</span>
                </label>
              </div>
            </FormField>
            {formData.risques_professionnels && (
              <FormField label="Détails des risques" fullWidth>
                <textarea
                  value={formData.details_risques_professionnels || ""}
                  onChange={(e) => handleChange("details_risques_professionnels", e.target.value)}
                  className="form-input min-h-[80px]"
                />
              </FormField>
            )}
          </>
        );

      case "mode_vie":
        return (
          <>
            <FormField label="Fumeur">
              <div className="flex items-center gap-4 h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.fumeur || false}
                    onChange={(e) => handleChange("fumeur", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Oui</span>
                </label>
              </div>
            </FormField>
            <FormField label="Activités sportives">
              <div className="flex items-center gap-4 h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.activites_sportives || false}
                    onChange={(e) => handleChange("activites_sportives", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Oui</span>
                </label>
              </div>
            </FormField>
            {formData.activites_sportives && (
              <>
                <FormField label="Détails des activités">
                  <input
                    type="text"
                    value={formData.details_activites_sportives || ""}
                    onChange={(e) => handleChange("details_activites_sportives", e.target.value)}
                    className="form-input"
                    placeholder="Football, natation..."
                  />
                </FormField>
                <FormField label="Niveau">
                  <select
                    value={formData.niveau_activites_sportives || ""}
                    onChange={(e) => handleChange("niveau_activites_sportives", e.target.value)}
                    className="form-input"
                  >
                    <option value="">Sélectionner...</option>
                    <option value="Loisir">Loisir</option>
                    <option value="Amateur">Amateur</option>
                    <option value="Compétition">Compétition</option>
                    <option value="Professionnel">Professionnel</option>
                  </select>
                </FormField>
              </>
            )}
          </>
        );

      case "entreprise":
        return (
          <>
            <FormField label="Chef d'entreprise">
              <div className="flex items-center gap-4 h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.chef_entreprise || false}
                    onChange={(e) => handleChange("chef_entreprise", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Oui</span>
                </label>
              </div>
            </FormField>
            <FormField label="Travailleur indépendant">
              <div className="flex items-center gap-4 h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.travailleur_independant || false}
                    onChange={(e) => handleChange("travailleur_independant", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Oui</span>
                </label>
              </div>
            </FormField>
            <FormField label="Mandataire social">
              <div className="flex items-center gap-4 h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.mandataire_social || false}
                    onChange={(e) => handleChange("mandataire_social", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Oui</span>
                </label>
              </div>
            </FormField>
          </>
        );

      case "conjoint":
        return (
          <>
            <FormField label="Nom">
              <input
                type="text"
                value={formData.nom || ""}
                onChange={(e) => handleChange("nom", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Nom de jeune fille">
              <input
                type="text"
                value={formData.nom_jeune_fille || ""}
                onChange={(e) => handleChange("nom_jeune_fille", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Prénom">
              <input
                type="text"
                value={formData.prenom || ""}
                onChange={(e) => handleChange("prenom", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Date de naissance">
              <input
                type="date"
                value={formData.date_naissance?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_naissance", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Lieu de naissance">
              <input
                type="text"
                value={formData.lieu_naissance || ""}
                onChange={(e) => handleChange("lieu_naissance", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Nationalité">
              <input
                type="text"
                value={formData.nationalite || ""}
                onChange={(e) => handleChange("nationalite", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Profession">
              <input
                type="text"
                value={formData.profession || ""}
                onChange={(e) => handleChange("profession", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Statut">
              <select
                value={formData.statut || ""}
                onChange={(e) => handleChange("statut", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="Salarié">Salarié</option>
                <option value="TNS">TNS</option>
                <option value="Fonctionnaire">Fonctionnaire</option>
                <option value="Profession libérale">Profession libérale</option>
                <option value="Dirigeant">Dirigeant</option>
                <option value="Retraité">Retraité</option>
                <option value="Sans emploi">Sans emploi</option>
              </select>
            </FormField>
            <FormField label="Téléphone">
              <input
                type="tel"
                value={formData.telephone || ""}
                onChange={(e) => handleChange("telephone", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Adresse" fullWidth>
              <input
                type="text"
                value={formData.adresse || ""}
                onChange={(e) => handleChange("adresse", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "enfant":
        return (
          <>
            <FormField label="Prénom">
              <input
                type="text"
                value={formData.prenom || ""}
                onChange={(e) => handleChange("prenom", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Nom">
              <input
                type="text"
                value={formData.nom || ""}
                onChange={(e) => handleChange("nom", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Date de naissance">
              <input
                type="date"
                value={formData.date_naissance?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_naissance", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Options" fullWidth>
              <div className="flex flex-wrap gap-6 h-[46px] items-center">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.fiscalement_a_charge || false}
                    onChange={(e) => handleChange("fiscalement_a_charge", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Fiscalement à charge</span>
                </label>
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.garde_alternee || false}
                    onChange={(e) => handleChange("garde_alternee", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Garde alternée</span>
                </label>
              </div>
            </FormField>
          </>
        );

      case "revenu":
        return (
          <>
            <FormField label="Nature">
              <input
                type="text"
                value={formData.nature || ""}
                onChange={(e) => handleChange("nature", e.target.value)}
                className="form-input"
                placeholder="Salaire, rente, etc."
              />
            </FormField>
            <FormField label="Périodicité">
              <select
                value={formData.periodicite || ""}
                onChange={(e) => handleChange("periodicite", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="Mensuel">Mensuel</option>
                <option value="Trimestriel">Trimestriel</option>
                <option value="Semestriel">Semestriel</option>
                <option value="Annuel">Annuel</option>
              </select>
            </FormField>
            <FormField label="Montant (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant || ""}
                onChange={(e) => handleChange("montant", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "passif":
        return (
          <>
            <FormField label="Nature">
              <input
                type="text"
                value={formData.nature || ""}
                onChange={(e) => handleChange("nature", e.target.value)}
                className="form-input"
                placeholder="Crédit immobilier, personnel, etc."
              />
            </FormField>
            <FormField label="Prêteur">
              <input
                type="text"
                value={formData.preteur || ""}
                onChange={(e) => handleChange("preteur", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Périodicité">
              <select
                value={formData.periodicite || ""}
                onChange={(e) => handleChange("periodicite", e.target.value)}
                className="form-input"
              >
                <option value="">Sélectionner...</option>
                <option value="Mensuel">Mensuel</option>
                <option value="Trimestriel">Trimestriel</option>
                <option value="Annuel">Annuel</option>
              </select>
            </FormField>
            <FormField label="Montant remboursement (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant_remboursement || ""}
                onChange={(e) => handleChange("montant_remboursement", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Capital restant dû (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.capital_restant_du || ""}
                onChange={(e) => handleChange("capital_restant_du", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Durée restante (mois)">
              <input
                type="number"
                min="0"
                value={formData.duree_restante || ""}
                onChange={(e) => handleChange("duree_restante", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "actif":
        return (
          <>
            <FormField label="Nature">
              <input
                type="text"
                value={formData.nature || ""}
                onChange={(e) => handleChange("nature", e.target.value)}
                className="form-input"
                placeholder="Assurance-vie, PEA, PER, etc."
              />
            </FormField>
            <FormField label="Établissement">
              <input
                type="text"
                value={formData.etablissement || ""}
                onChange={(e) => handleChange("etablissement", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Détenteur">
              <input
                type="text"
                value={formData.detenteur || ""}
                onChange={(e) => handleChange("detenteur", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Date ouverture/souscription">
              <input
                type="date"
                value={formData.date_ouverture_souscription?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_ouverture_souscription", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Valeur actuelle (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_actuelle || ""}
                onChange={(e) => handleChange("valeur_actuelle", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "bien":
        return (
          <>
            <FormField label="Désignation">
              <input
                type="text"
                value={formData.designation || ""}
                onChange={(e) => handleChange("designation", e.target.value)}
                className="form-input"
                placeholder="Résidence principale, appartement locatif, etc."
              />
            </FormField>
            <FormField label="Détenteur">
              <input
                type="text"
                value={formData.detenteur || ""}
                onChange={(e) => handleChange("detenteur", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Forme propriété">
              <input
                type="text"
                value={formData.forme_propriete || ""}
                onChange={(e) => handleChange("forme_propriete", e.target.value)}
                className="form-input"
                placeholder="Pleine propriété, indivision, etc."
              />
            </FormField>
            <FormField label="Valeur actuelle estimée (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_actuelle_estimee || ""}
                onChange={(e) => handleChange("valeur_actuelle_estimee", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Année acquisition">
              <input
                type="number"
                min="1900"
                max={new Date().getFullYear()}
                value={formData.annee_acquisition || ""}
                onChange={(e) => handleChange("annee_acquisition", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Valeur acquisition (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur_acquisition || ""}
                onChange={(e) => handleChange("valeur_acquisition", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "epargne":
        return (
          <>
            <FormField label="Désignation">
              <input
                type="text"
                value={formData.designation || ""}
                onChange={(e) => handleChange("designation", e.target.value)}
                className="form-input"
                placeholder="Or, crypto, œuvres d'art, etc."
              />
            </FormField>
            <FormField label="Détenteur">
              <input
                type="text"
                value={formData.detenteur || ""}
                onChange={(e) => handleChange("detenteur", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Valeur (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.valeur || ""}
                onChange={(e) => handleChange("valeur", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "sante":
        return (
          <>
            <FormField label="Contrat en place" fullWidth>
              <input
                type="text"
                value={formData.contrat_en_place || ""}
                onChange={(e) => handleChange("contrat_en_place", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Budget mensuel maximum (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.budget_mensuel_maximum || ""}
                onChange={(e) => handleChange("budget_mensuel_maximum", e.target.value)}
                className="form-input"
              />
            </FormField>
            <div className="col-span-2 border-t border-[#EBE9F1] pt-4 mt-2">
              <h4 className="text-sm font-semibold text-[#5E5873] mb-4">Niveaux de garantie (0-10)</h4>
              <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                {[
                  { key: "niveau_hospitalisation", label: "Hospitalisation" },
                  { key: "niveau_chambre_particuliere", label: "Chambre particulière" },
                  { key: "niveau_medecin_generaliste", label: "Médecin généraliste" },
                  { key: "niveau_analyses_imagerie", label: "Analyses/Imagerie" },
                  { key: "niveau_auxiliaires_medicaux", label: "Auxiliaires médicaux" },
                  { key: "niveau_pharmacie", label: "Pharmacie" },
                  { key: "niveau_dentaire", label: "Dentaire" },
                  { key: "niveau_optique", label: "Optique" },
                  { key: "niveau_protheses_auditives", label: "Prothèses auditives" },
                ].map(({ key, label }) => (
                  <div key={key}>
                    <label className="block text-xs text-[#6E6B7B] mb-1">{label}</label>
                    <input
                      type="number"
                      min="0"
                      max="10"
                      value={formData[key] ?? ""}
                      onChange={(e) => handleChange(key, e.target.value)}
                      className="form-input"
                    />
                  </div>
                ))}
              </div>
            </div>
          </>
        );

      case "prevoyance":
        return (
          <>
            <FormField label="Contrat en place">
              <input
                type="text"
                value={formData.contrat_en_place || ""}
                onChange={(e) => handleChange("contrat_en_place", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Date effet">
              <input
                type="date"
                value={formData.date_effet?.split("T")[0] || ""}
                onChange={(e) => handleChange("date_effet", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Cotisations (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.cotisations || ""}
                onChange={(e) => handleChange("cotisations", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Revenu à garantir (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.revenu_a_garantir || ""}
                onChange={(e) => handleChange("revenu_a_garantir", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Capital décès souhaité (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.capital_deces_souhaite || ""}
                onChange={(e) => handleChange("capital_deces_souhaite", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Garanties obsèques">
              <input
                type="text"
                value={formData.garanties_obseques || ""}
                onChange={(e) => handleChange("garanties_obseques", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Rente enfants">
              <input
                type="text"
                value={formData.rente_enfants || ""}
                onChange={(e) => handleChange("rente_enfants", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Rente conjoint">
              <input
                type="text"
                value={formData.rente_conjoint || ""}
                onChange={(e) => handleChange("rente_conjoint", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Couverture invalidité" fullWidth>
              <div className="flex items-center h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.souhaite_couverture_invalidite || false}
                    onChange={(e) => handleChange("souhaite_couverture_invalidite", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Souhaite une couverture invalidité</span>
                </label>
              </div>
            </FormField>
            <FormField label="Durée d'indemnisation souhaitée">
              <input
                type="text"
                placeholder="Ex: 3 ans, jusqu'à la retraite..."
                value={formData.duree_indemnisation_souhaitee || ""}
                onChange={(e) => handleChange("duree_indemnisation_souhaitee", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Payeur">
              <input
                type="text"
                placeholder="Qui paie les cotisations ?"
                value={formData.payeur || ""}
                onChange={(e) => handleChange("payeur", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Couvrir charges professionnelles" fullWidth>
              <div className="flex items-center h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.souhaite_couvrir_charges_professionnelles || false}
                    onChange={(e) => handleChange("souhaite_couvrir_charges_professionnelles", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Souhaite couvrir les charges professionnelles</span>
                </label>
              </div>
            </FormField>
            <FormField label="Montant annuel charges pro (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant_annuel_charges_professionnelles || ""}
                onChange={(e) => handleChange("montant_annuel_charges_professionnelles", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Garantir totalité charges pro" fullWidth>
              <div className="flex items-center h-[46px]">
                <label className="inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.garantir_totalite_charges_professionnelles || false}
                    onChange={(e) => handleChange("garantir_totalite_charges_professionnelles", e.target.checked)}
                    className="w-4 h-4 text-[#7367F0] border-[#D8D6DE] rounded focus:ring-[#7367F0]"
                  />
                  <span className="ml-2 text-sm text-[#5E5873]">Garantir la totalité des charges</span>
                </label>
              </div>
            </FormField>
            <FormField label="Montant charges pro à garantir (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.montant_charges_professionnelles_a_garantir || ""}
                onChange={(e) => handleChange("montant_charges_professionnelles_a_garantir", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "retraite":
        return (
          <>
            <FormField label="Revenus annuels (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.revenus_annuels || ""}
                onChange={(e) => handleChange("revenus_annuels", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Revenus annuels foyer (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.revenus_annuels_foyer || ""}
                onChange={(e) => handleChange("revenus_annuels_foyer", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Impôt revenu (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.impot_revenu || ""}
                onChange={(e) => handleChange("impot_revenu", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Nombre parts fiscales">
              <input
                type="number"
                min="0"
                step="0.5"
                value={formData.nombre_parts_fiscales || ""}
                onChange={(e) => handleChange("nombre_parts_fiscales", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="TMI">
              <input
                type="text"
                value={formData.tmi || ""}
                onChange={(e) => handleChange("tmi", e.target.value)}
                className="form-input"
                placeholder="Ex: 30%"
              />
            </FormField>
            <FormField label="Âge départ retraite">
              <input
                type="number"
                min="0"
                max="100"
                value={formData.age_depart_retraite || ""}
                onChange={(e) => handleChange("age_depart_retraite", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Âge départ retraite conjoint">
              <input
                type="number"
                min="0"
                max="100"
                value={formData.age_depart_retraite_conjoint || ""}
                onChange={(e) => handleChange("age_depart_retraite_conjoint", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="% revenu à maintenir">
              <input
                type="number"
                min="0"
                max="100"
                value={formData.pourcentage_revenu_a_maintenir || ""}
                onChange={(e) => handleChange("pourcentage_revenu_a_maintenir", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Cotisations annuelles (€)">
              <input
                type="number"
                min="0"
                step="0.01"
                value={formData.cotisations_annuelles || ""}
                onChange={(e) => handleChange("cotisations_annuelles", e.target.value)}
                className="form-input"
              />
            </FormField>
            <FormField label="Contrat en place">
              <input
                type="text"
                value={formData.contrat_en_place || ""}
                onChange={(e) => handleChange("contrat_en_place", e.target.value)}
                className="form-input"
              />
            </FormField>
          </>
        );

      case "contrat": {
        const contratType = formData.type as string;
        const selectedAssureur = assureurs.find((a) => a.id === Number(formData.assureur_id));
        const showMensualite = ['sante', 'prevoyance'].includes(contratType);
        const showEnCours = ['per', 'assurance_vie'].includes(contratType);
        const showFondEuro = ['per', 'assurance_vie'].includes(contratType);
        const showUc = ['per', 'assurance_vie'].includes(contratType);
        const showVersement = ['per', 'assurance_vie', 'vie_entiere'].includes(contratType);
        const fromBordereau = !!(initialData?.numero_contrat);
        const bordereau = fromBordereau ? (
          <span className="ml-2 text-xs font-semibold px-1.5 py-0.5 rounded bg-[#28C76F]/10 text-[#28C76F]">bordereau</span>
        ) : null;

        const typeLabels: Record<string, string> = {
          sante: 'Santé',
          prevoyance: 'Prévoyance',
          per: 'PER',
          assurance_vie: 'Assurance Vie',
          emprunteur: 'Emprunteur',
          vie_entiere: 'Vie Entière',
        };

        return (
          <>
            <FormField label="Type de contrat" fullWidth>
              <div className="px-3 py-2.5 bg-[#F3F2F7] rounded-lg text-sm font-semibold text-[#5E5873] flex items-center gap-2">
                {typeLabels[contratType] || contratType}
                {bordereau}
              </div>
            </FormField>

            {initialData?.numero_contrat && (
              <FormField label="Numéro de contrat" fullWidth>
                <div className="px-3 py-2.5 bg-[#F3F2F7] rounded-lg text-sm font-mono text-[#6E6B7B]">
                  {initialData.numero_contrat}
                </div>
              </FormField>
            )}

            {initialData?.produit && (
              <FormField label="Produit" fullWidth>
                <div className="px-3 py-2.5 bg-[#F3F2F7] rounded-lg text-sm text-[#6E6B7B]">
                  {initialData.produit}
                </div>
              </FormField>
            )}

            <FormField label="Assureur" fullWidth>
              <select
                value={formData.assureur_id || ""}
                onChange={(e) => handleChange("assureur_id", e.target.value ? Number(e.target.value) : null)}
                className="form-input"
              >
                <option value="">Aucun assureur</option>
                {assureurs.map((a) => (
                  <option key={a.id} value={a.id}>{a.nom}</option>
                ))}
              </select>
            </FormField>

            {selectedAssureur && (
              <FormField label="Lien espace client" fullWidth>
                <div className="flex gap-2">
                  <input
                    type="url"
                    value={lienEspaceClient}
                    onChange={(e) => setLienEspaceClient(e.target.value)}
                    placeholder="https://extranet.swisslife.fr/..."
                    className="form-input flex-1"
                  />
                  <button
                    type="button"
                    onClick={() => handleSaveLien(selectedAssureur.id)}
                    disabled={savingLien}
                    className="px-3 py-2 rounded-lg bg-[#7367F0] text-white text-sm font-medium hover:bg-[#6558E8] disabled:opacity-50 transition-colors flex-shrink-0"
                  >
                    {savingLien ? '...' : 'Enregistrer'}
                  </button>
                </div>
                {lienEspaceClient && (
                  <a
                    href={lienEspaceClient}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-[#7367F0] hover:underline"
                  >
                    <ExternalLink size={12} />
                    Ouvrir l'espace assureur
                  </a>
                )}
              </FormField>
            )}

            {showMensualite && (
              <FormField label={<span>Mensualité (€){fromBordereau && formData.mensualite ? bordereau : null}</span>}>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.mensualite ?? ""}
                  onChange={(e) => handleChange("mensualite", e.target.value)}
                  className="form-input"
                />
              </FormField>
            )}

            {showEnCours && (
              <FormField label={<span>En-cours (€){fromBordereau && formData.en_cours ? bordereau : null}</span>}>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.en_cours ?? ""}
                  onChange={(e) => handleChange("en_cours", e.target.value)}
                  className="form-input"
                />
              </FormField>
            )}

            {showFondEuro && (
              <FormField label={<span>Fonds euro (€){fromBordereau && formData.fond_euro ? bordereau : null}</span>}>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.fond_euro ?? ""}
                  onChange={(e) => handleChange("fond_euro", e.target.value)}
                  className="form-input"
                />
              </FormField>
            )}

            {showUc && (
              <FormField label={<span>Unités de compte (€){fromBordereau && formData.uc ? bordereau : null}</span>}>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.uc ?? ""}
                  onChange={(e) => handleChange("uc", e.target.value)}
                  className="form-input"
                />
              </FormField>
            )}

            {showVersement && (
              <FormField label={<span>Versement programmé (€){fromBordereau && formData.versement_programme ? bordereau : null}</span>}>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={formData.versement_programme ?? ""}
                  onChange={(e) => handleChange("versement_programme", e.target.value)}
                  className="form-input"
                />
              </FormField>
            )}
          </>
        );
      }

      default:
        return null;
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-8"
      style={{ backgroundColor: "rgba(94, 88, 115, 0.4)", backdropFilter: "blur(4px)" }}
      onClick={onClose}
    >
      {/* Modal */}
      <div
        className="bg-white rounded-xl shadow-2xl w-full max-w-[800px] max-h-[90vh] flex flex-col animate-modalSlideIn"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 border-b border-[#EBE9F1] flex items-center justify-between flex-shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white">
              {config.icon}
            </div>
            <div>
              <h2 className="text-xl font-bold text-[#5E5873]">
                {isNew ? `Ajouter un ${config.title.toLowerCase()}` : `Modifier ${config.title}`}
              </h2>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-9 h-9 rounded-lg flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200 hover:rotate-90"
          >
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="p-6 overflow-y-auto flex-1 custom-scrollbar">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">{renderForm()}</div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-[#EBE9F1] flex items-center justify-end gap-3 flex-shrink-0 bg-[#F8F8F8]">
          <button
            onClick={onClose}
            className="px-5 py-2.5 text-[#6E6B7B] font-semibold hover:text-[#5E5873] transition-colors"
          >
            Annuler
          </button>
          <button
            onClick={handleSubmit}
            disabled={saving}
            className={`
              inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold transition-all duration-200
              ${
                !saving
                  ? "bg-[#7367F0] text-white shadow-[0_2px_8px_rgba(115,103,240,0.3)] hover:bg-[#5E50EE] hover:shadow-[0_4px_12px_rgba(115,103,240,0.4)] hover:-translate-y-0.5"
                  : "bg-[#F3F2F7] text-[#B9B9C3] cursor-not-allowed"
              }
            `}
          >
            {saving ? (
              <>
                <Loader2 size={16} className="animate-spin" />
                Enregistrement...
              </>
            ) : (
              <>
                <Save size={16} />
                Enregistrer
              </>
            )}
          </button>
        </div>
      </div>

      {/* Animations CSS */}
      <style>{`
        @keyframes modalSlideIn {
          from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
          }
          to {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }

        .animate-modalSlideIn {
          animation: modalSlideIn 0.3s ease-out;
        }

        .custom-scrollbar::-webkit-scrollbar {
          width: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
          background: #F3F2F7;
          border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: #EBE9F1;
          border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
          background: #7367F0;
        }

        .form-input {
          width: 100%;
          padding: 0.75rem 1rem;
          border: 1px solid #EBE9F1;
          border-radius: 0.5rem;
          font-size: 0.9375rem;
          color: #5E5873;
          background: white;
          transition: all 0.2s;
        }

        .form-input:focus {
          outline: none;
          border-color: #7367F0;
          box-shadow: 0 0 0 3px rgba(115, 103, 240, 0.1);
        }

        .form-input::placeholder {
          color: #B9B9C3;
        }
      `}</style>
    </div>
  );
};

// Composant helper pour les champs de formulaire
const FormField: React.FC<{
  label: React.ReactNode;
  children: React.ReactNode;
  fullWidth?: boolean;
}> = ({ label, children, fullWidth }) => (
  <div className={fullWidth ? "md:col-span-2" : ""}>
    <label className="block text-sm font-semibold text-[#5E5873] mb-2 flex items-center">{label}</label>
    {children}
  </div>
);

export default SectionEditModal;
