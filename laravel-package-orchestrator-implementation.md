# laravel-package-orchestrator — Guide d'implémentation

> Ordre de développement, dépendances & détail technique
> Base : spatie/package-skeleton-laravel

> **⚠️ Contrainte de langue : toute la CLI doit être 100% en anglais.**
> Tous les libellés de prompts (`select()`, `multiselect()`, `search()`, `confirm()`), messages de succès/échec, noms de catégories, et sorties de la commande doivent être écrits en anglais dès leur première implémentation (Feature 4 et suivantes). Ce document de roadmap reste en français car c'est un document de travail interne, mais aucun texte français ne doit apparaître dans le code ou les strings affichées à l'utilisateur final. Les fiches YAML du catalogue (Feature 9) doivent aussi utiliser des noms de catégories en anglais (`Authentication`, `Admin Panels`, `Database`, `Debugging`, etc.) pour rester cohérentes.

---

## Vue d'ensemble des dépendances

| #  | Feature                                      | Dépend de | Priorité |
| -- | --------------------------------------------- | --------- | -------- |
| 1  | PackageSkeleton setup                         | —         | Haute    |
| 2  | PackageCatalog (statique PHP)                 | 1         | Haute    |
| 3  | ProcessRunner                                 | 1         | Haute    |
| 4  | InstallCommand (sélection simple)             | 2 + 3     | Haute    |
| 5  | CategoryMenu                                  | 4         | Haute    |
| 6  | MultiSelect + Panier cumulatif                | 5         | Haute    |
| 7  | DependencyResolver (depends_on/conflicts_with)| 6         | Haute    |
| 8  | SearchCommand                                 | 5         | Moyenne  |
| 9  | YamlCatalogLoader                             | 2         | Haute    |
| 10 | CompatibilityChecker (Laravel/PHP)            | 9         | Moyenne  |
| 11 | DryRun & Rollback                             | 7         | Moyenne  |
| 12 | CatalogValidator (commande contributeur)      | 9         | Basse    |
| 13 | InstallLogger                                 | 3         | Basse    |

---

## Feature 1 — PackageSkeleton setup

**Sprint 1 | Statut : À faire | Dépend de : Aucune**

### Objectif

Initialiser le repo depuis spatie/package-skeleton-laravel et le rendre installable en local. Point de départ de tout le package.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Générer le repo | `Use this template` sur GitHub ou script d'init du skeleton Spatie. |
| 2. Renommer | Namespace PHP, nom du Service Provider, `vendor/nom-package` dans `composer.json`. |
| 3. Nettoyer les stubs | Supprimer config/migrations/tests factices générés par défaut par le skeleton. |
| 4. Ajouter les dépendances | `composer require laravel/prompts` + `composer require symfony/process` (déjà transitive de Laravel en général, à vérifier). |
| 5. Vérifier Testbench | S'assurer que `Orchestra\Testbench` (inclus par le skeleton) charge bien le Service Provider dans les tests Pest. |
| 6. Premier commit + CI | Le skeleton fournit déjà Pest + Pint + Larastan en GitHub Actions — ne pas les désactiver. |

### Commande de vérification

```bash
composer require your-vendor/laravel-orchestrator --dev --repository='{"type":"path","url":"../laravel-orchestrator"}'
php artisan list | grep x:
```

---

## Feature 2 — PackageCatalog (statique PHP)

**Sprint 1 | Statut : À faire | Dépend de : Feature 1**

### Objectif

Catalogue de packages en dur dans le code PHP, pour valider le flow avant de passer au YAML (Feature 9). Définit l'interface `PackageDefinition` réutilisée partout ensuite.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Interface commune | `src/Catalog/PackageDefinition.php` — DTO : `name, category, tags[], installSteps[], supportedLaravel[], supportedPhp, dependsOn[], conflictsWith[]`. |
| 2. Catalogue en dur | `src/Catalog/StaticCatalog.php` — implémente `CatalogRepositoryInterface`, retourne un tableau de `PackageDefinition` codé directement (Filament, Sanctum, Spatie Permission, Horizon, minimum 6-8 packages pour tester). |
| 3. CatalogRepositoryInterface | `getAll(): array`, `findByName(string $name): ?PackageDefinition`, `findByCategory(string $category): array`. Interface indépendante de l'implémentation pour permettre le swap vers YAML en Feature 9 sans toucher au reste du code. |
| 4. Binding dans le ServiceProvider | `$this->app->bind(CatalogRepositoryInterface::class, StaticCatalog::class)` — un seul point de bascule pour changer d'implémentation plus tard. |

### Extrait PackageDefinition

```php
final class PackageDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly array $tags,
        public readonly array $installSteps,
        public readonly array $supportedLaravel = [],
        public readonly string $supportedPhp = '',
        public readonly array $dependsOn = [],
        public readonly array $conflictsWith = [],
    ) {}
}
```

---

## Feature 3 — ProcessRunner

**Sprint 1 | Statut : À faire | Dépend de : Feature 1 (parallèle à Feature 2)**

### Objectif

Exécuter les commandes shell (`composer require`, `artisan vendor:publish`, `artisan migrate`) de façon fiable, avec sortie lisible et gestion d'échec — contrairement à EzWizard qui échoue silencieusement.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Wrapper Process | `src/Support/ProcessRunner.php` — enveloppe `Symfony\Component\Process\Process`. Méthode `run(string $command): ProcessResult` où `ProcessResult = { success: bool, output: string, exitCode: int }`. |
| 2. Timeout | Timeout par défaut de 120s par commande (configurable), pour ne pas bloquer indéfiniment sur une commande composer qui traîne. |
| 3. Sortie streamée | Utiliser le callback de `Process::run()` pour afficher la sortie en direct via `Laravel\Prompts\spin()` plutôt que d'attendre la fin silencieusement. |
| 4. Détection d'échec | Vérifier `exitCode !== 0` → lever `PackageInstallationException` avec le message d'erreur brut de la commande, pas juste "failed". |
| 5. Répertoire de travail | Toujours exécuter depuis `base_path()`, jamais depuis le cwd du process PHP (peut différer en environnement de test). |

### Extrait ProcessRunner

```php
public function run(string $command): ProcessResult
{
    $process = Process::fromShellCommandline($command, base_path(), null, null, 120);
    $process->run();

    return new ProcessResult(
        success: $process->isSuccessful(),
        output: $process->getOutput() . $process->getErrorOutput(),
        exitCode: $process->getExitCode(),
    );
}
```

---

## Feature 4 — InstallCommand (sélection simple)

**Sprint 2 | Statut : À faire | Dépend de : Feature 2 + Feature 3**

### Objectif

Première commande Artisan fonctionnelle : liste plate, sélection d'**un** package, installation réelle. Valide que tout le pipeline catalogue → exécution fonctionne avant d'ajouter la complexité des catégories.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Déclarer la commande | `src/Commands/InstallCommand.php extends Command`, signature `x:install`. |
| 2. Menu plat | `Laravel\Prompts\select()` listant `CatalogRepositoryInterface::getAll()` par nom. |
| 3. Récap avant exécution | Afficher la liste des `installSteps` du package choisi, puis `confirm('Proceed with installation?')` (texte anglais, cf. contrainte de langue en tête de document). |
| 4. Exécution séquentielle | Boucler sur `installSteps`, appeler `ProcessRunner::run()` pour chacune, afficher `✓`/`✗` par étape. |
| 5. Gestion d'échec | Si une étape échoue → arrêter la boucle, afficher clairement quelle étape a échoué et pourquoi (pas de `"No items have been installed"` vague comme EzWizard). |

### Test manuel de validation

Installer Filament et Sanctum sur un projet Laravel frais, vérifier que le panel/les routes fonctionnent réellement — pas juste que la commande Artisan s'exécute sans erreur.

---

## Feature 5 — CategoryMenu

**Sprint 3 | Statut : À faire | Dépend de : Feature 4**

### Objectif

Remplacer la liste plate par une navigation à deux niveaux : catégorie puis package.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Extraire les catégories | `CatalogRepositoryInterface::getAll()` groupé par `category` via `collect()->groupBy('category')`. |
| 2. Premier select() | Liste des catégories distinctes (`Authentication`, `Admin Panels`, `Database`...). |
| 3. Deuxième select() | Packages de la catégorie choisie, + option `← retour` qui relance le premier select(). |
| 4. Boucle de navigation | Structurer en `while (true)` avec un `match()` sur le choix pour permettre l'aller-retour sans dupliquer le code du menu. |

---

## Feature 6 — MultiSelect + Panier cumulatif

**Sprint 4 | Statut : À faire | Dépend de : Feature 5**

### Objectif

Permettre de sélectionner plusieurs packages à travers plusieurs catégories avant de tout installer en une seule passe, comme `apt install pkg1 pkg2 pkg3`.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. État du panier | Classe `InstallationCart` (simple collection en mémoire pendant l'exécution de la commande) avec `add(PackageDefinition)`, `remove(string $name)`, `all(): array`. |
| 2. multiselect() par catégorie | Remplacer le `select()` de packages par `Laravel\Prompts\multiselect()` — coche plusieurs packages d'un coup, ajoutés au panier. |
| 3. Menu principal enrichi | Après retour d'une catégorie, proposer : `Parcourir une autre catégorie`, `Rechercher un package` (Feature 8), `Voir le panier`, `Terminer et installer`. |
| 4. Écran panier | `Voir le panier` liste les packages actuellement sélectionnés avec option de retirer un package avant de continuer. |
| 5. Récap global | Avant `confirm()` final, afficher la liste complète et concaténée de toutes les `installSteps` de tous les packages du panier, dans l'ordre d'ajout (l'ordre réel sera recalculé en Feature 7). |

---

## Feature 7 — DependencyResolver (depends_on / conflicts_with)

**Sprint 5 | Statut : À faire | Dépend de : Feature 6**

### Objectif

Calculer l'ordre d'installation correct du panier et bloquer les combinaisons incompatibles avant toute exécution — le point technique le plus délicat de l'orchestrateur.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Champs du catalogue | `PackageDefinition::$dependsOn` et `$conflictsWith` (déjà prévus dans le DTO de la Feature 2, à exploiter ici). |
| 2. Détection de conflit | Avant tri : pour chaque paire du panier, vérifier si l'un est dans le `conflictsWith` de l'autre. Si oui → bloquer, afficher clairement les deux noms et pourquoi, retour au menu panier (pas d'exécution possible). |
| 3. Dépendance manquante | Si un package du panier a un `dependsOn` absent du panier → proposer `confirm("X requiert Y, l'ajouter au panier ?")`. Si refusé → retirer X du panier ou avertir explicitement du risque. |
| 4. Tri topologique | Algorithme de tri topologique classique (DFS avec marquage temporaire/permanent) sur le sous-graphe `dependsOn` limité aux packages du panier. |
| 5. Détection de cycle | Si le DFS retombe sur un nœud marqué "temporaire" → dépendance circulaire dans le catalogue lui-même. Lever une exception explicite au lieu de boucler à l'infini — erreur de données, pas de bug utilisateur. |
| 6. Tests Pest dédiés | Cas à couvrir : dépendance simple, dépendance en chaîne (A→B→C), conflit direct, cycle A→B→A. |

### Extrait tri topologique

```php
final class DependencyResolver
{
    /** @param PackageDefinition[] $cart */
    public function resolveOrder(array $cart): array
    {
        $visited = [];
        $visiting = [];
        $sorted = [];
        $byName = collect($cart)->keyBy('name');

        $visit = function (PackageDefinition $pkg) use (&$visit, &$visited, &$visiting, &$sorted, $byName) {
            if (isset($visited[$pkg->name])) return;
            if (isset($visiting[$pkg->name])) {
                throw new CircularDependencyException($pkg->name);
            }
            $visiting[$pkg->name] = true;

            foreach ($pkg->dependsOn as $depName) {
                if ($dep = $byName->get($depName)) {
                    $visit($dep);
                }
            }

            unset($visiting[$pkg->name]);
            $visited[$pkg->name] = true;
            $sorted[] = $pkg;
        };

        foreach ($cart as $pkg) {
            $visit($pkg);
        }

        return $sorted;
    }
}
```

---

## Feature 8 — SearchCommand

**Sprint 4 | Statut : À faire | Dépend de : Feature 5 (parallèle à Feature 6)**

### Objectif

Recherche par mot-clé indépendante de la navigation par catégorie, pour l'utilisateur qui connaît un nom partiel.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. search() Prompts | `Laravel\Prompts\search()` avec callback filtrant `CatalogRepositoryInterface::getAll()` sur `name` et `tags`. |
| 2. Matching | `str_contains` insensible à la casse pour commencer ; `similar_text`/Levenshtein en option plus tard si tolérance aux fautes de frappe souhaitée. |
| 3. Résultat sans ambiguïté | Toujours afficher le nom Composer complet (`spatie/laravel-permission`) dans les résultats, jamais un nom raccourci. |
| 4. Intégration au panier | Un résultat sélectionné passe par le même `InstallationCart::add()` que le multiselect (Feature 6) — pas de chemin de code séparé. |

---

## Feature 9 — YamlCatalogLoader

**Sprint 6 | Statut : À faire | Dépend de : Feature 2**

### Objectif

Remplacer le `StaticCatalog` par un chargement depuis des fichiers YAML — le vrai différenciateur du package face à EzWizard.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Schéma YAML | Un fichier par package dans `resources/catalog/*.yaml` : `name, category, tags, install (liste), supported.laravel (liste), supported.php, depends_on, conflicts_with`. |
| 2. Parser | `symfony/yaml` (`Symfony\Component\Yaml\Yaml::parseFile()`), déjà présent comme dépendance transitive de Laravel. |
| 3. YamlCatalog | `src/Catalog/YamlCatalog.php implements CatalogRepositoryInterface` — scanne `resources/catalog/`, parse chaque fichier, construit un `PackageDefinition` par fichier. |
| 4. Validation au chargement | Champs requis absents ou malformés → logger un warning et ignorer la fiche (ne pas planter tout le catalogue pour une fiche cassée). |
| 5. Bascule du binding | Dans le ServiceProvider, remplacer `StaticCatalog::class` par `YamlCatalog::class` — aucun autre code ne doit changer grâce à l'interface définie en Feature 2. |
| 6. Publication | `vendor:publish --tag=package-catalog` pour que l'utilisateur puisse copier/override des fiches dans son propre projet (`config/package-catalog/` ou similaire). |

### Exemple de fiche YAML

```yaml
name: spatie/laravel-permission
category: authentication
tags: [roles, permissions, acl]
install:
  - composer require spatie/laravel-permission
  - php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  - php artisan migrate
supported:
  laravel: ["11.*", "12.*", "13.*"]
  php: "^8.2"
depends_on: []
conflicts_with: []
```

---

## Feature 10 — CompatibilityChecker

**Sprint 7 | Statut : À faire | Dépend de : Feature 9**

### Objectif

Vérifier la compatibilité Laravel/PHP déclarée dans chaque fiche avant de proposer le package, pour éviter d'installer quelque chose d'incompatible avec le projet courant.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Version courante | Récupérer `app()->version()` (Laravel) et `PHP_VERSION` au démarrage de la commande. |
| 2. Comparaison Laravel | Matcher contre les patterns `supported.laravel` (ex: `"12.*"`) via `Composer\Semver\Semver::satisfies()` (déjà dépendance transitive de Composer/Laravel). |
| 3. Comparaison PHP | Idem avec `supported.php` (ex: `"^8.2"`). |
| 4. Filtrage vs avertissement | Option configurable : soit masquer les packages incompatibles du menu, soit les afficher grisés avec un tag `⚠ incompatible` — préférer l'avertissement visible plutôt que le masquage silencieux. |
| 5. Blocage à la confirmation | Si l'utilisateur force malgré l'avertissement, demander une confirmation explicite supplémentaire avant exécution. |

---

## Feature 11 — DryRun & Rollback

**Sprint 8 | Statut : À faire | Dépend de : Feature 7**

### Objectif

Sécuriser l'exécution réelle : prévisualisation sans effet de bord, et gestion propre d'un échec en cours de panier.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Flag --dry-run | Sur `InstallCommand`. Si présent, `ProcessRunner` logue les commandes au lieu de les exécuter (`this->components->info("[DRY RUN] {$command}")`). |
| 2. Vérification pré-vol | Avant exécution réelle : état git (`git status --porcelain`) pour avertir si des modifications non commitées existent ; connexion DB testée si une étape `migrate` est prévue dans le panier. |
| 3. Rollback best-effort | Si une étape échoue en plein milieu du panier : proposer `composer remove {package}` pour le package en cours d'échec, mais ne pas toucher aux packages déjà installés avec succès avant lui (informer clairement l'utilisateur de ce qui a été fait vs annulé). |
| 4. Résumé final | `{ installed: string[], failed: string[], skipped: string[] }` affiché en fin de run, quel que soit le résultat. |

---

## Feature 12 — CatalogValidator (commande contributeur)

**Sprint 9 | Statut : À faire | Dépend de : Feature 9**

### Objectif

Permettre à un contributeur de vérifier sa fiche YAML avant de soumettre une PR au catalogue, sans avoir à lancer toute la CLI d'installation.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Commande dédiée | `x:catalog:validate {path?}` — valide un fichier précis ou tout `resources/catalog/` si aucun argument. |
| 2. Vérifications | Champs requis présents, `install` non vide, `supported.laravel`/`supported.php` sont des contraintes semver valides (`Semver::satisfies()` sur une version factice pour tester le pattern lui-même). |
| 3. Vérification des doublons | `name` déjà présent dans une autre fiche du catalogue → erreur bloquante. |
| 4. Sortie exploitable en CI | Exit code non-zéro si erreurs, pour brancher cette commande dans une GitHub Action de vérification des PR communautaires. |

---

## Feature 13 — InstallLogger

**Sprint 9 | Statut : À faire | Dépend de : Feature 3 — non critique, à implémenter en dernier**

### Objectif

Historiser les installations pour debug après coup, sans dépendre de la mémoire du terminal de l'utilisateur.

### Étapes d'implémentation

| Étape | Détail |
| --- | --- |
| 1. Canal de log dédié | Écrire dans `storage/logs/package-orchestrator.log` via un canal Laravel custom, plutôt que le canal applicatif par défaut. |
| 2. Contenu loggé | Par package installé : timestamp, nom, commandes exécutées, succès/échec, sortie brute en cas d'échec. |
| 3. Rotation | S'appuyer sur la config `single`/`daily` standard de Laravel plutôt que réinventer une rotation custom. |

---

_laravel-package-orchestrator — Guide d'implémentation interne — Août 2026_
