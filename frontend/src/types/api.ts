export interface AudioResponse {
  message: string;
  client: Record<string, any>;
  analysis: Record<string, any>;
}

export interface Client {
  id: number;
  nom: string;
  prenom: string;
  nom_complet: string; // Nouveau: calculé par le backend
  email?: string;
  telephone?: string;
  adresse?: string;
  code_postal?: string;
  ville?: string;
  date_naissance?: string;
  lieu_naissance?: string;
  nationalite?: string;
  profession?: string;
  situation_familiale?: string;
  regime_matrimonial?: string;
  nombre_enfants?: number;

  // Relations (toujours présentes, peuvent être null)
  conjoint?: Conjoint | null;
  enfants?: Enfant[];
  sante_souhait?: SanteSouhait | null;
  bae_prevoyance?: BaePrevoyance | null;
  bae_retraite?: BaeRetraite | null;
  bae_epargne?: BaeEpargne | null;
  revenus?: ClientRevenu[];
  passifs?: ClientPassif[];
  actifs_financiers?: ClientActifFinancier[];
  biens_immobiliers?: ClientBienImmobilier[];
  autres_epargnes?: ClientAutreEpargne[];

  created_at: string; // Format ISO
  updated_at: string; // Format ISO
}

export interface Conjoint {
  id: number;
  nom?: string;
  nom_jeune_fille?: string;
  prenom?: string;
  nom_complet: string; // Calculé par le backend
  date_naissance?: string;
  lieu_naissance?: string;
  nationalite?: string;
  profession?: string;
  situation_actuelle_statut?: string;
  telephone?: string;
  adresse?: string;
  created_at: string;
  updated_at: string;
}

export interface Enfant {
  id: number;
  prenom: string;
  nom?: string;
  nom_complet: string; // Nouveau: calculé par le backend
  date_naissance: string;
  age: number | null; // Nouveau: calculé par le backend
  fiscalement_a_charge?: boolean;
  garde_alternee?: boolean;
  created_at: string;
  updated_at: string;
}

export interface SanteSouhait {
  id: number;
  client_id: number;
  contrat_en_place?: string | null;
  budget_mensuel_maximum?: number | null;
  niveau_hospitalisation?: number | null;
  niveau_chambre_particuliere?: number | null;
  niveau_medecin_generaliste?: number | null;
  niveau_analyses_imagerie?: number | null;
  niveau_auxiliaires_medicaux?: number | null;
  niveau_pharmacie?: number | null;
  niveau_dentaire?: number | null;
  niveau_optique?: number | null;
  niveau_protheses_auditives?: number | null;
  created_at: string;
  updated_at: string;
}

export interface BaePrevoyance {
  id: number;
  client_id: number;
  contrat_en_place?: string | null;
  date_effet?: string | null;
  cotisations?: number | null;
  souhaite_couverture_invalidite?: boolean | null;
  revenu_a_garantir?: number | null;
  souhaite_couvrir_charges_professionnelles?: boolean | null;
  montant_annuel_charges_professionnelles?: number | null;
  garantir_totalite_charges_professionnelles?: boolean | null;
  montant_charges_professionnelles_a_garantir?: number | null;
  duree_indemnisation_souhaitee?: string | null;
  capital_deces_souhaite?: number | null;
  garanties_obseques?: string | null;
  rente_enfants?: string | null;
  rente_conjoint?: string | null;
  payeur?: string | null;
  created_at: string;
  updated_at: string;
}

export interface BaeRetraite {
  id: number;
  client_id: number;
  revenus_annuels?: number | null;
  revenus_annuels_foyer?: number | null;
  impot_revenu?: number | null;
  nombre_parts_fiscales?: number | null;
  tmi?: string | null;
  impot_paye_n_1?: number | null;
  age_depart_retraite?: number | null;
  age_depart_retraite_conjoint?: number | null;
  pourcentage_revenu_a_maintenir?: number | null;
  contrat_en_place?: string | null;
  bilan_retraite_disponible?: boolean | null;
  complementaire_retraite_mise_en_place?: boolean | null;
  designation_etablissement?: string | null;
  cotisations_annuelles?: number | null;
  titulaire?: string | null;
  created_at: string;
  updated_at: string;
}

export interface BaeEpargne {
  id: number;
  client_id: number;
  epargne_disponible?: boolean | null;
  montant_epargne_disponible?: number | null;
  donation_realisee?: boolean | null;
  donation_forme?: string | null;
  donation_date?: string | null;
  donation_montant?: number | null;
  donation_beneficiaires?: string | null;
  capacite_epargne_estimee?: number | null;
  actifs_financiers_pourcentage?: number | null;
  actifs_financiers_total?: number | null;
  actifs_financiers_details?: any | null; // JSON field
  actifs_immo_pourcentage?: number | null;
  actifs_immo_total?: number | null;
  actifs_immo_details?: any | null; // JSON field
  actifs_autres_pourcentage?: number | null;
  actifs_autres_total?: number | null;
  actifs_autres_details?: any | null; // JSON field
  passifs_total_emprunts?: number | null;
  passifs_details?: any | null; // JSON field
  charges_totales?: number | null;
  charges_details?: any | null; // JSON field
  situation_financiere_revenus_charges?: string | null;
  created_at: string;
  updated_at: string;
}

export interface ClientActifFinancier {
  id: number;
  client_id: number;
  nature?: string | null;
  etablissement?: string | null;
  detenteur?: string | null;
  date_ouverture_souscription?: string | null;
  valeur_actuelle?: number | null;
  created_at: string;
  updated_at: string;
}

export interface ClientAutreEpargne {
  id: number;
  client_id: number;
  designation?: string | null;
  detenteur?: string | null;
  valeur?: number | null;
  created_at: string;
  updated_at: string;
}

export interface ClientBienImmobilier {
  id: number;
  client_id: number;
  designation?: string | null;
  detenteur?: string | null;
  forme_propriete?: string | null;
  valeur_actuelle_estimee?: number | null;
  annee_acquisition?: number | null;
  valeur_acquisition?: number | null;
  created_at: string;
  updated_at: string;
}

export interface ClientPassif {
  id: number;
  client_id: number;
  nature?: string | null;
  preteur?: string | null;
  periodicite?: string | null;
  montant_remboursement?: number | null;
  capital_restant_du?: number | null;
  duree_restante?: number | null;
  created_at: string;
  updated_at: string;
}

export interface ClientRevenu {
  id: number;
  client_id: number;
  nature?: string | null;
  periodicite?: string | null;
  montant?: number | null;
  created_at: string;
  updated_at: string;
}

export interface AudioRecord {
  id: number;
  status: 'pending' | 'done' | 'failed';
  path: string;
  transcription?: string | null;
  error_message?: string | null;
  processed_at?: string | null;
  client_id?: number | null;
  created_at: string;
  updated_at: string;
}
