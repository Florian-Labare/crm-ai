import React from 'react';
import { InfoCard, InfoItem, StatCard } from './InfoCard';
import {
  UserIcon,
  MapPinIcon,
  BriefcaseIcon,
  HeartIcon,
  UsersIcon,
  BuildingIcon,
  ActivityIcon,
  PhoneIcon,
  MailIcon,
  CalendarIcon,
  DollarSignIcon,
  TrendingUpIcon,
  ShieldIcon,
  InfoIcon,
} from './icons/IconSet';

interface ClientInfoSectionProps {
  client: any; // Type à ajuster selon votre interface Client
  formatDate: (date?: string) => string;
  formatCurrency: (amount?: number) => string;
}

export const ClientInfoSection: React.FC<ClientInfoSectionProps> = ({
  client,
  formatDate,
  formatCurrency,
}) => {
  // Helper pour vérifier si un besoin est présent
  const hasBesoin = (besoinKeywords: string[]): boolean => {
    if (!client.besoins || !Array.isArray(client.besoins)) return false;
    return client.besoins.some((besoin: string) =>
      besoinKeywords.some((keyword) =>
        besoin.toLowerCase().includes(keyword.toLowerCase())
      )
    );
  };

  // Déterminer si les sections doivent être affichées (besoin détecté OU données existantes)
  const showSante = hasBesoin(['santé', 'sante', 'mutuelle', 'complémentaire', 'complementaire']) || client.sante_souhait;
  const showPrevoyance = hasBesoin(['prévoyance', 'prevoyance', 'décès', 'deces', 'invalidité', 'invalidite']) || client.bae_prevoyance;
  const showRetraite = hasBesoin(['retraite', 'per', 'pension']) || client.bae_retraite;
  const showEpargne = hasBesoin(['épargne', 'epargne', 'placement', 'investissement', 'patrimoine']) || client.bae_epargne;

  // Calcul des revenus annuels depuis toutes les sources possibles
  const calculateRevenusAnnuels = (): number | null => {
    // 1. Si revenus_annuels direct sur client
    if (client.revenus_annuels && client.revenus_annuels > 0) {
      return client.revenus_annuels;
    }

    // 2. Depuis bae_retraite (revenus fiscaux)
    if (client.bae_retraite?.revenus_annuels && client.bae_retraite.revenus_annuels > 0) {
      return client.bae_retraite.revenus_annuels;
    }

    // 3. Calculer depuis la table revenus (somme annualisée)
    if (client.revenus && Array.isArray(client.revenus) && client.revenus.length > 0) {
      let totalAnnuel = 0;
      for (const revenu of client.revenus) {
        if (revenu.montant) {
          const montant = Number(revenu.montant);
          const periodicite = (revenu.periodicite || '').toLowerCase();

          if (periodicite.includes('annuel')) {
            totalAnnuel += montant;
          } else if (periodicite.includes('mensuel')) {
            totalAnnuel += montant * 12;
          } else if (periodicite.includes('trimestr')) {
            totalAnnuel += montant * 4;
          } else if (periodicite.includes('semest')) {
            totalAnnuel += montant * 2;
          } else {
            // Par défaut, considérer comme mensuel
            totalAnnuel += montant * 12;
          }
        }
      }
      if (totalAnnuel > 0) {
        return totalAnnuel;
      }
    }

    return null;
  };

  // Calcul du nombre de membres de la famille
  const calculateFamilyMembers = (): number => {
    let count = 1; // Le client lui-même

    // Ajouter le conjoint s'il existe
    if (client.conjoint && (client.conjoint.nom || client.conjoint.prenom)) {
      count += 1;
    }

    // Ajouter les enfants
    if (client.enfants && Array.isArray(client.enfants)) {
      count += client.enfants.length;
    } else if (client.nombre_enfants && client.nombre_enfants > 0) {
      count += client.nombre_enfants;
    }

    return count;
  };

  const revenusAnnuels = calculateRevenusAnnuels();
  const familyMembers = calculateFamilyMembers();

  // Calcul des statistiques clés
  const stats = [
    {
      label: 'Âge',
      value: client.date_naissance
        ? `${new Date().getFullYear() - new Date(client.date_naissance).getFullYear()} ans`
        : 'N/A',
      icon: <CalendarIcon size={24} />,
      color: 'blue' as const,
    },
    {
      label: 'Revenus annuels',
      value: revenusAnnuels ? formatCurrency(revenusAnnuels) : 'N/A',
      icon: <DollarSignIcon size={24} />,
      color: 'green' as const,
    },
    {
      label: 'Besoins',
      value: client.besoins?.length || 0,
      icon: <TrendingUpIcon size={24} />,
      color: 'purple' as const,
    },
    {
      label: 'Famille',
      value: `${familyMembers} membre${familyMembers > 1 ? 's' : ''}`,
      icon: <UsersIcon size={24} />,
      color: 'orange' as const,
    },
  ];

  return (
    <div className="space-y-6">
      {/* Statistiques clés */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat, index) => (
          <StatCard key={index} {...stat} />
        ))}
      </div>

      {/* Carte État Civil */}
      <InfoCard
        title="État Civil"
        icon={<UserIcon size={20} />}
        color="blue"
        badge={client.civilite || undefined}
      >
        <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
          <InfoItem label="Nom complet" value={`${client.prenom} ${client.nom?.toUpperCase()}`} />
          {client.nom_jeune_fille && (
            <InfoItem label="Nom de jeune fille" value={client.nom_jeune_fille} />
          )}
          <InfoItem
            label="Date de naissance"
            value={formatDate(client.date_naissance)}
            icon={<CalendarIcon size={14} />}
          />
          <InfoItem label="Lieu de naissance" value={client.lieu_naissance} />
          <InfoItem label="Nationalité" value={client.nationalite} />
          <InfoItem label="Situation matrimoniale" value={client.situation_matrimoniale} />
          {client.date_situation_matrimoniale && (
            <InfoItem
              label="Date situation"
              value={formatDate(client.date_situation_matrimoniale)}
              icon={<CalendarIcon size={14} />}
            />
          )}
          <InfoItem label="Situation actuelle" value={client.situation_actuelle} />
        </dl>
      </InfoCard>

      {/* Carte Coordonnées */}
      <InfoCard title="Coordonnées" icon={<MapPinIcon size={20} />} color="indigo">
        <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
          <InfoItem
            label="Adresse complète"
            value={
              client.adresse && client.code_postal && client.ville
                ? `${client.adresse}, ${client.code_postal} ${client.ville}`
                : client.adresse || 'Non renseignée'
            }
            icon={<MapPinIcon size={14} />}
            fullWidth
          />
          <InfoItem
            label="Téléphone"
            value={client.telephone}
            icon={<PhoneIcon size={14} />}
          />
          <InfoItem
            label="Email"
            value={
              client.email ? (
                <a
                  href={`mailto:${client.email}`}
                  className="text-indigo-600 hover:text-indigo-800 hover:underline transition"
                >
                  {client.email}
                </a>
              ) : (
                'Non renseigné'
              )
            }
            icon={<MailIcon size={14} />}
          />
          <InfoItem label="Résidence fiscale" value={client.residence_fiscale} />
        </dl>
      </InfoCard>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Carte Professionnel */}
        <InfoCard title="Informations Professionnelles" icon={<BriefcaseIcon size={20} />} color="purple">
          <dl className="space-y-4">
            <InfoItem label="Profession" value={client.profession} />
            <InfoItem
              label="Revenus annuels"
              value={formatCurrency(client.revenus_annuels)}
              icon={<DollarSignIcon size={14} />}
            />
            {client.date_evenement_professionnel && (
              <InfoItem
                label="Date événement pro"
                value={formatDate(client.date_evenement_professionnel)}
                icon={<CalendarIcon size={14} />}
              />
            )}
            <InfoItem
              label="Risques professionnels"
              value={
                client.risques_professionnels ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Non
                  </span>
                )
              }
            />
            {client.details_risques_professionnels && (
              <InfoItem
                label="Détails des risques"
                value={client.details_risques_professionnels}
                fullWidth
              />
            )}
          </dl>
        </InfoCard>

        {/* Carte Mode de vie */}
        <InfoCard title="Mode de Vie" icon={<ActivityIcon size={20} />} color="green">
          <dl className="space-y-4">
            <InfoItem
              label="Fumeur"
              value={
                client.fumeur ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Non
                  </span>
                )
              }
            />
            <InfoItem
              label="Activités sportives"
              value={
                client.activites_sportives ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Non
                  </span>
                )
              }
            />
            {client.details_activites_sportives && (
              <InfoItem
                label="Détails des activités"
                value={client.details_activites_sportives}
              />
            )}
            {client.niveau_activites_sportives && (
              <InfoItem label="Niveau" value={client.niveau_activites_sportives} />
            )}
          </dl>
        </InfoCard>
      </div>

      {/* Carte Entreprise (si applicable) */}
      {(client.chef_entreprise || client.travailleur_independant || client.mandataire_social) && (
        <InfoCard title="Informations Entreprise" icon={<BuildingIcon size={20} />} color="orange">
          <dl className="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
            <InfoItem
              label="Chef d'entreprise"
              value={
                client.chef_entreprise ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Non
                  </span>
                )
              }
            />
            <InfoItem
              label="Travailleur indépendant"
              value={
                client.travailleur_independant ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Non
                  </span>
                )
              }
            />
            <InfoItem
              label="Mandataire social"
              value={
                client.mandataire_social ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Non
                  </span>
                )
              }
            />
            {client.statut && (
              <InfoItem label="Statut juridique" value={client.statut} fullWidth />
            )}
          </dl>
        </InfoCard>
      )}

      {/* Carte Conjoint (si applicable) */}
      {client.conjoint && (
        <InfoCard title="Conjoint" icon={<HeartIcon size={20} />} color="pink">
          <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            <InfoItem
              label="Nom complet"
              value={client.conjoint.prenom || client.conjoint.nom
                ? `${client.conjoint.prenom || ''} ${client.conjoint.nom?.toUpperCase() || ''}`.trim()
                : 'Non renseigné'}
            />
            <InfoItem
              label="Nom de jeune fille"
              value={client.conjoint.nom_jeune_fille || 'Non renseigné'}
            />
            <InfoItem
              label="Date de naissance"
              value={client.conjoint.date_naissance ? formatDate(client.conjoint.date_naissance) : 'Non renseigné'}
              icon={<CalendarIcon size={14} />}
            />
            <InfoItem
              label="Lieu de naissance"
              value={client.conjoint.lieu_naissance || 'Non renseigné'}
            />
            <InfoItem
              label="Nationalité"
              value={client.conjoint.nationalite || 'Non renseigné'}
            />
            <InfoItem
              label="Profession"
              value={client.conjoint.profession || 'Non renseigné'}
            />
            <InfoItem
              label="Situation actuelle"
              value={client.conjoint.situation_actuelle_statut || 'Non renseigné'}
            />
            <InfoItem
              label="Téléphone"
              value={client.conjoint.telephone || 'Non renseigné'}
              icon={<PhoneIcon size={14} />}
            />
            <InfoItem
              label="Adresse"
              value={client.conjoint.adresse || 'Non renseignée'}
              icon={<MapPinIcon size={14} />}
              fullWidth
            />
          </dl>
        </InfoCard>
      )}

      {/* Carte Enfants (si applicable) */}
      {client.enfants && client.enfants.length > 0 && (
        <InfoCard
          title="Enfants"
          icon={<UsersIcon size={20} />}
          color="teal"
          badge={`${client.enfants.length} enfant${client.enfants.length > 1 ? 's' : ''}`}
        >
          <div className="space-y-4">
            {client.enfants.map((enfant: any, index: number) => (
              <div
                key={enfant.id}
                className="p-4 bg-gray-50 rounded-lg border border-gray-200"
              >
                <div className="flex items-center justify-between mb-3">
                  <h4 className="font-semibold text-gray-900">
                    Enfant {index + 1}
                    {enfant.prenom && ` - ${enfant.prenom} ${enfant.nom || ''}`}
                  </h4>
                  <div className="flex space-x-2">
                    {enfant.fiscalement_a_charge && (
                      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        À charge
                      </span>
                    )}
                    {enfant.garde_alternee && (
                      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                        Garde alternée
                      </span>
                    )}
                  </div>
                </div>
                <dl className="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                  {enfant.prenom && (
                    <div>
                      <dt className="text-gray-500">Prénom</dt>
                      <dd className="font-medium text-gray-900">{enfant.prenom}</dd>
                    </div>
                  )}
                  {enfant.nom && (
                    <div>
                      <dt className="text-gray-500">Nom</dt>
                      <dd className="font-medium text-gray-900">{enfant.nom}</dd>
                    </div>
                  )}
                  {enfant.date_naissance && (
                    <div>
                      <dt className="text-gray-500">Date de naissance</dt>
                      <dd className="font-medium text-gray-900">
                        {formatDate(enfant.date_naissance)}
                      </dd>
                    </div>
                  )}
                </dl>
              </div>
            ))}
          </div>
        </InfoCard>
      )}

      {/* Carte Santé (si besoin détecté ou données existantes) */}
      {showSante && (
        <InfoCard title="Santé" icon={<HeartIcon size={20} />} color="pink">
          <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            <InfoItem
              label="Contrat en place"
              value={client.sante_souhait?.contrat_en_place || "Non renseigné"}
            />
            <InfoItem
              label="Budget mensuel maximum"
              value={client.sante_souhait?.budget_mensuel_maximum ? formatCurrency(client.sante_souhait.budget_mensuel_maximum) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Niveau hospitalisation"
              value={client.sante_souhait?.niveau_hospitalisation !== null && client.sante_souhait?.niveau_hospitalisation !== undefined ? `${client.sante_souhait.niveau_hospitalisation}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau chambre particulière"
              value={client.sante_souhait?.niveau_chambre_particuliere !== null && client.sante_souhait?.niveau_chambre_particuliere !== undefined ? `${client.sante_souhait.niveau_chambre_particuliere}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau médecin généraliste"
              value={client.sante_souhait?.niveau_medecin_generaliste !== null && client.sante_souhait?.niveau_medecin_generaliste !== undefined ? `${client.sante_souhait.niveau_medecin_generaliste}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau analyses/imagerie"
              value={client.sante_souhait?.niveau_analyses_imagerie !== null && client.sante_souhait?.niveau_analyses_imagerie !== undefined ? `${client.sante_souhait.niveau_analyses_imagerie}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau auxiliaires médicaux"
              value={client.sante_souhait?.niveau_auxiliaires_medicaux !== null && client.sante_souhait?.niveau_auxiliaires_medicaux !== undefined ? `${client.sante_souhait.niveau_auxiliaires_medicaux}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau pharmacie"
              value={client.sante_souhait?.niveau_pharmacie !== null && client.sante_souhait?.niveau_pharmacie !== undefined ? `${client.sante_souhait.niveau_pharmacie}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau dentaire"
              value={client.sante_souhait?.niveau_dentaire !== null && client.sante_souhait?.niveau_dentaire !== undefined ? `${client.sante_souhait.niveau_dentaire}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau optique"
              value={client.sante_souhait?.niveau_optique !== null && client.sante_souhait?.niveau_optique !== undefined ? `${client.sante_souhait.niveau_optique}/10` : "Non renseigné"}
            />
            <InfoItem
              label="Niveau prothèses auditives"
              value={client.sante_souhait?.niveau_protheses_auditives !== null && client.sante_souhait?.niveau_protheses_auditives !== undefined ? `${client.sante_souhait.niveau_protheses_auditives}/10` : "Non renseigné"}
            />
          </dl>
        </InfoCard>
      )}

      {/* Carte Prévoyance (si besoin détecté ou données existantes) */}
      {showPrevoyance && (
        <InfoCard title="Prévoyance" icon={<ShieldIcon size={20} />} color="orange">
          <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            <InfoItem
              label="Contrat en place"
              value={client.bae_prevoyance?.contrat_en_place || "Non renseigné"}
            />
            <InfoItem
              label="Date d'effet"
              value={client.bae_prevoyance?.date_effet ? formatDate(client.bae_prevoyance.date_effet) : "Non renseigné"}
              icon={<CalendarIcon size={14} />}
            />
            <InfoItem
              label="Cotisations mensuelles"
              value={client.bae_prevoyance?.cotisations ? formatCurrency(client.bae_prevoyance.cotisations) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Couverture invalidité"
              value={client.bae_prevoyance?.souhaite_couverture_invalidite !== null && client.bae_prevoyance?.souhaite_couverture_invalidite !== undefined ? (client.bae_prevoyance.souhaite_couverture_invalidite ? "Oui" : "Non") : "Non renseigné"}
            />
            <InfoItem
              label="Revenu à garantir"
              value={client.bae_prevoyance?.revenu_a_garantir ? formatCurrency(client.bae_prevoyance.revenu_a_garantir) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Couvrir charges pro"
              value={client.bae_prevoyance?.souhaite_couvrir_charges_professionnelles !== null && client.bae_prevoyance?.souhaite_couvrir_charges_professionnelles !== undefined ? (client.bae_prevoyance.souhaite_couvrir_charges_professionnelles ? "Oui" : "Non") : "Non renseigné"}
            />
            <InfoItem
              label="Montant charges pro (annuel)"
              value={client.bae_prevoyance?.montant_annuel_charges_professionnelles ? formatCurrency(client.bae_prevoyance.montant_annuel_charges_professionnelles) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Garantir totalité charges"
              value={client.bae_prevoyance?.garantir_totalite_charges_professionnelles !== null && client.bae_prevoyance?.garantir_totalite_charges_professionnelles !== undefined ? (client.bae_prevoyance.garantir_totalite_charges_professionnelles ? "Oui" : "Non") : "Non renseigné"}
            />
            <InfoItem
              label="Montant charges à garantir"
              value={client.bae_prevoyance?.montant_charges_professionnelles_a_garantir ? formatCurrency(client.bae_prevoyance.montant_charges_professionnelles_a_garantir) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Durée d'indemnisation"
              value={client.bae_prevoyance?.duree_indemnisation_souhaitee || "Non renseigné"}
            />
            <InfoItem
              label="Capital décès"
              value={client.bae_prevoyance?.capital_deces_souhaite ? formatCurrency(client.bae_prevoyance.capital_deces_souhaite) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Garanties obsèques"
              value={client.bae_prevoyance?.garanties_obseques ? formatCurrency(client.bae_prevoyance.garanties_obseques) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Rente enfants"
              value={client.bae_prevoyance?.rente_enfants ? formatCurrency(client.bae_prevoyance.rente_enfants) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Rente conjoint"
              value={client.bae_prevoyance?.rente_conjoint ? formatCurrency(client.bae_prevoyance.rente_conjoint) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Payeur"
              value={client.bae_prevoyance?.payeur || "Non renseigné"}
            />
          </dl>
        </InfoCard>
      )}

      {/* Carte Retraite (si besoin détecté ou données existantes) */}
      {showRetraite && (
        <InfoCard title="Retraite" icon={<TrendingUpIcon size={20} />} color="purple">
          <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            <InfoItem
              label="Revenus annuels"
              value={client.bae_retraite?.revenus_annuels ? formatCurrency(client.bae_retraite.revenus_annuels) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Revenus foyer"
              value={client.bae_retraite?.revenus_annuels_foyer ? formatCurrency(client.bae_retraite.revenus_annuels_foyer) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Impôt sur le revenu"
              value={client.bae_retraite?.impot_revenu ? formatCurrency(client.bae_retraite.impot_revenu) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Parts fiscales"
              value={client.bae_retraite?.nombre_parts_fiscales || "Non renseigné"}
            />
            <InfoItem
              label="TMI"
              value={client.bae_retraite?.tmi || "Non renseigné"}
            />
            <InfoItem
              label="Impôt payé N-1"
              value={client.bae_retraite?.impot_paye_n_1 ? formatCurrency(client.bae_retraite.impot_paye_n_1) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Âge départ retraite"
              value={client.bae_retraite?.age_depart_retraite ? `${client.bae_retraite.age_depart_retraite} ans` : "Non renseigné"}
              icon={<CalendarIcon size={14} />}
            />
            <InfoItem
              label="Âge départ retraite conjoint"
              value={client.bae_retraite?.age_depart_retraite_conjoint ? `${client.bae_retraite.age_depart_retraite_conjoint} ans` : "Non renseigné"}
              icon={<CalendarIcon size={14} />}
            />
            <InfoItem
              label="Revenu à maintenir"
              value={client.bae_retraite?.pourcentage_revenu_a_maintenir ? `${client.bae_retraite.pourcentage_revenu_a_maintenir}%` : "Non renseigné"}
            />
            <InfoItem
              label="Contrat en place"
              value={client.bae_retraite?.contrat_en_place || "Non renseigné"}
            />
            <InfoItem
              label="Bilan retraite disponible"
              value={client.bae_retraite?.bilan_retraite_disponible !== null && client.bae_retraite?.bilan_retraite_disponible !== undefined ? (client.bae_retraite.bilan_retraite_disponible ? "Oui" : "Non") : "Non renseigné"}
            />
            <InfoItem
              label="Complémentaire retraite"
              value={client.bae_retraite?.complementaire_retraite_mise_en_place !== null && client.bae_retraite?.complementaire_retraite_mise_en_place !== undefined ? (client.bae_retraite.complementaire_retraite_mise_en_place ? "Oui" : "Non") : "Non renseigné"}
            />
            <InfoItem
              label="Établissement"
              value={client.bae_retraite?.designation_etablissement || "Non renseigné"}
            />
            <InfoItem
              label="Cotisations annuelles"
              value={client.bae_retraite?.cotisations_annuelles ? formatCurrency(client.bae_retraite.cotisations_annuelles) : "Non renseigné"}
              icon={<DollarSignIcon size={14} />}
            />
            <InfoItem
              label="Titulaire"
              value={client.bae_retraite?.titulaire || "Non renseigné"}
            />
          </dl>
        </InfoCard>
      )}

      {/* Carte Épargne (si besoin détecté ou données existantes) */}
      {showEpargne && (
        <InfoCard title="Épargne" icon={<DollarSignIcon size={20} />} color="green">
          <div className="space-y-6">
            {/* Informations générales */}
            <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
              <InfoItem
                label="Épargne disponible"
                value={client.bae_epargne?.epargne_disponible !== null && client.bae_epargne?.epargne_disponible !== undefined ? (client.bae_epargne.epargne_disponible ? "Oui" : "Non") : "Non renseigné"}
              />
              <InfoItem
                label="Montant épargne"
                value={client.bae_epargne?.montant_epargne_disponible ? formatCurrency(client.bae_epargne.montant_epargne_disponible) : "Non renseigné"}
                icon={<DollarSignIcon size={14} />}
              />
              <InfoItem
                label="Donation réalisée"
                value={client.bae_epargne?.donation_realisee !== null && client.bae_epargne?.donation_realisee !== undefined ? (client.bae_epargne.donation_realisee ? "Oui" : "Non") : "Non renseigné"}
              />
              <InfoItem
                label="Forme donation"
                value={client.bae_epargne?.donation_forme || "Non renseigné"}
              />
              <InfoItem
                label="Date donation"
                value={client.bae_epargne?.donation_date ? formatDate(client.bae_epargne.donation_date) : "Non renseigné"}
                icon={<CalendarIcon size={14} />}
              />
              <InfoItem
                label="Montant donation"
                value={client.bae_epargne?.donation_montant ? formatCurrency(client.bae_epargne.donation_montant) : "Non renseigné"}
                icon={<DollarSignIcon size={14} />}
              />
              <InfoItem
                label="Bénéficiaires donation"
                value={client.bae_epargne?.donation_beneficiaires || "Non renseigné"}
              />
              <InfoItem
                label="Capacité d'épargne"
                value={client.bae_epargne?.capacite_epargne_estimee ? formatCurrency(client.bae_epargne.capacite_epargne_estimee) : "Non renseigné"}
                icon={<DollarSignIcon size={14} />}
              />
            </dl>

            {/* Actifs financiers */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                <TrendingUpIcon size={16} />
                <span>Actifs Financiers</span>
              </h4>
              <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pl-4">
                <InfoItem
                  label="Pourcentage"
                  value={client.bae_epargne?.actifs_financiers_pourcentage ? `${client.bae_epargne.actifs_financiers_pourcentage}%` : "Non renseigné"}
                />
                <InfoItem
                  label="Total"
                  value={client.bae_epargne?.actifs_financiers_total ? formatCurrency(client.bae_epargne.actifs_financiers_total) : "Non renseigné"}
                  icon={<DollarSignIcon size={14} />}
                />
                <div className="col-span-full">
                  <InfoItem
                    label="Détails"
                    value={client.bae_epargne?.actifs_financiers_details
                      ? (typeof client.bae_epargne.actifs_financiers_details === 'string'
                        ? client.bae_epargne.actifs_financiers_details
                        : JSON.stringify(client.bae_epargne.actifs_financiers_details))
                      : "Non renseigné"}
                  />
                </div>
              </dl>
            </div>

            {/* Actifs immobiliers */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                <BuildingIcon size={16} />
                <span>Actifs Immobiliers</span>
              </h4>
              <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pl-4">
                <InfoItem
                  label="Pourcentage"
                  value={client.bae_epargne?.actifs_immo_pourcentage ? `${client.bae_epargne.actifs_immo_pourcentage}%` : "Non renseigné"}
                />
                <InfoItem
                  label="Total"
                  value={client.bae_epargne?.actifs_immo_total ? formatCurrency(client.bae_epargne.actifs_immo_total) : "Non renseigné"}
                  icon={<DollarSignIcon size={14} />}
                />
                <div className="col-span-full">
                  <InfoItem
                    label="Détails"
                    value={client.bae_epargne?.actifs_immo_details
                      ? (typeof client.bae_epargne.actifs_immo_details === 'string'
                        ? client.bae_epargne.actifs_immo_details
                        : JSON.stringify(client.bae_epargne.actifs_immo_details))
                      : "Non renseigné"}
                  />
                </div>
              </dl>
            </div>

            {/* Autres actifs */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                <InfoIcon size={16} />
                <span>Autres Actifs</span>
              </h4>
              <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pl-4">
                <InfoItem
                  label="Pourcentage"
                  value={client.bae_epargne?.actifs_autres_pourcentage ? `${client.bae_epargne.actifs_autres_pourcentage}%` : "Non renseigné"}
                />
                <InfoItem
                  label="Total"
                  value={client.bae_epargne?.actifs_autres_total ? formatCurrency(client.bae_epargne.actifs_autres_total) : "Non renseigné"}
                  icon={<DollarSignIcon size={14} />}
                />
                <div className="col-span-full">
                  <InfoItem
                    label="Détails"
                    value={client.bae_epargne?.actifs_autres_details
                      ? (typeof client.bae_epargne.actifs_autres_details === 'string'
                        ? client.bae_epargne.actifs_autres_details
                        : JSON.stringify(client.bae_epargne.actifs_autres_details))
                      : "Non renseigné"}
                  />
                </div>
              </dl>
            </div>

            {/* Passifs */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                <InfoIcon size={16} />
                <span>Passifs</span>
              </h4>
              <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pl-4">
                <InfoItem
                  label="Total emprunts"
                  value={client.bae_epargne?.passifs_total_emprunts ? formatCurrency(client.bae_epargne.passifs_total_emprunts) : "Non renseigné"}
                  icon={<DollarSignIcon size={14} />}
                />
                <div className="col-span-full">
                  <InfoItem
                    label="Détails"
                    value={client.bae_epargne?.passifs_details
                      ? (typeof client.bae_epargne.passifs_details === 'string'
                        ? client.bae_epargne.passifs_details
                        : JSON.stringify(client.bae_epargne.passifs_details))
                      : "Non renseigné"}
                  />
                </div>
              </dl>
            </div>

            {/* Charges */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                <DollarSignIcon size={16} />
                <span>Charges</span>
              </h4>
              <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pl-4">
                <InfoItem
                  label="Total charges"
                  value={client.bae_epargne?.charges_totales ? formatCurrency(client.bae_epargne.charges_totales) : "Non renseigné"}
                  icon={<DollarSignIcon size={14} />}
                />
                <div className="col-span-full">
                  <InfoItem
                    label="Détails"
                    value={client.bae_epargne?.charges_details
                      ? (typeof client.bae_epargne.charges_details === 'string'
                        ? client.bae_epargne.charges_details
                        : JSON.stringify(client.bae_epargne.charges_details))
                      : "Non renseigné"}
                  />
                </div>
              </dl>
            </div>

            {/* Situation financière */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-2">Situation financière (revenus/charges)</h4>
              <p className="text-sm text-gray-600 whitespace-pre-wrap bg-gray-50 p-3 rounded">
                {client.bae_epargne?.situation_financiere_revenus_charges || "Non renseigné"}
              </p>
            </div>
          </div>
        </InfoCard>
      )}

      {/* Carte Revenus (si applicable) */}
      {client.revenus && client.revenus.length > 0 && (
        <InfoCard
          title="Revenus"
          icon={<DollarSignIcon size={20} />}
          color="green"
          badge={`${client.revenus.length} revenu${client.revenus.length > 1 ? 's' : ''}`}
        >
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nature</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Périodicité</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {client.revenus.map((revenu: any) => (
                  <tr key={revenu.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">{revenu.nature || 'Non renseigné'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{revenu.periodicite || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-900 text-right font-medium">
                      {revenu.montant ? formatCurrency(revenu.montant) : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </InfoCard>
      )}

      {/* Carte Passifs/Dettes (si applicable) */}
      {client.passifs && client.passifs.length > 0 && (
        <InfoCard
          title="Dettes et Emprunts"
          icon={<TrendingUpIcon size={20} />}
          color="orange"
          badge={`${client.passifs.length} emprunt${client.passifs.length > 1 ? 's' : ''}`}
        >
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nature</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prêteur</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Périodicité</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Mensualité</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Capital restant</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Durée restante</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {client.passifs.map((passif: any) => (
                  <tr key={passif.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">{passif.nature || 'Non renseigné'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{passif.preteur || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{passif.periodicite || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-900 text-right">
                      {passif.montant_remboursement ? formatCurrency(passif.montant_remboursement) : '-'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-900 text-right font-medium">
                      {passif.capital_restant_du ? formatCurrency(passif.capital_restant_du) : '-'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 text-right">
                      {passif.duree_restante ? `${passif.duree_restante} mois` : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </InfoCard>
      )}

      {/* Carte Actifs Financiers (si applicable) */}
      {client.actifs_financiers && client.actifs_financiers.length > 0 && (
        <InfoCard
          title="Patrimoine Financier"
          icon={<TrendingUpIcon size={20} />}
          color="blue"
          badge={`${client.actifs_financiers.length} actif${client.actifs_financiers.length > 1 ? 's' : ''}`}
        >
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nature</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Établissement</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Détenteur</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date ouverture</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur actuelle</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {client.actifs_financiers.map((actif: any) => (
                  <tr key={actif.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">{actif.nature || 'Non renseigné'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{actif.etablissement || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{actif.detenteur || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">
                      {actif.date_ouverture_souscription ? formatDate(actif.date_ouverture_souscription) : '-'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-900 text-right font-medium">
                      {actif.valeur_actuelle ? formatCurrency(actif.valeur_actuelle) : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </InfoCard>
      )}

      {/* Carte Biens Immobiliers (si applicable) */}
      {client.biens_immobiliers && client.biens_immobiliers.length > 0 && (
        <InfoCard
          title="Patrimoine Immobilier"
          icon={<BuildingIcon size={20} />}
          color="indigo"
          badge={`${client.biens_immobiliers.length} bien${client.biens_immobiliers.length > 1 ? 's' : ''}`}
        >
          <div className="space-y-4">
            {client.biens_immobiliers.map((bien: any, index: number) => (
              <div
                key={bien.id}
                className="p-4 bg-gray-50 rounded-lg border border-gray-200"
              >
                <div className="flex items-center justify-between mb-3">
                  <h4 className="font-semibold text-gray-900">
                    {bien.designation || `Bien ${index + 1}`}
                  </h4>
                  {bien.forme_propriete && (
                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                      {bien.forme_propriete}
                    </span>
                  )}
                </div>
                <dl className="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                  {bien.detenteur && (
                    <div>
                      <dt className="text-gray-500">Détenteur</dt>
                      <dd className="font-medium text-gray-900">{bien.detenteur}</dd>
                    </div>
                  )}
                  {bien.valeur_actuelle_estimee && (
                    <div>
                      <dt className="text-gray-500">Valeur estimée</dt>
                      <dd className="font-medium text-gray-900">
                        {formatCurrency(bien.valeur_actuelle_estimee)}
                      </dd>
                    </div>
                  )}
                  {bien.annee_acquisition && (
                    <div>
                      <dt className="text-gray-500">Année d'acquisition</dt>
                      <dd className="font-medium text-gray-900">{bien.annee_acquisition}</dd>
                    </div>
                  )}
                  {bien.valeur_acquisition && (
                    <div>
                      <dt className="text-gray-500">Valeur d'acquisition</dt>
                      <dd className="font-medium text-gray-900">
                        {formatCurrency(bien.valeur_acquisition)}
                      </dd>
                    </div>
                  )}
                </dl>
              </div>
            ))}
          </div>
        </InfoCard>
      )}

      {/* Carte Autres Épargnes (si applicable) */}
      {client.autres_epargnes && client.autres_epargnes.length > 0 && (
        <InfoCard
          title="Autres Épargnes"
          icon={<DollarSignIcon size={20} />}
          color="teal"
          badge={`${client.autres_epargnes.length} épargne${client.autres_epargnes.length > 1 ? 's' : ''}`}
        >
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Détenteur</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {client.autres_epargnes.map((epargne: any) => (
                  <tr key={epargne.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">{epargne.designation || 'Non renseigné'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{epargne.detenteur || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-900 text-right font-medium">
                      {epargne.valeur ? formatCurrency(epargne.valeur) : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </InfoCard>
      )}

      {/* Autres informations */}
      {(client.charge_clientele || client.consentement_audio !== undefined) && (
        <InfoCard title="Autres Informations" icon={<UserIcon size={20} />} color="cyan">
          <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            {client.charge_clientele && (
              <InfoItem label="Charge clientèle" value={client.charge_clientele} />
            )}
            {client.consentement_audio !== undefined && (
              <InfoItem
                label="Consentement audio"
                value={
                  client.consentement_audio ? (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      Oui
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                      Non
                    </span>
                  )
                }
              />
            )}
          </dl>
        </InfoCard>
      )}
    </div>
  );
};
