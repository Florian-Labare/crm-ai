import React from 'react';
import {
  User,
  MapPin,
  Briefcase,
  Heart,
  Users,
  Building,
  Activity,
  Calendar,
  DollarSign,
  TrendingUp,
  Shield,
  CreditCard,
  Home,
  Gem,
  LineChart,
} from 'lucide-react';
import { VuexyInfoSection, VuexyInfoRow, VuexySubSection } from './VuexyInfoSection';
import { VuexyStatCard } from './VuexyStatCard';
import { Target } from 'lucide-react';

interface VuexyClientInfoSectionProps {
  client: any;
  formatDate: (date?: string) => string;
  formatCurrency: (amount?: number) => string;
}

export const VuexyClientInfoSection: React.FC<VuexyClientInfoSectionProps> = ({
  client,
  formatDate,
  formatCurrency,
}) => {
  // Calcul des revenus annuels depuis toutes les sources possibles
  const calculateRevenusAnnuels = (): number | null => {
    if (client.revenus_annuels && client.revenus_annuels > 0) {
      return client.revenus_annuels;
    }

    if (client.bae_retraite?.revenus_annuels && client.bae_retraite.revenus_annuels > 0) {
      return client.bae_retraite.revenus_annuels;
    }

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
    let count = 1;

    if (client.conjoint && (client.conjoint.nom || client.conjoint.prenom)) {
      count += 1;
    }

    if (client.enfants && Array.isArray(client.enfants)) {
      count += client.enfants.length;
    } else if (client.nombre_enfants && client.nombre_enfants > 0) {
      count += client.nombre_enfants;
    }

    return count;
  };

  const revenusAnnuels = calculateRevenusAnnuels();
  const familyMembers = calculateFamilyMembers();

  // Helper pour vérifier si un besoin est présent
  const hasBesoin = (besoinKeywords: string[]): boolean => {
    if (!client.besoins || !Array.isArray(client.besoins)) return false;
    return client.besoins.some((besoin: string) =>
      besoinKeywords.some((keyword) =>
        besoin.toLowerCase().includes(keyword.toLowerCase())
      )
    );
  };

  const showSante = hasBesoin(['santé', 'sante', 'mutuelle', 'complémentaire', 'complementaire']) || client.sante_souhait;
  const showPrevoyance = hasBesoin(['prévoyance', 'prevoyance', 'décès', 'deces', 'invalidité', 'invalidite']) || client.bae_prevoyance;
  const showRetraite = hasBesoin(['retraite', 'per', 'pension']) || client.bae_retraite;

  // Section Épargne unifiée : afficher si BAE épargne OU actifs/passifs présents
  const showEpargneSection =
    client.actifs_financiers?.length > 0 ||
    client.biens_immobiliers?.length > 0 ||
    client.autres_epargnes?.length > 0 ||
    client.passifs?.length > 0 ||
    client.bae_epargne;

  return (
    <div className="space-y-8">
      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <VuexyStatCard
          label="Âge"
          value={
            client.date_naissance
              ? `${new Date().getFullYear() - new Date(client.date_naissance).getFullYear()} ans`
              : 'N/A'
          }
          icon={<Calendar size={20} />}
          color="blue"
          delay={0.1}
        />
        <VuexyStatCard
          label="Revenus annuels"
          value={revenusAnnuels ? formatCurrency(revenusAnnuels) : 'N/A'}
          icon={<DollarSign size={20} />}
          color="green"
          delay={0.2}
        />
        <VuexyStatCard
          label="Besoins"
          value={client.besoins?.length || 0}
          icon={<TrendingUp size={20} />}
          color="purple"
          delay={0.3}
        />
        <VuexyStatCard
          label="Famille"
          value={`${familyMembers} membre${familyMembers > 1 ? 's' : ''}`}
          icon={<Users size={20} />}
          color="orange"
          delay={0.4}
        />
      </div>

      {/* Info Sections Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* État Civil */}
        <VuexyInfoSection title="État Civil" icon={<User size={18} />}>
          <VuexyInfoRow
            label="Nom complet"
            value={`${client.prenom || ''} ${client.nom?.toUpperCase() || ''}`.trim()}
            empty={!client.prenom && !client.nom}
          />
          {client.nom_jeune_fille && (
            <VuexyInfoRow label="Nom de jeune fille" value={client.nom_jeune_fille} />
          )}
          <VuexyInfoRow
            label="Date de naissance"
            value={formatDate(client.date_naissance)}
            empty={!client.date_naissance}
          />
          <VuexyInfoRow label="Lieu de naissance" value={client.lieu_naissance} empty={!client.lieu_naissance} />
          <VuexyInfoRow label="Nationalité" value={client.nationalite} empty={!client.nationalite} />
          <VuexyInfoRow label="Situation matrimoniale" value={client.situation_matrimoniale} empty={!client.situation_matrimoniale} />
          {client.date_situation_matrimoniale && (
            <VuexyInfoRow
              label="Date situation"
              value={formatDate(client.date_situation_matrimoniale)}
            />
          )}
          <VuexyInfoRow label="Situation actuelle" value={client.situation_actuelle} empty={!client.situation_actuelle} />
        </VuexyInfoSection>

        {/* Coordonnées */}
        <VuexyInfoSection title="Coordonnées" icon={<MapPin size={18} />}>
          <VuexyInfoRow
            label="Adresse complète"
            value={
              client.adresse && client.code_postal && client.ville
                ? `${client.adresse}, ${client.code_postal} ${client.ville}`
                : client.adresse
            }
            empty={!client.adresse}
          />
          <VuexyInfoRow label="Téléphone" value={client.telephone} empty={!client.telephone} />
          <VuexyInfoRow
            label="Email"
            value={
              client.email ? (
                <a
                  href={`mailto:${client.email}`}
                  className="text-[#7367F0] hover:underline"
                >
                  {client.email}
                </a>
              ) : undefined
            }
            empty={!client.email}
          />
          <VuexyInfoRow label="Résidence fiscale" value={client.residence_fiscale} empty={!client.residence_fiscale} />
        </VuexyInfoSection>

        {/* Professionnel */}
        <VuexyInfoSection title="Informations Professionnelles" icon={<Briefcase size={18} />}>
          <VuexyInfoRow label="Profession" value={client.profession} empty={!client.profession} />
          <VuexyInfoRow
            label="Revenus annuels"
            value={revenusAnnuels ? formatCurrency(revenusAnnuels) : undefined}
            empty={!revenusAnnuels}
          />
          {client.date_evenement_professionnel && (
            <VuexyInfoRow
              label="Date événement pro"
              value={formatDate(client.date_evenement_professionnel)}
            />
          )}
          <VuexyInfoRow
            label="Risques professionnels"
            value={
              client.risques_professionnels ? (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#EA5455]/10 text-[#EA5455] text-xs font-semibold">
                  Oui
                </span>
              ) : (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#28C76F]/10 text-[#28C76F] text-xs font-semibold">
                  Non
                </span>
              )
            }
          />
          {client.details_risques_professionnels && (
            <VuexyInfoRow
              label="Détails des risques"
              value={client.details_risques_professionnels}
            />
          )}
        </VuexyInfoSection>

        {/* Mode de vie */}
        <VuexyInfoSection title="Mode de Vie" icon={<Activity size={18} />}>
          <VuexyInfoRow
            label="Fumeur"
            value={
              client.fumeur ? (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-xs font-semibold">
                  Oui
                </span>
              ) : (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#28C76F]/10 text-[#28C76F] text-xs font-semibold">
                  Non
                </span>
              )
            }
          />
          <VuexyInfoRow
            label="Activités sportives"
            value={
              client.activites_sportives ? (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#00CFE8]/10 text-[#00CFE8] text-xs font-semibold">
                  Oui
                </span>
              ) : (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#B9B9C3]/10 text-[#B9B9C3] text-xs font-semibold">
                  Non
                </span>
              )
            }
          />
          {client.details_activites_sportives && (
            <VuexyInfoRow label="Détails des activités" value={client.details_activites_sportives} />
          )}
          {client.niveau_activites_sportives && (
            <VuexyInfoRow label="Niveau" value={client.niveau_activites_sportives} />
          )}
        </VuexyInfoSection>
      </div>

      {/* Entreprise (si applicable) */}
      {(client.chef_entreprise || client.travailleur_independant || client.mandataire_social) && (
        <VuexyInfoSection title="Informations Entreprise" icon={<Building size={18} />}>
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <VuexyInfoRow
              label="Chef d'entreprise"
              value={
                client.chef_entreprise ? (
                  <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-xs font-semibold">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#B9B9C3]/10 text-[#B9B9C3] text-xs font-semibold">
                    Non
                  </span>
                )
              }
            />
            <VuexyInfoRow
              label="Travailleur indépendant"
              value={
                client.travailleur_independant ? (
                  <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-xs font-semibold">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#B9B9C3]/10 text-[#B9B9C3] text-xs font-semibold">
                    Non
                  </span>
                )
              }
            />
            <VuexyInfoRow
              label="Mandataire social"
              value={
                client.mandataire_social ? (
                  <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#FF9F43]/10 text-[#FF9F43] text-xs font-semibold">
                    Oui
                  </span>
                ) : (
                  <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#B9B9C3]/10 text-[#B9B9C3] text-xs font-semibold">
                    Non
                  </span>
                )
              }
            />
            {client.statut && (
              <VuexyInfoRow label="Statut juridique" value={client.statut} />
            )}
          </div>
        </VuexyInfoSection>
      )}

      {/* Conjoint */}
      {client.conjoint && (
        <VuexyInfoSection title="Conjoint" icon={<Heart size={18} />}>
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <VuexyInfoRow
              label="Nom complet"
              value={
                client.conjoint.prenom || client.conjoint.nom
                  ? `${client.conjoint.prenom || ''} ${client.conjoint.nom?.toUpperCase() || ''}`.trim()
                  : undefined
              }
              empty={!client.conjoint.prenom && !client.conjoint.nom}
            />
            <VuexyInfoRow
              label="Nom de jeune fille"
              value={client.conjoint.nom_jeune_fille}
              empty={!client.conjoint.nom_jeune_fille}
            />
            <VuexyInfoRow
              label="Date de naissance"
              value={client.conjoint.date_naissance ? formatDate(client.conjoint.date_naissance) : undefined}
              empty={!client.conjoint.date_naissance}
            />
            <VuexyInfoRow
              label="Lieu de naissance"
              value={client.conjoint.lieu_naissance}
              empty={!client.conjoint.lieu_naissance}
            />
            <VuexyInfoRow
              label="Nationalité"
              value={client.conjoint.nationalite}
              empty={!client.conjoint.nationalite}
            />
            <VuexyInfoRow
              label="Profession"
              value={client.conjoint.profession}
              empty={!client.conjoint.profession}
            />
            <VuexyInfoRow
              label="Situation actuelle"
              value={client.conjoint.situation_actuelle_statut}
              empty={!client.conjoint.situation_actuelle_statut}
            />
            <VuexyInfoRow
              label="Téléphone"
              value={client.conjoint.telephone}
              empty={!client.conjoint.telephone}
            />
            <VuexyInfoRow
              label="Adresse"
              value={client.conjoint.adresse}
              empty={!client.conjoint.adresse}
            />
          </div>
        </VuexyInfoSection>
      )}

      {/* Enfants */}
      {client.enfants && client.enfants.length > 0 && (
        <VuexyInfoSection
          title={`Enfants (${client.enfants.length})`}
          icon={<Users size={18} />}
        >
          <div className="space-y-4">
            {client.enfants.map((enfant: any, index: number) => (
              <div
                key={enfant.id}
                className="p-5 bg-[#F8F8F8] rounded-lg border border-[#EBE9F1]"
              >
                <div className="flex items-center justify-between mb-4">
                  <h4 className="font-semibold text-[#5E5873]">
                    Enfant {index + 1}
                    {enfant.prenom && ` - ${enfant.prenom} ${enfant.nom || ''}`}
                  </h4>
                  <div className="flex gap-2">
                    {enfant.fiscalement_a_charge && (
                      <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#00CFE8]/10 text-[#00CFE8] text-xs font-semibold">
                        À charge
                      </span>
                    )}
                    {enfant.garde_alternee && (
                      <span className="inline-flex items-center px-3 py-1 rounded-full bg-[#9055FD]/10 text-[#9055FD] text-xs font-semibold">
                        Garde alternée
                      </span>
                    )}
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                  {enfant.prenom && (
                    <div>
                      <div className="text-[#6E6B7B] font-medium">Prénom</div>
                      <div className="text-[#5E5873] font-semibold">{enfant.prenom}</div>
                    </div>
                  )}
                  {enfant.nom && (
                    <div>
                      <div className="text-[#6E6B7B] font-medium">Nom</div>
                      <div className="text-[#5E5873] font-semibold">{enfant.nom}</div>
                    </div>
                  )}
                  {enfant.date_naissance && (
                    <div>
                      <div className="text-[#6E6B7B] font-medium">Date de naissance</div>
                      <div className="text-[#5E5873] font-semibold">
                        {formatDate(enfant.date_naissance)}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </VuexyInfoSection>
      )}

      {/* Revenus */}
      {client.revenus && client.revenus.length > 0 && (
        <VuexyInfoSection
          title={`Revenus (${client.revenus.length})`}
          icon={<DollarSign size={18} />}
        >
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-[#EBE9F1]">
              <thead className="bg-[#F8F8F8]">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Nature</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Périodicité</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Montant</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-[#EBE9F1]">
                {client.revenus.map((revenu: any) => (
                  <tr key={revenu.id} className="hover:bg-[#F8F8F8] transition-colors">
                    <td className="px-4 py-3 text-sm text-[#5E5873] font-medium">{revenu.nature || <span className="text-[#B9B9C3] italic">Non renseigné</span>}</td>
                    <td className="px-4 py-3 text-sm text-[#6E6B7B]">{revenu.periodicite || <span className="text-[#B9B9C3] italic">Non renseigné</span>}</td>
                    <td className="px-4 py-3 text-sm text-[#5E5873] text-right font-semibold">
                      {revenu.montant ? formatCurrency(revenu.montant) : <span className="text-[#B9B9C3] italic font-normal">Non renseigné</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </VuexyInfoSection>
      )}

      {/* ========================================
          SECTION MÈRE : ÉPARGNE (UNIFIÉE)
          ======================================== */}
      {showEpargneSection && (
        <VuexyInfoSection title="Épargne & Patrimoine" icon={<DollarSign size={18} />}>
          <div className="space-y-4">
            {/* Sous-section : Synthèse patrimoniale (calculs dynamiques) */}
            <VuexySubSection
              title="Synthèse patrimoniale"
              icon={<Target size={14} />}
              color="blue"
              defaultOpen={true}
            >
              {(() => {
                // Calculs dynamiques depuis les sous-sections
                const totalActifsFinanciers = (client.actifs_financiers || []).reduce(
                  (sum: number, a: any) => sum + (Number(a.valeur_actuelle) || 0), 0
                );
                const totalActifsImmo = (client.biens_immobiliers || []).reduce(
                  (sum: number, b: any) => sum + (Number(b.valeur_actuelle_estimee) || 0), 0
                );
                const totalAutresActifs = (client.autres_epargnes || []).reduce(
                  (sum: number, e: any) => sum + (Number(e.valeur) || 0), 0
                );
                const totalPassifs = (client.passifs || []).reduce(
                  (sum: number, p: any) => sum + (Number(p.capital_restant_du) || 0), 0
                );

                return (
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <VuexyInfoRow
                      label="Épargne disponible"
                      value={client.bae_epargne?.montant_epargne_disponible ? formatCurrency(client.bae_epargne.montant_epargne_disponible) : undefined}
                      empty={!client.bae_epargne?.montant_epargne_disponible}
                    />
                    <VuexyInfoRow
                      label="Capacité d'épargne"
                      value={client.bae_epargne?.capacite_epargne_estimee ? formatCurrency(client.bae_epargne.capacite_epargne_estimee) : undefined}
                      empty={!client.bae_epargne?.capacite_epargne_estimee}
                    />
                    <VuexyInfoRow
                      label="Actifs financiers"
                      value={totalActifsFinanciers > 0 ? formatCurrency(totalActifsFinanciers) : undefined}
                      empty={totalActifsFinanciers === 0}
                    />
                    <VuexyInfoRow
                      label="Actifs immobiliers"
                      value={totalActifsImmo > 0 ? formatCurrency(totalActifsImmo) : undefined}
                      empty={totalActifsImmo === 0}
                    />
                    <VuexyInfoRow
                      label="Autres actifs"
                      value={totalAutresActifs > 0 ? formatCurrency(totalAutresActifs) : undefined}
                      empty={totalAutresActifs === 0}
                    />
                    <VuexyInfoRow
                      label="Total emprunts"
                      value={totalPassifs > 0 ? formatCurrency(totalPassifs) : undefined}
                      empty={totalPassifs === 0}
                    />
                  </div>
                );
              })()}
            </VuexySubSection>

            {/* Sous-section : Actifs Financiers */}
            {client.actifs_financiers && client.actifs_financiers.length > 0 && (
              <VuexySubSection
                title="Actifs Financiers"
                icon={<LineChart size={14} />}
                count={client.actifs_financiers.length}
                color="cyan"
              >
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-[#EBE9F1]">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Nature</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Établissement</th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-[#5E5873] uppercase">Valeur actuelle</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Détenteur</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Ouvert le</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-[#EBE9F1]">
                      {client.actifs_financiers.map((actif: any) => (
                        <tr key={actif.id} className="hover:bg-[#F8F8F8] transition-colors">
                          <td className="px-3 py-2 text-sm text-[#5E5873] font-medium">{actif.nature || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">{actif.etablissement || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#28C76F] text-right font-semibold">
                            {actif.valeur_actuelle ? formatCurrency(actif.valeur_actuelle) : <span className="text-[#B9B9C3] italic font-normal">-</span>}
                          </td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">{actif.detenteur || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">
                            {actif.date_ouverture_souscription ? formatDate(actif.date_ouverture_souscription) : <span className="text-[#B9B9C3] italic">-</span>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-[#E8FFFE]">
                      <tr>
                        <td colSpan={2} className="px-3 py-2 text-sm font-bold text-[#5E5873]">Total :</td>
                        <td className="px-3 py-2 text-sm text-[#28C76F] text-right font-bold">
                          {formatCurrency(client.actifs_financiers.reduce((sum: number, a: any) => sum + (Number(a.valeur_actuelle) || 0), 0))}
                        </td>
                        <td colSpan={2}></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </VuexySubSection>
            )}

            {/* Sous-section : Biens Immobiliers */}
            {client.biens_immobiliers && client.biens_immobiliers.length > 0 && (
              <VuexySubSection
                title="Biens Immobiliers & Pro"
                icon={<Home size={14} />}
                count={client.biens_immobiliers.length}
                color="orange"
              >
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-[#EBE9F1]">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Désignation</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Détenteur</th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-[#5E5873] uppercase">Valeur actuelle</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Forme propriété</th>
                        <th className="px-3 py-2 text-center text-xs font-semibold text-[#5E5873] uppercase">Acquisition</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-[#EBE9F1]">
                      {client.biens_immobiliers.map((bien: any) => (
                        <tr key={bien.id} className="hover:bg-[#F8F8F8] transition-colors">
                          <td className="px-3 py-2 text-sm text-[#5E5873] font-medium">{bien.designation || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">{bien.detenteur || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#28C76F] text-right font-semibold">
                            {bien.valeur_actuelle_estimee ? formatCurrency(bien.valeur_actuelle_estimee) : <span className="text-[#B9B9C3] italic font-normal">-</span>}
                          </td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">{bien.forme_propriete || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B] text-center">{bien.annee_acquisition || <span className="text-[#B9B9C3] italic">-</span>}</td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-[#FFF7ED]">
                      <tr>
                        <td colSpan={2} className="px-3 py-2 text-sm font-bold text-[#5E5873]">Total immo :</td>
                        <td className="px-3 py-2 text-sm text-[#28C76F] text-right font-bold">
                          {formatCurrency(client.biens_immobiliers.reduce((sum: number, b: any) => sum + (Number(b.valeur_actuelle_estimee) || 0), 0))}
                        </td>
                        <td colSpan={2}></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </VuexySubSection>
            )}

            {/* Sous-section : Autres Épargnes */}
            {client.autres_epargnes && client.autres_epargnes.length > 0 && (
              <VuexySubSection
                title="Autres Actifs"
                icon={<Gem size={14} />}
                count={client.autres_epargnes.length}
                color="purple"
              >
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-[#EBE9F1]">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Désignation</th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-[#5E5873] uppercase">Détenteur</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-[#EBE9F1]">
                      {client.autres_epargnes.map((epargne: any) => (
                        <tr key={epargne.id} className="hover:bg-[#F8F8F8] transition-colors">
                          <td className="px-3 py-2 text-sm text-[#5E5873] font-medium">
                            {epargne.designation || <span className="text-[#B9B9C3] italic">-</span>}
                            {epargne.valeur && (
                              <span className="ml-2 text-[#28C76F] font-semibold">{formatCurrency(epargne.valeur)}</span>
                            )}
                          </td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B] text-right">{epargne.detenteur || <span className="text-[#B9B9C3] italic">-</span>}</td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-[#F5F0FF]">
                      <tr>
                        <td className="px-3 py-2 text-sm font-bold text-[#5E5873]">
                          Total : <span className="text-[#28C76F]">{formatCurrency(client.autres_epargnes.reduce((sum: number, e: any) => sum + (Number(e.valeur) || 0), 0))}</span>
                        </td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </VuexySubSection>
            )}

            {/* Sous-section : Passifs */}
            {client.passifs && client.passifs.length > 0 && (
              <VuexySubSection
                title="Passifs & Emprunts"
                icon={<CreditCard size={14} />}
                count={client.passifs.length}
                color="red"
              >
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-[#EBE9F1]">
                    <thead className="bg-[#F8F8F8]">
                      <tr>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Nature</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Prêteur</th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-[#5E5873] uppercase">Montant remb.</th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-[#5E5873] uppercase">Capital restant dû</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-[#5E5873] uppercase">Durée restante</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-[#EBE9F1]">
                      {client.passifs.map((passif: any) => (
                        <tr key={passif.id} className="hover:bg-[#F8F8F8] transition-colors">
                          <td className="px-3 py-2 text-sm text-[#5E5873] font-medium">
                            {passif.nature || <span className="text-[#B9B9C3] italic">-</span>}
                            {passif.nature?.toLowerCase().includes('immobilier') && (
                              <span className="ml-2 text-xs bg-[#FF9F43]/20 text-[#FF9F43] px-2 py-0.5 rounded-full">Immo</span>
                            )}
                          </td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">{passif.preteur || <span className="text-[#B9B9C3] italic">-</span>}</td>
                          <td className="px-3 py-2 text-sm text-[#EA5455] text-right font-semibold">
                            {passif.montant_remboursement ? formatCurrency(passif.montant_remboursement) : <span className="text-[#B9B9C3] italic font-normal">-</span>}
                          </td>
                          <td className="px-3 py-2 text-sm text-[#EA5455] text-right font-semibold">
                            {passif.capital_restant_du ? formatCurrency(passif.capital_restant_du) : <span className="text-[#B9B9C3] italic font-normal">-</span>}
                          </td>
                          <td className="px-3 py-2 text-sm text-[#6E6B7B]">
                            {passif.duree_restante ? `${passif.duree_restante} mois` : <span className="text-[#B9B9C3] italic">-</span>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-[#FEF2F2]">
                      <tr>
                        <td colSpan={2} className="px-3 py-2 text-sm font-bold text-[#5E5873]">Total des emprunts :</td>
                        <td className="px-3 py-2 text-sm text-[#EA5455] text-right font-bold">
                          {formatCurrency(client.passifs.reduce((sum: number, p: any) => sum + (Number(p.montant_remboursement) || 0), 0))}/mois
                        </td>
                        <td className="px-3 py-2 text-sm text-[#EA5455] text-right font-bold">
                          {formatCurrency(client.passifs.reduce((sum: number, p: any) => sum + (Number(p.capital_restant_du) || 0), 0))}
                        </td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </VuexySubSection>
            )}

            {/* Synthèse Patrimoniale */}
            <div className="mt-4 pt-4 border-t border-[#EBE9F1]">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="text-center p-3 bg-[#E8FFFE] rounded-lg">
                  <div className="text-xs text-[#6E6B7B] mb-1 font-medium">Actifs Financiers</div>
                  <div className="text-lg font-bold text-[#00CFE8]">
                    {formatCurrency((client.actifs_financiers || []).reduce((sum: number, a: any) => sum + (Number(a.valeur_actuelle) || 0), 0))}
                  </div>
                </div>
                <div className="text-center p-3 bg-[#FFF7ED] rounded-lg">
                  <div className="text-xs text-[#6E6B7B] mb-1 font-medium">Actifs Immobiliers</div>
                  <div className="text-lg font-bold text-[#FF9F43]">
                    {formatCurrency((client.biens_immobiliers || []).reduce((sum: number, b: any) => sum + (Number(b.valeur_actuelle_estimee) || 0), 0))}
                  </div>
                </div>
                <div className="text-center p-3 bg-[#FEF2F2] rounded-lg">
                  <div className="text-xs text-[#6E6B7B] mb-1 font-medium">Total Emprunts</div>
                  <div className="text-lg font-bold text-[#EA5455]">
                    -{formatCurrency((client.passifs || []).reduce((sum: number, p: any) => sum + (Number(p.capital_restant_du) || 0), 0))}
                  </div>
                </div>
                <div className="text-center p-3 bg-gradient-to-br from-[#7367F0]/10 to-[#9055FD]/10 rounded-lg border-2 border-[#7367F0]/30">
                  <div className="text-xs text-[#6E6B7B] mb-1 font-medium">Patrimoine Net</div>
                  <div className="text-lg font-bold text-[#7367F0]">
                    {formatCurrency(
                      (client.actifs_financiers || []).reduce((sum: number, a: any) => sum + (Number(a.valeur_actuelle) || 0), 0) +
                      (client.biens_immobiliers || []).reduce((sum: number, b: any) => sum + (Number(b.valeur_actuelle_estimee) || 0), 0) +
                      (client.autres_epargnes || []).reduce((sum: number, e: any) => sum + (Number(e.valeur) || 0), 0) -
                      (client.passifs || []).reduce((sum: number, p: any) => sum + (Number(p.capital_restant_du) || 0), 0)
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </VuexyInfoSection>
      )}

      {/* Santé */}
      {showSante && (
        <VuexyInfoSection title="Santé" icon={<Heart size={18} />}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <VuexyInfoRow
              label="Contrat en place"
              value={client.sante_souhait?.contrat_en_place}
              empty={!client.sante_souhait?.contrat_en_place}
            />
            <VuexyInfoRow
              label="Budget mensuel max"
              value={client.sante_souhait?.budget_mensuel_maximum ? formatCurrency(client.sante_souhait.budget_mensuel_maximum) : undefined}
              empty={!client.sante_souhait?.budget_mensuel_maximum}
            />
            <VuexyInfoRow
              label="Hospitalisation"
              value={client.sante_souhait?.niveau_hospitalisation !== null && client.sante_souhait?.niveau_hospitalisation !== undefined ? `${client.sante_souhait.niveau_hospitalisation}/10` : undefined}
              empty={client.sante_souhait?.niveau_hospitalisation === null || client.sante_souhait?.niveau_hospitalisation === undefined}
            />
            <VuexyInfoRow
              label="Chambre particulière"
              value={client.sante_souhait?.niveau_chambre_particuliere !== null && client.sante_souhait?.niveau_chambre_particuliere !== undefined ? `${client.sante_souhait.niveau_chambre_particuliere}/10` : undefined}
              empty={client.sante_souhait?.niveau_chambre_particuliere === null || client.sante_souhait?.niveau_chambre_particuliere === undefined}
            />
            <VuexyInfoRow
              label="Médecin généraliste"
              value={client.sante_souhait?.niveau_medecin_generaliste !== null && client.sante_souhait?.niveau_medecin_generaliste !== undefined ? `${client.sante_souhait.niveau_medecin_generaliste}/10` : undefined}
              empty={client.sante_souhait?.niveau_medecin_generaliste === null || client.sante_souhait?.niveau_medecin_generaliste === undefined}
            />
            <VuexyInfoRow
              label="Analyses/Imagerie"
              value={client.sante_souhait?.niveau_analyses_imagerie !== null && client.sante_souhait?.niveau_analyses_imagerie !== undefined ? `${client.sante_souhait.niveau_analyses_imagerie}/10` : undefined}
              empty={client.sante_souhait?.niveau_analyses_imagerie === null || client.sante_souhait?.niveau_analyses_imagerie === undefined}
            />
            <VuexyInfoRow
              label="Auxiliaires médicaux"
              value={client.sante_souhait?.niveau_auxiliaires_medicaux !== null && client.sante_souhait?.niveau_auxiliaires_medicaux !== undefined ? `${client.sante_souhait.niveau_auxiliaires_medicaux}/10` : undefined}
              empty={client.sante_souhait?.niveau_auxiliaires_medicaux === null || client.sante_souhait?.niveau_auxiliaires_medicaux === undefined}
            />
            <VuexyInfoRow
              label="Pharmacie"
              value={client.sante_souhait?.niveau_pharmacie !== null && client.sante_souhait?.niveau_pharmacie !== undefined ? `${client.sante_souhait.niveau_pharmacie}/10` : undefined}
              empty={client.sante_souhait?.niveau_pharmacie === null || client.sante_souhait?.niveau_pharmacie === undefined}
            />
            <VuexyInfoRow
              label="Dentaire"
              value={client.sante_souhait?.niveau_dentaire !== null && client.sante_souhait?.niveau_dentaire !== undefined ? `${client.sante_souhait.niveau_dentaire}/10` : undefined}
              empty={client.sante_souhait?.niveau_dentaire === null || client.sante_souhait?.niveau_dentaire === undefined}
            />
            <VuexyInfoRow
              label="Optique"
              value={client.sante_souhait?.niveau_optique !== null && client.sante_souhait?.niveau_optique !== undefined ? `${client.sante_souhait.niveau_optique}/10` : undefined}
              empty={client.sante_souhait?.niveau_optique === null || client.sante_souhait?.niveau_optique === undefined}
            />
            <VuexyInfoRow
              label="Prothèses auditives"
              value={client.sante_souhait?.niveau_protheses_auditives !== null && client.sante_souhait?.niveau_protheses_auditives !== undefined ? `${client.sante_souhait.niveau_protheses_auditives}/10` : undefined}
              empty={client.sante_souhait?.niveau_protheses_auditives === null || client.sante_souhait?.niveau_protheses_auditives === undefined}
            />
          </div>
        </VuexyInfoSection>
      )}

      {/* Prévoyance */}
      {showPrevoyance && client.bae_prevoyance && (
        <VuexyInfoSection title="Prévoyance" icon={<Shield size={18} />}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <VuexyInfoRow
              label="Contrat en place"
              value={client.bae_prevoyance.contrat_en_place}
              empty={!client.bae_prevoyance.contrat_en_place}
            />
            <VuexyInfoRow
              label="Date d'effet"
              value={client.bae_prevoyance.date_effet ? formatDate(client.bae_prevoyance.date_effet) : undefined}
              empty={!client.bae_prevoyance.date_effet}
            />
            <VuexyInfoRow
              label="Cotisations mensuelles"
              value={client.bae_prevoyance.cotisations ? formatCurrency(client.bae_prevoyance.cotisations) : undefined}
              empty={!client.bae_prevoyance.cotisations}
            />
            <VuexyInfoRow
              label="Couverture invalidité"
              value={client.bae_prevoyance.souhaite_couverture_invalidite ? "Oui" : "Non"}
            />
            <VuexyInfoRow
              label="Revenu à garantir"
              value={client.bae_prevoyance.revenu_a_garantir ? formatCurrency(client.bae_prevoyance.revenu_a_garantir) : undefined}
              empty={!client.bae_prevoyance.revenu_a_garantir}
            />
            <VuexyInfoRow
              label="Capital décès"
              value={client.bae_prevoyance.capital_deces_souhaite ? formatCurrency(client.bae_prevoyance.capital_deces_souhaite) : undefined}
              empty={!client.bae_prevoyance.capital_deces_souhaite}
            />
            <VuexyInfoRow
              label="Garanties obsèques"
              value={client.bae_prevoyance.garanties_obseques ? formatCurrency(client.bae_prevoyance.garanties_obseques) : undefined}
              empty={!client.bae_prevoyance.garanties_obseques}
            />
            <VuexyInfoRow
              label="Rente enfants"
              value={client.bae_prevoyance.rente_enfants ? formatCurrency(client.bae_prevoyance.rente_enfants) : undefined}
              empty={!client.bae_prevoyance.rente_enfants}
            />
            <VuexyInfoRow
              label="Rente conjoint"
              value={client.bae_prevoyance.rente_conjoint ? formatCurrency(client.bae_prevoyance.rente_conjoint) : undefined}
              empty={!client.bae_prevoyance.rente_conjoint}
            />
          </div>
        </VuexyInfoSection>
      )}

      {/* Retraite */}
      {showRetraite && client.bae_retraite && (
        <VuexyInfoSection title="Retraite" icon={<TrendingUp size={18} />}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <VuexyInfoRow
              label="Revenus annuels"
              value={client.bae_retraite.revenus_annuels ? formatCurrency(client.bae_retraite.revenus_annuels) : undefined}
              empty={!client.bae_retraite.revenus_annuels}
            />
            <VuexyInfoRow
              label="Revenus foyer"
              value={client.bae_retraite.revenus_annuels_foyer ? formatCurrency(client.bae_retraite.revenus_annuels_foyer) : undefined}
              empty={!client.bae_retraite.revenus_annuels_foyer}
            />
            <VuexyInfoRow
              label="Impôt sur le revenu"
              value={client.bae_retraite.impot_revenu ? formatCurrency(client.bae_retraite.impot_revenu) : undefined}
              empty={!client.bae_retraite.impot_revenu}
            />
            <VuexyInfoRow
              label="Parts fiscales"
              value={client.bae_retraite.nombre_parts_fiscales}
              empty={!client.bae_retraite.nombre_parts_fiscales}
            />
            <VuexyInfoRow
              label="TMI"
              value={client.bae_retraite.tmi}
              empty={!client.bae_retraite.tmi}
            />
            <VuexyInfoRow
              label="Âge départ retraite"
              value={client.bae_retraite.age_depart_retraite ? `${client.bae_retraite.age_depart_retraite} ans` : undefined}
              empty={!client.bae_retraite.age_depart_retraite}
            />
            <VuexyInfoRow
              label="Âge départ retraite conjoint"
              value={client.bae_retraite.age_depart_retraite_conjoint ? `${client.bae_retraite.age_depart_retraite_conjoint} ans` : undefined}
              empty={!client.bae_retraite.age_depart_retraite_conjoint}
            />
            <VuexyInfoRow
              label="Revenu à maintenir"
              value={client.bae_retraite.pourcentage_revenu_a_maintenir ? `${client.bae_retraite.pourcentage_revenu_a_maintenir}%` : undefined}
              empty={!client.bae_retraite.pourcentage_revenu_a_maintenir}
            />
            <VuexyInfoRow
              label="Contrat en place"
              value={client.bae_retraite.contrat_en_place}
              empty={!client.bae_retraite.contrat_en_place}
            />
            <VuexyInfoRow
              label="Cotisations annuelles"
              value={client.bae_retraite.cotisations_annuelles ? formatCurrency(client.bae_retraite.cotisations_annuelles) : undefined}
              empty={!client.bae_retraite.cotisations_annuelles}
            />
          </div>
        </VuexyInfoSection>
      )}

    </div>
  );
};
