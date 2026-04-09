import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast, ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import api from "../api/apiClient";
import { UserPlus, ClipboardList, Upload, HeartPulse, ShieldAlert, PiggyBank, FileText, Wallet, ArrowUpRight, Mic, CheckCircle, AlertTriangle, Clock, TrendingUp, Building2, Users, User } from "lucide-react";
import {
  PieChart, Pie, Cell, Tooltip, ResponsiveContainer,
  BarChart, Bar, XAxis, YAxis, CartesianGrid,
  AreaChart, Area,
} from "recharts";
import { useAuth } from "../contexts/AuthContext";
import { usePage } from "../contexts/PageContext";
import type { PageAction } from "../contexts/PageContext";

const HomePage: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [dashStats, setDashStats] = useState<{
    pipeline: { prospects: number; clients: number; taux_conversion: number; nouveaux_mois: number; archives_mois: number };
    portefeuille: { nb_contrats: number; nb_contrats_signes: number; taux_equipement: number; en_cours_total: number; mensualites_total: number; par_assureur: { label: string; count: number }[] };
    conformite: { complets: number; partiels: number; incomplets: number; docs_expires: number; docs_expirant_30j: number; docs_pending: number };
    activite_ia: { enregistrements_mois: number; taux_diarisation: number; clients_enrichis: number; audio_6mois: { mois: string; count: number }[] };
    equipe: { id: number; nom: string; role: string; nb_contacts: number; nb_clients: number; audio_mois: number; taux_conformite: number }[];
    opportunites: { sans_sante: number; sans_prevoyance: number; sans_retraite: number; sans_epargne: number };
    besoins_repartition: { label: string; count: number }[];
    contrats_par_type: { type: string; label: string; count: number }[];
    nouveaux_6mois: { mois: string; count: number }[];
    contrats_6mois: { mois: string; count: number }[];
  } | null>(null);
  const navigate = useNavigate();
  const { isAdmin } = useAuth();
  const { setPage } = usePage();

  useEffect(() => {
    const actions: PageAction[] = [
      {
        label: 'Nouveau client',
        icon: <UserPlus size={15} />,
        onClick: () => navigate('/clients/new'),
        variant: 'primary' as const,
      },
    ];
    if (isAdmin) {
      actions.push({
        label: 'Importer',
        icon: <Upload size={15} />,
        onClick: () => navigate('/import'),
        variant: 'outline' as const,
      });
    }
    setPage('Tableau de bord', actions, [
      { label: 'Tableau de bord' },
    ]);
  }, [isAdmin, navigate, setPage]);

  useEffect(() => {
    fetchStats();
  }, []);

  const fetchStats = async () => {
    try {
      setLoading(true);
      const statsRes = await api.get("/dashboard/stats");
      setDashStats(statsRes.data);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors du chargement du tableau de bord");
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-screen bg-[#F8F8F8]">
        <div className="flex flex-col items-center space-y-4">
          <div className="w-16 h-16 border-4 border-[#7367F0] border-t-transparent rounded-full animate-spin"></div>
          <p className="text-[#6E6B7B] font-semibold">Chargement...</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <ToastContainer position="top-right" autoClose={3000} />
      <div className="min-h-screen bg-[#F8F8F8] py-6 px-4 lg:px-6">
        <div className="w-full max-w-7xl mx-auto space-y-6">
          {/* Dashboard */}
          {dashStats && (() => {
            const fmt = (n: number) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n);
            // Affiche 1 décimale uniquement si < 1% et non nul, sinon entier
            const fmtPct = (v: number) => v > 0 && v < 1 ? v.toFixed(1) : Math.round(v).toString();
            const total = dashStats.pipeline.prospects + dashStats.pipeline.clients;
            const pieData = [
              { name: 'Prospects', value: dashStats.pipeline.prospects, color: '#00CFE8' },
              { name: 'Clients', value: dashStats.pipeline.clients, color: '#28C76F' },
            ];
            const CONTRAT_COLORS: Record<string, string> = {
              sante: '#EA5455', prevoyance: '#7367F0', per: '#28C76F',
              assurance_vie: '#FF9F43', emprunteur: '#00CFE8', vie_entiere: '#9055FD',
            };
            const besoinsRepartition = dashStats.besoins_repartition;
            const besoinsTotal = besoinsRepartition.reduce((s, x) => s + x.count, 0);

            return (
              <div className="space-y-4">

                {/* Ligne 1 — 4 chips KPI */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  {[
                    { label: 'Clients actifs', value: dashStats.pipeline.clients, sub: `${fmtPct(dashStats.pipeline.taux_conversion)}% de conversion`, color: '#28C76F', bg: '#F0FFF6', icon: <User size={18} /> },
                    { label: 'Contrats', value: `${dashStats.portefeuille.nb_contrats_signes} / ${dashStats.portefeuille.nb_contrats}`, sub: `${dashStats.portefeuille.taux_equipement.toFixed(1)} / client · signés / renseignés`, color: '#FF9F43', bg: '#FFF8EE', icon: <FileText size={18} /> },
                    { label: 'En-cours', value: fmt(dashStats.portefeuille.en_cours_total), sub: 'PER + Ass. Vie', color: '#00CFE8', bg: '#F0FBFF', icon: <Wallet size={18} /> },
                  ].map((chip) => (
                    <div key={chip.label} className="vx-card p-4 flex items-center gap-3">
                      <div className="w-10 h-10 rounded-lg flex-shrink-0 flex items-center justify-center" style={{ background: chip.bg }}>
                        <span style={{ color: chip.color }}>{chip.icon}</span>
                      </div>
                      <div className="min-w-0">
                        <div className="text-xl font-bold text-[#5E5873] leading-tight truncate">{chip.value}</div>
                        <div className="text-xs text-[#6E6B7B] font-medium">{chip.label}</div>
                        <div className="text-[10px] text-[#B9B9C3] mt-0.5">{chip.sub}</div>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Ligne 2 — Donut + Contrats par type */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                  {/* Donut — Répartition portefeuille */}
                  <div className="vx-card p-5">
                    <div className="flex items-center justify-between mb-4">
                      <div>
                        <h3 className="text-sm font-bold text-[#5E5873]">Portefeuille</h3>
                        <p className="text-xs text-[#B9B9C3]">Répartition prospects / clients</p>
                      </div>
                      <span className="text-xs font-semibold px-2 py-1 rounded-full bg-[#F3F2FF] text-[#7367F0]">{total} contacts</span>
                    </div>
                    <div className="flex items-center gap-4">
                      <div style={{ width: 140, height: 140, flexShrink: 0 }}>
                        <ResponsiveContainer width="100%" height="100%">
                          <PieChart>
                            <Pie data={pieData} cx="50%" cy="50%" innerRadius={42} outerRadius={62} paddingAngle={3} dataKey="value" startAngle={90} endAngle={-270}>
                              {pieData.map((entry) => <Cell key={entry.name} fill={entry.color} />)}
                            </Pie>
                            <Tooltip formatter={(v: any) => [`${v}`, '']} contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #EBE9F1' }} />
                          </PieChart>
                        </ResponsiveContainer>
                      </div>
                      <div className="flex-1 space-y-3">
                        {pieData.map((d) => {
                          const pct = total > 0 ? d.value / total * 100 : 0;
                          return (
                            <div key={d.name}>
                              <div className="flex justify-between text-xs font-semibold text-[#5E5873] mb-1">
                                <span className="flex items-center gap-1.5">
                                  <span className="w-2 h-2 rounded-full inline-block" style={{ background: d.color }} />
                                  {d.name}
                                </span>
                                <span style={{ color: d.color }}>{d.value} · {fmtPct(pct)}%</span>
                              </div>
                              <div className="w-full bg-[#EBE9F1] rounded-full h-1.5">
                                <div className="h-1.5 rounded-full transition-all duration-700" style={{ width: `${pct}%`, background: d.color }} />
                              </div>
                            </div>
                          );
                        })}
                        <div className="pt-1 border-t border-[#EBE9F1]">
                          <div className="flex items-center gap-1 text-xs text-[#28C76F] font-semibold">
                            <ArrowUpRight size={12} />
                            {fmtPct(dashStats.pipeline.taux_conversion)}% taux de conversion
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Bar chart — Contrats par type */}
                  <div className="vx-card p-5">
                    <div className="flex items-center justify-between mb-4">
                      <div>
                        <h3 className="text-sm font-bold text-[#5E5873]">Contrats souscrits</h3>
                        <p className="text-xs text-[#B9B9C3]">Répartition par type</p>
                      </div>
                      <span className="text-xs font-semibold px-2 py-1 rounded-full bg-[#FFF8EE] text-[#FF9F43]">{dashStats.portefeuille.nb_contrats} contrats</span>
                    </div>
                    {dashStats.contrats_par_type.length > 0 ? (
                      <ResponsiveContainer width="100%" height={140}>
                        <BarChart data={dashStats.contrats_par_type} layout="vertical" margin={{ left: 8, right: 16, top: 0, bottom: 0 }}>
                          <CartesianGrid horizontal={false} stroke="#F1F0F5" />
                          <XAxis type="number" tick={{ fontSize: 11, fill: '#B9B9C3' }} axisLine={false} tickLine={false} allowDecimals={false} />
                          <YAxis type="category" dataKey="label" tick={{ fontSize: 11, fill: '#6E6B7B', fontWeight: 600 }} axisLine={false} tickLine={false} width={64} />
                          <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #EBE9F1' }} formatter={(v: any) => [v, 'contrats']} />
                          <Bar dataKey="count" radius={[0, 4, 4, 0]} maxBarSize={14}>
                            {dashStats.contrats_par_type.map((entry) => (
                              <Cell key={entry.type} fill={CONTRAT_COLORS[entry.type] ?? '#7367F0'} />
                            ))}
                          </Bar>
                        </BarChart>
                      </ResponsiveContainer>
                    ) : (
                      <div className="h-[140px] flex items-center justify-center text-sm text-[#B9B9C3] italic">Aucun contrat renseigné</div>
                    )}
                  </div>
                </div>

                {/* Ligne 3 — Tendances */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                  {/* Area chart — Contrats signés 6 mois */}
                  <div className="vx-card p-5">
                    <div className="flex items-center justify-between mb-4">
                      <div>
                        <h3 className="text-sm font-bold text-[#5E5873]">Contrats signés</h3>
                        <p className="text-xs text-[#B9B9C3]">Lettres de mission uploadées · 6 mois</p>
                      </div>
                      <span className="text-xs font-semibold px-2 py-1 rounded-full bg-[#FFF8EE] text-[#FF9F43]">
                        {dashStats.contrats_6mois[dashStats.contrats_6mois.length - 1]?.count ?? 0} ce mois
                      </span>
                    </div>
                    <ResponsiveContainer width="100%" height={130}>
                      <AreaChart data={dashStats.contrats_6mois} margin={{ left: -16, right: 8, top: 4, bottom: 0 }}>
                        <defs>
                          <linearGradient id="gradOrange" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#FF9F43" stopOpacity={0.2} />
                            <stop offset="95%" stopColor="#FF9F43" stopOpacity={0} />
                          </linearGradient>
                        </defs>
                        <CartesianGrid vertical={false} stroke="#F1F0F5" />
                        <XAxis dataKey="mois" tick={{ fontSize: 11, fill: '#B9B9C3' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 11, fill: '#B9B9C3' }} axisLine={false} tickLine={false} allowDecimals={false} />
                        <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #EBE9F1' }} formatter={(v: any) => [v, 'contrats']} />
                        <Area type="monotone" dataKey="count" stroke="#FF9F43" strokeWidth={2} fill="url(#gradOrange)" dot={{ r: 3, fill: '#FF9F43', strokeWidth: 0 }} activeDot={{ r: 5 }} />
                      </AreaChart>
                    </ResponsiveContainer>
                  </div>

                  {/* Besoins déclarés */}
                  {besoinsRepartition.length > 0 ? (
                    <div className="vx-card p-5">
                      <div className="flex items-center justify-between mb-4">
                        <div>
                          <h3 className="text-sm font-bold text-[#5E5873]">Besoins déclarés</h3>
                          <p className="text-xs text-[#B9B9C3]">Répartition par catégorie</p>
                        </div>
                        <ClipboardList size={16} className="text-[#B9B9C3]" />
                      </div>
                      <div className="space-y-3">
                        {besoinsRepartition.slice(0, 5).map((b, i) => {
                          const pct = besoinsTotal > 0 ? Math.round(b.count / besoinsTotal * 100) : 0;
                          const colors = ['#7367F0', '#28C76F', '#FF9F43', '#00CFE8', '#EA5455'];
                          const c = colors[i % colors.length];
                          return (
                            <div key={b.label}>
                              <div className="flex justify-between text-xs font-semibold text-[#5E5873] mb-1">
                                <span className="flex items-center gap-1.5">
                                  <span className="w-2 h-2 rounded-full inline-block" style={{ background: c }} />
                                  {b.label}
                                </span>
                                <span className="text-[#B9B9C3]">{b.count} · {pct}%</span>
                              </div>
                              <div className="w-full bg-[#EBE9F1] rounded-full h-1.5">
                                <div className="h-1.5 rounded-full transition-all duration-700" style={{ width: `${pct}%`, background: c }} />
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  ) : (
                    (dashStats.opportunites.sans_sante > 0 || dashStats.opportunites.sans_prevoyance > 0 || dashStats.opportunites.sans_retraite > 0 || dashStats.opportunites.sans_epargne > 0) && (
                      <div className="vx-card p-5">
                        <div className="flex items-center gap-2 mb-4">
                          <ShieldAlert size={16} className="text-[#FF9F43]" />
                          <h3 className="text-sm font-bold text-[#5E5873]">Opportunités</h3>
                        </div>
                        <div className="space-y-2">
                          {[
                            { label: 'Sans mutuelle santé', value: dashStats.opportunites.sans_sante, color: '#EA5455', bg: '#FFF5F5', icon: <HeartPulse size={14} /> },
                            { label: 'Sans prévoyance', value: dashStats.opportunites.sans_prevoyance, color: '#FF9F43', bg: '#FFF8EE', icon: <ShieldAlert size={14} /> },
                            { label: 'Sans retraite (PER)', value: dashStats.opportunites.sans_retraite, color: '#7367F0', bg: '#F3F2FF', icon: <PiggyBank size={14} /> },
                            { label: 'Sans épargne', value: dashStats.opportunites.sans_epargne, color: '#28C76F', bg: '#F0FFF6', icon: <Wallet size={14} /> },
                          ].map((o) => (
                            <div key={o.label} className="flex items-center gap-3 p-3 rounded-lg" style={{ background: o.bg }}>
                              <span style={{ color: o.color }}>{o.icon}</span>
                              <span className="text-xs font-semibold text-[#5E5873] flex-1">{o.label}</span>
                              <span className="text-sm font-bold" style={{ color: o.color }}>{o.value}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )
                  )}
                </div>

                {/* Ligne 4 — Opportunités (si besoins déjà affichés) */}
                {besoinsRepartition.length > 0 && (dashStats.opportunites.sans_sante > 0 || dashStats.opportunites.sans_prevoyance > 0 || dashStats.opportunites.sans_retraite > 0 || dashStats.opportunites.sans_epargne > 0) && (
                  <div className="vx-card p-5">
                    <div className="flex items-center gap-2 mb-4">
                      <ShieldAlert size={16} className="text-[#FF9F43]" />
                      <h3 className="text-sm font-bold text-[#5E5873]">Opportunités commerciales</h3>
                      <span className="text-xs text-[#B9B9C3]">— contacts sans couverture</span>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                      {[
                        { label: 'Sans mutuelle santé', value: dashStats.opportunites.sans_sante, color: '#EA5455', bg: '#FFF5F5', icon: <HeartPulse size={20} /> },
                        { label: 'Sans prévoyance', value: dashStats.opportunites.sans_prevoyance, color: '#FF9F43', bg: '#FFF8EE', icon: <ShieldAlert size={20} /> },
                        { label: 'Sans retraite (PER)', value: dashStats.opportunites.sans_retraite, color: '#7367F0', bg: '#F3F2FF', icon: <PiggyBank size={20} /> },
                        { label: 'Sans épargne', value: dashStats.opportunites.sans_epargne, color: '#28C76F', bg: '#F0FFF6', icon: <Wallet size={20} /> },
                      ].map((o) => (
                        <div key={o.label} className="text-center p-4 rounded-xl border" style={{ background: o.bg, borderColor: o.color + '33' }}>
                          <div className="flex justify-center mb-2" style={{ color: o.color }}>{o.icon}</div>
                          <div className="text-2xl font-bold" style={{ color: o.color }}>{o.value}</div>
                          <div className="text-xs font-semibold text-[#6E6B7B] mt-1">{o.label}</div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* ═══════════════════════════════════════════════
                    CONFORMITÉ RÉGLEMENTAIRE
                ════════════════════════════════════════════════ */}
                {(() => {
                  const conf = dashStats.conformite;
                  const totalConf = conf.complets + conf.partiels + conf.incomplets;
                  const pctComplets = totalConf > 0 ? Math.round(conf.complets / totalConf * 100) : 0;
                  const pctPartiels = totalConf > 0 ? Math.round(conf.partiels / totalConf * 100) : 0;
                  const pctIncomplets = totalConf > 0 ? Math.round(conf.incomplets / totalConf * 100) : 0;
                  const confDonutData = [
                    { name: 'Conformes', value: conf.complets, color: '#28C76F' },
                    { name: 'En cours', value: conf.partiels, color: '#FF9F43' },
                    { name: 'Incomplets', value: conf.incomplets, color: '#EA5455' },
                  ].filter(d => d.value > 0);

                  return (
                    <div className="vx-card p-5">
                      <div className="flex items-center justify-between mb-4">
                        <div>
                          <h3 className="text-sm font-bold text-[#5E5873]">Conformité réglementaire</h3>
                          <p className="text-xs text-[#B9B9C3]">État des dossiers · {totalConf} contacts actifs</p>
                        </div>
                        {(conf.docs_expires > 0 || conf.docs_expirant_30j > 0) && (
                          <div className="flex items-center gap-2">
                            {conf.docs_expires > 0 && (
                              <span className="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#EA5455]/10 text-[#EA5455]">
                                <AlertTriangle size={11} /> {conf.docs_expires} expiré{conf.docs_expires > 1 ? 's' : ''}
                              </span>
                            )}
                            {conf.docs_expirant_30j > 0 && (
                              <span className="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#FF9F43]/10 text-[#FF9F43]">
                                <Clock size={11} /> {conf.docs_expirant_30j} expirent bientôt
                              </span>
                            )}
                            {conf.docs_pending > 0 && (
                              <span className="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#00CFE8]/10 text-[#00CFE8]">
                                <Clock size={11} /> {conf.docs_pending} en attente de validation
                              </span>
                            )}
                          </div>
                        )}
                      </div>

                      <div className="flex items-center gap-8">
                        {/* Donut */}
                        <div style={{ width: 130, height: 130, flexShrink: 0 }}>
                          <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                              <Pie data={confDonutData.length > 0 ? confDonutData : [{ name: 'Vide', value: 1, color: '#EBE9F1' }]}
                                cx="50%" cy="50%" innerRadius={38} outerRadius={58} paddingAngle={3} dataKey="value" startAngle={90} endAngle={-270}>
                                {(confDonutData.length > 0 ? confDonutData : [{ name: 'Vide', value: 1, color: '#EBE9F1' }]).map((entry) => (
                                  <Cell key={entry.name} fill={entry.color} />
                                ))}
                              </Pie>
                              <Tooltip formatter={(v: any, name: any) => [v, name]} contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #EBE9F1' }} />
                            </PieChart>
                          </ResponsiveContainer>
                        </div>

                        {/* Légende + barres */}
                        <div className="flex-1 space-y-3">
                          {[
                            { label: 'Dossiers conformes', value: conf.complets, pct: pctComplets, color: '#28C76F', bg: '#F0FFF6', icon: <CheckCircle size={13} /> },
                            { label: 'En cours de conformité', value: conf.partiels, pct: pctPartiels, color: '#FF9F43', bg: '#FFF8EE', icon: <Clock size={13} /> },
                            { label: 'Dossiers incomplets', value: conf.incomplets, pct: pctIncomplets, color: '#EA5455', bg: '#FFF5F5', icon: <AlertTriangle size={13} /> },
                          ].map((row) => (
                            <div key={row.label}>
                              <div className="flex items-center justify-between text-xs font-semibold text-[#5E5873] mb-1">
                                <span className="flex items-center gap-1.5" style={{ color: row.color }}>{row.icon}<span className="text-[#5E5873]">{row.label}</span></span>
                                <span style={{ color: row.color }}>{row.value} · {row.pct}%</span>
                              </div>
                              <div className="w-full bg-[#EBE9F1] rounded-full h-1.5">
                                <div className="h-1.5 rounded-full transition-all duration-700" style={{ width: `${row.pct}%`, background: row.color }} />
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  );
                })()}

                {/* ═══════════════════════════════════════════════
                    FINANCIER (mensualités + assureurs) + IA
                ════════════════════════════════════════════════ */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">

                  {/* KPI mensualités + archives */}
                  <div className="vx-card p-5 flex flex-col justify-between">
                    <div>
                      <h3 className="text-sm font-bold text-[#5E5873] mb-1">Encaissements mensuels</h3>
                      <p className="text-xs text-[#B9B9C3] mb-4">Total des mensualités du portefeuille</p>
                    </div>
                    <div>
                      <div className="text-3xl font-bold text-[#5E5873]">
                        {new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(dashStats.portefeuille.mensualites_total)}
                      </div>
                      <div className="text-xs text-[#B9B9C3] mt-1">/mois · {dashStats.portefeuille.nb_contrats} contrats actifs</div>
                    </div>
                    <div className="mt-4 pt-4 border-t border-[#EBE9F1] flex items-center justify-between">
                      <div className="text-center">
                        <div className="text-lg font-bold text-[#28C76F]">{dashStats.pipeline.nouveaux_mois}</div>
                        <div className="text-[10px] text-[#B9B9C3] font-medium">Nouveaux ce mois</div>
                      </div>
                      <div className="w-px h-8 bg-[#EBE9F1]" />
                      <div className="text-center">
                        <div className="text-lg font-bold text-[#EA5455]">{dashStats.pipeline.archives_mois}</div>
                        <div className="text-[10px] text-[#B9B9C3] font-medium">Archivés ce mois</div>
                      </div>
                      <div className="w-px h-8 bg-[#EBE9F1]" />
                      <div className="text-center">
                        <div className="text-lg font-bold text-[#7367F0]">{dashStats.pipeline.taux_conversion}%</div>
                        <div className="text-[10px] text-[#B9B9C3] font-medium">Taux conversion</div>
                      </div>
                    </div>
                  </div>

                  {/* Top assureurs */}
                  <div className="vx-card p-5">
                    <div className="flex items-center justify-between mb-4">
                      <div>
                        <h3 className="text-sm font-bold text-[#5E5873]">Top assureurs</h3>
                        <p className="text-xs text-[#B9B9C3]">Par nombre de contrats</p>
                      </div>
                      <Building2 size={16} className="text-[#B9B9C3]" />
                    </div>
                    {dashStats.portefeuille.par_assureur.length > 0 ? (
                      <div className="space-y-2.5">
                        {dashStats.portefeuille.par_assureur.map((a, i) => {
                          const maxVal = dashStats.portefeuille.par_assureur[0]?.count ?? 1;
                          const pct = Math.round(a.count / maxVal * 100);
                          const colors = ['#7367F0','#28C76F','#FF9F43','#00CFE8','#EA5455'];
                          const c = colors[i % colors.length];
                          return (
                            <div key={a.label}>
                              <div className="flex justify-between text-xs font-semibold text-[#5E5873] mb-1">
                                <span className="flex items-center gap-1.5">
                                  <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ background: c }} />
                                  <span className="truncate max-w-[130px]">{a.label}</span>
                                </span>
                                <span className="text-[#B9B9C3]">{a.count}</span>
                              </div>
                              <div className="w-full bg-[#EBE9F1] rounded-full h-1.5">
                                <div className="h-1.5 rounded-full" style={{ width: `${pct}%`, background: c }} />
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    ) : (
                      <div className="h-[100px] flex items-center justify-center text-xs text-[#B9B9C3] italic">Aucun contrat renseigné</div>
                    )}
                  </div>

                  {/* Activité IA */}
                  <div className="vx-card p-5">
                    <div className="flex items-center justify-between mb-4">
                      <div>
                        <h3 className="text-sm font-bold text-[#5E5873]">Activité IA</h3>
                        <p className="text-xs text-[#B9B9C3]">Enregistrements & transcriptions</p>
                      </div>
                      <Mic size={16} className="text-[#7367F0]" />
                    </div>
                    <div className="space-y-3">
                      {[
                        { label: 'Enregistrements ce mois', value: dashStats.activite_ia.enregistrements_mois, icon: <Mic size={14} />, color: '#7367F0', bg: '#F3F2FF' },
                        { label: 'Dossiers enrichis ce mois', value: dashStats.activite_ia.clients_enrichis, icon: <TrendingUp size={14} />, color: '#28C76F', bg: '#F0FFF6' },
                        { label: 'Taux diarisation réussie', value: `${dashStats.activite_ia.taux_diarisation}%`, icon: <CheckCircle size={14} />, color: '#00CFE8', bg: '#F0FBFF' },
                      ].map((item) => (
                        <div key={item.label} className="flex items-center gap-3 p-3 rounded-xl" style={{ background: item.bg }}>
                          <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style={{ background: item.color + '20', color: item.color }}>
                            {item.icon}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="text-xs font-medium text-[#6E6B7B]">{item.label}</div>
                          </div>
                          <div className="text-lg font-bold flex-shrink-0" style={{ color: item.color }}>{item.value}</div>
                        </div>
                      ))}
                    </div>
                    {/* Mini area chart audio */}
                    {dashStats.activite_ia.audio_6mois.some(m => m.count > 0) && (
                      <div className="mt-3">
                        <ResponsiveContainer width="100%" height={60}>
                          <AreaChart data={dashStats.activite_ia.audio_6mois} margin={{ left: -16, right: 4, top: 4, bottom: 0 }}>
                            <defs>
                              <linearGradient id="gradPurple" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#7367F0" stopOpacity={0.2} />
                                <stop offset="95%" stopColor="#7367F0" stopOpacity={0} />
                              </linearGradient>
                            </defs>
                            <XAxis dataKey="mois" tick={{ fontSize: 10, fill: '#B9B9C3' }} axisLine={false} tickLine={false} />
                            <Tooltip contentStyle={{ fontSize: 11, borderRadius: 8, border: '1px solid #EBE9F1' }} formatter={(v: any) => [v, 'enreg.']} />
                            <Area type="monotone" dataKey="count" stroke="#7367F0" strokeWidth={2} fill="url(#gradPurple)" dot={false} />
                          </AreaChart>
                        </ResponsiveContainer>
                      </div>
                    )}
                  </div>
                </div>

                {/* ═══════════════════════════════════════════════
                    PERFORMANCE ÉQUIPE (admin only)
                ════════════════════════════════════════════════ */}
                {isAdmin && dashStats.equipe.length > 0 && (
                  <div className="vx-card p-5">
                    <div className="flex items-center justify-between mb-4">
                      <div>
                        <h3 className="text-sm font-bold text-[#5E5873]">Performance équipe</h3>
                        <p className="text-xs text-[#B9B9C3]">Activité par conseiller · mois en cours</p>
                      </div>
                      <Users size={16} className="text-[#B9B9C3]" />
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead>
                          <tr className="border-b border-[#EBE9F1]">
                            {['Conseiller', 'Rôle', 'Contacts', 'Clients', 'Audio (mois)', 'Conformité'].map(h => (
                              <th key={h} className="text-left px-3 py-2 text-[10px] font-bold text-[#B9B9C3] uppercase tracking-wide">{h}</th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {dashStats.equipe.map((m) => (
                            <tr key={m.id} className="border-b border-[#F3F2F7] hover:bg-[#F8F8F8]">
                              <td className="px-3 py-3 font-semibold text-[#5E5873]">{m.nom}</td>
                              <td className="px-3 py-3">
                                <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                  m.role === 'owner'      ? 'bg-purple-100 text-purple-700' :
                                  m.role === 'admin'      ? 'bg-blue-100 text-blue-700' :
                                  m.role === 'mia'        ? 'bg-green-100 text-green-700' :
                                  m.role === 'secretaire' ? 'bg-gray-100 text-gray-600' :
                                  'bg-gray-100 text-gray-600'
                                }`}>
                                  {{ owner: 'Propriétaire', admin: 'Admin', mia: 'MIA', secretaire: 'Secrétaire' }[m.role] ?? m.role}
                                </span>
                              </td>
                              <td className="px-3 py-3 font-bold text-[#5E5873]">{m.nb_contacts}</td>
                              <td className="px-3 py-3 font-bold text-[#28C76F]">{m.nb_clients}</td>
                              <td className="px-3 py-3">
                                <span className="flex items-center gap-1 text-[#7367F0] font-bold">
                                  <Mic size={12} />{m.audio_mois}
                                </span>
                              </td>
                              <td className="px-3 py-3">
                                <div className="flex items-center gap-2">
                                  <div className="flex-1 bg-[#EBE9F1] rounded-full h-1.5 min-w-[60px]">
                                    <div className="h-1.5 rounded-full transition-all" style={{
                                      width: `${m.taux_conformite}%`,
                                      background: m.taux_conformite >= 80 ? '#28C76F' : m.taux_conformite >= 40 ? '#FF9F43' : '#EA5455'
                                    }} />
                                  </div>
                                  <span className="text-xs font-bold text-[#5E5873] w-8 text-right">{m.taux_conformite}%</span>
                                </div>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}

              </div>
            );
          })()}

        </div>
      </div>
    </>
  );
};

export default HomePage;
