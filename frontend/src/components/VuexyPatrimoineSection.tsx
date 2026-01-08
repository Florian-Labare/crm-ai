import React, { useState, useMemo } from 'react';
import {
  Home,
  CreditCard,
  ChevronDown,
  PieChart,
  Layers,
  LineChart,
  Coins,
} from 'lucide-react';

interface PatrimoineSectionProps {
  client: any;
  formatDate: (date?: string) => string;
  formatCurrency: (amount?: number) => string;
}

// Helper pour parser les détails BAE (format: "nature: montant" ou juste "nature")
const parseBaeDetails = (details: string[] | null | undefined): { nature: string; montant: number | null }[] => {
  if (!details || !Array.isArray(details)) return [];

  return details.map(item => {
    if (typeof item !== 'string') return { nature: String(item), montant: null };

    // Format "nature: montant" ou "nature : montant"
    const match = item.match(/^(.+?)\s*:\s*(\d+(?:[.,]\d+)?)\s*€?$/);
    if (match) {
      return {
        nature: match[1].trim(),
        montant: parseFloat(match[2].replace(',', '.')),
      };
    }

    // Format avec montant à la fin sans séparateur
    const matchEnd = item.match(/^(.+?)\s+(\d+(?:[.,]\d+)?)\s*€?$/);
    if (matchEnd) {
      return {
        nature: matchEnd[1].trim(),
        montant: parseFloat(matchEnd[2].replace(',', '.')),
      };
    }

    return { nature: item.trim(), montant: null };
  }).filter(item => item.nature);
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
}> = ({ title, icon, count, color, children, defaultOpen = true }) => {
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
        onClick={() => setIsOpen(!isOpen)}
        className={`p-5 flex items-center justify-between cursor-pointer transition-colors hover:bg-[#F3F2F7] border-l-4 ${colorClasses[color].border}`}
      >
        <div className="flex items-center gap-4">
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
        <div
          className={`w-8 h-8 rounded-lg flex items-center justify-center transition-all duration-200 ${
            isOpen ? 'bg-[#E8E7FD] text-[#7367F0] rotate-180' : 'bg-[#F3F2F7] text-[#6E6B7B]'
          }`}
        >
          <ChevronDown size={18} />
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
            <tr key={rowIdx} className="transition-colors hover:bg-[#F3F2F7]">
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

  const baeAutresActifs = useMemo(() =>
    parseBaeDetails(client.bae_epargne?.actifs_autres_details),
    [client.bae_epargne?.actifs_autres_details]
  );

  const baePassifs = useMemo(() =>
    parseBaeDetails(client.bae_epargne?.passifs_details),
    [client.bae_epargne?.passifs_details]
  );

  // Calculs des totaux depuis les tables principales
  const totalActifsFinanciersTable = (client.actifs_financiers || []).reduce(
    (sum: number, a: any) => sum + (Number(a.valeur_actuelle) || 0),
    0
  );

  // Totaux depuis BAE
  const totalActifsFinanciersBae = baeActifsFinanciers.reduce(
    (sum, item) => sum + (item.montant || 0),
    0
  );

  // Utiliser le total BAE si disponible, sinon le total table
  const totalActifsFinanciers = client.bae_epargne?.actifs_financiers_total
    ? Number(client.bae_epargne.actifs_financiers_total)
    : (totalActifsFinanciersTable + totalActifsFinanciersBae);

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

  const totalAutresActifsTable = (client.autres_epargnes || []).reduce(
    (sum: number, e: any) => sum + (Number(e.valeur) || 0),
    0
  );
  const totalAutresActifsBae = baeAutresActifs.reduce(
    (sum, item) => sum + (item.montant || 0),
    0
  );
  const totalAutresActifs = client.bae_epargne?.actifs_autres_total
    ? Number(client.bae_epargne.actifs_autres_total)
    : (totalAutresActifsTable + totalAutresActifsBae);

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
  const countActifsFinanciers = (client.actifs_financiers?.length || 0) + baeActifsFinanciers.length;
  const countBiensImmo = (client.biens_immobiliers?.length || 0) + baeActifsImmo.length;
  const countAutresActifs = (client.autres_epargnes?.length || 0) + baeAutresActifs.length;
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

  // Détecter si c'est un actif crypto/bitcoin
  const isCrypto = (nature: string): boolean => {
    const lower = nature.toLowerCase();
    return lower.includes('crypto') || lower.includes('bitcoin') || lower.includes('btc') ||
           lower.includes('ethereum') || lower.includes('eth') || lower.includes('nft');
  };

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
        {countActifsFinanciers > 0 && (
          <CollapsibleSection
            title="Actifs Financiers"
            icon={<LineChart size={22} />}
            count={countActifsFinanciers}
            color="info"
          >
            <DataTable
              headers={[
                { label: 'Nature', align: 'left' },
                { label: 'Établissement', align: 'left' },
                { label: 'Valeur actuelle', align: 'right' },
                { label: 'Détenteur', align: 'left' },
                { label: 'Ouvert le', align: 'left' },
              ]}
              rows={[
                // Données de la table actifs_financiers
                ...(client.actifs_financiers || []).map((actif: any) => [
                  <strong className="text-[#5E5873]">{actif.nature || '-'}</strong>,
                  actif.etablissement || <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {actif.valeur_actuelle ? formatCurrency(actif.valeur_actuelle) : '-'}
                  </span>,
                  actif.detenteur || <span className="text-[#B9B9C3]">-</span>,
                  actif.date_ouverture_souscription
                    ? formatDate(actif.date_ouverture_souscription)
                    : <span className="text-[#B9B9C3]">-</span>,
                ]),
                // Données de bae_epargne.actifs_financiers_details
                ...baeActifsFinanciers.map((item) => [
                  <div className="flex items-center gap-2">
                    <strong className="text-[#5E5873]">{item.nature}</strong>
                    {isCrypto(item.nature) && (
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F7931A]/15 text-[#F7931A]">
                        <Coins size={12} />
                        Crypto
                      </span>
                    )}
                  </div>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {item.montant ? formatCurrency(item.montant) : <span className="text-[#B9B9C3]">-</span>}
                  </span>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#B9B9C3]">-</span>,
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total :</strong>,
                '',
                <span className="text-[#28C76F] font-bold">{formatCurrency(totalActifsFinanciers)}</span>,
                '',
                '',
              ]}
              footerBgColor="bg-[#E8FFFE]"
            />
          </CollapsibleSection>
        )}

        {/* Sous-section : Biens Immobiliers */}
        {countBiensImmo > 0 && (
          <CollapsibleSection
            title="Biens Immobiliers & Pro"
            icon={<Home size={22} />}
            count={countBiensImmo}
            color="warning"
          >
            <DataTable
              headers={[
                { label: 'Désignation', align: 'left' },
                { label: 'Détenteur', align: 'left' },
                { label: 'Valeur actuelle', align: 'right' },
                { label: 'Forme propriété', align: 'left' },
                { label: 'Acquisition', align: 'center' },
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
                ]),
                // Données de bae_epargne.actifs_immo_details
                ...baeActifsImmo.map((item) => [
                  <strong className="text-[#5E5873]">{item.nature}</strong>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#28C76F] font-semibold">
                    {item.montant ? formatCurrency(item.montant) : <span className="text-[#B9B9C3]">-</span>}
                  </span>,
                  <span className="text-[#B9B9C3]">-</span>,
                  <span className="text-[#B9B9C3]">-</span>,
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total immo :</strong>,
                '',
                <span className="text-[#28C76F] font-bold">{formatCurrency(totalActifsImmo)}</span>,
                '',
                '',
              ]}
              footerBgColor="bg-[#FFF7ED]"
            />
          </CollapsibleSection>
        )}

        {/* Sous-section : Passifs & Emprunts */}
        {countPassifs > 0 && (
          <CollapsibleSection
            title="Passifs & Emprunts"
            icon={<CreditCard size={22} />}
            count={countPassifs}
            color="danger"
          >
            <DataTable
              headers={[
                { label: 'Nature', align: 'left' },
                { label: 'Prêteur', align: 'left' },
                { label: 'Montant remb.', align: 'right' },
                { label: 'Capital restant dû', align: 'right' },
                { label: 'Durée restante', align: 'left' },
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
                ]),
                // Données de bae_epargne.passifs_details
                ...baePassifs.map((item) => [
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
                ]),
              ]}
              footer={[
                <strong className="text-[#5E5873]">Total des emprunts :</strong>,
                '',
                <span className="text-[#EA5455] font-bold">{formatCurrency(totalMensualites)}/mois</span>,
                <span className="text-[#EA5455] font-bold">{formatCurrency(totalPassifs)}</span>,
                '',
              ]}
              footerBgColor="bg-[#FEF2F2]"
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
