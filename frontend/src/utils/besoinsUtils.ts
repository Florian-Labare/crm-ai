export const BESOINS_SLUGS = ['prevoyance', 'retraite', 'epargne', 'sante', 'emprunteur'] as const;

export type BesoinSlug = typeof BESOINS_SLUGS[number];

export const BESOINS_LABELS: Record<BesoinSlug, string> = {
  prevoyance: 'Prévoyance',
  retraite: 'Retraite',
  epargne: 'Épargne',
  sante: 'Santé',
  emprunteur: 'Emprunteur',
};

export const BESOINS_COLORS: Record<BesoinSlug, string> = {
  prevoyance: '#7367F0',
  retraite: '#00CFE8',
  epargne: '#28C76F',
  sante: '#FF9F43',
  emprunteur: '#EA5455',
};

/** Retourne le label lisible d'un slug besoin. */
export function besoinLabel(slug: string): string {
  return BESOINS_LABELS[slug as BesoinSlug] ?? slug;
}

/** Retourne la couleur associée à un slug besoin. */
export function besoinColor(slug: string): string {
  return BESOINS_COLORS[slug as BesoinSlug] ?? '#6E6B7B';
}
