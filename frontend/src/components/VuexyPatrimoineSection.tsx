import React, { useState, useMemo } from 'react';
import {
  Home,
  CreditCard,
  ChevronDown,
  PieChart,
  Layers,
  LineChart,
  Coins,
  Plus,
  Pencil,
  Trash2,
} from 'lucide-react';
import type { SectionType } from './SectionEditModal';

interface PatrimoineSectionProps {
  client: any;
  formatDate: (date?: string) => string;
  formatCurrency: (amount?: number) => string;
  onEditItem?: (type: SectionType, data?: any, isNew?: boolean) => void;
  onDeleteItem?: (type: 'actif' | 'bien' | 'passif' | 'epargne', id: number) => void;
  onDeleteBaeDetail?: (
    field: 'actifs_financiers_details' | 'actifs_immo_details' | 'actifs_autres_details' | 'passifs_details',
    index: number
  ) => void;
}

// Helper pour parser un détail BAE (format: "nature: montant" ou juste "nature")
const parseBaeDetailItem = (item: unknown): { nature: string; montant: number | null } | null => {
  if (item === null || item === undefined) return null;
  if (typeof item !== 'string') {
    const value = String(item).trim();
    return value ? { nature: value, montant: null } : null;
  }

  const raw = item.trim();
  if (!raw) return null;

  // Format "nature: montant" ou "nature : montant"
  const match = raw.match(/^(.+?)\s*:\s*(\d+(?:[.,]\d+)?)\s*€?$/);
  if (match) {
    return {
      nature: match[1].trim(),
      montant: parseFloat(match[2].replace(',', '.')),
    };
  }

  // Format avec montant à la fin sans séparateur
  const matchEnd = raw.match(/^(.+?)\s+(\d+(?:[.,]\d+)?)\s*€?$/);
  if (matchEnd) {
    return {
      nature: matchEnd[1].trim(),
      montant: parseFloat(matchEnd[2].replace(',', '.')),
    };
  }

  return { nature: raw, montant: null };
};

const parseBaeDetails = (details: string[] | null | undefined): { nature: string; montant: number | null }[] => {
  if (!details || !Array.isArray(details)) return [];
  return details
    .map(parseBaeDetailItem)
    .filter((item): item is { nature: string; montant: number | null } => Boolean(item?.nature));
};

const normalizeTextKey = (value: string): string => {
  const normalized = value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
  return normalized.replace(/\s+/g, ' ');
};

const canonicalizeNature = (value: string): string => {
  const normalized = normalizeTextKey(value);
  if (!normalized) return value.trim();
  if (normalized.includes('assurance vie')) return 'assurance-vie';
  if (normalized.includes('compte titre')) return 'compte-titres';
  if (normalized.includes('livret') && normalized.includes('a')) return 'livret A';
  if (normalized.includes('ldds') || normalized === 'ldd') return 'LDDS';
  if (normalized.includes('lep')) return 'LEP';
  if (normalized.includes('pea')) return 'PEA';
  if (normalized.includes('scpi')) return 'SCPI';
  if (normalized.includes('opcvm')) return 'OPCVM';
  if (normalized.includes('pel')) return 'PEL';
  if (normalized.includes('cel')) return 'CEL';
  if (normalized.includes('action')) return 'actions';
  return value.trim();
};

const isCryptoNature = (nature: string): boolean => {
  const lower = normalizeTextKey(nature);
  return (
    lower.includes('crypto') ||
    lower.includes('bitcoin') ||
    lower.includes('btc') ||
    lower.includes('ethereum') ||
    lower.includes('eth') ||
    lower.includes('nft') ||
    lower.includes('token')
  );
};

type ActifFinancierRow = {
  source: 'table' | 'bae';
  id?: number;
  nature: string;
  etablissement?: string | null;
  valeur?: number | null;
  detenteur?: string | null;
  date?: string | null;
  raw?: any;
  baeField?: 'actifs_financiers_details';
  baeIndex?: number;
};

type AutreActifRow = {
  source: 'table' | 'bae' | 'moved';
  id?: number;
  nature: string;
  etablissement?: string | null;
  valeur?: number | null;
  date?: string | null;
  raw?: any;
  originType?: 'actif';
  baeField?: 'actifs_autres_details' | 'actifs_financiers_details';
  baeIndex?: number;
};

const buildDedupKey = (nature: string, etablissement?: string | null, valeur?: number | null, date?: string | null): string => {
  const natureKey = canonicalizeNature(nature || '');
  const etabKey = normalizeTextKey(etablissement || '');
  const valueKey = typeof valeur === 'number' && !Number.isNaN(valeur) ? valeur.toFixed(2) : '';
  const dateKey = date ? normalizeTextKey(date) : '';
  return [natureKey, etabKey, valueKey, dateKey].join('|');
};

const dedupeByKey = <T,>(items: T[], getKey: (item: T) => string): T[] => {
  const map = new Map<string, T>();
  items.forEach(item => {
    const key = getKey(item);
    if (!map.has(key)) {
      map.set(key, item);
    }
  });
  return Array.from(map.values());
};

// Stat Card Component - Design avec bordure gauche colorée
const StatCard: React.FC<{
  label: string;
  value: string;
  color: 'info' | 'warning' | 'danger' | 'primary';
  delay?: number;
}> = ({ label, value, color, delay = 0 }) => {
  const colorClasses = {
    info: {
      border: 'border-l-[#00CFE8]',
      text: 'text-[#00CFE8]',
    },
    warning: {
      border: 'border-l-[#FF9F43]',
      text: 'text-[#FF9F43]',
    },
    danger: {
      border: 'border-l-[#EA5455]',
      text: 'text-[#EA5455]',
    },
    primary: {
      border: 'border-l-[#7367F0]',
      text: 'text-[#7367F0]',
    },
  };

  return (
    <div
      className={`bg-white rounded-xl p-5 shadow-[0_4px_24px_rgba(0,0,0,0.06)] border-l-4 ${colorClasses[color].border} transition-transform duration-200 hover:-translate-y-1`}
      style={{ animation: `fadeIn 0.4s ease-out ${delay}s both` }}
    >
      <div className="text-sm text-[#6E6B7B] font-medium mb-2">{label}</div>
      <div className={`text-2xl font-bold ${colorClasses[color].text}`}>{value}</div>
    </div>
  );
};

// Collapsible Section Component - Design avec ombre et bordure colorée
const CollapsibleSection: React.FC<{
  title: string;
  icon: React.ReactNode;
  count: number;
  color: 'info' | 'warning' | 'danger';
  children: React.ReactNode;
  defaultOpen?: boolean;
  onAdd?: () => void;
}> = ({ title, icon, count, color, children, defaultOpen = true, onAdd }) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  const colorClasses = {
    info: {
      border: 'border-l-[#00CFE8]',
      iconBg: 'bg-gradient-to-br from-[#00CFE8] to-[#00E5FF]',
    },
    warning: {
      border: 'border-l-[#FF9F43]',
      iconBg: 'bg-gradient-to-br from-[#FF9F43] to-[#FFB976]',
    },
    danger: {
      border: 'border-l-[#EA5455]',
      iconBg: 'bg-gradient-to-br from-[#EA5455] to-[#EF6E6F]',
    },
  };

  return (
    <div className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)] overflow-hidden">
      {/* Header */}
      <div
        className={`p-5 flex items-center justify-between border-l-4 ${colorClasses[color].border}`}
      >
        <div
          className="flex items-center gap-4 flex-1 cursor-pointer hover:opacity-80 transition-opacity"
          onClick={() => setIsOpen(!isOpen)}
        >
          <div className={`w-11 h-11 rounded-lg flex items-center justify-center text-white ${colorClasses[color].iconBg}`}>
            {icon}
          </div>
          <div>
            <h3 className="text-lg font-semibold text-[#5E5873] mb-1">{title}</h3>
            <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-[#F3F2F7] rounded-full text-xs font-semibold text-[#6E6B7B]">
              <Layers size={12} />
              {count} élément{count > 1 ? 's' : ''}
            </span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {onAdd && (
            <button
              onClick={(e) => { e.stopPropagation(); onAdd(); }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-[#7367F0]/10 text-[#7367F0] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
              title="Ajouter"
            >
              <Plus size={16} />
              <span className="hidden sm:inline">Ajouter</span>
            </button>
          )}
          <div
            onClick={() => setIsOpen(!isOpen)}
            className={`w-8 h-8 rounded-lg flex items-center justify-center cursor-pointer transition-all duration-200 ${
              isOpen ? 'bg-[#E8E7FD] text-[#7367F0] rotate-180' : 'bg-[#F3F2F7] text-[#6E6B7B]'
            }`}
          >
            <ChevronDown size={18} />
          </div>
        </div>
      </div>

      {/* Content */}
      <div
        className={`overflow-hidden transition-all duration-300 ${
          isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0'
        }`}
      >
        <div className="px-6 pb-6">{children}</div>
      </div>
    </div>
  );
};

// Data Table Component
const DataTable: React.FC<{
  headers: { label: string; align?: 'left' | 'right' | 'center' }[];
  rows: React.ReactNode[][];
  footer?: React.ReactNode[];
  footerBgColor?: string;
}> = ({ headers, rows, footer, footerBgColor = 'bg-[#F3F2F7]' }) => {
  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse">
        <thead className="bg-[#F3F2F7]">
          <tr>
            {headers.map((header, idx) => (
              <th
                key={idx}
                className={`px-4 py-3 text-xs font-bold text-[#6E6B7B] uppercase tracking-wide border-b-2 border-[#EBE9F1] ${
                  header.align === 'right' ? 'text-right' : header.align === 'center' ? 'text-center' : 'text-left'
                }`}
              >
                {header.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIdx) => (
          <tr key={rowIdx} className="transition-colors hover:bg-[#F3F2F7] group">
            {row.map((cell, cellIdx) => (
              <td
                  key={cellIdx}
                  className={`px-4 py-3 text-sm text-[#5E5873] border-b border-[#EBE9F1] ${
                    headers[cellIdx]?.align === 'right'
                      ? 'text-right'
                      : headers[cellIdx]?.align === 'center'
                      ? 'text-center'
                      : 'text-left'
                  }`}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        {footer && (
          <tfoot className={footerBgColor}>
            <tr>
              {footer.map((cell, idx) => (
                <td
                  key={idx}
                  className={`px-4 py-3 text-sm font-semibold border-t-2 border-[#EBE9F1] ${
                    headers[idx]?.align === 'right'
                      ? 'text-right'
                      : headers[idx]?.align === 'center'
                      ? 'text-center'
                      : 'text-left'
                  }`}
                >
                  {cell}
                </td>
              ))}
            </tr>
          </tfoot>
        )}
      </table>
    </div>
  );
};

export const VuexyPatrimoineSection: React.FC<PatrimoineSectionProps> = ({
  client,
  formatDate,
  formatCurrency,
  onEditItem,
  onDeleteItem,
  onDeleteBaeDetail,
}) => {
  // Parser les détails BAE
  const baeActifsFinanciers = useMemo(() =>
    parseBaeDetails(client.bae_epargne?.actifs_financiers_details),
    [client.bae_epargne?.actifs_financiers_details]
  );

  const baeActifsImmo = useMemo(() =>
    parseBaeDetails(client.bae_epargne?.actifs_immo_details),
    [client.bae_epargne?.actifs_immo_details]
  );

  const baeActifsImmoDisplay = useMemo(() => {
    const raw = Array.isArray(client.bae_epargne?.actifs_immo_details)
      ? client.bae_epargne.actifs_immo_details
      : [];
    return raw
      .map((item: unknown, index: number) => {
        const parsed = parseBaeDetailItem(item);
        if (!parsed) return null;
        return { ...parsed, baeIndex: index };
      })
      .filter((item): item is { nature: string; montant: number | null; baeIndex: number } => Boolean(item?.nature));
  }, [client.bae_epargne?.actifs_immo_details]);

  const baeAutresActifs = useMemo(() =>
    parseBaeDetails(client.bae_epargne?.actifs_autres_details),
    [client.bae_epargne?.actifs_autres_details]
  );

  const baePassifs = useMemo(() =>
    parseBaeDetails(client.bae_epargne?.passifs_details),
    [client.bae_epargne?.passifs_details]
  );

  const baePassifsDisplay = useMemo(() => {
    const raw = Array.isArray(client.bae_epargne?.passifs_details)
      ? client.bae_epargne.passifs_details
      : [];
    return raw
      .map((item: unknown, index: number) => {
        const parsed = parseBaeDetailItem(item);
        if (!parsed) return null;
        return { ...parsed, baeIndex: index };
      })
      .filter((item): item is { nature: string; montant: number | null; baeIndex: number } => Boolean(item?.nature));
  }, [client.bae_epargne?.passifs_details]);

  const { actifsFinanciersDisplay, autresActifsDisplay } = useMemo(() => {
    const actifs: ActifFinancierRow[] = [];
    const movedCrypto: AutreActifRow[] = [];
    const autres: AutreActifRow[] = [];

    (client.actifs_financiers || []).forEach((actif: any) => {
      const nature = actif.nature || '';
      if (!nature && !actif.valeur_actuelle && !actif.etablissement && !actif.detenteur) return;
      const valeur = actif.valeur_actuelle !== undefined && actif.valeur_actuelle !== null
        ? Number(actif.valeur_actuelle)
        : null;
      const entry = {
        source: 'table' as const,
        id: actif.id,
        nature,
        etablissement: actif.etablissement || null,
        valeur: Number.isFinite(valeur as number) ? (valeur as number) : null,
        detenteur: actif.detenteur || null,
        date: actif.date_ouverture_souscription || null,
        raw: actif,
      };

      if (isCryptoNature(nature)) {
        movedCrypto.push({
          source: 'moved',
          nature,
          etablissement: entry.etablissement,
          valeur: entry.valeur,
          date: entry.date,
          id: entry.id,
          raw: entry.raw,
          originType: 'actif',
        });
        return;
      }
      actifs.push(entry);
    });

    const baeActifsFinanciersRaw = Array.isArray(client.bae_epargne?.actifs_financiers_details)
      ? client.bae_epargne.actifs_financiers_details
      : [];
    baeActifsFinanciersRaw.forEach((rawItem: unknown, index: number) => {
      const parsed = parseBaeDetailItem(rawItem);
      if (!parsed) return;
      const { nature, montant } = parsed;
      const entry: ActifFinancierRow = {
        source: 'bae',
        nature,
        etablissement: null,
        valeur: montant ?? null,
        detenteur: null,
        date: null,
        baeField: 'actifs_financiers_details',
        baeIndex: index,
      };

      if (isCryptoNature(nature)) {
        movedCrypto.push({
          source: 'moved',
          nature,
          etablissement: null,
          valeur: montant ?? null,
          date: null,
          baeField: 'actifs_financiers_details',
          baeIndex: index,
        });
        return;
      }
      actifs.push(entry);
    });

    (client.autres_epargnes || []).forEach((epargne: any) => {
      const nature = epargne.designation || epargne.nature || '';
      if (!nature && !epargne.valeur && !epargne.etablissement) return;
      const valeur = epargne.valeur !== undefined && epargne.valeur !== null ? Number(epargne.valeur) : null;
      autres.push({
        source: 'table',
        id: epargne.id,
        nature,
        etablissement: epargne.etablissement || null,
        valeur: Number.isFinite(valeur as number) ? (valeur as number) : null,
        date: epargne.date_ouverture || null,
        raw: epargne,
      });
    });

    const baeAutresActifsRaw = Array.isArray(client.bae_epargne?.actifs_autres_details)
      ? client.bae_epargne.actifs_autres_details
      : [];
    baeAutresActifsRaw.forEach((rawItem: unknown, index: number) => {
      const parsed = parseBaeDetailItem(rawItem);
      if (!parsed) return;
      autres.push({
        source: 'bae',
        nature: parsed.nature,
        etablissement: null,
        valeur: parsed.montant ?? null,
        date: null,
        baeField: 'actifs_autres_details',
        baeIndex: index,
      });
    });

    const actifsDeduped = dedupeByKey(actifs, item =>
      buildDedupKey(item.nature, item.etablissement ?? undefined, item.valeur ?? undefined)
    );
    const movedCryptoDeduped = dedupeByKey(movedCrypto, item =>
      buildDedupKey(item.nature, item.etablissement ?? undefined, item.valeur ?? undefined)
    );
    const autresWithCrypto = [...autres, ...movedCryptoDeduped];
    const autresDeduped = dedupeByKey(autresWithCrypto, item =>
      buildDedupKey(item.nature, item.etablissement ?? undefined, item.valeur ?? undefined, item.date ?? undefined)
    );

    return {
      actifsFinanciersDisplay: actifsDeduped,
      autresActifsDisplay: autresDeduped,
    };
  }, [
    client.actifs_financiers,
    client.autres_epargnes,
    client.bae_epargne?.actifs_financiers_details,
    client.bae_epargne?.actifs_autres_details,
  ]);

  // Calculs des totaux depuis les tables principales
  const totalActifsFinanciersDisplay = actifsFinanciersDisplay.reduce(
    (sum, item) => sum + (item.valeur || 0),
    0
  );
  const totalActifsFinanciers = totalActifsFinanciersDisplay > 0
    ? totalActifsFinanciersDisplay
    : (client.bae_epargne?.actifs_financiers_total ? Number(client.bae_epargne.actifs_financiers_total) : 0);

  const totalActifsImmoTable = (client.biens_immobiliers || []).reduce(
    (sum: number, b: any) => sum + (Number(b.valeur_actuelle_estimee) || 0),
    0
  );
  const totalActifsImmoBae = baeActifsImmo.reduce(
    (sum, item) => sum + (item.montant || 0),
    0
  );
  const totalActifsImmo = client.bae_epargne?.actifs_immo_total
    ? Number(client.bae_epargne.actifs_immo_total)
    : (totalActifsImmoTable + totalActifsImmoBae);

  const totalAutresActifsDisplay = autresActifsDisplay.reduce(
    (sum, item) => sum + (item.valeur || 0),
    0
  );
  const totalAutresActifs = totalAutresActifsDisplay > 0
    ? totalAutresActifsDisplay
    : (client.bae_epargne?.actifs_autres_total ? Number(client.bae_epargne.actifs_autres_total) : 0);

  const totalPassifsTable = (client.passifs || []).reduce(
    (sum: number, p: any) => sum + (Number(p.capital_restant_du) || 0),
    0
  );
  const totalPassifsBae = baePassifs.reduce(
    (sum, item) => sum + (item.montant || 0),
    0
  );
  const totalPassifs = client.bae_epargne?.passifs_total_emprunts
    ? Number(client.bae_epargne.passifs_total_emprunts)
    : (totalPassifsTable + totalPassifsBae);

  const totalMensualites = (client.passifs || []).reduce(
    (sum: number, p: any) => sum + (Number(p.montant_remboursement) || 0),
    0
  );

  const patrimoineNet = totalActifsFinanciers + totalActifsImmo + totalAutresActifs - totalPassifs;

  // Compteurs d'éléments
  const countActifsFinanciers = actifsFinanciersDisplay.length;
  const countBiensImmo = (client.biens_immobiliers?.length || 0) + baeActifsImmo.length;
  const countAutresActifs = autresActifsDisplay.length;
  const countPassifs = (client.passifs?.length || 0) + baePassifs.length;

  // Vérifie si la section doit être affichée
  const hasContent =
    countActifsFinanciers > 0 ||
    countBiensImmo > 0 ||
    countAutresActifs > 0 ||
    countPassifs > 0 ||
    client.bae_epargne;

  if (!hasContent) {
    return null;
  }

  const showActifsActions = Boolean(onEditItem || onDeleteItem || onDeleteBaeDetail);
  const showImmoActions = Boolean(onEditItem || onDeleteItem || onDeleteBaeDetail);
  const showPassifsActions = Boolean(onEditItem || onDeleteItem || onDeleteBaeDetail);
  const showAutresActions = Boolean(onEditItem || onDeleteItem || onDeleteBaeDetail);

  return (
    <div className="bg-white rounded-xl shadow-[0_4px_24px_rgba(0,0,0,0.06)] overflow-hidden">
      {/* Header de la section principale : Épargne (synthèse patrimoniale) */}
      <div className="p-6 border-b border-[#EBE9F1]">
        <div className="flex items-center gap-3 mb-6">
          <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white shadow-[0_4px_12px_rgba(115,103,240,0.3)]">
            <PieChart size={24} />
          </div>
          <h2 className="text-xl font-semibold text-[#5E5873]">
            Épargne <span className="text-[#6E6B7B] font-normal">(synthèse patrimoniale)</span>
          </h2>
        </div>

        {/* Grille de synthèse */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
          <div className="flex flex-col gap-1">
            <span className="text-sm text-[#6E6B7B] font-medium">Épargne disponible</span>
            <span className="text-lg font-semibold text-[#5E5873]">
              {client.bae_epargne?.montant_epargne_disponible
                ? formatCurrency(client.bae_epargne.montant_epargne_disponible)
                : <span className="text-[#B9B9C3] italic text-sm font-normal">Non renseigné</span>}
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-sm text-[#6E6B7B] font-medium">Capacité d'épargne</span>
            <span className="text-lg font-semibold text-[#5E5873]">
              {client.bae_epargne?.capacite_epargne_estimee
                ? formatCurrency(client.bae_epargne.capacite_epargne_estimee)
                : <span className="text-[#B9B9C3] italic text-sm font-normal">Non renseigné</span>}
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-sm text-[#6E6B7B] font-medium">Actifs financiers</span>
            <span className="text-lg font-semibold text-[#00CFE8]">
              {totalActifsFinanciers > 0
                ? formatCurrency(totalActifsFinanciers)
                : <span className="text-[#B9B9C3] italic text-sm font-normal">Non renseigné</span>}
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-sm text-[#6E6B7B] font-medium">Actifs immobiliers</span>
            <span className="text-lg font-semibold text-[#FF9F43]">
              {totalActifsImmo > 0
                ? formatCurrency(totalActifsImmo)
                : <span className="text-[#B9B9C3] italic text-sm font-normal">Non renseigné</span>}
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-sm text-[#6E6B7B] font-medium">Autres actifs</span>
            <span className="text-lg font-semibold text-[#5E5873]">
              {totalAutresActifs > 0
                ? formatCurrency(totalAutresActifs)
                : <span className="text-[#B9B9C3] italic text-sm font-normal">Non renseigné</span>}
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-sm text-[#6E6B7B] font-medium">Total emprunts</span>
            <span className="text-lg font-semibold text-[#EA5455]">
              {totalPassifs > 0
                ? formatCurrency(totalPassifs)
                : <span className="text-[#B9B9C3] italic text-sm font-normal">Non renseigné</span>}
            </span>
          </div>
        </div>
      </div>

      {/* Contenu : sous-sections avec design cards */}
      <div className="p-6 bg-[#F8F8F8] space-y-5">
        {/* Sous-section : Actifs Financiers */}
        {(countActifsFinanciers > 0 || onEditItem) && (
          <CollapsibleSection
            title="Actifs Financiers"
            icon={<LineChart size={22} />}
            count={countActifsFinanciers}
            color="info"
            onAdd={onEditItem ? () => onEditItem('actif', {}, true) : undefined}
          >
            <DataTable
              headers={[
                { label: 'Nature', align: 'left' },
                { label: 'Établissement', align: 'left' },
                { label: 'Valeur actuelle', align: 'right' },
                { label: 'Détenteur', align: 'left' },
                { label: 'Ouvert le', align: 'left' },
                ...(showActifsActions ? [{ label: 'Actions', align: 'right' as const }] : []),
              ]}
              rows={[
                ...actifsFinanciersDisplay.map((actif) => [
                  <strong className="text-[#5E5873]">{canonicalizeNature(actif.nature || '') || actif.nature || '-'}</strong>,
                  actif.etablissement || <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {actif.valeur ? formatCurrency(actif.valeur) : <span className="text-[#B9B9C3]">-</span>}
                  </span>,
                  actif.detenteur || <span className="text-[#B9B9C3]">-</span>,
                  actif.date
                    ? formatDate(actif.date)
                    : <span className="text-[#B9B9C3]">-</span>,
                  ...(showActifsActions
                    ? [(
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onEditItem && actif.source === 'table' && actif.id && (
                          <button
                            onClick={() => onEditItem('actif', actif.raw || { id: actif.id })}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
                            title="Modifier"
                          >
                            <Pencil size={14} />
                          </button>
                        )}
                        {onDeleteItem && actif.source === 'table' && actif.id && (
                          <button
                            onClick={() => onDeleteItem('actif', actif.id as number)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {onDeleteBaeDetail && actif.source === 'bae' && typeof actif.baeIndex === 'number' && (
                          <button
                            onClick={() => onDeleteBaeDetail('actifs_financiers_details', actif.baeIndex as number)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {!onEditItem && !onDeleteItem && !onDeleteBaeDetail && (
                          <span className="text-[#B9B9C3] text-xs">-</span>
                        )}
                        {(onEditItem || onDeleteItem || onDeleteBaeDetail) &&
                          actif.source === 'bae' &&
                          !onDeleteBaeDetail && (
                            <span className="text-[#B9B9C3] text-xs">-</span>
                          )}
                      </div>
                    )]
                    : []),
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total :</strong>,
                '',
                <span className="text-[#28C76F] font-bold">{formatCurrency(totalActifsFinanciers)}</span>,
                '',
                '',
                ...(showActifsActions ? [''] : []),
              ]}
              footerBgColor="bg-[#E8FFFE]"
            />
          </CollapsibleSection>
        )}

        {/* Sous-section : Biens Immobiliers */}
        {(countBiensImmo > 0 || onEditItem) && (
          <CollapsibleSection
            title="Biens Immobiliers & Pro"
            icon={<Home size={22} />}
            count={countBiensImmo}
            color="warning"
            onAdd={onEditItem ? () => onEditItem('bien', {}, true) : undefined}
          >
            <DataTable
              headers={[
                { label: 'Désignation', align: 'left' },
                { label: 'Détenteur', align: 'left' },
                { label: 'Valeur actuelle', align: 'right' },
                { label: 'Forme propriété', align: 'left' },
                { label: 'Acquisition', align: 'center' },
                ...(showImmoActions ? [{ label: 'Actions', align: 'right' as const }] : []),
              ]}
              rows={[
                // Données de la table biens_immobiliers
                ...(client.biens_immobiliers || []).map((bien: any) => [
                  <strong className="text-[#5E5873]">{bien.designation || '-'}</strong>,
                  bien.detenteur || <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {bien.valeur_actuelle_estimee ? formatCurrency(bien.valeur_actuelle_estimee) : '-'}
                  </span>,
                  bien.forme_propriete || <span className="text-[#B9B9C3]">-</span>,
                  bien.annee_acquisition || <span className="text-[#B9B9C3]">-</span>,
                  ...(showImmoActions
                    ? [(
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onEditItem && bien.id && (
                          <button
                            onClick={() => onEditItem('bien', bien)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
                            title="Modifier"
                          >
                            <Pencil size={14} />
                          </button>
                        )}
                        {onDeleteItem && bien.id && (
                          <button
                            onClick={() => onDeleteItem('bien', bien.id)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                      </div>
                    )]
                    : []),
                ]),
                // Données de bae_epargne.actifs_immo_details
                ...baeActifsImmoDisplay.map((item) => [
                  <strong className="text-[#5E5873]">{item.nature}</strong>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {item.montant ? formatCurrency(item.montant) : <span className="text-[#B9B9C3]">-</span>}
                  </span>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#B9B9C3]">-</span>,
                  ...(showImmoActions
                    ? [(
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onDeleteBaeDetail && (
                          <button
                            onClick={() => onDeleteBaeDetail('actifs_immo_details', item.baeIndex)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {!onDeleteBaeDetail && <span className="text-[#B9B9C3] text-xs">-</span>}
                      </div>
                    )]
                    : []),
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total immo :</strong>,
                '',
                <span className="text-[#28C76F] font-bold">{formatCurrency(totalActifsImmo)}</span>,
                '',
                '',
                ...(showImmoActions ? [''] : []),
              ]}
              footerBgColor="bg-[#FFF7ED]"
            />
          </CollapsibleSection>
        )}

        {/* Sous-section : Passifs & Emprunts */}
        {(countPassifs > 0 || onEditItem) && (
          <CollapsibleSection
            title="Passifs & Emprunts"
            icon={<CreditCard size={22} />}
            count={countPassifs}
            color="danger"
            onAdd={onEditItem ? () => onEditItem('passif', {}, true) : undefined}
          >
            <DataTable
              headers={[
                { label: 'Nature', align: 'left' },
                { label: 'Prêteur', align: 'left' },
                { label: 'Montant remb.', align: 'right' },
                { label: 'Capital restant dû', align: 'right' },
                { label: 'Durée restante', align: 'left' },
                ...(showPassifsActions ? [{ label: 'Actions', align: 'right' as const }] : []),
              ]}
              rows={[
                // Données de la table passifs
                ...(client.passifs || []).map((passif: any) => [
                  <div className="flex items-center gap-2">
                    <strong className="text-[#5E5873]">{passif.nature || '-'}</strong>
                    {passif.nature?.toLowerCase().includes('immobilier') && (
                      <span className="px-2 py-0.5 rounded-full text-xs font-semibold uppercase bg-[rgba(255,159,67,0.12)] text-[#FF9F43]">
                        immo
                      </span>
                    )}
                  </div>,
                  passif.preteur || <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#EA5455] font-semibold">
                    {passif.montant_remboursement ? formatCurrency(passif.montant_remboursement) : '-'}
                  </span>,
                  <span className="text-[#EA5455] font-semibold">
                    {passif.capital_restant_du ? formatCurrency(passif.capital_restant_du) : '-'}
                  </span>,
                  passif.duree_restante ? `${passif.duree_restante} mois` : <span className="text-[#B9B9C3]">-</span>,
                  ...(showPassifsActions
                    ? [(
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onEditItem && passif.id && (
                          <button
                            onClick={() => onEditItem('passif', passif)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
                            title="Modifier"
                          >
                            <Pencil size={14} />
                          </button>
                        )}
                        {onDeleteItem && passif.id && (
                          <button
                            onClick={() => onDeleteItem('passif', passif.id)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                      </div>
                    )]
                    : []),
                ]),
                // Données de bae_epargne.passifs_details
                ...baePassifsDisplay.map((item) => [
                  <div className="flex items-center gap-2">
                    <strong className="text-[#5E5873]">{item.nature}</strong>
                    {item.nature.toLowerCase().includes('immobilier') && (
                      <span className="px-2 py-0.5 rounded-full text-xs font-semibold uppercase bg-[rgba(255,159,67,0.12)] text-[#FF9F43]">
                        immo
                      </span>
                    )}
                  </div>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#EA5455] font-semibold">
                    {item.montant ? formatCurrency(item.montant) : <span className="text-[#B9B9C3]">-</span>}
                  </span>,
                  <span className="text-[#B9B9C3]">-</span>,
                  ...(showPassifsActions
                    ? [(
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onDeleteBaeDetail && (
                          <button
                            onClick={() => onDeleteBaeDetail('passifs_details', item.baeIndex)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {!onDeleteBaeDetail && <span className="text-[#B9B9C3] text-xs">-</span>}
                      </div>
                    )]
                    : []),
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total des emprunts :</strong>,
                '',
                <span className="text-[#EA5455] font-bold">{formatCurrency(totalMensualites)}/mois</span>,
                <span className="text-[#EA5455] font-bold">{formatCurrency(totalPassifs)}</span>,
                '',
                ...(showPassifsActions ? [''] : []),
              ]}
              footerBgColor="bg-[#FEF2F2]"
            />
          </CollapsibleSection>
        )}

        {/* Sous-section : Autres Épargnes */}
        {(countAutresActifs > 0 || onEditItem) && (
          <CollapsibleSection
            title="Autres Épargnes"
            icon={<Coins size={22} />}
            count={countAutresActifs}
            color="info"
            onAdd={onEditItem ? () => onEditItem('epargne', {}, true) : undefined}
          >
            <DataTable
              headers={[
                { label: 'Nature', align: 'left' },
                { label: 'Établissement', align: 'left' },
                { label: 'Valeur', align: 'right' },
                { label: 'Date', align: 'left' },
                ...(showAutresActions ? [{ label: 'Actions', align: 'right' as const }] : []),
              ]}
              rows={[
                // Données de la table autres_epargnes
                ...autresActifsDisplay.map((item) => [
                  <strong className="text-[#5E5873]">{item.nature || '-'}</strong>,
                  item.etablissement || <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {item.valeur ? formatCurrency(item.valeur) : <span className="text-[#B9B9C3]">-</span>}
                  </span>,
                  item.date ? formatDate(item.date) : <span className="text-[#B9B9C3]">-</span>,
                  ...(showAutresActions
                    ? [(
                      <div className="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        {onEditItem && item.source === 'table' && item.id && (
                          <button
                            onClick={() => onEditItem('epargne', item.raw || { id: item.id })}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
                            title="Modifier"
                          >
                            <Pencil size={14} />
                          </button>
                        )}
                        {onEditItem && item.source === 'moved' && item.originType === 'actif' && item.id && (
                          <button
                            onClick={() => onEditItem('actif', item.raw || { id: item.id })}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#7367F0] hover:text-white transition-all duration-200"
                            title="Modifier"
                          >
                            <Pencil size={14} />
                          </button>
                        )}
                        {onDeleteItem && item.source === 'table' && item.id && (
                          <button
                            onClick={() => onDeleteItem('epargne', item.id as number)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {onDeleteItem && item.source === 'moved' && item.originType === 'actif' && item.id && (
                          <button
                            onClick={() => onDeleteItem('actif', item.id as number)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {onDeleteBaeDetail && item.source === 'bae' && typeof item.baeIndex === 'number' && (
                          <button
                            onClick={() => onDeleteBaeDetail('actifs_autres_details', item.baeIndex as number)}
                            className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                            title="Supprimer"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                        {onDeleteBaeDetail &&
                          item.source === 'moved' &&
                          item.baeField === 'actifs_financiers_details' &&
                          typeof item.baeIndex === 'number' && (
                            <button
                              onClick={() => onDeleteBaeDetail('actifs_financiers_details', item.baeIndex as number)}
                              className="w-7 h-7 rounded-md flex items-center justify-center bg-[#F3F2F7] text-[#6E6B7B] hover:bg-[#EA5455] hover:text-white transition-all duration-200"
                              title="Supprimer"
                            >
                              <Trash2 size={14} />
                            </button>
                          )}
                        {(onEditItem || onDeleteItem || onDeleteBaeDetail) &&
                          item.source !== 'table' &&
                          item.source !== 'moved' &&
                          !onDeleteBaeDetail && (
                            <span className="text-[#B9B9C3] text-xs">-</span>
                          )}
                      </div>
                    )]
                    : []),
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total :</strong>,
                '',
                <span className="text-[#28C76F] font-bold">{formatCurrency(totalAutresActifs)}</span>,
                '',
                ...(showAutresActions ? [''] : []),
              ]}
              footerBgColor="bg-[#E8FFFE]"
            />
          </CollapsibleSection>
        )}

        {/* Totaux - Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
          <StatCard
            label="Actifs Financiers"
            value={formatCurrency(totalActifsFinanciers)}
            color="info"
            delay={0.05}
          />
          <StatCard
            label="Actifs Immobiliers"
            value={formatCurrency(totalActifsImmo)}
            color="warning"
            delay={0.1}
          />
          <StatCard
            label="Total Emprunts"
            value={`-${formatCurrency(totalPassifs)}`}
            color="danger"
            delay={0.15}
          />
          <StatCard
            label="Patrimoine Net"
            value={formatCurrency(patrimoineNet)}
            color="primary"
            delay={0.2}
          />
        </div>
      </div>

      {/* Animation keyframes */}
      <style>{`
        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: translateY(10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>
    </div>
  );
};

export default VuexyPatrimoineSection;
