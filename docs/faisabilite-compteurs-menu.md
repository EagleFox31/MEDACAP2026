# Faisabilité — Compteurs (badges) sur le menu latéral MEDACAP

> Objectif : afficher un **compteur de nouveautés** à côté de certaines entrées du menu
> (ex. nouveau collaborateur, nouveau test planifié, nouveau résultat de campagne de
> recrutement), pour signaler à l'utilisateur qu'il y a du neuf dans la page correspondante
> depuis sa dernière visite.
>
> Contexte : migration progressive du produit. Ce document décrit **l'existant**, **la
> faisabilité**, une **architecture cible** et un **plan par étapes** compatible PHP 7.3 /
> MongoDB 4.2 (lib `mongodb/mongodb` 1.0.0).

---

## 1. Verdict rapide

**Faisable, et bien positionné pour la migration progressive.** ✅

- Le rendu des items de menu passe par **un seul point** (`echoItem()` dans
  `views/measure/partials/helpersMenu.php`) → un badge s'y ajoute proprement.
- Chaque item a une **clé stable** (`functionalities.key` : `candidats`,
  `rapport_recrutement`, …) → point de jointure naturel pour la logique de comptage.
- Les primitives de **cloisonnement** existent déjà en session (`subsidiary`, `country`,
  `department`, `agency`) et les requêtes Mongo par page sont déjà écrites.

**Ce qui manque (à créer) :**

1. Une notion de **« vu / non-vu »** par utilisateur et par entrée de menu
   (aucune n'existe aujourd'hui dans Measure).
2. Un **résolveur de compteurs** (une fonction par type de nouveauté).
3. Une stratégie de **performance** (le header s'exécute à chaque page).

---

## 2. Comment le menu est construit aujourd'hui

Fichiers clés :

| Rôle | Fichier |
|------|---------|
| Assemblage header + sidebar | `views/measure/partials/header.php` |
| Helpers + rendu d'un item (`echoItem`) | `views/measure/partials/helpersMenu.php` |
| Libellés FR/EN | `views/measure/includes/language.php` |

Pipeline : **profil** → IDs de fonctionnalités (`profiles.functionalities`) → documents
`functionalities` actifs du module courant, triés (`group_order`, `order`) → regroupement
par `group` → rendu HTML via `echoItem()`.

Structure HTML d'un item (le seul endroit à modifier) :

```html
<div class="menu-item [here]">
  <a class="menu-link [active]" href="...">
    <span class="menu-icon"><i class="... fs-2">...</i></span>
    <span class="menu-title">Libellé</span>
    <!-- ICI : futur badge compteur -->
  </a>
</div>
```

> Note : « **Participation aux Tests** » est un lien **codé en dur** (pointe vers
> `dashboardAdmin`), pas issu de `functionalities`. Un badge sur cet item se poserait
> directement dans `header.php` (lignes ~741-756), pas via `echoItem()`.

Champs d'un document `functionalities` : `key`, `name` / `name_EN`, `url`, `icon`,
`icon_type`, `group` / `group_EN`, `group_order`, `order`, `modules`, `active`.

Champs de session disponibles dans le header (posés au login,
`views/index.php`) : `id`, `profile`, `subsidiary`, `agency`, `department`, `country`,
`lang`, `test`.

---

## 3. Cartographie : entrée de menu → page → données à compter

| Entrée de menu | Page | `key` probable | Collections | Ce qu'on compterait |
|---|---|---|---|---|
| Participation aux Tests | `dashboardAdmin.php` | *(codé en dur)* | `users`, `tests`, `results`, `allocations` | Techniciens avec nouvelle activité de test |
| Etat d'Avancement des Tests | `bilanTest.php` / `bilanTestNotes.php` | `bilan_notes` / `bilan_test_notes` | `users`, `tests`, `results`, `allocations` | QCM nouvellement terminés/commencés |
| Etat d'Avancement des Campagnes de Recrutement | `candidats.php` | `candidats` | `users`, `results`, `allocations`, `papers` | Candidats nouvellement composés / importés |
| Rapport recrutement | `rapportRecrutement.php` | `rapport_recrutement` | `papers`, `users`, `results`, `allocations`, `paper_campaign_status` | Hausse de `composed` par épreuve |
| Visualiser mes Collaborateurs | `users.php` (+ `_users-logic.php`) | *(à confirmer en prod)* | `users`, `connections` | Nouveaux collaborateurs (`created > lastSeen`) |
| Planning des tests des filiales | `calendar.php` | *(à confirmer en prod)* | `calendars`, `users` | Nouveaux événements planifiés |

Cloisonnement par profil **Admin filiale** (déjà en place dans ces pages) :

```php
// Motif récurrent (bilanTest.php, _users-logic.php, …)
$query = ['subsidiary' => $_SESSION['subsidiary'], 'active' => true];
if ($_SESSION['department'] != 'Equipment & Motors') {
    $query['department'] = $_SESSION['department'];
}
```

---

## 4. Difficultés identifiées

1. **Aucun modèle « vu / non-vu ».** Il faut définir la sémantique du compteur et
   persister un `lastSeenAt` par utilisateur et par clé de menu. (Rien de tel n'existe
   côté Measure ; `paper_campaign_status.autoAlertSentAt` sert à l'idempotence mail, pas à
   l'état de lecture par utilisateur.)

2. **Coût dans le header.** Le header tourne à **chaque** chargement de page. Calculer 5+
   compteurs à chaque fois multiplie les requêtes Mongo → prévoir agrégation, cache court,
   ou chargement **asynchrone (AJAX)** après affichage du menu.

3. **`calendars` sans `subsidiary`.** Les événements sont chargés globalement
   (`calendars.find({active:true})`) ; le rattachement à la filiale est **indirect** via
   les techniciens liés (`users.subsidiary`). Le compteur « Planning » nécessite donc un
   `$lookup` / une résolution des techniciens de la filiale.

4. **Libellés ↔ clés à confirmer en prod.** Certaines `key` exactes (Collaborateurs,
   Planning) doivent être relevées sur la base prod (`functionalities` filtrées par le
   profil `Admin`), le Mongo local étant souvent indisponible.

5. **Contraintes PHP 7.3.** Pas d'arrow functions, pas d'union types, `countDocuments()`
   interdit (lib figée en 1.0.0) → passer par `medacapCountDocuments()`
   (`core/mongo_compat.php`).

---

## 5. Architecture cible proposée

### 5.1 Sémantique du compteur

Deux options, non exclusives :

- **A — « Depuis ma dernière visite »** (recommandé) : le badge = nombre d'éléments dont
  l'horodatage de création/mise à jour est postérieur au `lastSeenAt` de l'utilisateur
  pour cette entrée. À l'ouverture de la page, on met `lastSeenAt = now` → le badge
  retombe à 0. Simple, intuitif, proche d'un « non-lu ».

- **B — « À traiter »** (métier) : le badge = nombre d'éléments dans un état actionnable
  (ex. candidats `awaiting`, tests non corrigés). Indépendant de la visite ; utile mais
  demande une règle métier par entrée.

> Recommandation : démarrer avec **A** (générique, réutilisable), puis ajouter **B** au
> cas par cas là où c'est pertinent (recrutement notamment).

### 5.2 Stockage de l'état « vu »

Nouvelle collection `menu_last_seen` (un document par utilisateur + clé) :

```javascript
{
  user: ObjectId("..."),       // _id utilisateur
  menuKey: "candidats",        // functionalities.key (ou clé logique pour items codés en dur)
  lastSeenAt: ISODate("...")   // mis à jour à l'ouverture de la page correspondante
}
// Index unique : { user: 1, menuKey: 1 }
```

Alternative : sous-document `menuLastSeen: { candidats: <date>, ... }` dans `users`
(moins de collections, mais grossit le doc utilisateur). La collection dédiée est plus
propre pour la migration.

### 5.3 Résolveur de compteurs

Une librairie dédiée, ex. `views/measure/includes/menu_counters_lib.php` :

```php
// PHP 7.3 — pas d'arrow function, countDocuments interdit
require_once __DIR__ . '/../../core/mongo_compat.php';

/**
 * Retourne un tableau [ menuKey => int ] des compteurs pour l'utilisateur courant.
 * $since = tableau [ menuKey => DateTime lastSeen ] issu de menu_last_seen.
 */
function menuCounters_computeForAdmin($db, array $session, array $since) {
    $counts = [];

    // Ex. nouveaux collaborateurs de la filiale
    $filter = [
        'subsidiary' => $session['subsidiary'],
        'active'     => true,
    ];
    if (isset($since['collaborateurs'])) {
        $filter['created'] = ['$gt' => $since['collaborateurs']];
    }
    $counts['collaborateurs'] = medacapCountDocuments($db->users, $filter);

    // … un bloc par entrée (candidats, rapport_recrutement, calendar, bilan…)
    return $counts;
}
```

Chaque bloc réutilise **exactement** les filtres de cloisonnement déjà présents dans la
page cible, pour que le compteur corresponde à ce que l'utilisateur verra.

> ⚠ Attention au format des dates : certaines collections stockent `created` en **chaîne**
> (`date('d-m-Y H:i:s')` dans `calendar.php`, `date('Y-m-d H:i:s')` ailleurs), pas en
> `ISODate`. Le comparateur `$gt` sur une chaîne `d-m-Y` **ne fonctionne pas**
> chronologiquement. Deux options : normaliser en ISODate à la source (migration), ou
> comparer côté PHP après lecture. À arbitrer par collection.

### 5.4 Affichage du badge

Extension **minimale** de `echoItem()` :

```php
function echoItem(array $item, string $lang, string $extraHref = '', $badgeCount = null): void
{
    // … rendu existant jusqu'au menu-title …
    echo '</span><span class="menu-title">' . htmlspecialchars(menuLabel($item, $lang)) . '</span>';
    if ($badgeCount !== null && (int)$badgeCount > 0) {
        echo '<span class="badge badge-circle badge-danger ms-2">' . (int)$badgeCount . '</span>';
    }
    echo '</a></div>';
}
```

L'appelant (`header.php`) passe `$counts[$item['key']]` à `echoItem()`.

### 5.5 Performance : chargement asynchrone (recommandé)

Pour ne pas ralentir chaque page :

1. Le header rend le menu **sans** compteur (ou avec valeurs en cache).
2. Un petit endpoint JSON (ex. `views/measure/api/menu-counters.php`) calcule les
   compteurs pour la session et renvoie `{ candidats: 3, collaborateurs: 1, ... }`.
3. Un JS léger dans le footer appelle l'endpoint et injecte les badges après coup.

Variante plus simple : calcul **synchrone** mais avec **cache court** (30-60 s) par
utilisateur dans une collection `menu_counters_cache` ou en session. À privilégier si on
veut éviter d'ajouter du JS pour l'instant.

### 5.6 Remise à zéro

Sur chaque page cible, en tête (après la garde de session), un appel du type :

```php
menuCounters_markSeen($db, $_SESSION['id'], 'candidats'); // upsert lastSeenAt = now
```

À poser dans les pages : `candidats.php`, `rapportRecrutement.php`, `users.php`
(mode collaborateurs), `calendar.php`, `bilanTest.php` / `bilanTestNotes.php`.

---

## 6. Plan de mise en œuvre par étapes

Approche incrémentale, alignée sur la migration progressive :

- **Étape 0 — Cadrage (0,5 j).** Confirmer en prod les `key` exactes des entrées
  (Collaborateurs, Planning) et la sémantique retenue par entrée (A vs B).

- **Étape 1 — Infrastructure (1 j).**
  - Collection `menu_last_seen` + index unique.
  - Lib `menu_counters_lib.php` avec `markSeen()` et un premier compteur
    (ex. `collaborateurs`).
  - Extension de `echoItem()` + affichage badge (rendu synchrone, 1 seule entrée).

- **Étape 2 — Un badge de bout en bout (0,5 j).** Câbler « Visualiser mes
  Collaborateurs » : compteur + remise à zéro sur `users.php`. Valider l'UX et la perf.

- **Étape 3 — Généralisation (1-2 j).** Ajouter les autres entrées une par une
  (candidats, rapport recrutement, planning, bilan), chacune réutilisant le filtre de sa
  page. Gérer le cas des dates en chaîne (planning) et le `$lookup` techniciens.

- **Étape 4 — Perf (0,5-1 j, si besoin).** Passer en chargement AJAX ou cache court si le
  header devient lent.

- **Étape 5 — Extension autres profils/modules.** Réutiliser la lib pour Manager, RH,
  Directeurs et les modules Define/Explore (même mécanisme `echoItem` / `key`).

**Estimation globale : ~3,5 à 5 jours** pour Measure/Admin, hors extension multi-profils.

---

## 7. Points à valider avant de coder

1. Sémantique définitive par entrée : « depuis ma dernière visite » (A) ou « à traiter »
   (B) ?
2. Rendu **synchrone + cache** ou **asynchrone (AJAX)** ? (impacte l'effort et l'UX)
3. `key` exactes en prod pour Collaborateurs et Planning.
4. Faut-il un badge sur « Participation aux Tests » (item codé en dur) ?
5. Portée : uniquement **Admin filiale** au départ, ou d'emblée tous les profils ?

---

## 8. Références fichiers

- Rendu menu : `views/measure/partials/header.php`, `views/measure/partials/helpersMenu.php`
- Helper comptage Mongo : `core/mongo_compat.php` (`medacapCountDocuments`)
- Pages cibles : `views/measure/candidats.php`, `rapportRecrutement.php`,
  `users.php` (+ `_users-logic.php`), `calendar.php`, `bilanTest.php`, `bilanTestNotes.php`
- Recrutement (lib métier) : `views/measure/includes/paper_candidate_report_lib.php`,
  collection `paper_campaign_status`
- Setup menu (exemple d'insertion `functionalities`) :
  `views/measure/scripts/setup_rapport_recrutement_menu.php`
