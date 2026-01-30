import React, { useState, useMemo } from 'react';
import {
  User, Users, Shield, Clock, Coins, Home,
  CreditCard, Building2, Heart, ChevronDown, ChevronUp,
  Sparkles, Check, AlertTriangle, Wand2, RotateCcw
} from 'lucide-react';
import { SearchableFieldSelect } from './SearchableFieldSelect';

interface FieldOption {
  value: string;
  label: string;
  group: string;
  table: string;
  field: string;
  index?: number | null;
}

interface AISuggestion {
  suggested_field: string;
  confidence: number;
}

interface SmartMappingFormProps {
  columns: string[];
  availableFields: Record<string, string[]>;
  enhancedFields?: FieldOption[];
  aiSuggestions?: Record<string, AISuggestion>;
  columnMappings: Record<string, string>;
  onMappingChange: (column: string, field: string) => void;
  onSubmit: () => void;
}

// Catégories sémantiques pour regrouper les colonnes détectées
interface SemanticCategory {
  id: string;
  label: string;
  icon: React.ReactNode;
  color: string;
  bgColor: string;
  keywords: string[];
}

const semanticCategories: SemanticCategory[] = [
  {
    id: 'identity',
    label: 'Identité',
    icon: <User size={18} />,
    color: 'text-[#7367F0]',
    bgColor: 'bg-[#7367F0]/10',
    keywords: ['nom', 'prenom', 'prénom', 'name', 'civilite', 'civilité', 'titre', 'naissance', 'birth', 'nationalite', 'nationalité', 'jeune_fille', 'maiden']
  },
  {
    id: 'contact',
    label: 'Coordonnées',
    icon: <Home size={18} />,
    color: 'text-[#00CFE8]',
    bgColor: 'bg-[#00CFE8]/10',
    keywords: ['adresse', 'address', 'rue', 'street', 'ville', 'city', 'code_postal', 'cp', 'zip', 'postal', 'telephone', 'tel', 'phone', 'mobile', 'email', 'mail', 'courriel', 'residence', 'résidence']
  },
  {
    id: 'professional',
    label: 'Professionnel',
    icon: <Building2 size={18} />,
    color: 'text-[#FF9F43]',
    bgColor: 'bg-[#FF9F43]/10',
    keywords: ['profession', 'job', 'emploi', 'metier', 'métier', 'travail', 'work', 'entreprise', 'company', 'societe', 'société', 'employeur', 'employer', 'statut', 'status', 'situation', 'independant', 'indépendant', 'mandataire', 'chef']
  },
  {
    id: 'family',
    label: 'Famille',
    icon: <Users size={18} />,
    color: 'text-[#EA5455]',
    bgColor: 'bg-[#EA5455]/10',
    keywords: ['conjoint', 'spouse', 'mari', 'femme', 'epoux', 'époux', 'epouse', 'épouse', 'enfant', 'child', 'kid', 'fils', 'fille', 'matrimonial', 'mariage', 'marriage', 'famille', 'family', 'charge']
  },
  {
    id: 'financial',
    label: 'Finances & Revenus',
    icon: <Coins size={18} />,
    color: 'text-[#28C76F]',
    bgColor: 'bg-[#28C76F]/10',
    keywords: ['revenu', 'revenue', 'income', 'salaire', 'salary', 'montant', 'amount', 'argent', 'money', 'euro', 'actif', 'asset', 'patrimoine', 'wealth', 'epargne', 'épargne', 'savings', 'placement', 'investissement', 'investment', 'capital']
  },
  {
    id: 'property',
    label: 'Immobilier',
    icon: <Home size={18} />,
    color: 'text-[#9055FD]',
    bgColor: 'bg-[#9055FD]/10',
    keywords: ['immobilier', 'immo', 'real_estate', 'property', 'maison', 'house', 'appartement', 'apartment', 'logement', 'housing', 'propriete', 'propriété', 'bien', 'terrain', 'land']
  },
  {
    id: 'insurance',
    label: 'Assurance & Prévoyance',
    icon: <Shield size={18} />,
    color: 'text-[#EA5455]',
    bgColor: 'bg-[#EA5455]/10',
    keywords: ['assurance', 'insurance', 'prevoyance', 'prévoyance', 'mutuelle', 'sante', 'santé', 'health', 'deces', 'décès', 'death', 'invalidite', 'invalidité', 'disability', 'garantie', 'guarantee', 'cotisation', 'contribution']
  },
  {
    id: 'retirement',
    label: 'Retraite',
    icon: <Clock size={18} />,
    color: 'text-[#00CFE8]',
    bgColor: 'bg-[#00CFE8]/10',
    keywords: ['retraite', 'retirement', 'pension', 'age', 'depart', 'départ', 'complementaire', 'complémentaire', 'per', 'perp', 'madelin']
  },
  {
    id: 'credit',
    label: 'Crédits & Emprunts',
    icon: <CreditCard size={18} />,
    color: 'text-[#FF9F43]',
    bgColor: 'bg-[#FF9F43]/10',
    keywords: ['credit', 'crédit', 'loan', 'emprunt', 'pret', 'prêt', 'dette', 'debt', 'remboursement', 'repayment', 'passif', 'liability', 'preteur', 'prêteur', 'lender', 'mensualite', 'mensualité']
  },
  {
    id: 'lifestyle',
    label: 'Mode de vie',
    icon: <Heart size={18} />,
    color: 'text-[#EA5455]',
    bgColor: 'bg-[#EA5455]/10',
    keywords: ['fumeur', 'smoker', 'sport', 'activite', 'activité', 'activity', 'loisir', 'hobby', 'km', 'kilometr', 'kilométr', 'voiture', 'car', 'vehicule', 'véhicule']
  },
];

// Fonction pour catégoriser une colonne
const categorizeColumn = (columnName: string, suggestion?: AISuggestion): string => {
  const normalizedName = columnName.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  // Si on a une suggestion avec haute confiance, utiliser le groupe suggéré
  if (suggestion && suggestion.confidence >= 0.7) {
    const suggestedField = suggestion.suggested_field.toLowerCase();
    for (const category of semanticCategories) {
      if (category.keywords.some(kw => suggestedField.includes(kw))) {
        return category.id;
      }
    }
  }

  // Sinon, analyser le nom de la colonne
  for (const category of semanticCategories) {
    if (category.keywords.some(kw => normalizedName.includes(kw))) {
      return category.id;
    }
  }

  return 'other';
};

export const SmartMappingForm: React.FC<SmartMappingFormProps> = ({
  columns,
  availableFields,
  enhancedFields,
  aiSuggestions = {},
  columnMappings,
  onMappingChange,
  onSubmit,
}) => {
  const [expandedSections, setExpandedSections] = useState<Set<string>>(new Set(['identity', 'contact', 'other']));
  const [showOnlyUnmapped, setShowOnlyUnmapped] = useState(false);

  // Utiliser les champs enrichis du backend si disponibles, sinon parser availableFields
  const flatFieldOptions: FieldOption[] = useMemo(() => {
    // Si on a des champs enrichis du backend, les utiliser directement
    if (enhancedFields && enhancedFields.length > 0) {
      return enhancedFields;
    }

    // Sinon, fallback: convertir availableFields en format plat avec labels
    const tableLabels: Record<string, string> = {
      client: 'Client',
      conjoint: 'Conjoint',
      enfant: 'Enfants',
      sante_souhaits: 'Santé / Mutuelle',
      bae_prevoyance: 'Prévoyance',
      bae_retraite: 'Retraite',
      bae_epargne: 'Épargne',
      client_revenu: 'Revenus',
      client_actif_financier: 'Actifs Financiers',
      client_bien_immobilier: 'Biens Immobiliers',
      client_passif: 'Passifs / Emprunts',
      client_autre_epargne: 'Autres Épargnes',
      entreprise: 'Entreprise',
      questionnaire_risque: 'Questionnaire Risque',
    };

    const fieldLabels: Record<string, string> = {
      civilite: 'Civilité',
      nom: 'Nom',
      nom_jeune_fille: 'Nom de jeune fille',
      prenom: 'Prénom',
      date_naissance: 'Date de naissance',
      lieu_naissance: 'Lieu de naissance',
      nationalite: 'Nationalité',
      situation_matrimoniale: 'Situation matrimoniale',
      adresse: 'Adresse',
      code_postal: 'Code postal',
      ville: 'Ville',
      telephone: 'Téléphone',
      email: 'Email',
      profession: 'Profession',
      statut: 'Statut',
      situation_actuelle: 'Situation actuelle',
      revenus_annuels: 'Revenus annuels',
      fumeur: 'Fumeur',
      activites_sportives: 'Activités sportives',
      chef_entreprise: "Chef d'entreprise",
      travailleur_independant: 'Travailleur indépendant',
      mandataire_social: 'Mandataire social',
      residence_fiscale: 'Résidence fiscale',
      nature: 'Nature',
      periodicite: 'Périodicité',
      montant: 'Montant',
      details: 'Détails',
      etablissement: 'Établissement',
      detenteur: 'Détenteur',
      valeur_actuelle: 'Valeur actuelle',
      designation: 'Désignation',
      forme_propriete: 'Forme de propriété',
      preteur: 'Prêteur',
      capital_restant_du: 'Capital restant dû',
      duree_restante: 'Durée restante',
      fiscalement_a_charge: 'Fiscalement à charge',
      garde_alternee: 'Garde alternée',
      contrat_en_place: 'Contrat en place',
      budget_mensuel_maximum: 'Budget mensuel max',
      niveau_hospitalisation: 'Niveau hospitalisation',
      niveau_dentaire: 'Niveau dentaire',
      niveau_optique: 'Niveau optique',
      age_depart_retraite: 'Âge départ retraite',
      tmi: 'TMI',
      nombre_parts_fiscales: 'Nombre parts fiscales',
    };

    const options: FieldOption[] = [];

    Object.entries(availableFields).forEach(([table, fields]) => {
      if (table.startsWith('_')) return;

      const groupLabel = tableLabels[table] || table;

      (fields as string[]).forEach(fieldValue => {
        // Parser le nom du champ pour extraire l'index si présent
        let fieldName = fieldValue;
        let index: number | null = null;

        // Détecter les patterns comme enfant1_nom, revenu2_montant, etc.
        const indexMatch = fieldValue.match(/^([a-z_]+)(\d+)_(.+)$/);
        if (indexMatch) {
          index = parseInt(indexMatch[2]);
          fieldName = indexMatch[3];
        } else if (fieldValue.includes('_')) {
          // Pour les champs sans index mais avec préfixe (conjoint_nom, etc.)
          const parts = fieldValue.split('_');
          fieldName = parts[parts.length - 1];
        }

        const label = fieldLabels[fieldName] || fieldName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

        options.push({
          value: fieldValue,
          label: index ? `${label} #${index}` : label,
          group: groupLabel,
          table,
          field: fieldName,
          index,
        });
      });
    });

    return options;
  }, [availableFields, enhancedFields]);

  // Grouper les colonnes par catégorie sémantique
  const categorizedColumns = useMemo(() => {
    const categories: Record<string, string[]> = {};
    const safeColumns = columns || [];

    safeColumns.forEach(column => {
      const categoryId = categorizeColumn(column, aiSuggestions[column]);
      if (!categories[categoryId]) {
        categories[categoryId] = [];
      }
      categories[categoryId].push(column);
    });

    return categories;
  }, [columns, aiSuggestions]);

  // Statistiques de mapping (avec protection contre division par zéro)
  const stats = useMemo(() => {
    const safeColumns = columns || [];
    const mapped = Object.values(columnMappings).filter(v => v).length;
    const total = safeColumns.length;
    const withSuggestions = Object.keys(aiSuggestions).filter(k =>
      aiSuggestions[k]?.confidence >= 0.6
    ).length;

    return { mapped, total, withSuggestions };
  }, [columnMappings, columns, aiSuggestions]);

  // Toggle section
  const toggleSection = (sectionId: string) => {
    setExpandedSections(prev => {
      const next = new Set(prev);
      if (next.has(sectionId)) {
        next.delete(sectionId);
      } else {
        next.add(sectionId);
      }
      return next;
    });
  };

  // Appliquer toutes les suggestions
  const applyAllSuggestions = () => {
    Object.entries(aiSuggestions).forEach(([column, suggestion]) => {
      if (suggestion.confidence >= 0.6 && !columnMappings[column]) {
        onMappingChange(column, suggestion.suggested_field);
      }
    });
  };

  // Réinitialiser tous les mappings
  const resetAllMappings = () => {
    (columns || []).forEach(column => {
      onMappingChange(column, '');
    });
  };

  // Obtenir les colonnes filtrées
  const getFilteredColumns = (categoryColumns: string[]) => {
    if (!showOnlyUnmapped) return categoryColumns;
    return categoryColumns.filter(col => !columnMappings[col]);
  };

  return (
    <div className="space-y-6">
      {/* Header avec stats et actions */}
      <div className="flex flex-wrap items-center justify-between gap-4 p-4 bg-gradient-to-r from-[#7367F0]/5 to-[#9055FD]/5 rounded-xl border border-[#7367F0]/10">
        <div className="flex items-center gap-6">
          {/* Progress */}
          <div className="flex items-center gap-3">
            <div className="relative w-14 h-14">
              <svg className="w-14 h-14 transform -rotate-90">
                <circle
                  cx="28"
                  cy="28"
                  r="24"
                  fill="none"
                  stroke="#EBE9F1"
                  strokeWidth="4"
                />
                <circle
                  cx="28"
                  cy="28"
                  r="24"
                  fill="none"
                  stroke="#7367F0"
                  strokeWidth="4"
                  strokeLinecap="round"
                  strokeDasharray={`${stats.total > 0 ? (stats.mapped / stats.total) * 151 : 0} 151`}
                />
              </svg>
              <span className="absolute inset-0 flex items-center justify-center text-sm font-bold text-[#7367F0]">
                {stats.total > 0 ? Math.round((stats.mapped / stats.total) * 100) : 0}%
              </span>
            </div>
            <div>
              <p className="text-sm font-semibold text-[#5E5873]">
                {stats.mapped}/{stats.total} colonnes mappées
              </p>
              {stats.withSuggestions > 0 && (
                <p className="text-xs text-[#28C76F] flex items-center gap-1">
                  <Sparkles size={12} />
                  {stats.withSuggestions} suggestions disponibles
                </p>
              )}
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-2">
          <label className="flex items-center gap-2 text-sm text-[#6E6B7B] cursor-pointer">
            <input
              type="checkbox"
              checked={showOnlyUnmapped}
              onChange={(e) => setShowOnlyUnmapped(e.target.checked)}
              className="w-4 h-4 rounded border-[#D8D6DE] text-[#7367F0] focus:ring-[#7367F0]/20"
            />
            Non mappés uniquement
          </label>

          <button
            onClick={applyAllSuggestions}
            className="flex items-center gap-2 px-3 py-2 bg-[#28C76F]/10 text-[#28C76F] rounded-lg hover:bg-[#28C76F]/20 transition-colors text-sm font-medium"
          >
            <Wand2 size={16} />
            Appliquer suggestions
          </button>

          <button
            onClick={resetAllMappings}
            className="flex items-center gap-2 px-3 py-2 bg-[#F3F2F7] text-[#6E6B7B] rounded-lg hover:bg-[#EBE9F1] transition-colors text-sm font-medium"
          >
            <RotateCcw size={16} />
            Réinitialiser
          </button>
        </div>
      </div>

      {/* Sections de mapping */}
      <div className="space-y-4">
        {/* Catégories définies */}
        {semanticCategories.map(category => {
          const categoryColumns = categorizedColumns[category.id] || [];
          const filteredColumns = getFilteredColumns(categoryColumns);

          if (categoryColumns.length === 0) return null;

          const mappedInCategory = categoryColumns.filter(col => columnMappings[col]).length;
          const isExpanded = expandedSections.has(category.id);

          return (
            <div
              key={category.id}
              className="border border-[#EBE9F1] rounded-xl overflow-hidden bg-white"
            >
              {/* Section Header */}
              <button
                onClick={() => toggleSection(category.id)}
                className={`w-full px-4 py-3 flex items-center justify-between ${category.bgColor} hover:opacity-90 transition-opacity`}
              >
                <div className="flex items-center gap-3">
                  <span className={category.color}>{category.icon}</span>
                  <span className={`font-semibold ${category.color}`}>{category.label}</span>
                  <span className="text-xs text-[#6E6B7B] bg-white/50 px-2 py-0.5 rounded-full">
                    {mappedInCategory}/{categoryColumns.length} mappées
                  </span>
                </div>
                {isExpanded ? (
                  <ChevronUp size={18} className="text-[#6E6B7B]" />
                ) : (
                  <ChevronDown size={18} className="text-[#6E6B7B]" />
                )}
              </button>

              {/* Section Content */}
              {isExpanded && filteredColumns.length > 0 && (
                <div className="p-4 space-y-4">
                  {filteredColumns.map(column => (
                    <div key={column} className="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                      {/* Colonne source */}
                      <div className="flex items-center gap-2">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2">
                            <span className="font-medium text-[#5E5873] truncate">{column}</span>
                            {columnMappings[column] && (
                              <Check size={14} className="text-[#28C76F] flex-shrink-0" />
                            )}
                          </div>
                          {aiSuggestions[column]?.confidence >= 0.6 && !columnMappings[column] && (
                            <p className="text-xs text-[#FF9F43] mt-0.5 flex items-center gap-1">
                              <AlertTriangle size={10} />
                              Suggestion disponible
                            </p>
                          )}
                        </div>
                        <span className="text-[#B9B9C3]">→</span>
                      </div>

                      {/* Select de mapping */}
                      <SearchableFieldSelect
                        value={columnMappings[column] || ''}
                        onChange={(value) => onMappingChange(column, value)}
                        options={flatFieldOptions}
                        suggestion={aiSuggestions[column]}
                        placeholder="Rechercher un champ..."
                      />
                    </div>
                  ))}
                </div>
              )}

              {isExpanded && filteredColumns.length === 0 && (
                <div className="p-4 text-center text-sm text-[#B9B9C3]">
                  {showOnlyUnmapped ? 'Toutes les colonnes sont mappées' : 'Aucune colonne dans cette catégorie'}
                </div>
              )}
            </div>
          );
        })}

        {/* Catégorie "Autres" pour les colonnes non catégorisées */}
        {categorizedColumns.other && categorizedColumns.other.length > 0 && (
          <div className="border border-[#EBE9F1] rounded-xl overflow-hidden bg-white">
            <button
              onClick={() => toggleSection('other')}
              className="w-full px-4 py-3 flex items-center justify-between bg-[#F8F8F8] hover:bg-[#F3F2F7] transition-colors"
            >
              <div className="flex items-center gap-3">
                <span className="text-[#6E6B7B]">
                  <AlertTriangle size={18} />
                </span>
                <span className="font-semibold text-[#6E6B7B]">Autres colonnes</span>
                <span className="text-xs text-[#6E6B7B] bg-white/50 px-2 py-0.5 rounded-full">
                  {categorizedColumns.other.filter(col => columnMappings[col]).length}/{categorizedColumns.other.length} mappées
                </span>
              </div>
              {expandedSections.has('other') ? (
                <ChevronUp size={18} className="text-[#6E6B7B]" />
              ) : (
                <ChevronDown size={18} className="text-[#6E6B7B]" />
              )}
            </button>

            {expandedSections.has('other') && (
              <div className="p-4 space-y-4">
                {getFilteredColumns(categorizedColumns.other).map(column => (
                  <div key={column} className="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                    <div className="flex items-center gap-2">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="font-medium text-[#5E5873] truncate">{column}</span>
                          {columnMappings[column] && (
                            <Check size={14} className="text-[#28C76F] flex-shrink-0" />
                          )}
                        </div>
                      </div>
                      <span className="text-[#B9B9C3]">→</span>
                    </div>

                    <SearchableFieldSelect
                      value={columnMappings[column] || ''}
                      onChange={(value) => onMappingChange(column, value)}
                      options={flatFieldOptions}
                      suggestion={aiSuggestions[column]}
                      placeholder="Rechercher un champ..."
                    />
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Footer avec bouton de validation */}
      <div className="flex justify-end pt-4 border-t border-[#EBE9F1]">
        <button
          onClick={onSubmit}
          disabled={stats.mapped === 0}
          className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-[#7367F0] to-[#9055FD] text-white rounded-xl font-semibold hover:shadow-lg hover:shadow-[#7367F0]/30 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Check size={18} />
          Valider le mapping ({stats.mapped} champ{stats.mapped > 1 ? 's' : ''})
        </button>
      </div>
    </div>
  );
};

export default SmartMappingForm;
