import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";

interface Conjoint {
  id: number;
  nom: string;
  nom_jeune_fille?: string;
  prenom: string;
  datedenaissance?: string;
  lieudenaissance?: string;
  nationalite?: string;
  profession?: string;
  chef_entreprise?: string;
  situation_actuelle_statut?: string;
  date_evenement_professionnel?: string;
  risques_professionnels?: boolean;
  details_risques_professionnels?: string;
  telephone?: string;
  adresse?: string;
}

interface Enfant {
  id: number;
  nom: string;
  prenom: string;
  datedenaissance?: string;
  fiscalement_a_charge?: boolean;
  garde_alternee?: boolean;
}

interface Entreprise {
  id: number;
  chef_entreprise?: boolean;
  statut?: string;
  travailleur_independant?: boolean;
  mandataire_social?: boolean;
}

interface SanteSouhait {
  id: number;
  contrat_en_place?: string;
  budget_mensuel_maximum?: number;
  niveau_hospitalisation?: number;
  niveau_chambre_particuliere?: number;
  niveau_medecin_generaliste?: number;
  niveau_analyses_imagerie?: number;
  niveau_auxiliaires_medicaux?: number;
  niveau_pharmacie?: number;
  niveau_dentaire?: number;
  niveau_optique?: number;
  niveau_protheses_auditives?: number;
}

interface Client {
  id: number;
  // Identité de base
  civilite?: string;
  nom: string;
  nom_jeune_fille?: string;
  prenom: string;
  datedenaissance?: string;
  lieudenaissance?: string;
  nationalite?: string;

  // Situation
  situationmatrimoniale?: string;
  date_situation_matrimoniale?: string;
  situation_actuelle?: string;

  // Professionnel
  profession?: string;
  date_evenement_professionnel?: string;
  risques_professionnels?: boolean;
  details_risques_professionnels?: string;
  revenusannuels?: number;

  // Coordonnées
  adresse?: string;
  code_postal?: string;
  ville?: string;
  residence_fiscale?: string;
  telephone?: string;
  email?: string;

  // Mode de vie
  fumeur?: boolean;
  activites_sportives?: boolean;
  details_activites_sportives?: string;
  niveau_activites_sportives?: string;

  // Famille
  nombreenfants?: number;

  // Besoins
  besoins?: string[] | null;

  // Autres
  transcription_path?: string;
  consentement_audio?: boolean;
  charge_clientele?: string;

  // Relations
  conjoint?: Conjoint;
  enfants?: Enfant[];
  entreprise?: Entreprise;
  santeSouhait?: SanteSouhait;

  // Timestamps
  created_at?: string;
  updated_at?: string;
}

const ClientDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [client, setClient] = useState<Client | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchClient = async () => {
    try {
      const res = await api.get(`/clients/${id}`);
      setClient(res.data);
    } catch (err) {
      console.error("Erreur lors du chargement du client :", err);
      toast.error("Erreur lors du chargement du client");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchClient();
  }, [id]);

  const handleDelete = async () => {
    if (!confirm("Voulez-vous vraiment supprimer ce client ?")) return;

    try {
      await api.delete(`/clients/${id}`);
      toast.success("Client supprimé avec succès");
      setTimeout(() => navigate("/clients"), 1000);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la suppression du client");
    }
  };

  const handleExportPDF = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${import.meta.env.VITE_API_URL}/clients/${id}/export/pdf`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error('Erreur lors de l\'export PDF');
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `fiche_client_${client?.nom}_${client?.prenom}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success("PDF téléchargé avec succès");
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de l'export PDF");
    }
  };

  const handleExportWord = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${import.meta.env.VITE_API_URL}/clients/${id}/export/word`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error('Erreur lors de l\'export Word');
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `fiche_client_${client?.nom}_${client?.prenom}.docx`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success("Document Word téléchargé avec succès");
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de l'export Word");
    }
  };

  if (loading) return <div className="text-center mt-10">Chargement...</div>;
  if (!client) return <div className="text-center mt-10">Client introuvable.</div>;

  const formatDate = (date?: string) => {
    if (!date) return "Non renseigné";
    return new Date(date).toLocaleDateString("fr-FR");
  };

  const formatCurrency = (amount?: number) => {
    if (!amount) return "Non renseigné";
    return new Intl.NumberFormat("fr-FR", {
      style: "currency",
      currency: "EUR",
    }).format(amount);
  };

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="p-6 max-w-4xl mx-auto bg-white shadow-md rounded-lg">
        <div className="flex justify-between items-start mb-6">
          <div>
            <h1 className="text-3xl font-bold text-gray-800">
              {client.civilite && `${client.civilite} `}{client.prenom} {client.nom?.toUpperCase()}
            </h1>
            <p className="text-sm text-gray-500 mt-1">
              Client #{client.id} • Dernière mise à jour : {formatDate(client.updated_at)}
            </p>
          </div>
          <div className="flex gap-2 flex-wrap">
            <button
              onClick={() => navigate(`/clients/${client.id}/edit`)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-all flex items-center space-x-2"
            >
              <svg
                className="w-4 h-4"
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
              <span>Éditer</span>
            </button>
            <button
              onClick={handleExportPDF}
              className="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-4 py-2 rounded-lg transition-all flex items-center space-x-2 shadow-md hover:shadow-lg"
            >
              <svg
                className="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                />
              </svg>
              <span>PDF</span>
            </button>
            <button
              onClick={handleExportWord}
              className="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white px-4 py-2 rounded-lg transition-all flex items-center space-x-2 shadow-md hover:shadow-lg"
            >
              <svg
                className="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              <span>Word</span>
            </button>
            <button
              onClick={handleDelete}
              className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-all"
            >
              🗑️ Supprimer
            </button>
          </div>
        </div>

        {/* État Civil */}
        <div className="bg-gray-50 p-4 rounded-lg mb-4">
          <h3 className="font-semibold text-gray-700 mb-3">📋 État Civil</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
            {client.nom_jeune_fille && (
              <p>
                <strong>Nom de jeune fille :</strong> {client.nom_jeune_fille}
              </p>
            )}
            <p>
              <strong>Date de naissance :</strong> {formatDate(client.datedenaissance)}
            </p>
            <p>
              <strong>Lieu de naissance :</strong> {client.lieudenaissance || "Non renseigné"}
            </p>
            <p>
              <strong>Nationalité :</strong> {client.nationalite || "Non renseigné"}
            </p>
            <p>
              <strong>Situation matrimoniale :</strong>{" "}
              {client.situationmatrimoniale || "Non renseigné"}
            </p>
            {client.date_situation_matrimoniale && (
              <p>
                <strong>Date :</strong> {formatDate(client.date_situation_matrimoniale)}
              </p>
            )}
            <p>
              <strong>Situation actuelle :</strong> {client.situation_actuelle || "Non renseigné"}
            </p>
            <p>
              <strong>Nombre d'enfants :</strong> {client.nombreenfants ?? "Non renseigné"}
            </p>
          </div>
        </div>

        {/* Coordonnées */}
        <div className="bg-gray-50 p-4 rounded-lg mb-4">
          <h3 className="font-semibold text-gray-700 mb-3">📞 Coordonnées</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <p>
              <strong>Adresse :</strong> {client.adresse || "Non renseignée"}
            </p>
            <p>
              <strong>Code postal :</strong> {client.code_postal || "Non renseigné"}
            </p>
            <p>
              <strong>Ville :</strong> {client.ville || "Non renseignée"}
            </p>
            <p>
              <strong>Résidence fiscale :</strong> {client.residence_fiscale || "Non renseignée"}
            </p>
            <p>
              <strong>Téléphone :</strong> {client.telephone || "Non renseigné"}
            </p>
            <p>
              <strong>Email :</strong> {client.email || "Non renseigné"}
            </p>
          </div>
        </div>

        {/* Informations professionnelles */}
        <div className="bg-gray-50 p-4 rounded-lg mb-4">
          <h3 className="font-semibold text-gray-700 mb-3">💼 Informations professionnelles</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <p>
              <strong>Profession :</strong> {client.profession || "Non renseignée"}
            </p>
            {client.date_evenement_professionnel && (
              <p>
                <strong>Date événement professionnel :</strong>{" "}
                {formatDate(client.date_evenement_professionnel)}
              </p>
            )}
            <p>
              <strong>Revenus annuels :</strong> {formatCurrency(client.revenusannuels)}
            </p>
            <p>
              <strong>Risques professionnels :</strong>{" "}
              {client.risques_professionnels ? "Oui" : "Non"}
            </p>
            {client.details_risques_professionnels && (
              <p className="col-span-2">
                <strong>Détails des risques :</strong> {client.details_risques_professionnels}
              </p>
            )}
          </div>
        </div>

        {/* Mode de vie */}
        <div className="bg-gray-50 p-4 rounded-lg mb-4">
          <h3 className="font-semibold text-gray-700 mb-3">🏃 Mode de vie</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <p>
              <strong>Fumeur :</strong> {client.fumeur ? "Oui" : "Non"}
            </p>
            <p>
              <strong>Activités sportives :</strong>{" "}
              {client.activites_sportives ? "Oui" : "Non"}
            </p>
            {client.details_activites_sportives && (
              <p>
                <strong>Détails des activités :</strong> {client.details_activites_sportives}
              </p>
            )}
            {client.niveau_activites_sportives && (
              <p>
                <strong>Niveau :</strong> {client.niveau_activites_sportives}
              </p>
            )}
          </div>
        </div>

        {/* Autres informations */}
        {(client.charge_clientele || client.consentement_audio) && (
          <div className="bg-gray-50 p-4 rounded-lg mb-4">
            <h3 className="font-semibold text-gray-700 mb-3">ℹ️ Autres informations</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              {client.charge_clientele && (
                <p>
                  <strong>Charge clientèle :</strong> {client.charge_clientele}
                </p>
              )}
              {client.consentement_audio !== undefined && (
                <p>
                  <strong>Consentement audio :</strong>{" "}
                  {client.consentement_audio ? "Oui" : "Non"}
                </p>
              )}
            </div>
          </div>
        )}

        {/* Conjoint */}
        {client.conjoint && (
          <div className="bg-purple-50 p-4 rounded-lg mb-4">
            <h3 className="font-semibold text-gray-700 mb-3">💑 Conjoint</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
              <p>
                <strong>Nom :</strong> {client.conjoint.nom}
              </p>
              <p>
                <strong>Prénom :</strong> {client.conjoint.prenom}
              </p>
              {client.conjoint.nom_jeune_fille && (
                <p>
                  <strong>Nom de jeune fille :</strong> {client.conjoint.nom_jeune_fille}
                </p>
              )}
              {client.conjoint.datedenaissance && (
                <p>
                  <strong>Date de naissance :</strong>{" "}
                  {formatDate(client.conjoint.datedenaissance)}
                </p>
              )}
              {client.conjoint.lieudenaissance && (
                <p>
                  <strong>Lieu de naissance :</strong> {client.conjoint.lieudenaissance}
                </p>
              )}
              {client.conjoint.nationalite && (
                <p>
                  <strong>Nationalité :</strong> {client.conjoint.nationalite}
                </p>
              )}
              {client.conjoint.profession && (
                <p>
                  <strong>Profession :</strong> {client.conjoint.profession}
                </p>
              )}
              {client.conjoint.telephone && (
                <p>
                  <strong>Téléphone :</strong> {client.conjoint.telephone}
                </p>
              )}
              {client.conjoint.adresse && (
                <p className="col-span-2">
                  <strong>Adresse :</strong> {client.conjoint.adresse}
                </p>
              )}
              {client.conjoint.risques_professionnels && (
                <p>
                  <strong>Risques professionnels :</strong> Oui
                </p>
              )}
              {client.conjoint.details_risques_professionnels && (
                <p className="col-span-2">
                  <strong>Détails des risques :</strong>{" "}
                  {client.conjoint.details_risques_professionnels}
                </p>
              )}
            </div>
          </div>
        )}

        {/* Enfants */}
        {client.enfants && client.enfants.length > 0 && (
          <div className="bg-green-50 p-4 rounded-lg mb-4">
            <h3 className="font-semibold text-gray-700 mb-3">
              👶 Enfants ({client.enfants.length})
            </h3>
            <div className="space-y-3">
              {client.enfants.map((enfant, index) => (
                <div
                  key={enfant.id}
                  className="bg-white p-3 rounded border border-green-200"
                >
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
                    <p>
                      <strong>Enfant {index + 1} :</strong> {enfant.prenom} {enfant.nom}
                    </p>
                    {enfant.datedenaissance && (
                      <p>
                        <strong>Date de naissance :</strong>{" "}
                        {formatDate(enfant.datedenaissance)}
                      </p>
                    )}
                    <p>
                      <strong>À charge fiscalement :</strong>{" "}
                      {enfant.fiscalement_a_charge ? "Oui" : "Non"}
                    </p>
                    {enfant.garde_alternee && (
                      <p>
                        <strong>Garde alternée :</strong> Oui
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Entreprise */}
        {client.entreprise && (
          <div className="bg-orange-50 p-4 rounded-lg mb-4">
            <h3 className="font-semibold text-gray-700 mb-3">🏢 Entreprise</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <p>
                <strong>Chef d'entreprise :</strong>{" "}
                {client.entreprise.chef_entreprise ? "Oui" : "Non"}
              </p>
              {client.entreprise.statut && (
                <p>
                  <strong>Statut :</strong> {client.entreprise.statut}
                </p>
              )}
              <p>
                <strong>Travailleur indépendant :</strong>{" "}
                {client.entreprise.travailleur_independant ? "Oui" : "Non"}
              </p>
              <p>
                <strong>Mandataire social :</strong>{" "}
                {client.entreprise.mandataire_social ? "Oui" : "Non"}
              </p>
            </div>
          </div>
        )}

        {/* Santé - Souhaits */}
        {client.santeSouhait && (
          <div className="bg-red-50 p-4 rounded-lg mb-4">
            <h3 className="font-semibold text-gray-700 mb-3">❤️ Santé - Souhaits</h3>
            <div className="space-y-3">
              {client.santeSouhait.contrat_en_place && (
                <p className="text-sm">
                  <strong>Contrat en place :</strong> {client.santeSouhait.contrat_en_place}
                </p>
              )}
              {client.santeSouhait.budget_mensuel_maximum && (
                <p className="text-sm">
                  <strong>Budget mensuel maximum :</strong>{" "}
                  {formatCurrency(client.santeSouhait.budget_mensuel_maximum)}
                </p>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
                {client.santeSouhait.niveau_hospitalisation !== undefined &&
                  client.santeSouhait.niveau_hospitalisation !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Hospitalisation</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_hospitalisation}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_chambre_particuliere !== undefined &&
                  client.santeSouhait.niveau_chambre_particuliere !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Chambre particulière</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_chambre_particuliere}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_medecin_generaliste !== undefined &&
                  client.santeSouhait.niveau_medecin_generaliste !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Médecin généraliste</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_medecin_generaliste}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_analyses_imagerie !== undefined &&
                  client.santeSouhait.niveau_analyses_imagerie !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Analyses & imagerie</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_analyses_imagerie}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_auxiliaires_medicaux !== undefined &&
                  client.santeSouhait.niveau_auxiliaires_medicaux !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Auxiliaires médicaux</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_auxiliaires_medicaux}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_pharmacie !== undefined &&
                  client.santeSouhait.niveau_pharmacie !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Pharmacie</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_pharmacie}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_dentaire !== undefined &&
                  client.santeSouhait.niveau_dentaire !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Dentaire</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_dentaire}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_optique !== undefined &&
                  client.santeSouhait.niveau_optique !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Optique</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_optique}/10
                      </p>
                    </div>
                  )}
                {client.santeSouhait.niveau_protheses_auditives !== undefined &&
                  client.santeSouhait.niveau_protheses_auditives !== null && (
                    <div className="bg-white p-2 rounded">
                      <p className="text-xs text-gray-600">Prothèses auditives</p>
                      <p className="font-semibold">
                        {client.santeSouhait.niveau_protheses_auditives}/10
                      </p>
                    </div>
                  )}
              </div>
            </div>
          </div>
        )}

        {client.besoins && client.besoins.length > 0 && (
          <div className="bg-blue-50 p-4 rounded-lg mb-6">
            <h3 className="font-semibold text-gray-700 mb-3">🎯 Besoins exprimés</h3>
            <div className="flex flex-wrap gap-2">
              {client.besoins.map((besoin, index) => (
                <span
                  key={index}
                  className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200"
                >
                  {besoin}
                </span>
              ))}
            </div>
          </div>
        )}
      </div>
    </>
  );
};

export default ClientDetailPage;
