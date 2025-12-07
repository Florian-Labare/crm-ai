# Guide Laravel Boost - IA Accelerated Development

## 📖 Qu'est-ce que Laravel Boost ?

**Laravel Boost** v1.8.2 est un serveur MCP (Model Context Protocol) qui accélère le développement assisté par IA en fournissant le contexte et la structure essentiels pour générer du code Laravel de haute qualité.

### Avantages
- 🤖 **15+ outils MCP** spécialisés pour Laravel
- 📚 **Documentation API** avec +17,000 éléments Laravel
- 🎯 **Guidelines IA** spécifiques à l'écosystème Laravel
- 🔍 **Recherche sémantique** avec embeddings pour des résultats précis
- 🚀 **Support multi-éditeurs** : Claude Code, Cursor, VS Code, PhpStorm, etc.

## 🔧 Installation

Laravel Boost a été installé dans ce projet :

```bash
# Package installé
composer require laravel/boost --dev

# Vérifier l'installation
php artisan list | grep boost
```

## ⚙️ Configuration

### Configuration MCP

Un fichier `.mcp.json` a été créé dans le dossier `backend/` :

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "php",
      "args": ["artisan", "boost:mcp"]
    }
  }
}
```

### Mise à jour des Guidelines

```bash
# Mettre à jour les guidelines Boost
php artisan boost:update

# Via Docker
docker-compose exec backend php artisan boost:update
```

## 🎨 Configuration par Éditeur

### Claude Code (Recommandé pour ce projet)

1. **Automatique** : Claude Code détecte automatiquement le fichier `.mcp.json`

2. **Manuel** (si nécessaire) :
```bash
cd backend
claude mcp add -s local -t stdio laravel-boost php artisan boost:mcp
```

3. **Vérifier** que Boost est actif dans Claude Code

### Cursor

1. Ouvrir la palette de commandes (`Cmd+Shift+P` ou `Ctrl+Shift+P`)
2. Sélectionner "/open MCP Settings"
3. Activer le toggle pour `laravel-boost`

### VS Code

1. Ouvrir la palette de commandes (`Cmd+Shift+P` ou `Ctrl+Shift+P`)
2. Sélectionner "MCP: List Servers"
3. Sélectionner `laravel-boost` et choisir "Start server"

### PhpStorm

1. Appuyer sur `shift` deux fois pour ouvrir la palette
2. Rechercher "MCP Settings" et appuyer sur `enter`
3. Cocher la case `laravel-boost`
4. Cliquer "Apply"

## 🛠️ Outils MCP Disponibles

Laravel Boost fournit 15+ outils spécialisés :

| Outil | Description |
|-------|-------------|
| **Application Info** | Versions PHP/Laravel, moteur DB, packages, modèles Eloquent |
| **Browser Logs** | Logs et erreurs du navigateur |
| **Database Connections** | Inspecter les connexions DB disponibles |
| **Database Query** | Exécuter des requêtes SQL |
| **Database Schema** | Lire le schéma de la base de données |
| **Get Absolute URL** | Convertir les URIs relatives en absolues |
| **Get Config** | Récupérer des valeurs de configuration (notation "dot") |
| **Last Error** | Lire la dernière erreur des logs |
| **List Artisan Commands** | Inspecter les commandes Artisan disponibles |
| **List Available Config Keys** | Inspecter les clés de configuration |
| **List Available Env Vars** | Inspecter les variables d'environnement |
| **List Routes** | Inspecter les routes de l'application |
| **Read Log Entries** | Lire les N dernières entrées de logs |
| **Report Feedback** | Partager des retours sur Boost & Laravel AI |
| **Search Docs** | Rechercher dans la documentation Laravel |

## 💡 Utilisation avec Claude Code

### Exemples de Prompts Optimisés

Avec Laravel Boost actif, vous pouvez utiliser des prompts naturels :

```
"Montre-moi le schéma de la base de données"
→ Boost utilisera l'outil "Database Schema"

"Quelles sont les routes disponibles ?"
→ Boost utilisera l'outil "List Routes"

"Exécute une requête pour compter les clients"
→ Boost utilisera l'outil "Database Query"

"Quelles sont les dernières erreurs ?"
→ Boost utilisera l'outil "Read Log Entries"

"Comment créer un job dans Laravel 11 ?"
→ Boost utilisera l'outil "Search Docs"
```

### Accès au Contexte Laravel

Boost donne automatiquement accès à :
- Structure de votre application
- Modèles Eloquent
- Routes définies
- Configuration
- Schéma de base de données
- Documentation Laravel à jour

## 🚀 Workflow Recommandé

### 1. Développement de Features

```
Prompt: "Je veux créer un nouveau module de facturation.
Utilise le schéma actuel de la base de données et suis les
conventions Laravel 11. Crée les migrations, modèles,
contrôleurs et routes nécessaires."
```

Boost va :
- ✅ Inspecter le schéma existant
- ✅ Générer du code cohérent avec votre structure
- ✅ Suivre les meilleures pratiques Laravel 11
- ✅ Suggérer les tests appropriés

### 2. Debugging

```
Prompt: "J'ai une erreur 500 sur la route /api/clients.
Montre-moi les derniers logs et analyse le problème."
```

Boost va :
- ✅ Lire les logs récents
- ✅ Identifier l'erreur
- ✅ Suggérer une solution

### 3. Refactoring

```
Prompt: "Refactorise le ClientController pour utiliser
des Form Requests et suivre les patterns SOLID."
```

Boost va :
- ✅ Analyser le code existant
- ✅ Appliquer les patterns Laravel recommandés
- ✅ Générer du code testé et maintenable

## 📊 Commandes Utiles

```bash
# Lister les outils Boost
php artisan boost

# Démarrer le serveur MCP (utilisé automatiquement par l'éditeur)
php artisan boost:mcp

# Mettre à jour les guidelines
php artisan boost:update

# Via Docker
docker-compose exec backend php artisan boost:mcp
docker-compose exec backend php artisan boost:update
```

## ⚠️ Notes Importantes

### Fichiers Générés

Boost peut générer ces fichiers (ajoutés au `.gitignore`) :
- `.mcp.json` - Configuration MCP
- `CLAUDE.md` - Guidelines pour Claude
- `AGENTS.md` - Guidelines pour autres agents
- `junie/` - Dossier de guidelines avancées
- `boost.json` - Configuration Boost

### Performance

- Boost fonctionne en **temps réel** pendant le développement
- Aucun impact sur les performances de l'application en production
- Les outils MCP sont disponibles **uniquement en développement**

### Compatibilité

- ✅ Laravel 11+ (ce projet)
- ✅ PHP 8.2+
- ✅ Fonctionne avec Octane
- ✅ Compatible Docker

## 🔍 Exemples d'Utilisation

### Exemple 1 : Créer un Nouveau Modèle

**Prompt :**
```
"Crée un modèle Invoice avec migration, factory et policy.
Utilise les colonnes : id, client_id, amount, status, due_date.
Ajoute une relation avec le modèle Client existant."
```

**Boost va :**
1. Vérifier que le modèle Client existe
2. Générer la migration avec les bonnes colonnes
3. Créer le modèle avec la relation
4. Créer la factory avec des données réalistes
5. Créer la policy suivant les conventions

### Exemple 2 : Optimiser une Requête

**Prompt :**
```
"Analyse la requête dans ClientController@index et
optimise-la pour éviter le problème N+1."
```

**Boost va :**
1. Lire le code du contrôleur
2. Identifier les relations chargées
3. Suggérer l'utilisation de `with()`
4. Optimiser la requête

### Exemple 3 : Générer une API RESTful

**Prompt :**
```
"Crée une API RESTful complète pour les documents.
Inclus : routes, contrôleur API, resource, validation,
et documentation OpenAPI."
```

**Boost va :**
1. Vérifier le schéma `documents`
2. Générer les routes API
3. Créer le contrôleur avec toutes les méthodes CRUD
4. Créer les Form Requests pour validation
5. Générer les API Resources
6. Ajouter la documentation OpenAPI

## 🎯 Bonnes Pratiques

### 1. Soyez Spécifique

✅ **Bon :** "Crée un job pour envoyer des emails de rappel aux clients avec des factures impayées de plus de 30 jours"

❌ **Mauvais :** "Crée un job d'email"

### 2. Mentionnez le Contexte

✅ **Bon :** "En utilisant le modèle Client existant et la table clients, ajoute un champ last_login_at"

❌ **Mauvais :** "Ajoute un champ de connexion"

### 3. Demandez des Tests

✅ **Bon :** "Crée un service UserService avec tests unitaires pour gérer l'authentification"

❌ **Mauvais :** "Crée un service d'authentification"

## 📚 Ressources

- [Documentation Laravel Boost](https://github.com/laravel/boost)
- [Model Context Protocol](https://modelcontextprotocol.io/)
- [Documentation Laravel 11](https://laravel.com/docs/11.x)

## 🐛 Dépannage

### Boost ne démarre pas

```bash
# Vérifier que Boost est installé
composer show laravel/boost

# Vérifier les permissions
chmod +x artisan

# Tester manuellement
php artisan boost:mcp
```

### Outils MCP non disponibles

1. Vérifier que `.mcp.json` existe
2. Redémarrer l'éditeur
3. Vérifier les logs de l'éditeur

### Erreurs de connexion Redis

Si Boost échoue à cause de Redis :

```bash
# En local : changer REDIS_HOST dans .env
REDIS_HOST=127.0.0.1

# Avec Docker : utiliser le nom du container
REDIS_HOST=redis
```

## ✨ Conclusion

Laravel Boost transforme radicalement votre workflow de développement Laravel en fournissant un contexte IA intelligent. Utilisez-le pour :

- 🚀 **Développer plus rapidement** avec des suggestions contextuelles
- 🎯 **Suivre les best practices** automatiquement
- 🔍 **Accéder à la documentation** instantanément
- 🛠️ **Débugger efficacement** avec l'accès aux logs et au schéma

**Bon développement avec Laravel Boost ! 🎉**
