---
name: component
description: Creer un composant React TypeScript avec le design system Vuexy. Utiliser automatiquement quand on demande de creer un composant frontend, une section, un formulaire ou un element d'interface.
argument-hint: "[NomDuComposant]"
---

# Creer un composant React Vuexy

Composant demande : $ARGUMENTS

## Template de base

```tsx
import React, { useState, useEffect } from 'react';
import api from '../api/apiClient';

interface NomComposantProps {
  // Props typees
}

export const NomComposant: React.FC<NomComposantProps> = ({ /* props */ }) => {
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Chargement initial
  }, []);

  if (loading) {
    return (
      <div className="animate-pulse">
        <div className="h-32 bg-gray-200 rounded-xl" />
      </div>
    );
  }

  return (
    <div className="vx-card">
      {/* Contenu */}
    </div>
  );
};

export default NomComposant;
```

## Design system Vuexy - Regles obligatoires

### Palette de couleurs
```
Primary:    #7367F0 (boutons, liens, accents)
Success:    #28C76F (validation, succes)
Warning:    #FF9F43 (alertes, attention)
Danger:     #EA5455 (erreurs, suppressions)
Info:       #00CFE8 (information)

Text titre: #5E5873
Text body:  #6E6B7B
Text muted: #B9B9C3

Border:     #EBE9F1
Background: #F8F8F8
```

### Classes CSS recurrentes

**Card :**
```html
<div className="vx-card"> <!-- bg-white rounded-xl shadow border p-6 -->
```

**Bouton primary :**
```html
<button className="px-4 py-2.5 rounded-lg bg-[#7367F0] text-white font-semibold hover:bg-[#6355E0] transition-all shadow-lg shadow-purple-500/30">
```

**Bouton outline :**
```html
<button className="px-4 py-2.5 rounded-lg border border-[#EBE9F1] bg-white text-[#5E5873] font-semibold hover:bg-[#F3F2F7] hover:border-[#7367F0] hover:text-[#7367F0] transition-all">
```

**Bouton danger :**
```html
<button className="px-4 py-2.5 rounded-lg border border-[#EA5455] text-[#EA5455] hover:bg-[#EA5455]/10 transition-all">
```

**Badge / Tag :**
```html
<span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#7367F0]/10 text-[#7367F0] font-semibold text-xs uppercase tracking-wider">
```

**Input :**
```html
<input className="w-full px-4 py-2.5 rounded-lg border border-[#EBE9F1] focus:border-[#7367F0] focus:ring-2 focus:ring-[#7367F0]/20 transition-all outline-none text-[#5E5873]" />
```

**Section titre :**
```html
<h2 className="text-lg font-semibold text-[#5E5873] mb-4">
```

### Regles

- **Icons** : Toujours `lucide-react` (jamais FontAwesome, heroicons, etc.)
- **API** : Toujours `import api from '../api/apiClient'` (jamais `axios` direct)
- **Fichier** : Creer dans `frontend/src/components/`
- **Nommage** : PascalCase, prefix `Vuexy` pour les composants generiques du design system
- **Export** : Named export + default export
- **Loading state** : Toujours gerer l'etat de chargement avec skeleton/pulse
- **Erreurs** : Toujours gerer les erreurs API avec try/catch et `console.error`
