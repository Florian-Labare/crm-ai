---
name: migration
description: Creer et executer une migration de base de donnees Laravel. Utiliser automatiquement quand on demande de modifier le schema, ajouter une colonne, creer une table.
argument-hint: "[description de la modification]"
---

# Creer une migration Laravel

Modification demandee : $ARGUMENTS

## Etapes

### 1. Generer la migration

```bash
docker exec laravel_app php artisan make:migration <nom_descriptif>
```

Convention de nommage :
- `create_<table>_table` : nouvelle table
- `add_<colonne>_to_<table>_table` : ajout colonne
- `modify_<colonne>_in_<table>_table` : modification colonne
- `drop_<colonne>_from_<table>_table` : suppression colonne

### 2. Ecrire la migration

**Regles obligatoires :**
- TOUJOURS inclure `team_id` (foreign key vers `teams`) pour les tables principales
- TOUJOURS ecrire la methode `down()` pour le rollback
- Utiliser `->nullable()` pour les champs optionnels
- Ajouter des index sur les colonnes frequemment filtrees
- Utiliser `$table->foreignId('x_id')->constrained()->cascadeOnDelete()` pour les FK

```php
public function up(): void
{
    Schema::create('resources', function (Blueprint $table) {
        $table->id();
        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        // ... colonnes metier
        $table->timestamps();

        // Index
        $table->index(['team_id', 'client_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('resources');
}
```

### 3. Executer

```bash
docker exec laravel_app php artisan migrate
```

Si erreur, verifier avec :
```bash
docker exec laravel_app php artisan migrate:status
```

### 4. Mettre a jour le modele Eloquent

- Ajouter les nouveaux champs dans `$fillable`
- Ajouter les `$casts` si necessaire (datetime, boolean, array, integer)
- Ajouter les relations si FK creee
