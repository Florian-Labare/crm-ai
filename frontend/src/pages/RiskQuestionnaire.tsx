import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { RiskProfileCard } from '../components/RiskProfileCard';

interface RadioOptionProps {
  name: string;
  value: string;
  label: string;
  checked: boolean;
  onChange: (value: string) => void;
}

const RadioOption: React.FC<RadioOptionProps> = ({ name, value, label, checked, onChange }) => (
  <label
    className={`flex items-center justify-between w-full border rounded-2xl px-4 py-3 cursor-pointer transition-all ${
      checked ? 'border-[#7367F0] bg-[#7367F0]/10 shadow-sm' : 'border-[#EBE9F1] hover:border-[#7367F0]/30'
    }`}
  >
    <span className="text-[#5E5873]">{label}</span>
    <span className={`flex items-center justify-center h-5 w-5 rounded-full border-2 ${checked ? 'border-[#7367F0]' : 'border-[#D8D6DE]'}`}>
      <span className={`h-3 w-3 rounded-full ${checked ? 'bg-[#7367F0]/100' : 'bg-transparent'}`} />
    </span>
    <input
      type="radio"
      name={name}
      value={value}
      checked={checked}
      onChange={(e) => onChange(e.target.value)}
      className="sr-only"
    />
  </label>
);

interface CheckboxPillProps {
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}

const CheckboxPill: React.FC<CheckboxPillProps> = ({ label, checked, onChange }) => (
  <label
    className={`flex items-center justify-between border rounded-2xl px-4 py-2 cursor-pointer transition ${
      checked ? 'border-[#28C76F] bg-[#28C76F]/10 text-[#28C76F] shadow-sm' : 'border-[#EBE9F1] text-[#6E6B7B] hover:border-[#28C76F]/30'
    }`}
  >
    <span>{label}</span>
    <span
      className={`flex items-center justify-center h-5 w-5 border rounded ${
        checked ? 'border-[#28C76F] bg-[#28C76F]/100 text-white' : 'border-[#D8D6DE] text-transparent'
      }`}
    >
      ✓
    </span>
    <input
      type="checkbox"
      checked={checked}
      onChange={(e) => onChange(e.target.checked)}
      className="sr-only"
    />
  </label>
);

interface RiskQuestionnaireProps {
  clientIdProp?: string;
  embedded?: boolean;
}

export const RiskQuestionnaire: React.FC<RiskQuestionnaireProps> = ({ clientIdProp, embedded = false }) => {
  const params = useParams<{ clientId: string }>();
  const navigate = useNavigate();
  const resolvedClientId = clientIdProp ?? params.clientId;

  useEffect(() => {
    if (!resolvedClientId) {
      console.error('Client ID manquant pour le questionnaire');
    }
  }, [resolvedClientId]);

  const [activeTab, setActiveTab] = useState<'comportement' | 'connaissances' | 'quiz'>('comportement');
  const [score, setScore] = useState(0);
  const [profil, setProfil] = useState('Prudent');
  const [recommandation, setRecommandation] = useState('');
  const [loading, setLoading] = useState(false);

  const [financier, setFinancier] = useState<Record<string, string>>({});
  const [connaissances, setConnaissances] = useState<Record<string, boolean | string>>({});
  const [quiz, setQuiz] = useState<Record<string, string>>({});

  const comportementQuestions = [
    {
      field: 'temps_attente_recuperation_valeur',
      label: 'La valeur de votre investissement baisse, combien de temps êtes-vous disposé à attendre et à rester investi avant qu’il ne revienne à sa valeur initiale ?',
      options: [
        { value: 'moins_1_an', label: 'Moins d’1 an' },
        { value: '1_3_ans', label: 'Entre 1 an et 3 ans' },
        { value: 'plus_3_ans', label: 'Plus de 3 ans' },
      ],
    },
    {
      field: 'niveau_perte_inquietude',
      label: 'À partir de quel niveau de perte êtes-vous vraiment inquiet ?',
      options: [
        { value: 'perte_5', label: '5%' },
        { value: 'perte_20', label: '20%' },
        { value: 'pas_inquietude', label: 'Je sais que cela peut remonter donc pas d’inquiétude' },
      ],
    },
    {
      field: 'reaction_baisse_25',
      label: 'La Bourse dégringole et vos actions perdent 25% de leur valeur : que faites-vous ?',
      options: [
        { value: 'vendre_partie', label: 'J’hésite peut-être à vendre une partie' },
        { value: 'acheter_plus', label: 'J’achète plus de ces actions' },
        { value: 'vendre_tout', label: 'Je vends tout sans attendre' },
      ],
    },
    {
      field: 'attitude_placements',
      label: 'Quelle affirmation vous convient le mieux s’agissant de vos placements ?',
      options: [
        { value: 'eviter_pertes', label: 'Je redoute avant tout les pertes' },
        { value: 'recherche_gains', label: 'Je m’intéresse surtout aux gains' },
        { value: 'equilibre_gains', label: 'Je m’intéresse aux deux' },
      ],
    },
    {
      field: 'allocation_epargne',
      label: 'Quelle allocation de votre épargne vous convient le mieux ?',
      options: [
        { value: 'allocation_70_30', label: '70% en actifs de croissance / 30% en actifs défensifs' },
        { value: 'allocation_30_70', label: '30% en actifs de croissance / 70% en actifs défensifs' },
        { value: 'allocation_50_50', label: '50% en actifs de croissance / 50% en actifs défensifs' },
      ],
    },
    {
      field: 'objectif_placement',
      label: 'Quelle affirmation vous correspond le mieux s’agissant du placement de votre épargne ?',
      options: [
        { value: 'protection_capital', label: 'La protection du capital est ma priorité' },
        { value: 'risque_modere', label: 'Je suis prêt à prendre des risques modérés pour viser de meilleurs rendements' },
        { value: 'risque_important', label: 'Je suis prêt à prendre des risques importants en contrepartie d’une espérance de gain élevé' },
      ],
    },
    {
      field: 'reaction_moins_value',
      label: 'Vous constatez qu’un de vos placements est en moins-value, votre réaction ?',
      options: [
        { value: 'contacter_immediat', label: 'Vous appelez tout de suite votre conseiller' },
        { value: 'voir_plus_tard', label: 'Vous poserez la question à votre conseiller la prochaine fois que vous le verrez' },
      ],
    },
    {
      field: 'impact_baisse_train_vie',
      label: 'Vous constatez une baisse de la valeur de vos placements, financièrement, quelle incidence sur votre train de vie ?',
      options: [
        { value: 'aucun_impact', label: 'Je ne vis pas de mes placements, une baisse n’aura donc aucun effet' },
        { value: 'ajustements', label: 'Je compte un peu sur mes placements, une baisse impliquerait des ajustements' },
        { value: 'fort_impact', label: 'Je vis de mes placements, une baisse nuirait à mon train de vie' },
      ],
    },
    {
      field: 'perte_supportable',
      label: 'À quel niveau de perte êtes-vous prêt à subir et supporter ?',
      options: [
        { value: 'aucune_perte', label: 'Aucune perte' },
        { value: 'perte_10', label: 'Une perte limitée à 10% du capital investi' },
        { value: 'perte_25', label: 'Une perte limitée à 25% du capital investi' },
        { value: 'perte_50', label: 'Une perte limitée à 50% du capital investi' },
        { value: 'perte_capital', label: 'Une perte limitée au capital investi' },
      ],
    },
    {
      field: 'horizon_investissement',
      label: 'Votre horizon d’investissement pour atteindre cet(ces) objectif(s) :',
      options: [
        { value: 'court_terme', label: 'Court terme (moins de 3 ans)' },
        { value: 'moyen_terme', label: 'Moyen terme (3 à 8 ans)' },
        { value: 'long_terme', label: 'Long terme (plus de 8 ans)' },
      ],
    },
    {
      field: 'objectif_global',
      label: 'Votre objectif d’investissement pour atteindre cet(ces) objectif(s) :',
      options: [
        { value: 'securitaire', label: 'Sécuritaire (préservation du capital)' },
        { value: 'revenus', label: 'Revenus (dividendes, etc.)' },
        { value: 'croissance', label: 'Croissance (faire fructifier le capital)' },
      ],
    },
    {
      field: 'tolerance_risque',
      label: 'La tolérance au risque du client est :',
      options: [
        { value: 'faible', label: 'Faible' },
        { value: 'moyen', label: 'Moyen' },
        { value: 'elevee', label: 'Élevée' },
      ],
    },
    {
      field: 'niveau_connaissance_globale',
      label: 'SOCOGEA vous indique que, selon vos réponses au questionnaire, votre connaissance des instruments financiers s’apparente à :',
      options: [
        { value: 'neophyte', label: 'Néophyte' },
        { value: 'moyennement_experimente', label: 'Moyennement Expérimenté' },
        { value: 'experimente', label: 'Expérimenté' },
      ],
    },
  ];

  const yesNoQuestions = [
    {
      field: 'placements_inquietude',
      label: 'Vos placements financiers sont-ils une source d’inquiétude ?',
    },
    {
      field: 'epargne_precaution',
      label: 'Avez-vous besoin de constituer une épargne de précaution concernant des dépenses ou un changement à court terme ?',
    },
  ];

  const renderRadioQuestion = (question: { field: string; label: string; options: { value: string; label: string }[] }) => (
    <div key={question.field} className="space-y-3">
      <p className="font-semibold text-[#5E5873]">{question.label}</p>
      <div className="space-y-2">
        {question.options.map((option) => (
          <RadioOption
            key={option.value}
            name={question.field}
            value={option.value}
            label={option.label}
            checked={financier[question.field] === option.value}
            onChange={(value) => handleFinancierChange(question.field, value)}
          />
        ))}
      </div>
    </div>
  );

  const getQuestion = (field: string) => comportementQuestions.find((question) => question.field === field);

  useEffect(() => {
    if (resolvedClientId) {
      loadQuestionnaire(resolvedClientId);
    }
  }, [resolvedClientId]);

  const loadQuestionnaire = async (id: string) => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`http://localhost:8000/api/questionnaire-risque/client/${id}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      const data = await response.json();
      if (data.questionnaire) {
        setScore(data.score || 0);
        setProfil(data.profil || 'Prudent');
        setRecommandation(data.recommandation || '');
        if (data.questionnaire.financier) {
          setFinancier(data.questionnaire.financier);
        }
        if (data.questionnaire.connaissances) {
          setConnaissances(data.questionnaire.connaissances);
        }
        if (data.questionnaire.quiz) {
          setQuiz(data.questionnaire.quiz);
        }
      }
    } catch (error) {
      console.error('Erreur chargement questionnaire:', error);
    }
  };

  const saveQuestionnaire = async (section: string, data: Record<string, any>) => {
    if (!resolvedClientId) return;
    setLoading(true);
    try {
      const token = localStorage.getItem('token');
      const payload: any = {
        client_id: Number(resolvedClientId),
      };
      payload[section] = data;

      const response = await fetch('http://localhost:8000/api/questionnaire-risque/live', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(payload),
      });

      const result = await response.json();
      setScore(result.score || 0);
      setProfil(result.profil || 'Prudent');
      setRecommandation(result.recommandation || '');
    } catch (error) {
      console.error('Erreur sauvegarde questionnaire:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFinancierChange = (field: string, value: string) => {
    const updated = { ...financier, [field]: value };
    setFinancier(updated);
    saveQuestionnaire('financier', updated);
  };

  const handleConnaissanceChange = (field: string, value: boolean | string) => {
    const updated = { ...connaissances, [field]: value };
    setConnaissances(updated);
    saveQuestionnaire('connaissances', updated);
  };

  const handleQuizChange = (field: string, value: string) => {
    const updated = { ...quiz, [field]: value };
    setQuiz(updated);
    saveQuestionnaire('quiz', updated);
  };

  return (
    <div className={embedded ? '' : 'min-h-screen bg-[#F8F8F8] p-6'}>
      <div className={embedded ? '' : 'max-w-6xl mx-auto'}>
        {!embedded && (
          <div className="mb-6">
            <button
              onClick={() => navigate(`/clients/${resolvedClientId}`)}
              className="text-[#7367F0] hover:text-[#5E50EE] flex items-center gap-2 mb-4"
            >
              ← Retour à la fiche client
            </button>
            <h1 className="text-3xl font-bold text-[#5E5873]">Questionnaire de Risque</h1>
            <p className="text-[#6E6B7B] mt-2">Évaluation du profil investisseur</p>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          <div className="lg:col-span-2">
            <div className="bg-white rounded-lg shadow">
              <div className="border-b border-[#EBE9F1]">
                <nav className="flex">
                  <button
                    onClick={() => setActiveTab('comportement')}
                    className={`px-6 py-3 font-medium ${
                      activeTab === 'comportement'
                        ? 'border-b-2 border-[#7367F0] text-[#7367F0]'
                        : 'text-[#B9B9C3] hover:text-[#6E6B7B]'
                    }`}
                  >
                    Comportement
                  </button>
                  <button
                    onClick={() => setActiveTab('connaissances')}
                    className={`px-6 py-3 font-medium ${
                      activeTab === 'connaissances'
                        ? 'border-b-2 border-[#7367F0] text-[#7367F0]'
                        : 'text-[#B9B9C3] hover:text-[#6E6B7B]'
                    }`}
                  >
                    Connaissances
                  </button>
                  <button
                    onClick={() => setActiveTab('quiz')}
                    className={`px-6 py-3 font-medium ${
                      activeTab === 'quiz'
                        ? 'border-b-2 border-[#7367F0] text-[#7367F0]'
                        : 'text-[#B9B9C3] hover:text-[#6E6B7B]'
                    }`}
                  >
                    Quiz (32 questions)
                  </button>
                </nav>
              </div>

              <div className="p-6">
                {activeTab === 'comportement' && (
                  <div className="space-y-8">
                    {[
                      'temps_attente_recuperation_valeur',
                      'niveau_perte_inquietude',
                      'reaction_baisse_25',
                      'attitude_placements',
                      'allocation_epargne',
                      'objectif_placement',
                    ].map((field) => {
                      const question = getQuestion(field);
                      return question ? renderRadioQuestion(question) : null;
                    })}

                    {yesNoQuestions.map((question) => (
                      <div key={question.field} className="space-y-3">
                        <p className="font-semibold text-[#5E5873]">{question.label}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                          {[
                            { value: 'true', label: 'OUI' },
                            { value: 'false', label: 'NON' },
                          ].map((option) => (
                            <RadioOption
                              key={option.value}
                              name={question.field}
                              value={option.value}
                              label={option.label}
                              checked={financier[question.field] === option.value}
                              onChange={(value) => handleFinancierChange(question.field, value)}
                            />
                          ))}
                        </div>
                      </div>
                    ))}

                    {['reaction_moins_value', 'impact_baisse_train_vie', 'perte_supportable'].map((field) => {
                      const question = getQuestion(field);
                      return question ? renderRadioQuestion(question) : null;
                    })}

                    <div className="space-y-3">
                      <p className="font-semibold text-[#5E5873]">Le présent rapport répond à (aux) objectif(s) suivant(s) :</p>
                      <textarea
                        value={financier.objectifs_rapport || ''}
                        onChange={(e) => handleFinancierChange('objectifs_rapport', e.target.value)}
                        className="w-full border border-[#D8D6DE] rounded-md px-3 py-2"
                        rows={3}
                        placeholder="Décrivez ici les objectifs énoncés pendant l’entretien"
                      />
                    </div>

                    {['horizon_investissement', 'objectif_global'].map((field) => {
                      const question = getQuestion(field);
                      return question ? renderRadioQuestion(question) : null;
                    })}

                    <div className="space-y-3">
                      <p className="font-semibold text-[#5E5873]">Le profil de risque du client est :</p>
                      <div className="flex flex-wrap gap-4">
                        {['Prudent', 'Modéré', 'Dynamique'].map((option) => (
                          <label key={option} className="flex items-center space-x-2 text-[#6E6B7B]">
                            <input
                              type="radio"
                              name="profil_calcule_display"
                              checked={profil === option}
                              readOnly
                              className="h-4 w-4 text-[#7367F0] border-[#D8D6DE]"
                            />
                            <span>{option}</span>
                          </label>
                        ))}
                      </div>
                    </div>

                    {['tolerance_risque', 'niveau_connaissance_globale'].map((field) => {
                      const question = getQuestion(field);
                      return question ? renderRadioQuestion(question) : null;
                    })}

                    <div className="space-y-3">
                      <p className="font-semibold text-[#5E5873]">Pourcentage maximum de pertes :</p>
                      <div className="flex items-center space-x-3">
                        <input
                          type="number"
                          min="0"
                          max="100"
                          step="1"
                          value={financier.pourcentage_perte_max || ''}
                          onChange={(e) => handleFinancierChange('pourcentage_perte_max', e.target.value)}
                          className="w-full px-3 py-2 border border-[#D8D6DE] rounded-md"
                          placeholder="Ex : 25"
                        />
                        <span className="text-[#6E6B7B] font-semibold">%</span>
                      </div>
                    </div>
                  </div>
                )}

                {activeTab === 'connaissances' && (
                  <div className="space-y-4">
                    <p className="text-sm text-[#6E6B7B] mb-4">
                      Cochez les produits financiers que vous connaissez:
                    </p>

                    {[
                      { key: 'connaissance_obligations', label: 'Obligations' },
                      { key: 'connaissance_actions', label: 'Actions' },
                      { key: 'connaissance_fip_fcpi', label: 'FIP / FCPI' },
                      { key: 'connaissance_opci_scpi', label: 'OPCI / SCPI' },
                      { key: 'connaissance_produits_structures', label: 'Produits structurés' },
                      { key: 'connaissance_monetaires', label: 'Fonds monétaires' },
                      { key: 'connaissance_parts_sociales', label: 'Parts sociales' },
                      { key: 'connaissance_titres_participatifs', label: 'Titres participatifs' },
                      { key: 'connaissance_fps_slp', label: 'FPS / SLP' },
                      { key: 'connaissance_girardin', label: 'Girardin' },
                    ].map(({ key, label }) => (
                      <CheckboxPill
                        key={key}
                        label={label}
                        checked={!!connaissances[key]}
                        onChange={(checked) => handleConnaissanceChange(key, checked)}
                      />
                    ))}
                  </div>
                )}

                {activeTab === 'quiz' && (
                  <div className="space-y-6">
                    <p className="text-sm text-[#6E6B7B] mb-4">
                      Répondez aux questions suivantes (Vrai / Faux / Aucune idée) :
                    </p>

                    {[
                      { key: 'volatilite_risque_gain', label: 'La volatilité mesure le niveau de risque et de gain potentiel d\'un placement' },
                      { key: 'instruments_tous_cotes', label: 'Tous les instruments financiers sont cotés en bourse' },
                      { key: 'risque_liquidite_signification', label: 'Le risque de liquidité signifie qu\'on pourrait ne pas pouvoir revendre un placement rapidement' },
                      { key: 'livret_a_rendement_negatif', label: 'Le livret A peut avoir un rendement réel négatif (après inflation)' },
                      { key: 'assurance_vie_valeur_rachats_uc', label: 'En assurance vie, la valeur de rachat des UC est toujours garantie' },
                      { key: 'assurance_vie_fiscalite_deces', label: 'L\'assurance vie bénéficie d\'une fiscalité avantageuse en cas de décès' },
                      { key: 'per_non_rachatable', label: 'Le PER est en principe non rachatable avant la retraite (sauf exceptions)' },
                      { key: 'per_objectif_revenus_retraite', label: 'Le PER a pour objectif de générer des revenus complémentaires à la retraite' },
                      { key: 'compte_titres_ordres_directs', label: 'Un compte-titres permet de passer des ordres en direct sur les marchés' },
                      { key: 'pea_actions_europeennes', label: 'Le PEA permet d\'investir uniquement en actions européennes' },
                      { key: 'opc_pas_de_risque', label: 'Les OPC (organismes de placement collectif) ne présentent aucun risque' },
                      { key: 'opc_definition_fonds_investissement', label: 'Un OPC est un fonds d\'investissement qui mutualise l\'épargne de plusieurs investisseurs' },
                      { key: 'opcvm_actions_plus_risquees', label: 'Les OPCVM investis en actions sont plus risqués que ceux investis en obligations' },
                      { key: 'scpi_revenus_garantis', label: 'Les SCPI garantissent des revenus locatifs constants' },
                      { key: 'opci_scpi_capital_non_garanti', label: 'En OPCI ou SCPI, le capital investi n\'est pas garanti' },
                      { key: 'scpi_liquides', label: 'Les SCPI sont des placements très liquides' },
                      { key: 'obligations_risque_emetteur', label: 'Les obligations comportent un risque lié à la solvabilité de l\'émetteur' },
                      { key: 'obligations_cotees_liquidite', label: 'Les obligations cotées présentent une liquidité variable selon les titres' },
                      { key: 'obligation_risque_defaut', label: 'Une obligation peut faire défaut si l\'émetteur ne rembourse pas' },
                      { key: 'parts_sociales_cotees', label: 'Les parts sociales sont cotées en bourse' },
                      { key: 'parts_sociales_dividendes_voix', label: 'Les parts sociales donnent droit à des dividendes et un droit de vote' },
                      { key: 'fonds_capital_investissement_non_cotes', label: 'Les fonds de capital-investissement investissent dans des entreprises non cotées' },
                      { key: 'fcp_rachetable_apres_dissolution', label: 'Un FCP n\'est rachetable qu\'après dissolution' },
                      { key: 'fip_fcpi_reduction_impot', label: 'Les FIP et FCPI donnent droit à une réduction d\'impôt' },
                      { key: 'actions_non_cotees_risque_perte', label: 'Les actions non cotées comportent un risque de perte en capital' },
                      { key: 'actions_cotees_rendement_duree', label: 'Les actions cotées sont plus performantes sur longue durée que sur courte durée' },
                      { key: 'produits_structures_complexes', label: 'Les produits structurés sont des instruments financiers complexes' },
                      { key: 'produits_structures_risque_defaut_banque', label: 'Les produits structurés comportent un risque de défaut de la banque émettrice' },
                      { key: 'etf_fonds_indiciels', label: 'Les ETF sont des fonds indiciels cotés' },
                      { key: 'etf_cotes_en_continu', label: 'Les ETF sont cotés en continu pendant les heures de bourse' },
                      { key: 'girardin_fonds_perdus', label: 'Le Girardin industriel est un investissement à fonds perdus' },
                      { key: 'girardin_non_residents', label: 'Le Girardin n\'est accessible qu\'aux non-résidents fiscaux français' },
                    ].map(({ key, label }) => (
                      <div key={key} className="border-b border-[#EBE9F1] pb-4">
                        <p className="text-sm font-medium text-[#6E6B7B] mb-2">{label}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                          {['vrai', 'faux', 'aucune_idee'].map((choice) => (
                            <RadioOption
                              key={choice}
                              name={key}
                              value={choice}
                              label={choice === 'vrai' ? 'Vrai' : choice === 'faux' ? 'Faux' : 'Aucune idée'}
                              checked={quiz[key] === choice}
                              onChange={(value) => handleQuizChange(key, value)}
                            />
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {loading && (
                  <div className="mt-4 text-center text-sm text-[#B9B9C3]">
                    Sauvegarde en cours...
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="lg:col-span-1">
            <RiskProfileCard score={score} profil={profil} recommandation={recommandation} />
          </div>
        </div>
      </div>
    </div>
  );
};
