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
      value: client.revenus_annuels ? formatCurrency(client.revenus_annuels) : 'N/A',
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
      value: client.nombre_enfants || 0,
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
              value={`${client.conjoint.prenom} ${client.conjoint.nom?.toUpperCase()}`}
            />
            {client.conjoint.nom_jeune_fille && (
              <InfoItem label="Nom de jeune fille" value={client.conjoint.nom_jeune_fille} />
            )}
            {client.conjoint.date_naissance && (
              <InfoItem
                label="Date de naissance"
                value={formatDate(client.conjoint.date_naissance)}
                icon={<CalendarIcon size={14} />}
              />
            )}
            {client.conjoint.lieu_naissance && (
              <InfoItem label="Lieu de naissance" value={client.conjoint.lieu_naissance} />
            )}
            {client.conjoint.nationalite && (
              <InfoItem label="Nationalité" value={client.conjoint.nationalite} />
            )}
            {client.conjoint.profession && (
              <InfoItem label="Profession" value={client.conjoint.profession} />
            )}
            {client.conjoint.situation_actuelle_statut && (
              <InfoItem label="Situation actuelle" value={client.conjoint.situation_actuelle_statut} />
            )}
            {client.conjoint.telephone && (
              <InfoItem
                label="Téléphone"
                value={client.conjoint.telephone}
                icon={<PhoneIcon size={14} />}
              />
            )}
            {client.conjoint.adresse && (
              <InfoItem
                label="Adresse"
                value={client.conjoint.adresse}
                icon={<MapPinIcon size={14} />}
                fullWidth
              />
            )}
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

      {/* Carte Prévoyance (si applicable) */}
      {client.bae_prevoyance && (
        <InfoCard title="Prévoyance" icon={<ShieldIcon size={20} />} color="orange">
          <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            {client.bae_prevoyance.contrat_en_place !== undefined && (
              <InfoItem
                label="Contrat en place"
                value={
                  client.bae_prevoyance.contrat_en_place ? (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      Oui
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                      Non
                    </span>
                  )
                }
              />
            )}
            {client.bae_prevoyance.date_effet && (
              <InfoItem
                label="Date d'effet"
                value={formatDate(client.bae_prevoyance.date_effet)}
                icon={<CalendarIcon size={14} />}
              />
            )}
            {client.bae_prevoyance.cotisations && (
              <InfoItem
                label="Cotisations"
                value={formatCurrency(client.bae_prevoyance.cotisations)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_prevoyance.couverture_invalidite && (
              <InfoItem label="Couverture invalidité" value={client.bae_prevoyance.couverture_invalidite} />
            )}
            {client.bae_prevoyance.revenu_a_garantir && (
              <InfoItem
                label="Revenu à garantir"
                value={formatCurrency(client.bae_prevoyance.revenu_a_garantir)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_prevoyance.charges_professionnelles && (
              <InfoItem
                label="Charges professionnelles"
                value={formatCurrency(client.bae_prevoyance.charges_professionnelles)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_prevoyance.capital_deces && (
              <InfoItem
                label="Capital décès"
                value={formatCurrency(client.bae_prevoyance.capital_deces)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_prevoyance.rente_enfants && (
              <InfoItem
                label="Rente enfants"
                value={formatCurrency(client.bae_prevoyance.rente_enfants)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_prevoyance.rente_conjoint && (
              <InfoItem
                label="Rente conjoint"
                value={formatCurrency(client.bae_prevoyance.rente_conjoint)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
          </dl>
        </InfoCard>
      )}

      {/* Carte Retraite (si applicable) */}
      {client.bae_retraite && (
        <InfoCard title="Retraite" icon={<TrendingUpIcon size={20} />} color="purple">
          <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            {client.bae_retraite.revenus_annuels && (
              <InfoItem
                label="Revenus annuels"
                value={formatCurrency(client.bae_retraite.revenus_annuels)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_retraite.revenus_foyer && (
              <InfoItem
                label="Revenus foyer"
                value={formatCurrency(client.bae_retraite.revenus_foyer)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_retraite.impot_revenu && (
              <InfoItem
                label="Impôt sur le revenu"
                value={formatCurrency(client.bae_retraite.impot_revenu)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
            {client.bae_retraite.parts_fiscales && (
              <InfoItem label="Parts fiscales" value={client.bae_retraite.parts_fiscales} />
            )}
            {client.bae_retraite.TMI && (
              <InfoItem label="TMI" value={`${client.bae_retraite.TMI}%`} />
            )}
            {client.bae_retraite.age_depart_retraite && (
              <InfoItem
                label="Âge départ retraite"
                value={`${client.bae_retraite.age_depart_retraite} ans`}
                icon={<CalendarIcon size={14} />}
              />
            )}
            {client.bae_retraite.pourcentage_revenu_a_maintenir && (
              <InfoItem
                label="Revenu à maintenir"
                value={`${client.bae_retraite.pourcentage_revenu_a_maintenir}%`}
              />
            )}
            {client.bae_retraite.contrat_en_place !== undefined && (
              <InfoItem
                label="Contrat en place"
                value={
                  client.bae_retraite.contrat_en_place ? (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      Oui
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                      Non
                    </span>
                  )
                }
              />
            )}
            {client.bae_retraite.cotisations_annuelles && (
              <InfoItem
                label="Cotisations annuelles"
                value={formatCurrency(client.bae_retraite.cotisations_annuelles)}
                icon={<DollarSignIcon size={14} />}
              />
            )}
          </dl>
        </InfoCard>
      )}

      {/* Carte Épargne (si applicable) */}
      {client.bae_epargne && (
        <InfoCard title="Épargne" icon={<DollarSignIcon size={20} />} color="green">
          <div className="space-y-6">
            <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
              {client.bae_epargne.epargne_disponible && (
                <InfoItem
                  label="Épargne disponible"
                  value={formatCurrency(client.bae_epargne.epargne_disponible)}
                  icon={<DollarSignIcon size={14} />}
                />
              )}
              {client.bae_epargne.capacite_epargne && (
                <InfoItem
                  label="Capacité d'épargne"
                  value={formatCurrency(client.bae_epargne.capacite_epargne)}
                  icon={<DollarSignIcon size={14} />}
                />
              )}
              {client.bae_epargne.charges && (
                <InfoItem
                  label="Charges"
                  value={formatCurrency(client.bae_epargne.charges)}
                  icon={<DollarSignIcon size={14} />}
                />
              )}
              {client.bae_epargne.donations && (
                <InfoItem
                  label="Donations"
                  value={formatCurrency(client.bae_epargne.donations)}
                  icon={<DollarSignIcon size={14} />}
                />
              )}
            </dl>

            {/* Actifs financiers */}
            {client.bae_epargne.actifs_financiers && client.bae_epargne.actifs_financiers.length > 0 && (
              <div>
                <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                  <TrendingUpIcon size={16} />
                  <span>Actifs Financiers</span>
                </h4>
                <div className="space-y-3">
                  {client.bae_epargne.actifs_financiers.map((actif: any, index: number) => (
                    <div
                      key={index}
                      className="p-4 bg-emerald-50 rounded-lg border border-emerald-200"
                    >
                      <dl className="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                        {actif.type && (
                          <div>
                            <dt className="text-gray-500">Type</dt>
                            <dd className="font-medium text-gray-900">{actif.type}</dd>
                          </div>
                        )}
                        {actif.montant && (
                          <div>
                            <dt className="text-gray-500">Montant</dt>
                            <dd className="font-medium text-gray-900">{formatCurrency(actif.montant)}</dd>
                          </div>
                        )}
                        {actif.details && (
                          <div className="col-span-full">
                            <dt className="text-gray-500">Détails</dt>
                            <dd className="font-medium text-gray-900">{actif.details}</dd>
                          </div>
                        )}
                      </dl>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Actifs immobiliers */}
            {client.bae_epargne.actifs_immo && client.bae_epargne.actifs_immo.length > 0 && (
              <div>
                <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                  <BuildingIcon size={16} />
                  <span>Actifs Immobiliers</span>
                </h4>
                <div className="space-y-3">
                  {client.bae_epargne.actifs_immo.map((actif: any, index: number) => (
                    <div
                      key={index}
                      className="p-4 bg-emerald-50 rounded-lg border border-emerald-200"
                    >
                      <dl className="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                        {actif.type && (
                          <div>
                            <dt className="text-gray-500">Type</dt>
                            <dd className="font-medium text-gray-900">{actif.type}</dd>
                          </div>
                        )}
                        {actif.valeur && (
                          <div>
                            <dt className="text-gray-500">Valeur</dt>
                            <dd className="font-medium text-gray-900">{formatCurrency(actif.valeur)}</dd>
                          </div>
                        )}
                        {actif.details && (
                          <div className="col-span-full">
                            <dt className="text-gray-500">Détails</dt>
                            <dd className="font-medium text-gray-900">{actif.details}</dd>
                          </div>
                        )}
                      </dl>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Passifs */}
            {client.bae_epargne.passifs && client.bae_epargne.passifs.length > 0 && (
              <div>
                <h4 className="text-sm font-semibold text-gray-700 mb-3 flex items-center space-x-2">
                  <InfoIcon size={16} />
                  <span>Passifs</span>
                </h4>
                <div className="space-y-3">
                  {client.bae_epargne.passifs.map((passif: any, index: number) => (
                    <div
                      key={index}
                      className="p-4 bg-emerald-50 rounded-lg border border-emerald-200"
                    >
                      <dl className="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                        {passif.type && (
                          <div>
                            <dt className="text-gray-500">Type</dt>
                            <dd className="font-medium text-gray-900">{passif.type}</dd>
                          </div>
                        )}
                        {passif.montant && (
                          <div>
                            <dt className="text-gray-500">Montant</dt>
                            <dd className="font-medium text-gray-900">{formatCurrency(passif.montant)}</dd>
                          </div>
                        )}
                        {passif.details && (
                          <div className="col-span-full">
                            <dt className="text-gray-500">Détails</dt>
                            <dd className="font-medium text-gray-900">{passif.details}</dd>
                          </div>
                        )}
                      </dl>
                    </div>
                  ))}
                </div>
              </div>
            )}
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
