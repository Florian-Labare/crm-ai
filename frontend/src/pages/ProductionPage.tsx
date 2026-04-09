import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { usePage } from '../contexts/PageContext';
import { useAuth } from '../contexts/AuthContext';
import {
  TrendingUp,
  Upload,
  Plus,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  X,
  FileSpreadsheet,
  ArrowRight,
  Check,
  AlertCircle,
  Pencil,
  Trash2,
  Save,
  Banknote,
  Repeat2,
  Clock,
  FileText,
} from 'lucide-react';
import api from '../api/apiClient';
import { toast } from 'react-toastify';

// ─── Types ───────────────────────────────────────────────────────────────────

interface TeamMember {
  id: number;
  name: string;
  firstname?: string;
}

interface Assureur {
  id: number;
  nom: string;
}

interface Production {
  id: number;
  user_id: number;
  client_id?: number | null;
  nom_client?: string;
  prenom_client?: string;
  assureur_id?: number | null;
  assureur?: { id: number; nom: string };
  compagnie_libre?: string;
  categorie?: string;
  type_contrat?: string;
  annee?: number | null;
  date_signature?: string | null;
  date_effet?: string | null;
  prime_ttc?: number | null;
  prime_ht?: number | null;
  fond_euro?: number | null;
  uc?: number | null;
  taux_commission?: number | null;
  commission_compagnie?: number | null;
  commission_mia?: number | null;
  commission_recurrente?: number | null;
  encours_commission?: number | null;
  date_commission?: string | null;
  regul_transmise?: boolean;
  regul_signee?: boolean;
  date_resiliation?: string | null;
  date_reprise?: string | null;
  statut: string;
  notes?: string;
  user?: { id: number; name: string; firstname?: string };
  client?: { id: number; nom: string; prenom?: string };
}

type ProductionForm = Omit<
  Production,
  'id' | 'assureur' | 'user' | 'client' |
  'prime_ttc' | 'prime_ht' | 'fond_euro' | 'uc' |
  'taux_commission' | 'commission_compagnie' | 'commission_mia' |
  'commission_recurrente' | 'encours_commission'
> & {
  prime_ttc: string;
  prime_ht: string;
  fond_euro: string;
  uc: string;
  taux_commission: string;
  commission_compagnie: string;
  commission_mia: string;
  commission_recurrente: string;
  encours_commission: string;
};

interface StatsByMia {
  user_id: number;
  user_name: string;
  commission_mia: number;
  commission_recurrente: number;
  encours_commission: number;
  nb_contrats: number;
}

interface StatsByClient {
  client_id: number | null;
  client_name: string;
  is_crm_client: boolean;
  commission_mia: number;
  prime_ttc: number;
  nb_contrats: number;
}

interface Stats {
  total_commission_mia: number;
  total_commission_recurrente: number;
  total_encours: number;
  nb_contrats: number;
  by_annee: { annee: number; commission_mia: number; nb_contrats: number }[];
  by_categorie: { categorie: string; commission_mia: number; nb_contrats: number }[];
  by_mia: StatsByMia[];
  by_client: StatsByClient[];
}

interface PaginatedProductions {
  data: Production[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

// ─── Champs CRM mappables (import wizard) ────────────────────────────────────

const CRM_FIELDS: { value: string; label: string }[] = [
  { value: '', label: 'Ignorer' },
  { value: 'nom_client', label: 'Nom client' },
  { value: 'prenom_client', label: 'Prénom client' },
  { value: 'compagnie_libre', label: 'Compagnie' },
  { value: 'categorie', label: 'Catégorie' },
  { value: 'type_contrat', label: 'Type de contrat' },
  { value: 'annee', label: 'Année' },
  { value: 'date_signature', label: 'Date signature' },
  { value: 'date_effet', label: "Date d'effet" },
  { value: 'prime_ttc', label: 'Prime TTC' },
  { value: 'prime_ht', label: 'Prime HT' },
  { value: 'fond_euro', label: 'Fond euro' },
  { value: 'uc', label: 'UC' },
  { value: 'taux_commission', label: 'Taux commission' },
  { value: 'commission_compagnie', label: 'Com compagnie' },
  { value: 'commission_mia', label: 'Com MIA' },
  { value: 'commission_recurrente', label: 'Com récurrente' },
  { value: 'encours_commission', label: 'Encours' },
  { value: 'date_commission', label: 'Date commission' },
  { value: 'date_resiliation', label: 'Date résiliation' },
  { value: 'date_reprise', label: 'Date reprise' },
  { value: 'statut', label: 'Statut' },
  { value: 'regul_transmise', label: 'Régul transmise' },
  { value: 'regul_signee', label: 'Régul signée' },
  { value: 'notes', label: 'Notes' },
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

const fmt = (v?: number | null) =>
  v != null ? new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v) : '—';

const memberName = (m: TeamMember) => [m.firstname, m.name].filter(Boolean).join(' ');

const EMPTY_FORM: ProductionForm = {
  user_id: 0,
  client_id: null,
  nom_client: '',
  prenom_client: '',
  assureur_id: null,
  compagnie_libre: '',
  categorie: '',
  type_contrat: '',
  annee: new Date().getFullYear(),
  date_signature: null,
  date_effet: null,
  prime_ttc: '',
  prime_ht: '',
  fond_euro: '',
  uc: '',
  taux_commission: '',
  commission_compagnie: '',
  commission_mia: '',
  commission_recurrente: '',
  encours_commission: '',
  date_commission: null,
  regul_transmise: false,
  regul_signee: false,
  date_resiliation: null,
  date_reprise: null,
  statut: 'active',
  notes: '',
};

const productionToForm = (p: Production): ProductionForm => ({
  user_id: p.user_id,
  client_id: p.client_id ?? null,
  nom_client: p.nom_client ?? '',
  prenom_client: p.prenom_client ?? '',
  assureur_id: p.assureur_id ?? null,
  compagnie_libre: p.compagnie_libre ?? '',
  categorie: p.categorie ?? '',
  type_contrat: p.type_contrat ?? '',
  annee: p.annee ?? null,
  date_signature: p.date_signature ?? null,
  date_effet: p.date_effet ?? null,
  prime_ttc: p.prime_ttc != null ? String(p.prime_ttc) : '',
  prime_ht: p.prime_ht != null ? String(p.prime_ht) : '',
  fond_euro: p.fond_euro != null ? String(p.fond_euro) : '',
  uc: p.uc != null ? String(p.uc) : '',
  taux_commission: p.taux_commission != null ? String(p.taux_commission) : '',
  commission_compagnie: p.commission_compagnie != null ? String(p.commission_compagnie) : '',
  commission_mia: p.commission_mia != null ? String(p.commission_mia) : '',
  commission_recurrente: p.commission_recurrente != null ? String(p.commission_recurrente) : '',
  encours_commission: p.encours_commission != null ? String(p.encours_commission) : '',
  date_commission: p.date_commission ?? null,
  regul_transmise: p.regul_transmise ?? false,
  regul_signee: p.regul_signee ?? false,
  date_resiliation: p.date_resiliation ?? null,
  date_reprise: p.date_reprise ?? null,
  statut: p.statut ?? 'active',
  notes: p.notes ?? '',
});

// ─── KPI Card ─────────────────────────────────────────────────────────────────

const KpiCard: React.FC<{
  label: string;
  value: string;
  icon: React.ReactNode;
  iconColor: string;
  iconBg: string;
}> = ({ label, value, icon, iconColor, iconBg }) => (
  <div className="vx-card p-4 flex items-center gap-3">
    <div className="w-10 h-10 rounded-lg flex-shrink-0 flex items-center justify-center" style={{ background: iconBg }}>
      <span style={{ color: iconColor }}>{icon}</span>
    </div>
    <div className="min-w-0">
      <p className="text-xs text-[#B9B9C3] font-semibold uppercase tracking-wide truncate">{label}</p>
      <p className="text-xl font-bold text-[#5E5873] leading-tight">{value}</p>
    </div>
  </div>
);

// ─── Form helpers ─────────────────────────────────────────────────────────────

const Field: React.FC<{ label: string; children: React.ReactNode; half?: boolean }> = ({ label, children, half }) => (
  <div className={half ? 'col-span-1' : 'col-span-2 sm:col-span-1'}>
    <label className="block text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide mb-1">{label}</label>
    {children}
  </div>
);

const inputCls = 'w-full border border-[#D0CDE1] rounded-lg px-3 py-2 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0] bg-white placeholder:text-[#C4C4C4]';

// ─── Production Form Modal ────────────────────────────────────────────────────

interface ProductionFormModalProps {
  initial: Production | null; // null = création
  members: TeamMember[];
  assureurs: Assureur[];
  isAdmin: boolean;
  currentUserId: number;
  onClose: () => void;
  onSaved: () => void;
}

const ProductionFormModal: React.FC<ProductionFormModalProps> = ({
  initial, members, assureurs, isAdmin, currentUserId, onClose, onSaved,
}) => {
  const isEdit = initial !== null;
  const [form, setForm] = useState<ProductionForm>(() =>
    initial ? productionToForm(initial) : { ...EMPTY_FORM, user_id: currentUserId }
  );
  const [saving, setSaving] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);

  const set = (field: keyof ProductionForm, value: any) =>
    setForm((prev) => ({ ...prev, [field]: value }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: Record<string, any> = { ...form };
      // Convertir les champs numériques
      for (const f of ['prime_ttc', 'prime_ht', 'fond_euro', 'uc', 'taux_commission',
                        'commission_compagnie', 'commission_mia', 'commission_recurrente', 'encours_commission']) {
        payload[f] = payload[f] !== '' && payload[f] != null ? parseFloat(String(payload[f]).replace(',', '.')) : null;
      }
      // Vider les chaînes vides → null
      for (const f of ['nom_client', 'prenom_client', 'compagnie_libre', 'categorie', 'type_contrat', 'notes',
                        'date_signature', 'date_effet', 'date_commission', 'date_resiliation', 'date_reprise']) {
        if (payload[f] === '') payload[f] = null;
      }

      if (isEdit) {
        await api.put(`/productions/${initial!.id}`, payload);
        toast.success('Ligne mise à jour');
      } else {
        await api.post('/productions', payload);
        toast.success('Ligne créée');
      }
      onSaved();
      onClose();
    } catch (e: any) {
      const errors = e?.response?.data?.errors;
      if (errors) {
        const first = Object.values(errors)[0] as string[];
        toast.error(first[0]);
      } else {
        toast.error(e?.response?.data?.message ?? 'Erreur lors de la sauvegarde');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    setSaving(true);
    try {
      await api.delete(`/productions/${initial!.id}`);
      toast.success('Ligne supprimée');
      onSaved();
      onClose();
    } catch {
      toast.error('Erreur lors de la suppression');
    } finally {
      setSaving(false);
    }
  };

  const currentYear = new Date().getFullYear();
  const yearOptions = Array.from({ length: 10 }, (_, i) => currentYear - i + 2);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-[#EBE9F1] flex-shrink-0">
          <span className="font-semibold text-[#5E5873]">
            {isEdit ? 'Modifier la ligne de production' : 'Nouvelle ligne de production'}
          </span>
          <button onClick={onClose} className="text-[#B9B9C3] hover:text-[#5E5873] transition-colors">
            <X size={20} />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto">
          <div className="p-6 space-y-6">

            {/* ── Identification ── */}
            <section>
              <h3 className="text-xs font-bold text-[#B9B9C3] uppercase tracking-widest mb-3">Identification</h3>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Nom client">
                  <input className={inputCls} placeholder="Dupont" value={form.nom_client ?? ''} onChange={(e) => set('nom_client', e.target.value)} />
                </Field>
                <Field label="Prénom client">
                  <input className={inputCls} placeholder="Jean" value={form.prenom_client ?? ''} onChange={(e) => set('prenom_client', e.target.value)} />
                </Field>
                {isAdmin && members.length > 0 && (
                  <Field label="MIA">
                    <select className={inputCls} value={form.user_id || ''} onChange={(e) => set('user_id', parseInt(e.target.value))}>
                      <option value="">— Sélectionner —</option>
                      {members.map((m) => <option key={m.id} value={m.id}>{memberName(m)}</option>)}
                    </select>
                  </Field>
                )}
              </div>
            </section>

            {/* ── Contrat ── */}
            <section>
              <h3 className="text-xs font-bold text-[#B9B9C3] uppercase tracking-widest mb-3">Contrat</h3>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Compagnie">
                  <select
                    className={inputCls}
                    value={form.assureur_id ?? ''}
                    onChange={(e) => set('assureur_id', e.target.value ? parseInt(e.target.value) : null)}
                  >
                    <option value="">Saisie libre…</option>
                    {assureurs.map((a) => <option key={a.id} value={a.id}>{a.nom}</option>)}
                  </select>
                </Field>
                {!form.assureur_id && (
                  <Field label="Compagnie (libre)">
                    <input className={inputCls} placeholder="AXA, Allianz…" value={form.compagnie_libre ?? ''} onChange={(e) => set('compagnie_libre', e.target.value)} />
                  </Field>
                )}
                <Field label="Catégorie">
                  <input className={inputCls} placeholder="Prévoyance, Épargne…" value={form.categorie ?? ''} onChange={(e) => set('categorie', e.target.value)} />
                </Field>
                <Field label="Type de contrat">
                  <input className={inputCls} placeholder="Individuel, Collectif…" value={form.type_contrat ?? ''} onChange={(e) => set('type_contrat', e.target.value)} />
                </Field>
                <Field label="Année">
                  <select className={inputCls} value={form.annee ?? ''} onChange={(e) => set('annee', e.target.value ? parseInt(e.target.value) : null)}>
                    <option value="">—</option>
                    {yearOptions.map((y) => <option key={y} value={y}>{y}</option>)}
                  </select>
                </Field>
                <Field label="Date signature">
                  <input type="date" className={inputCls} value={form.date_signature ?? ''} onChange={(e) => set('date_signature', e.target.value || null)} />
                </Field>
                <Field label="Date d'effet">
                  <input type="date" className={inputCls} value={form.date_effet ?? ''} onChange={(e) => set('date_effet', e.target.value || null)} />
                </Field>
                <Field label="Statut">
                  <select className={inputCls} value={form.statut} onChange={(e) => set('statut', e.target.value)}>
                    <option value="active">Actif</option>
                    <option value="attente">En attente</option>
                    <option value="resilie">Résilié</option>
                    <option value="frigo">Frigo</option>
                  </select>
                </Field>
              </div>
            </section>

            {/* ── Montants ── */}
            <section>
              <h3 className="text-xs font-bold text-[#B9B9C3] uppercase tracking-widest mb-3">Montants</h3>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Prime TTC (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.prime_ttc} onChange={(e) => set('prime_ttc', e.target.value)} />
                </Field>
                <Field label="Prime HT (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.prime_ht} onChange={(e) => set('prime_ht', e.target.value)} />
                </Field>
                <Field label="Fond euro (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.fond_euro} onChange={(e) => set('fond_euro', e.target.value)} />
                </Field>
                <Field label="UC (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.uc} onChange={(e) => set('uc', e.target.value)} />
                </Field>
                <Field label="Taux commission (ex: 0.60)">
                  <input type="number" step="0.0001" min="0" max="1" className={inputCls} placeholder="0.00" value={form.taux_commission} onChange={(e) => set('taux_commission', e.target.value)} />
                </Field>
              </div>
            </section>

            {/* ── Commissions ── */}
            <section>
              <h3 className="text-xs font-bold text-[#B9B9C3] uppercase tracking-widest mb-3">Commissions</h3>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Com compagnie (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.commission_compagnie} onChange={(e) => set('commission_compagnie', e.target.value)} />
                </Field>
                <Field label="Com MIA à payer (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.commission_mia} onChange={(e) => set('commission_mia', e.target.value)} />
                </Field>
                <Field label="Com récurrente (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.commission_recurrente} onChange={(e) => set('commission_recurrente', e.target.value)} />
                </Field>
                <Field label="Encours (€)">
                  <input type="number" step="0.01" className={inputCls} placeholder="0.00" value={form.encours_commission} onChange={(e) => set('encours_commission', e.target.value)} />
                </Field>
                <Field label="Date commission">
                  <input type="date" className={inputCls} value={form.date_commission ?? ''} onChange={(e) => set('date_commission', e.target.value || null)} />
                </Field>
              </div>
            </section>

            {/* ── Suivi ── */}
            <section>
              <h3 className="text-xs font-bold text-[#B9B9C3] uppercase tracking-widest mb-3">Suivi</h3>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Date résiliation">
                  <input type="date" className={inputCls} value={form.date_resiliation ?? ''} onChange={(e) => set('date_resiliation', e.target.value || null)} />
                </Field>
                <Field label="Date reprise">
                  <input type="date" className={inputCls} value={form.date_reprise ?? ''} onChange={(e) => set('date_reprise', e.target.value || null)} />
                </Field>
                <div className="col-span-2 flex items-center gap-6">
                  <label className="flex items-center gap-2 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={form.regul_transmise}
                      onChange={(e) => set('regul_transmise', e.target.checked)}
                      className="w-4 h-4 accent-[#7367F0]"
                    />
                    <span className="text-sm text-[#5E5873]">Régul transmise</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={form.regul_signee}
                      onChange={(e) => set('regul_signee', e.target.checked)}
                      className="w-4 h-4 accent-[#7367F0]"
                    />
                    <span className="text-sm text-[#5E5873]">Régul signée</span>
                  </label>
                </div>
                <div className="col-span-2">
                  <Field label="Notes">
                    <textarea
                      className={`${inputCls} resize-none`}
                      rows={3}
                      placeholder="Observations…"
                      value={form.notes ?? ''}
                      onChange={(e) => set('notes', e.target.value)}
                    />
                  </Field>
                </div>
              </div>
            </section>
          </div>

          {/* Footer */}
          <div className="flex items-center justify-between px-6 py-4 border-t border-[#EBE9F1] flex-shrink-0 bg-[#FAFAFA]">
            <div>
              {isEdit && !confirmDelete && (
                <button
                  type="button"
                  onClick={() => setConfirmDelete(true)}
                  className="flex items-center gap-2 text-sm text-[#EA5455] hover:bg-[#EA5455]/10 px-3 py-2 rounded-lg transition-colors"
                >
                  <Trash2 size={15} /> Supprimer
                </button>
              )}
              {isEdit && confirmDelete && (
                <div className="flex items-center gap-2">
                  <span className="text-sm text-[#EA5455] font-semibold">Confirmer ?</span>
                  <button type="button" onClick={handleDelete} disabled={saving} className="text-sm bg-[#EA5455] text-white px-3 py-1.5 rounded-lg hover:bg-[#d04343] disabled:opacity-50">
                    Oui, supprimer
                  </button>
                  <button type="button" onClick={() => setConfirmDelete(false)} className="text-sm text-[#6E6B7B] hover:text-[#5E5873] px-2 py-1.5">
                    Annuler
                  </button>
                </div>
              )}
            </div>
            <div className="flex items-center gap-3">
              <button type="button" onClick={onClose} className="text-sm text-[#6E6B7B] hover:text-[#5E5873] px-4 py-2">
                Annuler
              </button>
              <button
                type="submit"
                disabled={saving}
                className="flex items-center gap-2 bg-[#7367F0] text-white text-sm font-semibold px-5 py-2 rounded-xl hover:bg-[#6557e0] transition-colors disabled:opacity-50"
              >
                {saving ? <RefreshCw size={15} className="animate-spin" /> : <Save size={15} />}
                {isEdit ? 'Enregistrer' : 'Créer'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

// ─── Import Wizard ────────────────────────────────────────────────────────────

interface ImportWizardProps {
  members: TeamMember[];
  onClose: () => void;
  onDone: () => void;
}

const ImportWizard: React.FC<ImportWizardProps> = ({ members, onClose, onDone }) => {
  const [step, setStep] = useState(1);
  const [file, setFile] = useState<File | null>(null);
  const [dragging, setDragging] = useState(false);
  const [loading, setLoading] = useState(false);

  const [fileToken, setFileToken] = useState('');
  const [fileExtension, setFileExtension] = useState('');
  const [sheets, setSheets] = useState<string[]>([]);
  const [sheetMapping, setSheetMapping] = useState<Record<string, number | ''>>({});
  const [columns, setColumns] = useState<string[]>([]);
  const [preview, setPreview] = useState<string[][]>([]);
  const [columnMapping, setColumnMapping] = useState<Record<string, string>>({});
  const [result, setResult] = useState<{ imported: number; skipped: number; errors: string[] } | null>(null);

  const dropRef = useRef<HTMLDivElement>(null);

  const handleFile = (f: File) => {
    if (!f.name.match(/\.(xlsx|xls)$/i)) { toast.error('Seuls les fichiers .xlsx ou .xls sont acceptés'); return; }
    setFile(f);
  };

  const uploadPreview = async () => {
    if (!file) return;
    setLoading(true);
    try {
      const form = new FormData();
      form.append('file', file);
      const res = await api.post('/productions/import/preview', form, { headers: { 'Content-Type': 'multipart/form-data' } });
      const data = res.data;
      setFileToken(data.file_token);
      setFileExtension(data.file_extension);
      setSheets(data.sheets);
      setColumns(data.columns);
      setPreview(data.preview);
      const sm: Record<string, number | ''> = {};
      data.sheets.forEach((s: string) => { sm[s] = ''; });
      setSheetMapping(sm);
      const cm: Record<string, string> = {};
      data.columns.forEach((c: string) => { cm[c] = ''; });
      setColumnMapping(cm);
      setStep(2);
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erreur lors de la lecture du fichier');
    } finally {
      setLoading(false);
    }
  };

  const executeImport = async () => {
    setLoading(true);
    try {
      const res = await api.post('/productions/import/execute', {
        file_token: fileToken,
        file_extension: fileExtension,
        sheet_mapping: sheetMapping,
        column_mapping: columnMapping,
      });
      setResult(res.data);
      setStep(4);
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? "Erreur lors de l'import");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <div className="flex items-center justify-between px-6 py-4 border-b border-[#EBE9F1]">
          <div className="flex items-center gap-3">
            <FileSpreadsheet size={20} className="text-[#7367F0]" />
            <span className="font-semibold text-[#5E5873]">Import Excel — Production</span>
          </div>
          <button onClick={onClose} className="text-[#B9B9C3] hover:text-[#5E5873]"><X size={20} /></button>
        </div>

        {/* Steps */}
        <div className="flex items-center justify-center gap-2 px-6 py-3 border-b border-[#EBE9F1] bg-[#F8F8F8]">
          {['Fichier', 'Feuilles → MIA', 'Colonnes → Champs', 'Résultat'].map((label, i) => (
            <React.Fragment key={i}>
              <div className={`flex items-center gap-1.5 text-xs font-semibold ${step === i + 1 ? 'text-[#7367F0]' : step > i + 1 ? 'text-[#28C76F]' : 'text-[#B9B9C3]'}`}>
                <span className={`w-5 h-5 rounded-full flex items-center justify-center text-xs ${step === i + 1 ? 'bg-[#7367F0] text-white' : step > i + 1 ? 'bg-[#28C76F] text-white' : 'bg-[#EBE9F1] text-[#B9B9C3]'}`}>
                  {step > i + 1 ? <Check size={10} /> : i + 1}
                </span>
                <span className="hidden sm:inline">{label}</span>
              </div>
              {i < 3 && <ArrowRight size={12} className="text-[#D0CDE1]" />}
            </React.Fragment>
          ))}
        </div>

        <div className="flex-1 overflow-y-auto p-6">
          {/* Step 1 */}
          {step === 1 && (
            <div className="space-y-4">
              <p className="text-sm text-[#6E6B7B]">Glissez-déposez votre fichier Excel (.xlsx ou .xls) ou cliquez pour sélectionner.</p>
              <div
                ref={dropRef}
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => { e.preventDefault(); setDragging(false); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); }}
                onClick={() => document.getElementById('xl-file-input')?.click()}
                className={`border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors ${dragging ? 'border-[#7367F0] bg-[#7367F0]/5' : 'border-[#D0CDE1] hover:border-[#7367F0]'}`}
              >
                <FileSpreadsheet size={40} className="mx-auto mb-3 text-[#B9B9C3]" />
                {file ? <p className="text-sm font-semibold text-[#5E5873]">{file.name}</p> : <p className="text-sm text-[#B9B9C3]">Glisser & déposer ou cliquer</p>}
                <input id="xl-file-input" type="file" accept=".xlsx,.xls" className="hidden" onChange={(e) => e.target.files?.[0] && handleFile(e.target.files[0])} />
              </div>
            </div>
          )}

          {/* Step 2 */}
          {step === 2 && (
            <div className="space-y-4">
              <p className="text-sm text-[#6E6B7B]">Associez chaque feuille à un membre de l'équipe.</p>
              <div className="space-y-3">
                {sheets.map((sheet) => (
                  <div key={sheet} className="flex items-center gap-4">
                    <span className="w-32 text-sm font-semibold text-[#5E5873] truncate">{sheet}</span>
                    <ArrowRight size={14} className="text-[#B9B9C3] flex-shrink-0" />
                    <select
                      value={sheetMapping[sheet] ?? ''}
                      onChange={(e) => setSheetMapping((prev) => ({ ...prev, [sheet]: e.target.value ? parseInt(e.target.value) : '' }))}
                      className="flex-1 border border-[#D0CDE1] rounded-lg px-3 py-2 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0]"
                    >
                      <option value="">— Sélectionner —</option>
                      {members.map((m) => <option key={m.id} value={m.id}>{memberName(m)}</option>)}
                    </select>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Step 3 */}
          {step === 3 && (
            <div className="space-y-3">
              <p className="text-sm text-[#6E6B7B]">Mappez chaque colonne Excel à un champ CRM.</p>
              <div className="rounded-xl border border-[#EBE9F1] overflow-hidden">
                <table className="w-full text-sm">
                  <thead className="bg-[#F8F8F8]">
                    <tr>
                      <th className="text-left px-4 py-2 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide w-1/2">Colonne Excel</th>
                      <th className="text-left px-4 py-2 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide w-1/2">Champ CRM</th>
                    </tr>
                  </thead>
                  <tbody>
                    {columns.map((col, i) => (
                      <tr key={col} className={i % 2 === 0 ? 'bg-white' : 'bg-[#FAFAFA]'}>
                        <td className="px-4 py-2 font-medium text-[#5E5873]">{col}</td>
                        <td className="px-4 py-2">
                          <select
                            value={columnMapping[col] ?? ''}
                            onChange={(e) => setColumnMapping((prev) => ({ ...prev, [col]: e.target.value }))}
                            className="w-full border border-[#D0CDE1] rounded-lg px-2 py-1.5 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0]"
                          >
                            {CRM_FIELDS.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                          </select>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {preview.length > 0 && (
                <div>
                  <p className="text-xs text-[#B9B9C3] mb-1 font-semibold uppercase tracking-wide">Aperçu</p>
                  <div className="overflow-x-auto rounded-xl border border-[#EBE9F1] text-xs">
                    <table className="w-full">
                      <thead className="bg-[#F8F8F8]">
                        <tr>{columns.map((c) => <th key={c} className="px-3 py-1.5 text-left text-[#B9B9C3] font-semibold whitespace-nowrap">{c}</th>)}</tr>
                      </thead>
                      <tbody>
                        {preview.map((row, ri) => (
                          <tr key={ri} className={ri % 2 === 0 ? 'bg-white' : 'bg-[#FAFAFA]'}>
                            {row.map((cell, ci) => <td key={ci} className="px-3 py-1.5 text-[#5E5873] whitespace-nowrap max-w-[120px] truncate">{cell}</td>)}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Step 4 */}
          {step === 4 && result && (
            <div className="space-y-4">
              <div className="flex items-center gap-3 p-4 bg-[#28C76F]/10 rounded-xl">
                <Check size={24} className="text-[#28C76F]" />
                <div>
                  <p className="font-semibold text-[#5E5873]">Import terminé</p>
                  <p className="text-sm text-[#6E6B7B]">
                    <span className="font-bold text-[#28C76F]">{result.imported}</span> importées,{' '}
                    <span className="font-bold text-[#FF9F43]">{result.skipped}</span> ignorées
                  </p>
                </div>
              </div>
              {result.errors.length > 0 && (
                <div className="p-4 bg-[#EA5455]/10 rounded-xl space-y-1">
                  <div className="flex items-center gap-2 text-[#EA5455] font-semibold text-sm mb-2">
                    <AlertCircle size={16} /> Erreurs
                  </div>
                  {result.errors.slice(0, 10).map((err, i) => <p key={i} className="text-xs text-[#6E6B7B]">{err}</p>)}
                  {result.errors.length > 10 && <p className="text-xs text-[#B9B9C3]">... et {result.errors.length - 10} autres</p>}
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between px-6 py-4 border-t border-[#EBE9F1]">
          {step > 1 && step < 4 ? (
            <button onClick={() => setStep(step - 1)} disabled={loading} className="flex items-center gap-2 text-sm text-[#6E6B7B] hover:text-[#5E5873] disabled:opacity-50">
              <ChevronLeft size={16} /> Retour
            </button>
          ) : <div />}
          <div>
            {step === 4 ? (
              <button onClick={() => { onDone(); onClose(); }} className="flex items-center gap-2 bg-[#28C76F] text-white text-sm font-semibold px-5 py-2 rounded-xl hover:bg-[#24B263] transition-colors">
                <Check size={16} /> Fermer et actualiser
              </button>
            ) : (
              <button
                disabled={loading || (step === 1 && !file) || (step === 2 && Object.values(sheetMapping).every(v => v === ''))}
                onClick={() => { if (step === 1) uploadPreview(); else if (step === 2) setStep(3); else if (step === 3) executeImport(); }}
                className="flex items-center gap-2 bg-[#7367F0] text-white text-sm font-semibold px-5 py-2 rounded-xl hover:bg-[#6557e0] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? <RefreshCw size={16} className="animate-spin" /> : <ChevronRight size={16} />}
                {step === 3 ? 'Importer' : 'Suivant'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

// ─── Main Page ────────────────────────────────────────────────────────────────

const ProductionPage: React.FC = () => {
  const navigate = useNavigate();
  const { setPage } = usePage();
  const { user, isAdmin } = useAuth();

  const [productions, setProductions] = useState<PaginatedProductions | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [assureurs, setAssureurs] = useState<Assureur[]>([]);
  const [loading, setLoading] = useState(true);

  const [showImport, setShowImport] = useState(false);
  const [formTarget, setFormTarget] = useState<Production | null | undefined>(undefined);
  // undefined = fermé, null = nouvelle ligne, Production = édition

  const [selectedUserId, setSelectedUserId] = useState<number | ''>('');
  const [selectedAnnee, setSelectedAnnee] = useState<number | ''>('');
  const [selectedCategorie, setSelectedCategorie] = useState('');
  const [searchClient, setSearchClient] = useState('');
  const [page, setCurrentPage] = useState(1);

  useEffect(() => {
    setPage('Production', [], [{ label: 'Production' }]);
  }, [setPage]);

  useEffect(() => {
    api.get('/assureurs').then((r) => setAssureurs(r.data?.data ?? [])).catch(() => {});
    if (isAdmin && user?.current_team_id) {
      api.get(`/teams/${user.current_team_id}/members`)
        .then((r) => setMembers(r.data?.members ?? r.data?.data ?? []))
        .catch(() => {});
    }
  }, [isAdmin, user?.current_team_id]);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, any> = { page, per_page: 50 };
      if (selectedUserId) params.user_id = selectedUserId;
      if (selectedAnnee) params.annee = selectedAnnee;
      if (selectedCategorie) params.categorie = selectedCategorie;
      if (searchClient.trim()) params.search = searchClient.trim();

      const [prodRes, statsRes] = await Promise.all([
        api.get('/productions', { params }),
        api.get('/productions/stats', { params }),
      ]);
      setProductions(prodRes.data);
      setStats(statsRes.data);
    } catch {
      toast.error('Erreur lors du chargement des données');
    } finally {
      setLoading(false);
    }
  }, [page, selectedUserId, selectedAnnee, selectedCategorie, searchClient]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const currentYear = new Date().getFullYear();
  const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - i);

  const clientName = (p: Production) => {
    if (p.client) return [p.client.prenom, p.client.nom].filter(Boolean).join(' ');
    return [p.prenom_client, p.nom_client].filter(Boolean).join(' ') || '—';
  };
  const companyName = (p: Production) => p.assureur?.nom ?? p.compagnie_libre ?? '—';

  const statutColor: Record<string, string> = {
    active: 'bg-[#28C76F]/10 text-[#28C76F]',
    resilie: 'bg-[#EA5455]/10 text-[#EA5455]',
    attente: 'bg-[#FF9F43]/10 text-[#FF9F43]',
    frigo: 'bg-[#00CFE8]/10 text-[#00CFE8]',
  };
  const statutLabel: Record<string, string> = {
    active: 'Actif', resilie: 'Résilié', attente: 'Attente', frigo: 'Frigo',
  };

  const colSpan = isAdmin ? 10 : 9;

  return (
    <div className="p-6 space-y-6 max-w-[1400px] mx-auto">
      {/* Header */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-3 flex-1 min-w-0">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center shadow-md shadow-purple-500/20">
            <TrendingUp size={20} className="text-white" />
          </div>
          <div>
            <h1 className="text-xl font-bold text-[#5E5873]">Production du cabinet</h1>
            {stats && <p className="text-xs text-[#B9B9C3]">{stats.nb_contrats} contrat{stats.nb_contrats !== 1 ? 's' : ''}</p>}
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <input
            type="text"
            placeholder="Rechercher un client…"
            value={searchClient}
            onChange={(e) => { setSearchClient(e.target.value); setCurrentPage(1); }}
            className="border border-[#D0CDE1] rounded-xl px-3 py-2 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0] bg-white w-44"
          />

          <select
            value={selectedAnnee}
            onChange={(e) => { setSelectedAnnee(e.target.value ? parseInt(e.target.value) : ''); setCurrentPage(1); }}
            className="border border-[#D0CDE1] rounded-xl px-3 py-2 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0] bg-white"
          >
            <option value="">Toutes les années</option>
            {yearOptions.map((y) => <option key={y} value={y}>{y}</option>)}
          </select>

          <input
            type="text"
            placeholder="Catégorie…"
            value={selectedCategorie}
            onChange={(e) => { setSelectedCategorie(e.target.value); setCurrentPage(1); }}
            className="border border-[#D0CDE1] rounded-xl px-3 py-2 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0] bg-white w-32"
          />

          {isAdmin && members.length > 0 && (
            <select
              value={selectedUserId}
              onChange={(e) => { setSelectedUserId(e.target.value ? parseInt(e.target.value) : ''); setCurrentPage(1); }}
              className="border border-[#D0CDE1] rounded-xl px-3 py-2 text-sm text-[#5E5873] focus:outline-none focus:border-[#7367F0] bg-white"
            >
              <option value="">Tout le cabinet</option>
              {members.map((m) => <option key={m.id} value={m.id}>{memberName(m)}</option>)}
            </select>
          )}

          <button
            onClick={() => setShowImport(true)}
            className="flex items-center gap-2 border border-[#7367F0] text-[#7367F0] text-sm font-semibold px-4 py-2 rounded-xl hover:bg-[#7367F0]/10 transition-colors"
          >
            <Upload size={16} /> Importer Excel
          </button>

          <button
            onClick={() => setFormTarget(null)}
            className="flex items-center gap-2 bg-[#7367F0] text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-[#6557e0] transition-colors"
          >
            <Plus size={16} /> Nouvelle ligne
          </button>
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <KpiCard label="Com à payer" value={fmt(stats?.total_commission_mia)} icon={<Banknote size={18} />} iconColor="#7367F0" iconBg="#F0EEFF" />
        <KpiCard label="Récurrentes"  value={fmt(stats?.total_commission_recurrente)} icon={<Repeat2 size={18} />} iconColor="#28C76F" iconBg="#F0FFF6" />
        <KpiCard label="Encours"      value={fmt(stats?.total_encours)} icon={<Clock size={18} />} iconColor="#FF9F43" iconBg="#FFF8EE" />
        <KpiCard label="Contrats"     value={stats?.nb_contrats?.toString() ?? '—'} icon={<FileText size={18} />} iconColor="#00CFE8" iconBg="#F0FBFF" />
      </div>

      {/* Rétrocessions par MIA */}
      {stats && stats.by_mia.length > 0 && (
        <div className="bg-white rounded-xl border border-[#EBE9F1] overflow-hidden">
          <div className="px-5 py-4 border-b border-[#EBE9F1]">
            <h2 className="font-semibold text-[#5E5873] text-sm">Rétrocessions à verser par MIA</h2>
            <p className="text-xs text-[#B9B9C3] mt-0.5">Cliquer sur une ligne pour filtrer le détail ci-dessous</p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#F8F8F8]">
                <tr>
                  <th className="text-left px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">MIA</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Com à payer</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Récurrentes</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Encours</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Contrats</th>
                </tr>
              </thead>
              <tbody>
                {stats.by_mia.map((row, i) => {
                  const isSelected = selectedUserId === row.user_id;
                  return (
                    <tr
                      key={row.user_id}
                      onClick={() => {
                        setSelectedUserId(isSelected ? '' : row.user_id);
                        setCurrentPage(1);
                      }}
                      className={`border-t border-[#EBE9F1] cursor-pointer transition-colors ${
                        isSelected
                          ? 'bg-[#7367F0]/5 ring-1 ring-inset ring-[#7367F0]/20'
                          : i % 2 === 0 ? 'bg-white hover:bg-[#F3F2F7]' : 'bg-[#FAFAFA] hover:bg-[#F3F2F7]'
                      }`}
                    >
                      <td className="px-5 py-3">
                        <div className="flex items-center gap-2">
                          <div className="w-7 h-7 rounded-full bg-gradient-to-br from-[#7367F0] to-[#9055FD] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                            {row.user_name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()}
                          </div>
                          <span className={`font-semibold ${isSelected ? 'text-[#7367F0]' : 'text-[#5E5873]'}`}>
                            {row.user_name}
                          </span>
                          {isSelected && (
                            <span className="ml-1 text-[10px] font-semibold bg-[#7367F0]/10 text-[#7367F0] px-2 py-0.5 rounded-full">
                              filtré
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-3 text-right font-bold text-[#7367F0]">{fmt(row.commission_mia)}</td>
                      <td className="px-5 py-3 text-right text-[#28C76F] font-semibold">{fmt(row.commission_recurrente)}</td>
                      <td className="px-5 py-3 text-right text-[#FF9F43]">{fmt(row.encours_commission)}</td>
                      <td className="px-5 py-3 text-right text-[#6E6B7B]">{row.nb_contrats}</td>
                    </tr>
                  );
                })}
                {/* Ligne total */}
                <tr className="border-t-2 border-[#EBE9F1] bg-[#F8F8F8] font-semibold">
                  <td className="px-5 py-3 text-[#5E5873]">Total cabinet</td>
                  <td className="px-5 py-3 text-right text-[#7367F0]">{fmt(stats.total_commission_mia)}</td>
                  <td className="px-5 py-3 text-right text-[#28C76F]">{fmt(stats.total_commission_recurrente)}</td>
                  <td className="px-5 py-3 text-right text-[#FF9F43]">{fmt(stats.total_encours)}</td>
                  <td className="px-5 py-3 text-right text-[#6E6B7B]">{stats.nb_contrats}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Clients du MIA sélectionné */}
      {selectedUserId && stats && stats.by_client.length > 0 && (
        <div className="bg-white rounded-xl border border-[#EBE9F1] overflow-hidden">
          <div className="px-5 py-4 border-b border-[#EBE9F1] flex items-center justify-between">
            <div>
              <h2 className="font-semibold text-[#5E5873] text-sm">
                Portefeuille clients — {stats.by_mia.find(m => m.user_id === selectedUserId)?.user_name ?? 'MIA'}
              </h2>
              <p className="text-xs text-[#B9B9C3] mt-0.5">{stats.by_client.length} client{stats.by_client.length > 1 ? 's' : ''} en portefeuille</p>
            </div>
            <button
              onClick={() => { setSelectedUserId(''); setCurrentPage(1); }}
              className="text-xs text-[#B9B9C3] hover:text-[#EA5455] flex items-center gap-1 transition-colors"
            >
              <X size={13} /> Effacer le filtre
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#F8F8F8]">
                <tr>
                  <th className="text-left px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Client</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Contrats</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Prime TTC</th>
                  <th className="text-right px-5 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Com MIA</th>
                </tr>
              </thead>
              <tbody>
                {stats.by_client.map((c, i) => (
                  <tr
                    key={`${c.client_id ?? 'free'}-${i}`}
                    onClick={() => c.is_crm_client && c.client_id && navigate(`/clients/${c.client_id}`)}
                    className={`border-t border-[#EBE9F1] transition-colors ${
                      c.is_crm_client ? 'cursor-pointer hover:bg-[#F3F2F7]' : ''
                    } ${i % 2 === 0 ? 'bg-white' : 'bg-[#FAFAFA]'}`}
                  >
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-2">
                        <span className={`font-medium ${c.is_crm_client ? 'text-[#7367F0]' : 'text-[#5E5873]'}`}>
                          {c.client_name}
                        </span>
                        {c.is_crm_client && (
                          <span className="text-[10px] font-semibold bg-[#7367F0]/10 text-[#7367F0] px-1.5 py-0.5 rounded-full">CRM</span>
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-3 text-right text-[#6E6B7B]">{c.nb_contrats}</td>
                    <td className="px-5 py-3 text-right text-[#6E6B7B]">{fmt(c.prime_ttc)}</td>
                    <td className="px-5 py-3 text-right font-semibold text-[#7367F0]">{fmt(c.commission_mia)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Table */}
      <div className="bg-white rounded-xl border border-[#EBE9F1] overflow-hidden">
        <div className="flex items-center justify-between px-5 py-4 border-b border-[#EBE9F1]">
          <span className="font-semibold text-[#5E5873] text-sm">
            Lignes de production {productions ? `(${productions.total})` : ''}
          </span>
          <button onClick={fetchData} className="text-[#B9B9C3] hover:text-[#7367F0] transition-colors">
            <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-[#F8F8F8]">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Client</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Compagnie</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Catégorie</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Année</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Prime TTC</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Com MIA</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Récurrent</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">Statut</th>
                {isAdmin && <th className="text-left px-4 py-3 text-xs font-semibold text-[#B9B9C3] uppercase tracking-wide">MIA</th>}
                <th className="px-4 py-3 w-10" />
              </tr>
            </thead>
            <tbody>
              {loading && !productions && (
                <tr>
                  <td colSpan={colSpan} className="text-center py-12 text-[#B9B9C3]">
                    <RefreshCw size={24} className="animate-spin mx-auto mb-2" />
                    Chargement…
                  </td>
                </tr>
              )}
              {!loading && productions?.data.length === 0 && (
                <tr>
                  <td colSpan={colSpan} className="text-center py-12 text-[#B9B9C3]">
                    Aucune ligne de production.{' '}
                    <button onClick={() => setFormTarget(null)} className="text-[#7367F0] underline">Créer la première ?</button>
                  </td>
                </tr>
              )}
              {productions?.data.map((p, i) => (
                <tr
                  key={p.id}
                  className={`border-t border-[#EBE9F1] ${i % 2 === 0 ? 'bg-white' : 'bg-[#FAFAFA]'} hover:bg-[#F3F2F7] transition-colors cursor-pointer`}
                  onClick={() => setFormTarget(p)}
                >
                  <td className="px-4 py-3">
                    {p.client_id ? (
                      <button
                        onClick={(e) => { e.stopPropagation(); navigate(`/clients/${p.client_id}`); }}
                        className="font-medium text-[#7367F0] hover:underline text-left"
                      >
                        {clientName(p)}
                      </button>
                    ) : (
                      <span className="font-medium text-[#5E5873]">{clientName(p)}</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-[#6E6B7B]">{companyName(p)}</td>
                  <td className="px-4 py-3 text-[#6E6B7B]">{p.categorie ?? '—'}</td>
                  <td className="px-4 py-3 text-[#6E6B7B]">{p.annee ?? '—'}</td>
                  <td className="px-4 py-3 text-right text-[#6E6B7B]">{fmt(p.prime_ttc)}</td>
                  <td className="px-4 py-3 text-right font-semibold text-[#7367F0]">{fmt(p.commission_mia)}</td>
                  <td className="px-4 py-3 text-right text-[#28C76F]">{fmt(p.commission_recurrente)}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${statutColor[p.statut] ?? 'bg-[#EBE9F1] text-[#6E6B7B]'}`}>
                      {statutLabel[p.statut] ?? p.statut}
                    </span>
                  </td>
                  {isAdmin && (
                    <td className="px-4 py-3 text-[#6E6B7B]">
                      {p.user ? [p.user.firstname, p.user.name].filter(Boolean).join(' ') : '—'}
                    </td>
                  )}
                  <td className="px-4 py-3 text-right">
                    <Pencil size={14} className="text-[#B9B9C3] group-hover:text-[#7367F0]" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {productions && productions.last_page > 1 && (
          <div className="flex items-center justify-between px-5 py-3 border-t border-[#EBE9F1]">
            <span className="text-xs text-[#B9B9C3]">Page {productions.current_page} / {productions.last_page}</span>
            <div className="flex items-center gap-2">
              <button disabled={productions.current_page === 1} onClick={() => setCurrentPage(page - 1)} className="p-1.5 rounded-lg border border-[#D0CDE1] text-[#6E6B7B] hover:bg-[#F3F2F7] disabled:opacity-40 disabled:cursor-not-allowed">
                <ChevronLeft size={16} />
              </button>
              <button disabled={productions.current_page === productions.last_page} onClick={() => setCurrentPage(page + 1)} className="p-1.5 rounded-lg border border-[#D0CDE1] text-[#6E6B7B] hover:bg-[#F3F2F7] disabled:opacity-40 disabled:cursor-not-allowed">
                <ChevronRight size={16} />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modals */}
      {showImport && (
        <ImportWizard members={members} onClose={() => setShowImport(false)} onDone={fetchData} />
      )}
      {formTarget !== undefined && (
        <ProductionFormModal
          initial={formTarget}
          members={members}
          assureurs={assureurs}
          isAdmin={isAdmin}
          currentUserId={user?.id ?? 0}
          onClose={() => setFormTarget(undefined)}
          onSaved={fetchData}
        />
      )}
    </div>
  );
};

export default ProductionPage;
