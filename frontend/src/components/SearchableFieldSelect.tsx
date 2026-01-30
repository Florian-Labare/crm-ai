import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Search, X, ChevronDown, Check, Sparkles, User, Users, Baby, Heart, Building2, Coins, Stethoscope, Shield, Clock, Home, CreditCard, PiggyBank } from 'lucide-react';

interface FieldOption {
  value: string;
  label: string;
  group: string;
  table: string;
  field: string;
  index?: number | null;
}

interface GroupedFields {
  [group: string]: FieldOption[];
}

interface SearchableFieldSelectProps {
  value: string;
  onChange: (value: string) => void;
  options: FieldOption[];
  placeholder?: string;
  suggestion?: {
    suggested_field: string;
    confidence: number;
  } | null;
}

// Icônes par catégorie
const categoryIcons: Record<string, React.ReactNode> = {
  'Client': <User size={16} />,
  'Conjoint': <Users size={16} />,
  'Enfants': <Baby size={16} />,
  'Santé / Mutuelle': <Stethoscope size={16} />,
  'Prévoyance': <Shield size={16} />,
  'Retraite': <Clock size={16} />,
  'Épargne': <PiggyBank size={16} />,
  'Revenus': <Coins size={16} />,
  'Actifs Financiers': <Coins size={16} />,
  'Biens Immobiliers': <Home size={16} />,
  'Passifs / Emprunts': <CreditCard size={16} />,
  'Autres Épargnes': <PiggyBank size={16} />,
  'Entreprise': <Building2 size={16} />,
  'Questionnaire Risque': <Heart size={16} />,
};

// Couleurs par catégorie
const categoryColors: Record<string, string> = {
  'Client': 'bg-[#7367F0]/10 text-[#7367F0] border-[#7367F0]/20',
  'Conjoint': 'bg-[#00CFE8]/10 text-[#00CFE8] border-[#00CFE8]/20',
  'Enfants': 'bg-[#FF9F43]/10 text-[#FF9F43] border-[#FF9F43]/20',
  'Santé / Mutuelle': 'bg-[#28C76F]/10 text-[#28C76F] border-[#28C76F]/20',
  'Prévoyance': 'bg-[#EA5455]/10 text-[#EA5455] border-[#EA5455]/20',
  'Retraite': 'bg-[#9055FD]/10 text-[#9055FD] border-[#9055FD]/20',
  'Épargne': 'bg-[#00CFE8]/10 text-[#00CFE8] border-[#00CFE8]/20',
  'Revenus': 'bg-[#28C76F]/10 text-[#28C76F] border-[#28C76F]/20',
  'Actifs Financiers': 'bg-[#7367F0]/10 text-[#7367F0] border-[#7367F0]/20',
  'Biens Immobiliers': 'bg-[#FF9F43]/10 text-[#FF9F43] border-[#FF9F43]/20',
  'Passifs / Emprunts': 'bg-[#EA5455]/10 text-[#EA5455] border-[#EA5455]/20',
  'Autres Épargnes': 'bg-[#00CFE8]/10 text-[#00CFE8] border-[#00CFE8]/20',
  'Entreprise': 'bg-[#5E5873]/10 text-[#5E5873] border-[#5E5873]/20',
  'Questionnaire Risque': 'bg-[#9055FD]/10 text-[#9055FD] border-[#9055FD]/20',
};

export const SearchableFieldSelect: React.FC<SearchableFieldSelectProps> = ({
  value,
  onChange,
  options,
  placeholder = 'Rechercher un champ...',
  suggestion,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);

  // Trouver l'option sélectionnée
  const selectedOption = (options || []).find(opt => opt.value === value);

  // Grouper et filtrer les options
  const groupedOptions = useMemo(() => {
    const safeOptions = options || [];
    const filtered = safeOptions.filter(opt => {
      if (!searchTerm) return true;
      const search = searchTerm.toLowerCase();
      return (
        opt.label?.toLowerCase().includes(search) ||
        opt.value?.toLowerCase().includes(search) ||
        opt.group?.toLowerCase().includes(search) ||
        opt.field?.toLowerCase().includes(search)
      );
    });

    // Grouper par catégorie
    const groups: GroupedFields = {};
    filtered.forEach(opt => {
      if (!groups[opt.group]) {
        groups[opt.group] = [];
      }
      groups[opt.group].push(opt);
    });

    return groups;
  }, [options, searchTerm]);

  // Liste plate pour la navigation clavier
  const flatFilteredOptions = useMemo(() => {
    const result: FieldOption[] = [];
    Object.values(groupedOptions).forEach(group => {
      result.push(...group);
    });
    return result;
  }, [groupedOptions]);

  // Fermer le dropdown si on clique à l'extérieur
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        setSearchTerm('');
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Scroll vers l'élément surligné
  useEffect(() => {
    if (listRef.current && highlightedIndex >= 0) {
      const items = listRef.current.querySelectorAll('[data-option-index]');
      const item = items[highlightedIndex];
      if (item) {
        item.scrollIntoView({ block: 'nearest' });
      }
    }
  }, [highlightedIndex]);

  // Gestion du clavier
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen) {
      if (e.key === 'Enter' || e.key === 'ArrowDown') {
        setIsOpen(true);
        e.preventDefault();
      }
      return;
    }

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex(prev =>
          prev < flatFilteredOptions.length - 1 ? prev + 1 : prev
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex(prev => prev > 0 ? prev - 1 : 0);
        break;
      case 'Enter':
        e.preventDefault();
        if (flatFilteredOptions[highlightedIndex]) {
          onChange(flatFilteredOptions[highlightedIndex].value);
          setIsOpen(false);
          setSearchTerm('');
        }
        break;
      case 'Escape':
        setIsOpen(false);
        setSearchTerm('');
        break;
    }
  };

  const handleSelect = (optValue: string) => {
    onChange(optValue);
    setIsOpen(false);
    setSearchTerm('');
  };

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation();
    onChange('');
    setSearchTerm('');
  };

  const handleUseSuggestion = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (suggestion?.suggested_field) {
      onChange(suggestion.suggested_field);
    }
  };

  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 0.8) return 'text-[#28C76F] bg-[#28C76F]/10';
    if (confidence >= 0.6) return 'text-[#FF9F43] bg-[#FF9F43]/10';
    return 'text-[#6E6B7B] bg-[#F3F2F7]';
  };

  return (
    <div ref={containerRef} className="relative">
      {/* Trigger / Selected Value */}
      <div
        onClick={() => {
          setIsOpen(true);
          setTimeout(() => inputRef.current?.focus(), 0);
        }}
        className={`
          w-full px-3 py-2.5 border rounded-lg cursor-pointer
          flex items-center justify-between gap-2
          transition-all duration-200
          ${isOpen
            ? 'border-[#7367F0] ring-2 ring-[#7367F0]/20'
            : 'border-[#D8D6DE] hover:border-[#7367F0]/50'
          }
          ${value ? 'bg-white' : 'bg-[#FAFAFC]'}
        `}
      >
        <div className="flex-1 min-w-0">
          {selectedOption ? (
            <div className="flex items-center gap-2">
              <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border ${categoryColors[selectedOption.group] || 'bg-gray-100 text-gray-600'}`}>
                {categoryIcons[selectedOption.group]}
                {selectedOption.group}
              </span>
              <span className="text-[#5E5873] font-medium truncate">
                {selectedOption.label}
              </span>
            </div>
          ) : (
            <span className="text-[#B9B9C3]">-- Ignorer cette colonne --</span>
          )}
        </div>

        <div className="flex items-center gap-1">
          {value && (
            <button
              onClick={handleClear}
              className="p-1 hover:bg-[#F3F2F7] rounded transition-colors"
            >
              <X size={14} className="text-[#6E6B7B]" />
            </button>
          )}
          <ChevronDown
            size={18}
            className={`text-[#6E6B7B] transition-transform ${isOpen ? 'rotate-180' : ''}`}
          />
        </div>
      </div>

      {/* Suggestion Badge */}
      {suggestion && suggestion.confidence >= 0.5 && !value && (
        <button
          onClick={handleUseSuggestion}
          className={`
            mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium
            transition-all hover:scale-105 cursor-pointer
            ${getConfidenceColor(suggestion.confidence)}
          `}
        >
          <Sparkles size={12} />
          <span>Suggestion: {(options || []).find(o => o.value === suggestion.suggested_field)?.label || suggestion.suggested_field}</span>
          <span className="opacity-70">({Math.round(suggestion.confidence * 100)}%)</span>
        </button>
      )}

      {/* Dropdown */}
      {isOpen && (
        <div className="absolute z-50 w-full mt-1 bg-white border border-[#EBE9F1] rounded-xl shadow-xl overflow-hidden animate-modalSlideIn">
          {/* Search Input */}
          <div className="p-3 border-b border-[#EBE9F1] bg-[#FAFAFC]">
            <div className="relative">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#B9B9C3]" />
              <input
                ref={inputRef}
                type="text"
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value);
                  setHighlightedIndex(0);
                }}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                className="w-full pl-9 pr-3 py-2 bg-white border border-[#EBE9F1] rounded-lg text-sm focus:outline-none focus:border-[#7367F0] focus:ring-1 focus:ring-[#7367F0]/20"
              />
            </div>
          </div>

          {/* Options List */}
          <div ref={listRef} className="max-h-72 overflow-y-auto vx-scrollbar">
            {/* Option "Ignorer" */}
            <div
              onClick={() => handleSelect('')}
              className={`
                px-4 py-2.5 cursor-pointer flex items-center gap-2
                ${!value ? 'bg-[#7367F0]/5' : 'hover:bg-[#F8F8F8]'}
              `}
            >
              <X size={16} className="text-[#B9B9C3]" />
              <span className="text-[#6E6B7B]">-- Ignorer cette colonne --</span>
              {!value && <Check size={16} className="ml-auto text-[#7367F0]" />}
            </div>

            {/* Grouped Options */}
            {Object.entries(groupedOptions).length === 0 ? (
              <div className="px-4 py-8 text-center text-[#B9B9C3]">
                Aucun champ trouvé pour "{searchTerm}"
              </div>
            ) : (
              Object.entries(groupedOptions).map(([group, groupOptions]) => (
                <div key={group}>
                  {/* Group Header */}
                  <div className={`px-4 py-2 sticky top-0 flex items-center gap-2 ${categoryColors[group]?.split(' ')[0] || 'bg-[#F8F8F8]'} border-y border-[#EBE9F1]/50`}>
                    <span className={`${categoryColors[group]?.split(' ')[1] || 'text-[#5E5873]'}`}>
                      {categoryIcons[group]}
                    </span>
                    <span className={`text-xs font-semibold uppercase tracking-wide ${categoryColors[group]?.split(' ')[1] || 'text-[#5E5873]'}`}>
                      {group}
                    </span>
                    <span className="ml-auto text-xs text-[#B9B9C3]">
                      {groupOptions.length} champ{groupOptions.length > 1 ? 's' : ''}
                    </span>
                  </div>

                  {/* Group Options */}
                  {groupOptions.map((option) => {
                    const globalIndex = flatFilteredOptions.findIndex(o => o.value === option.value);
                    const isHighlighted = globalIndex === highlightedIndex;
                    const isSelected = option.value === value;
                    const isSuggested = suggestion?.suggested_field === option.value;

                    return (
                      <div
                        key={option.value}
                        data-option-index={globalIndex}
                        onClick={() => handleSelect(option.value)}
                        className={`
                          px-4 py-2 cursor-pointer flex items-center gap-2
                          transition-colors
                          ${isHighlighted ? 'bg-[#7367F0]/10' : ''}
                          ${isSelected ? 'bg-[#7367F0]/5' : 'hover:bg-[#F8F8F8]'}
                        `}
                      >
                        <span className="text-sm text-[#5E5873]">{option.label}</span>

                        {isSuggested && (
                          <span className={`ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs ${getConfidenceColor(suggestion!.confidence)}`}>
                            <Sparkles size={10} />
                            {Math.round(suggestion!.confidence * 100)}%
                          </span>
                        )}

                        {option.index && (
                          <span className="text-xs text-[#B9B9C3]">#{option.index}</span>
                        )}

                        {isSelected && (
                          <Check size={16} className="ml-auto text-[#7367F0]" />
                        )}
                      </div>
                    );
                  })}
                </div>
              ))
            )}
          </div>

          {/* Footer with stats */}
          <div className="px-4 py-2 bg-[#FAFAFC] border-t border-[#EBE9F1] text-xs text-[#B9B9C3]">
            {flatFilteredOptions.length} champ{flatFilteredOptions.length > 1 ? 's' : ''} disponible{flatFilteredOptions.length > 1 ? 's' : ''}
            {searchTerm && ` pour "${searchTerm}"`}
          </div>
        </div>
      )}
    </div>
  );
};

export default SearchableFieldSelect;
