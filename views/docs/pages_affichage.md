# MEDACAP — Pages d'affichage (avec interface visuelle)

> Pages qui rendent du HTML avec interface utilisateur.
> Exclu : handlers AJAX, exports Excel, redirections, calculs, traitements.
>
> **Backgrounds disponibles** (via `views/core/background-manager.php`) :
> - `bg-welcome-tech` → `welcome_tech.png`
> - `bg-dashboard`    → `tof_dashboard_tech.png`
> - `bg-welcome`      → `welcome.jpg`

---

## Racine (2 pages)

| Fichier | Rôle | Background |
|---------|------|-----------|
| `index.php` | Page de connexion (login) | `welcome.jpg` |
| `portal.php` | Portail principal (navigation 7 étapes) | Carousel images |

---

## Module `define` (15 pages)

| Fichier | Rôle | Background |
|---------|------|-----------|
| `views/define/views/director/dashboard.php` | Dashboard Directeur | `welcome_tech.png` |
| `views/define/views/pif_progress.php` | Progression PIF | Via header |
| `views/define/planning.php` | Planning | Dégradé inline |
| `views/define/trainings.php` | Liste des formations | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/createTraining.php` | Création de formation | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/editDeletTraining.php` | Édition formation | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/listProfiles.php` | Liste des profils | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/createProfile.php` | Création de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/editProfile.php` | Édition de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/listFunctionalities.php` | Liste des fonctionnalités | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/createFunctionality.php` | Création fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/editFunctionality.php` | Édition fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/manageGroups.php` | Gestion des groupes | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/managePermissions.php` | Gestion des permissions | ✅ `bg-welcome-tech` (ajouté) |
| `views/define/profile.php` | Profil utilisateur | ✅ `bg-welcome-tech` (ajouté) |

---

## Module `explore` (20 pages)

| Fichier | Rôle | Background |
|---------|------|-----------|
| `views/explore/userSpeciality.php` | Spécialité utilisateur | `background-manager.php` |
| `views/explore/stateAgency.php` | État par agence | `background-manager.php` |
| `views/explore/quizzes.php` | Liste des QCM | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/questions.php` | Liste des questions | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/vehicles.php` | Liste des véhicules | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/specialities.php` | Liste des spécialités | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/tests.php` | Liste des tests | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/userInfo.php` | Infos utilisateur | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/userManagerResults.php` | Résultats manager | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/listProfiles.php` | Liste des profils | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/createProfile.php` | Création de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/editProfile.php` | Édition de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/listFunctionalities.php` | Liste fonctionnalités | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/createFunctionality.php` | Création fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/editFunctionality.php` | Édition fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/manageGroups.php` | Gestion des groupes | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/managePermissions.php` | Gestion des permissions | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/profile.php` | Profil utilisateur | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/importeQuestion.php` | Import questions | ✅ `bg-welcome-tech` (ajouté) |
| `views/explore/editDeletQuiz.php` | Édition/suppression QCM | ✅ `bg-welcome-tech` (ajouté) |

---

## Module `measure` (14 pages)

| Fichier | Rôle | Background |
|---------|------|-----------|
| `views/measure/ListeDesQcm.php` | Liste des QCM disponibles | `bg-dashboard` |
| `views/measure/bilanTest.php` | Bilan du test | `background-manager.php` |
| `views/measure/candidats.php` | Liste des candidats | `background-manager.php` |
| `views/measure/testSavoirFaire.php` | Test savoir-faire | `bg-dashboard` |
| `views/measure/createProfile.php` | Création de profil | `bg-default` |
| `views/measure/createFunctionality.php` | Création fonctionnalité | `bg-default` |
| `views/measure/congrat.php` | Écran de félicitations | ✅ `bg-welcome-tech` (ajouté) |
| `views/measure/chat.php` | Interface chat | — widget flottant |
| `views/measure/listProfiles.php` | Liste des profils | ✅ `bg-welcome-tech` (ajouté) |
| `views/measure/editProfile.php` | Édition de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/measure/listFunctionalities.php` | Liste fonctionnalités | ✅ `bg-welcome-tech` (ajouté) |
| `views/measure/editFunctionality.php` | Édition fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/measure/manageGroups.php` | Gestion des groupes | ✅ `bg-welcome-tech` (ajouté) |
| `views/measure/managePermissions.php` | Gestion des permissions | ✅ `bg-welcome-tech` (ajouté) |

---

## Module `acquire` (9 pages)

| Fichier | Rôle | Background |
|---------|------|-----------|
| `views/acquire/listProfiles.php` | Liste des profils | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/createProfile.php` | Création de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/editProfile.php` | Édition de profil | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/listFunctionalities.php` | Liste fonctionnalités | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/createFunctionality.php` | Création fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/editFunctionality.php` | Édition fonctionnalité | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/importFunctionalities.php` | Import fonctionnalités | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/importProfiles.php` | Import profils | ✅ `bg-welcome-tech` (ajouté) |
| `views/acquire/profile.php` | Profil utilisateur | ✅ `bg-welcome-tech` (ajouté) |

---

## Vues générales (3 pages)

| Fichier | Rôle | Background |
|---------|------|-----------|
| `views/calendar.php` | Calendrier | Via CSS |
| `views/profile.php` | Profil utilisateur | Via includes |
| `views/404.php` | Page d'erreur 404 | ❌ Aucun |

---

## Récapitulatif

| Module | Total | Avec background | Sans background |
|--------|-------|----------------|----------------|
| Racine | 2 | 2 | 0 |
| define | 15 | 15 | 0 |
| explore | 20 | 20 | 0 |
| measure | 14 | 13 | 1 (chat.php — widget) |
| acquire | 9 | 9 | 0 |
| Vues générales | 3 | 2 | 1 |
| **TOTAL** | **63** | **61** | **2** |

---

## Pages restantes SANS background (2)

- `views/measure/chat.php` — widget flottant `position:fixed`, pas une page à fond
- `views/404.php` — page d'erreur (background non prioritaire)
