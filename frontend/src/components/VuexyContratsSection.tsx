import React from 'react';
import { Plus, Pencil, ExternalLink, FileText, Heart, Umbrella, TrendingUp, PiggyBank, Home, Shield, Hash, Package } from 'lucide-react';
import type { SectionType } from './SectionEditModal';

interface Assureur {
  id: number;
  nom: string;
  lien_espace_client: string | null;
}

interface ClientContrat {
  id: number;
  type: 'sante' | 'prevoyance' | 'per' | 'assurance_vie' | 'emprunteur' | 'vie_entiere';
  assureur_id: number | null;
  assureur?: Assureur | null;
  numero_contrat?: string | null;
  produit?: string | null;
  mensualite: number | null;
  en_cours: number | null;
  fond_euro: number | null;
  uc: number | null;
  versement_programme: number | null;
}

interface VuexyContratsSectionProps {
  client: any;
  onEditSection: (sectionType: SectionType, data?: any, isNew?: boolean) => void;
}

const formatCurrency = (amount: number | null | undefined): string => {
  if (amount === null || amount === undefined) return '—';
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(amount);
};

const TYPE_CONFIG: Record<ClientContrat['type'], {
  label: string;
  icon: React.ReactNode;
  color: string;
  gradient: string;
  fields: Array<{ key: keyof Pick<ClientContrat, 'mensualite' | 'en_cours' | 'fond_euro' | 'uc' | 'versement_programme'>; label: string }>;
}> = {
  sante: {
    label: 'Santé',
    icon: <Heart size={16} />,
    color: 'text-[#EA5455]',
    gradient: 'from-[#EA5455] to-[#FF6B6B]',
    fields: [{ key: 'mensualite', label: 'Mensualité' }],
  },
  prevoyance: {
    label: 'Prévoyance',
    icon: <Umbrella size={16} />,
    color: 'text-[#7367F0]',
    gradient: 'from-[#7367F0] to-[#9055FD]',
    fields: [{ key: 'mensualite', label: 'Mensualité' }],
  },
  per: {
    label: 'PER',
    icon: <TrendingUp size={16} />,
    color: 'text-[#28C76F]',
    gradient: 'from-[#28C76F] to-[#48DA89]',
    fields: [
      { key: 'en_cours', label: 'En-cours' },
      { key: 'fond_euro', label: 'Fonds euro' },
      { key: 'uc', label: 'UC' },
      { key: 'versement_programme', label: 'Versement programmé' },
    ],
  },
  assurance_vie: {
    label: 'Assurance Vie',
    icon: <PiggyBank size={16} />,
    color: 'text-[#FF9F43]',
    gradient: 'from-[#FF9F43] to-[#FFB976]',
    fields: [
      { key: 'en_cours', label: 'En-cours' },
      { key: 'fond_euro', label: 'Fonds euro' },
      { key: 'uc', label: 'UC' },
      { key: 'versement_programme', label: 'Versement programmé' },
    ],
  },
  emprunteur: {
    label: 'Emprunteur',
    icon: <Home size={16} />,
    color: 'text-[#00CFE8]',
    gradient: 'from-[#00CFE8] to-[#1DE9B6]',
    fields: [],
  },
  vie_entiere: {
    label: 'Vie Entière',
    icon: <Shield size={16} />,
    color: 'text-[#9055FD]',
    gradient: 'from-[#9055FD] to-[#B085FF]',
    fields: [{ key: 'versement_programme', label: 'Versement programmé' }],
  },
};

const TYPE_ORDER: ClientContrat['type'][] = ['sante', 'prevoyance', 'per', 'assurance_vie', 'emprunteur', 'vie_entiere'];

interface ContratRowProps {
  contrat: ClientContrat;
  cfg: typeof TYPE_CONFIG[ClientContrat['type']];
  onEdit: () => void;
}

const ContratRow: React.FC<ContratRowProps> = ({ contrat, cfg, onEdit }) => {
  const visibleFields = cfg.fields.filter(f => contrat[f.key] !== null && contrat[f.key] !== undefined);

  return (
    <div className="border border-[#EBE9F1] rounded-xl p-4 hover:border-[#7367F0]/30 transition-colors bg-white">
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-start gap-3 min-w-0 flex-1">
          {/* Assureur + produit */}
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
              {contrat.assureur ? (
                <span className="text-sm font-semibold text-[#5E5873]">{contrat.assureur.nom}</span>
              ) : (
                <span className="text-sm font-semibold text-[#B9B9C3]">Assureur non renseigné</span>
              )}
              {contrat.produit && (
                <>
                  <span className="text-xs text-[#B9B9C3]">·</span>
                  <span className="text-xs text-[#6E6B7B] flex items-center gap-1">
                    <Package size={11} className="flex-shrink-0" />
                    {contrat.produit}
                  </span>
                </>
              )}
            </div>

            {/* Numéro contrat */}
            {contrat.numero_contrat && (
              <div className="flex items-center gap-1 mt-0.5">
                <Hash size={11} className="text-[#B9B9C3] flex-shrink-0" />
                <span className="text-xs text-[#B9B9C3] font-mono">{contrat.numero_contrat}</span>
              </div>
            )}

            {/* Champs financiers */}
            {visibleFields.length > 0 && (
              <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2">
                {visibleFields.map(f => (
                  <div key={f.key} className="flex items-center gap-1.5">
                    <span className="text-xs text-[#B9B9C3]">{f.label}</span>
                    <span className="text-xs font-semibold text-[#5E5873]">{formatCurrency(contrat[f.key] as number)}</span>
                  </div>
                ))}
              </div>
            )}

            {/* Lien espace client */}
            {contrat.assureur?.lien_espace_client && (
              <a
                href={contrat.assureur.lien_espace_client}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 mt-2 text-xs text-[#7367F0] hover:underline"
              >
                <ExternalLink size={11} />
                Espace {contrat.assureur.nom}
              </a>
            )}
          </div>
        </div>

        <button
          onClick={onEdit}
          className="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-[#F3F2F7] text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-colors"
        >
          <Pencil size={11} />
          Modifier
        </button>
      </div>
    </div>
  );
};

export const VuexyContratsSection: React.FC<VuexyContratsSectionProps> = ({
  client,
  onEditSection,
}) => {
  const contrats: ClientContrat[] = client.contrats || [];

  const grouped = TYPE_ORDER.reduce<Record<string, ClientContrat[]>>((acc, type) => {
    acc[type] = contrats.filter(c => c.type === type);
    return acc;
  }, {} as Record<string, ClientContrat[]>);

  const totalContrats = contrats.length;
  const typesWithContrats = TYPE_ORDER.filter(t => (grouped[t]?.length ?? 0) > 0);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="vx-card p-5">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white">
              <FileText size={20} />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-[#5E5873]">Contrats souscrits</h3>
              <p className="text-sm text-[#6E6B7B]">
                {totalContrats > 0
                  ? `${totalContrats} contrat${totalContrats > 1 ? 's' : ''} — ${typesWithContrats.map(t => TYPE_CONFIG[t].label).join(', ')}`
                  : "Gérez les contrats d'assurance du client"}
              </p>
            </div>
          </div>
          <button
            onClick={() => onEditSection('contrat' as SectionType, {}, true)}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-[#7367F0]/10 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-colors"
          >
            <Plus size={14} />
            Ajouter
          </button>
        </div>
      </div>

      {/* Groupes par type — uniquement les types avec contrats, puis les vides */}
      {TYPE_ORDER.map(type => {
        const cfg = TYPE_CONFIG[type];
        const list = grouped[type] ?? [];

        return (
          <div key={type} className="vx-card p-0 overflow-hidden">
            {/* En-tête du groupe */}
            <div className="px-5 py-3 border-b border-[#EBE9F1] flex items-center justify-between bg-[#F8F8F8]">
              <div className="flex items-center gap-2">
                <div className={`w-7 h-7 rounded-lg bg-gradient-to-br ${cfg.gradient} flex items-center justify-center text-white`}>
                  {cfg.icon}
                </div>
                <span className="text-sm font-semibold text-[#5E5873]">{cfg.label}</span>
                {list.length > 0 && (
                  <span className="text-xs text-white bg-[#7367F0] rounded-full px-2 py-0.5">{list.length}</span>
                )}
              </div>
              <button
                onClick={() => onEditSection('contrat' as SectionType, { type }, true)}
                className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-[#7367F0]/10 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-colors"
              >
                <Plus size={11} />
                Ajouter
              </button>
            </div>

            {/* Contrats ou état vide */}
            <div className="px-4 py-3 space-y-2">
              {list.length === 0 ? (
                <p className="text-sm text-[#B9B9C3] italic py-1">Aucun contrat renseigné</p>
              ) : (
                list.map(contrat => (
                  <ContratRow
                    key={contrat.id}
                    contrat={contrat}
                    cfg={cfg}
                    onEdit={() => onEditSection('contrat' as SectionType, { ...contrat, type }, false)}
                  />
                ))
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
};

export default VuexyContratsSection;