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
  Pencil,
  Trash2,
} from 'lucide-react';
import { VuexyInfoSection, VuexyInfoRow } from './VuexyInfoSection';
import { VuexyStatCard } from './VuexyStatCard';
import { VuexyPatrimoineSection } from './VuexyPatrimoineSection';
import type { SectionType } from './SectionEditModal';

interface VuexyClientInfoSectionProps {
  client: any;
  formatDate: (date?: string) => string;
  formatCurrency: (amount?: number) => string;
  onEditSection?: (sectionType: SectionType, data?: any, isNew?: boolean) => void;
  onDeleteItem?: (type: 'enfant' | 'revenu' | 'conjoint' | 'actif' | 'bien' | 'passif' | 'epargne', id: number) => void;
  onDeleteBaeDetail?: (
    field: 'actifs_financiers_details' | 'actifs_immo_details' | 'actifs_autres_details' | 'passifs_details',
    index: number
  ) => void;
}

export const VuexyClientInfoSection: React.FC<VuexyClientInfoSectionProps> = ({
  client,
  formatDate,
  formatCurrency,
  onEditSection,
  onDeleteItem,
  onDeleteBaeDetail,
}) => {
  // Calcul des revenus annuels depuis le tableau client.revenus (prioritaire)
  // Fallback sur les champs uniques pour compatibilité avec anciennes données
  const calculateRevenusAnnuels = (): number | null => {
    // 1. PRIORITÉ : Calculer depuis le tableau des revenus (client_revenus)
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

    // 2. FALLBACK : Champ unique revenus_annuels (anciennes données)
    if (client.revenus_annuels && client.revenus_annuels > 0) {
      return client.revenus_annuels;
    }

    // 3. FALLBACK : Revenus dans bae_retraite
    if (client.bae_retraite?.revenus_annuels && client.bae_retraite.revenus_annuels > 0) {
      return client.bae_retraite.revenus_annuels;
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

  const showAudioConsent = client.consentement_audio !== undefined;

  const buildBesoinLabels = (): string[] => {
    const labels: string[] = [];
    const needs: string[] = Array.isArray(client.besoins) ? client.besoins : [];

    const isRetraiteBesoinText = (besoin: string) => {
      const text = besoin.toLowerCase();
      if (text.includes('retraite') || text.includes('plan epargne retraite') || text.includes('plan épargne retraite')) {
        return true;
      }
      return /\bper\b/.test(text);
    };

    const matches = (keywords: string[]) =>
      needs.some((besoin) => keywords.some((keyword) => besoin.toLowerCase().includes(keyword)));

    if (matches(['santé', 'sante', 'mutuelle', 'complémentaire', 'complementaire'])) {
      labels.push('Santé');
    }
    if (matches(['prévoyance', 'prevoyance', 'décès', 'deces', 'invalidité', 'invalidite', 'obsèques', 'obseques'])) {
      labels.push('Prévoyance');
    }
    if (matches(['retraite', 'per', 'pension'])) {
      labels.push('Retraite');
    }
    if (matches(['emprunt', 'emprunteur', 'crédit', 'credit', 'assurance emprunteur'])) {
      labels.push('Emprunteur');
    }
    const isEpargneBesoin = needs.some((besoin) => {
      const lower = besoin.toLowerCase();
      if (isRetraiteBesoinText(lower)) {
        return false;
      }
      return ['epargne', 'épargne', 'patrimoine', 'placement', 'investissement', 'assurance vie', 'pea', 'livret', 'immobilier']
        .some((keyword) => lower.includes(keyword));
    });

    if (isEpargneBesoin) {
      labels.push('Épargne');
    }

    if (showSante && !labels.includes('Santé')) {
      labels.push('Santé');
    }
    if (showPrevoyance && !labels.includes('Prévoyance')) {
      labels.push('Prévoyance');
    }
    if (showRetraite && !labels.includes('Retraite')) {
      labels.push('Retraite');
    }
    if (showEpargneSection && !labels.includes('Épargne')) {
      labels.push('Épargne');
    }

    const normalized = needs
      .map((besoin) => besoin.trim())
      .filter((besoin) => besoin.length > 0);

    if (labels.length === 0 && normalized.length > 0) {
      return normalized.map((besoin) => besoin[0].toUpperCase() + besoin.slice(1));
    }

    return labels;
  };

  const besoinLabels = buildBesoinLabels();
  const renderBesoinBadges = () => {
    if (besoinLabels.length === 0) {
      return (
        <span className="inline-flex items-center rounded-full bg-[#F2F0FF] px-4 py-1 text-xs font-semibold text-[#6F67F4]">
          0 besoins
        </span>
      );
    }

    return (
      <div className="mt-3 flex flex-wrap gap-2">
        {besoinLabels.map((label) => (
          <span
            key={label}
            className="inline-flex items-center rounded-full bg-[#F2F0FF] px-4 py-1 text-xs font-semibold text-[#6F67F4]"
          >
            {label}
          </span>
        ))}
      </div>
    );
  };

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
          footer={renderBesoinBadges()}
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
        <VuexyInfoSection
          title="État Civil"
          icon={<User size={18} />}
          onEdit={onEditSection ? () => onEditSection('etat_civil', client) : undefined}
        >
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
        <VuexyInfoSection
          title="Coordonnées"
          icon={<MapPin size={18} />}
          onEdit={onEditSection ? () => onEditSection('coordonnees', client) : undefined}
        >
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
        <VuexyInfoSection
          title="Informations Professionnelles"
          icon={<Briefcase size={18} />}
          onEdit={onEditSection ? () => onEditSection('professionnel', client) : undefined}
        >
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
        <VuexyInfoSection
          title="Mode de Vie"
          icon={<Activity size={18} />}
          onEdit={onEditSection ? () => onEditSection('mode_vie', client) : undefined}
        >
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
        <VuexyInfoSection
          title="Informations Entreprise"
          icon={<Building size={18} />}
          onEdit={onEditSection ? () => onEditSection('entreprise', client) : undefined}
        >
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
      {client.conjoint ? (
        <VuexyInfoSection
          title="Conjoint"
          icon={<Heart size={18} />}
          onEdit={onEditSection ? () => onEditSection('conjoint', client.conjoint) : undefined}
        >
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
      ) : onEditSection && (
        <VuexyInfoSection
          title="Conjoint"
          icon={<Heart size={18} />}
          onAdd={() => onEditSection('conjoint', {}, true)}
          addLabel="Ajouter un conjoint"
        >
          <div className="text-center py-6 text-[#B9B9C3]">
            <Heart size={32} className="mx-auto mb-2 opacity-50" />
            <p>Aucun conjoint enregistré</p>
          </div>
        </VuexyInfoSection>
      )}

      {/* Enfants */}
      <VuexyInfoSection
        title={`Enfants${client.enfants?.length > 0 ? ` (${client.enfants.length})` : ''}`}
        icon={<Users size={18} />}
        onAdd={onEditSection ? () => onEditSection('enfant', {}, true) : undefined}
        addLabel="Ajouter"
      >
        {client.enfants && client.enfants.length > 0 ? (
          <div className="space-y-4">
            {client.enfants.map((enfant: any, index: number) => (
              <div
                key={enfant.id}
                className="p-5 bg-[#F8F8F8] rounded-lg border border-[#EBE9F1] relative group"
              >
                {/* Boutons d'action sur hover */}
                <div className="absolute top-3 right-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  {onEditSection && (
                    <button
                      onClick={() => onEditSection('enfant', enfant)}
                      className="w-7 h-7 rounded-md flex items-center justify-center bg-white text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200 shadow-sm"
                      title="Modifier"
                    >
                      <Pencil size={14} />
                    </button>
                  )}
                  {onDeleteItem && (
                    <button
                      onClick={() => onDeleteItem('enfant', enfant.id)}
                      className="w-7 h-7 rounded-md flex items-center justify-center bg-white text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200 shadow-sm"
                      title="Supprimer"
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
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
        ) : (
          <div className="text-center py-6 text-[#B9B9C3]">
            <Users size={32} className="mx-auto mb-2 opacity-50" />
            <p>Aucun enfant enregistré</p>
          </div>
        )}
      </VuexyInfoSection>

      {/* Revenus */}
      <VuexyInfoSection
        title={`Revenus${client.revenus?.length > 0 ? ` (${client.revenus.length})` : ''}`}
        icon={<DollarSign size={18} />}
        onAdd={onEditSection ? () => onEditSection('revenu', {}, true) : undefined}
        addLabel="Ajouter"
      >
        {client.revenus && client.revenus.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-[#EBE9F1]">
              <thead className="bg-[#F8F8F8]">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Nature</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Périodicité</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-[#5E5873] uppercase tracking-wider">Montant</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-[#5E5873] uppercase tracking-wider w-24">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-[#EBE9F1]">
                {client.revenus.map((revenu: any) => (
                  <tr key={revenu.id} className="hover:bg-[#F8F8F8] transition-colors group">
                    <td className="px-4 py-3 text-sm text-[#5E5873] font-medium">
                        {revenu.nature ? (
                          <>
                            {revenu.nature}
                            {revenu.nature.toLowerCase() === 'autre' && revenu.details && (
                              <span className="text-[#6E6B7B] font-normal"> ({revenu.details})</span>
                            )}
                          </>
                        ) : (
                          <span className="text-[#B9B9C3] italic">Non renseigné</span>
                        )}
                      </td>
                    <td className="px-4 py-3 text-sm text-[#6E6B7B]">{revenu.periodicite || <span className="text-[#B9B9C3] italic">Non renseigné</span>}</td>
                    <td className="px-4 py-3 text-sm text-[#5E5873] text-right font-semibold">
                      {revenu.montant ? formatCurrency(revenu.montant) : <span className="text-[#B9B9C3] italic font-normal">Non renseigné</span>}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onEditSection && (
                          <button
                            onClick={() => onEditSection('revenu', revenu)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
                            title="Modifier"
                          >
                            <Pencil size={14} />
                          </button>
                        )}
                        {onDeleteItem && (
                          <button
                            onClick={() => onDeleteItem('revenu', revenu.id)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="text-center py-6 text-[#B9B9C3]">
            <DollarSign size={32} className="mx-auto mb-2 opacity-50" />
            <p>Aucun revenu enregistré</p>
          </div>
        )}
      </VuexyInfoSection>

      {showAudioConsent && (
        <VuexyInfoSection
          title="Consentement"
          icon={<Activity size={18} />}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <VuexyInfoRow
              label="Enregistrement accepté"
              value={
                client.consentement_audio
                  ? 'Oui, le client a accepté'
                  : 'Non, le client a refusé'
              }
              empty={false}
            />
          </div>
        </VuexyInfoSection>
      )}

      {/* ========================================
          SECTION MÈRE : ÉPARGNE & PATRIMOINE (UNIFIÉE)
          ======================================== */}
      {showEpargneSection && (
        <VuexyPatrimoineSection
          client={client}
          formatDate={formatDate}
          formatCurrency={formatCurrency}
          onEditItem={onEditSection}
          onDeleteItem={onDeleteItem}
          onDeleteBaeDetail={onDeleteBaeDetail}
        />
      )}

      {/* Santé */}
      {showSante && (
        <VuexyInfoSection
          title="Santé"
          icon={<Heart size={18} />}
          onEdit={onEditSection ? () => onEditSection('sante', client.sante_souhait || {}) : undefined}
        >
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
        <VuexyInfoSection
          title="Prévoyance"
          icon={<Shield size={18} />}
          onEdit={onEditSection ? () => onEditSection('prevoyance', client.bae_prevoyance) : undefined}
        >
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
        <VuexyInfoSection
          title="Retraite"
          icon={<TrendingUp size={18} />}
          onEdit={onEditSection ? () => onEditSection('retraite', client.bae_retraite) : undefined}
        >
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
              value={(() => {
                const value = client.bae_retraite.contrat_en_place;
                if (!value) {
                  return undefined;
                }
                const lowered = value.toLowerCase();
                if (/\bper\b/.test(lowered) || lowered.includes('plan epargne retraite') || lowered.includes('plan épargne retraite')) {
                  return undefined;
                }
                const etablissement = client.bae_retraite.designation_etablissement;
                if (etablissement) {
                  return `${value} (${etablissement})`;
                }
                return value;
              })()}
              empty={
                !client.bae_retraite.contrat_en_place
                || /\bper\b/i.test(client.bae_retraite.contrat_en_place)
                || client.bae_retraite.contrat_en_place.toLowerCase().includes('plan epargne retraite')
                || client.bae_retraite.contrat_en_place.toLowerCase().includes('plan épargne retraite')
              }
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
