import React from 'react';
import { Plus, Pencil, ExternalLink, FileText, Heart, Umbrella, TrendingUp, PiggyBank, Home, Shield } from 'lucide-react';
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

const CONTRAT_TYPES: Array<{
  key: ClientContrat['type'];
  label: string;
  icon: React.ReactNode;
  color: string;
  gradient: string;
  fields: Array<{ key: 'mensualite' | 'en_cours' | 'fond_euro' | 'uc' | 'versement_programme'; label: string }>;
}> = [
  {
    key: 'sante',
    label: 'Santé',
    icon: <Heart size={18} />,
    color: 'text-[#EA5455]',
    gradient: 'from-[#EA5455] to-[#FF6B6B]',
    fields: [{ key: 'mensualite', label: 'Mensualité' }],
  },
  {
    key: 'prevoyance',
    label: 'Prévoyance',
    icon: <Umbrella size={18} />,
    color: 'text-[#7367F0]',
    gradient: 'from-[#7367F0] to-[#9055FD]',
    fields: [{ key: 'mensualite', label: 'Mensualité' }],
  },
  {
    key: 'per',
    label: 'PER',
    icon: <TrendingUp size={18} />,
    color: 'text-[#28C76F]',
    gradient: 'from-[#28C76F] to-[#48DA89]',
    fields: [
      { key: 'en_cours', label: 'En-cours' },
      { key: 'fond_euro', label: 'Fonds euro' },
      { key: 'uc', label: 'UC' },
      { key: 'versement_programme', label: 'Versement programmé' },
    ],
  },
  {
    key: 'assurance_vie',
    label: 'Assurance Vie',
    icon: <PiggyBank size={18} />,
    color: 'text-[#FF9F43]',
    gradient: 'from-[#FF9F43] to-[#FFB976]',
    fields: [
      { key: 'en_cours', label: 'En-cours' },
      { key: 'fond_euro', label: 'Fonds euro' },
      { key: 'uc', label: 'UC' },
      { key: 'versement_programme', label: 'Versement programmé' },
    ],
  },
  {
    key: 'emprunteur',
    label: 'Emprunteur',
    icon: <Home size={18} />,
    color: 'text-[#00CFE8]',
    gradient: 'from-[#00CFE8] to-[#1DE9B6]',
    fields: [],
  },
  {
    key: 'vie_entiere',
    label: 'Vie Entière',
    icon: <Shield size={18} />,
    color: 'text-[#9055FD]',
    gradient: 'from-[#9055FD] to-[#B085FF]',
    fields: [{ key: 'versement_programme', label: 'Versement programmé' }],
  },
];

interface ContratCardProps {
  typeConfig: typeof CONTRAT_TYPES[number];
  contrat: ClientContrat | null;
  onEdit: () => void;
  onAdd: () => void;
}

const ContratCard: React.FC<ContratCardProps> = ({ typeConfig, contrat, onEdit, onAdd }) => {
  const hasData = contrat !== null;

  return (
    <div className="vx-card p-0 overflow-hidden">
      {/* Header */}
      <div className="px-5 py-4 border-b border-[#EBE9F1] flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className={`w-9 h-9 rounded-lg bg-gradient-to-br ${typeConfig.gradient} flex items-center justify-center text-white flex-shrink-0`}>
            {typeConfig.icon}
          </div>
          <div>
            <h4 className="font-semibold text-[#5E5873] text-sm">{typeConfig.label}</h4>
            {hasData && contrat.assureur && (
              <p className="text-xs text-[#6E6B7B]">{contrat.assureur.nom}</p>
            )}
          </div>
        </div>
        {hasData ? (
          <button
            onClick={onEdit}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#F3F2F7] text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-colors"
          >
            <Pencil size={12} />
            Modifier
          </button>
        ) : (
          <button
            onClick={onAdd}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#7367F0]/10 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-colors"
          >
            <Plus size={12} />
            Ajouter
          </button>
        )}
      </div>

      {/* Content */}
      <div className="px-5 py-4">
        {!hasData ? (
          <p className="text-sm text-[#B9B9C3] italic">Aucun contrat renseigné</p>
        ) : (
          <div className="space-y-2.5">
            {/* Lien espace assureur */}
            {contrat.assureur?.lien_espace_client && (
              <a
                href={contrat.assureur.lien_espace_client}
                target="_blank"
                rel="noopener noreferrer"
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-[#7367F0]/20 bg-[#7367F0]/5 text-[#7367F0] hover:bg-[#7367F0]/10 transition-colors`}
              >
                <ExternalLink size={12} />
                Espace {contrat.assureur.nom}
              </a>
            )}

            {/* Champs financiers */}
            {typeConfig.fields.map((field) => {
              const val = contrat[field.key];
              if (val === null || val === undefined) return null;
              return (
                <div key={field.key} className="flex items-center justify-between">
                  <span className="text-sm text-[#6E6B7B]">{field.label}</span>
                  <span className="text-sm font-semibold text-[#5E5873]">{formatCurrency(val)}</span>
                </div>
              );
            })}

            {/* Emprunteur : aucun champ financier */}
            {typeConfig.fields.length === 0 && (
              <p className="text-sm text-[#B9B9C3] italic">Contrat renseigné</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export const VuexyContratsSection: React.FC<VuexyContratsSectionProps> = ({
  client,
  onEditSection,
}) => {
  const contrats: ClientContrat[] = client.contrats || [];

  const getContratByType = (type: ClientContrat['type']): ClientContrat | null => {
    return contrats.find((c) => c.type === type) || null;
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="vx-card p-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white">
            <FileText size={20} />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-[#5E5873]">Contrats souscrits</h3>
            <p className="text-sm text-[#6E6B7B]">
              {contrats.length > 0 ? `${contrats.length} contrat${contrats.length > 1 ? 's' : ''} renseigné${contrats.length > 1 ? 's' : ''}` : 'Gérez les contrats d\'assurance du client'}
            </p>
          </div>
        </div>
      </div>

      {/* Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {CONTRAT_TYPES.map((typeConfig) => {
          const contrat = getContratByType(typeConfig.key);
          return (
            <ContratCard
              key={typeConfig.key}
              typeConfig={typeConfig}
              contrat={contrat}
              onEdit={() => contrat && onEditSection('contrat' as SectionType, { ...contrat, type: typeConfig.key }, false)}
              onAdd={() => onEditSection('contrat' as SectionType, { type: typeConfig.key }, true)}
            />
          );
        })}
      </div>
    </div>
  );
};

export default VuexyContratsSection;
