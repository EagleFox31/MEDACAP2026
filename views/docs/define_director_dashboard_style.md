# MEDACAP — Style Guide : Define Director Dashboard

> Source files :
> - Vue : `views/define/views/director/dashboard.php`
> - CSS : `views/define/views/director/dashboard.css` + `public/assets/css/dashboard.css`
> - Controller : `views/define/controllers/DirectorDashboardController.php`
> - Composants : `views/define/components/`
> - Logos marques : `views/define/brands/`

---

## 1. Variables CSS (Design Tokens)

```css
--primary-black:   #1a1a1a
--primary-red:     #dc2626
--primary-navy:    #1e3a8a
--secondary-navy:  #3b82f6
--light-gray:      #f8fafc
--medium-gray:     #64748b
--dark-gray:       #334155
--white:           #ffffff
--border-color:    #e2e8f0

--shadow-light:    0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06)
--shadow-medium:   0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)
--shadow-large:    0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)

--border-radius:    12px
--border-radius-lg: 16px
--transition:       all 0.3s cubic-bezier(0.4, 0, 0.2, 1)
```

---

## 2. Background (Fond général)

```css
/* Body */
background-image: url('/MEDACAP/public/images/welcome_tech.png');
background-repeat: no-repeat;
background-position: center center;
background-attachment: fixed;

/* Conteneur principal */
min-height: 100vh;
position: relative;
z-index: 1;
background: none; /* laisse transparaître l'image du body */
```

---

## 3. Typographie

```css
/* Font principale */
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

/* Titres H1 */
color: #1a1a1a;
font-weight: 700;
font-size: 1.25rem (dashboard) / 2.5rem (titre principal);
letter-spacing: -0.025em;

/* Grandes valeurs numériques (.big-number) */
font-size: 1.5rem (stats cards) / 2.2rem+ (KPI cards);
font-weight: 800–900;
color: #1a1a1a;
line-height: 1;
letter-spacing: -0.02em à -0.03em;

/* Labels de carte (.card-title-text) */
font-size: 0.75rem;
font-weight: 600;
color: #64748b;
text-transform: uppercase;
letter-spacing: 0.03em;
line-height: 1.1–1.2;
```

---

## 4. Cards

### 4.1 Carte glass (effet glassmorphisme)
```css
/* .glass-effect */
background: rgba(255, 255, 255, 0.7);
backdrop-filter: blur(10px);
-webkit-backdrop-filter: blur(10px);
border: 1px solid rgba(255, 255, 255, 0.18);
box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
border-radius: 15px;
transition: all 0.3s ease;

/* Hover */
background: rgba(255, 255, 255, 0.8);
box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.2);
transform: translateY(-3px);
```

### 4.2 Carte avec profondeur
```css
/* .depth-effect */
transform: translateZ(0);
box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.05), 0 12px 20px rgba(0,0,0,0.06);
transition: all 0.3s ease-in-out;

/* Hover */
transform: translateY(-5px) translateZ(0);
box-shadow: (ombres amplifiées);
```

### 4.3 Carte stats (.stats-card)
```css
background: rgba(255, 255, 255, 0.7);
border: 1px solid rgba(255, 255, 255, 0.2);
border-radius: 12px;
min-height: 100px;
display: flex;
flex-direction: column;
align-items: center;
justify-content: center;
backdrop-filter: blur(8px);
box-shadow: 0 4px 20px 0 rgba(0,0,0,0.1),
            0 1px 5px 0 rgba(0,0,0,0.05),
            0 0 0 1px rgba(255,255,255,0.2) inset;

/* Hover */
transform: translateY(-8px) scale(1.02);
border-color: rgba(30, 58, 138, 0.3);
```

### 4.4 En-tête de carte (.card-header-bg)
```css
background: linear-gradient(135deg, #1a1a1a 0%, #1e3a8a 100%);
color: white;
padding: 1.25rem 1.5rem;
border: none;

/* Titre dans l'en-tête */
color: white;
font-weight: 600;
font-size: 0.875rem;
text-transform: uppercase;
letter-spacing: 0.05em;
```

---

## 5. Cards KPI

### KPI Formations
```css
/* .kpi-card-trainings */
background: linear-gradient(135deg, rgba(255, 193, 7, 0.4), rgba(255, 193, 7, 0.2));
border: 1px solid rgba(255, 193, 7, 0.6);
color: white;

/* Hover : texte passe en noir */
```

### KPI Validations
```css
/* .kpi-card-validated */
background: linear-gradient(135deg, rgba(2, 117, 216, 0.4), rgba(2, 117, 216, 0.2));
border: 1px solid rgba(2, 117, 216, 0.6);
color: white;

/* Hover : texte passe en noir */
```

---

## 6. Icônes

### Bibliothèques utilisées
- **Keenicons (duotone)** — classe principale : `ki-duotone`
- **FontAwesome** — classes : `fas`, `far`

### Icônes spécifiques au dashboard
| Icône | Classe | Contexte |
|-------|--------|---------|
| Document | `ki-duotone ki-document fs-2` | Formations |
| Personnes | `ki-duotone ki-people fs-2` | Techniciens |
| Validation | `ki-duotone ki-check-circle fs-2` | Validations |
| Filtre | `ki-duotone ki-filter fs-6` | Filtres |
| Fichier | `fas fa-file-alt fs-2 text-primary` | Documents |
| Check | `fas fa-check-circle fs-2 text-success` | Statut validé |
| Utilisateur validé | `fas fa-user-check` | Technicien |

### Structure Keenicons duotone
```html
<i class="ki-duotone ki-document fs-2">
  <span class="path1"></span>
  <span class="path2"></span>
</i>
```
Couleur appliquée : `#1a1a1a` (noir) dans la plupart des contextes.

---

## 7. Logos des marques

Répertoire : `views/define/brands/`

| Marque (affichée) | Fichier image |
|------------------|--------------|
| RENAULT TRUCK | `renaultTrucks.png` |
| HINO | `hino_logo.png` |
| TOYOTA BT | `bt.png` |
| SINOTRUK | `sinotruk.png` |
| JCB | `jcb.png` |
| MERCEDES TRUCK | `mercedestruck.png` |
| TOYOTA FORKLIFT | `forklift.png` |
| FUSO | `fuso.png` |
| LOVOL | `lovol.png` |
| KING LONG | `kl2.png` |
| MERCEDES | `mercedestruck.png` |
| TOYOTA | `toyota-logo.png` |
| SUZUKI | `suzuki-logo.png` |
| PEUGEOT | `peugeot-logo.png` |

---

## 8. Selects / Filtres

```css
/* .form-select, .form-control */
border: 2px solid #e2e8f0;
border-radius: 10px;
padding: 0.75rem 1rem;
font-size: 1rem;
color: #1a1a1a;
background: #ffffff;
transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

/* Focus */
border-color: #1e3a8a;
box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
outline: none;

/* Désactivé (.filter-disabled) */
opacity: 0.5;
pointer-events: none;

/* Verrouillé (.filter-locked) */
opacity: 0.6;
pointer-events: none;
position: relative;
/* ::after → affiche 🔒 à droite */
```

Filtres disponibles :
- Filiales (pays)
- Agences (par filiale)
- Niveaux (Junior, Senior, Expert)
- Marques
- Techniciens

### En-tête du panneau de filtres
```css
/* .filter-header, .training-header */
background: linear-gradient(135deg, rgb(248, 250, 251) 0%, rgb(250, 251, 251) 100%);
color: #333;
text-align: center;

/* H5 dans l'en-tête */
color: #333;
font-weight: 700;
display: inline-block;
```

---

## 9. Drapeaux (sélecteur de langue)

Sélection FR / EN via drapeau image.
- Valeur stockée en cookie + session
- Priorité : paramètre URL > session > cookie > défaut FR
- Fichiers de langue : `views/language.php` (FR), `views/language_EN.php` (EN)

---

## 10. Histogrammes (Histogram Cards)

```css
/* Conteneur .histogram-card */
min-width: 280px;
max-width: 340px;
flex: 1 1 0;
border-radius: 30px; /* style pilule */
classes: glass-effect depth-effect shadow-lg border-0

/* En-tête */
border-radius: 30px 30px 0 0;
padding: 20px 25px;
background: transparent;
/* Barre d'accent colorée : 5px de large, côté gauche, 30%–70% vertical */

/* Conteneur intérieur */
background: #fff;
border-radius: 25px;
padding: 10px;
border: 1px solid rgba(0,0,0,0.03);
height: 320px;
```

### Couleurs des barres
| Niveau | Couleur |
|--------|---------|
| Junior/Senior/Expert | `#8b5cf6` (violet) |
| Total | `#6366f1` (indigo) |
| Non fait | `#e2e8f0` (gris clair) |

---

## 11. Barres de progression

```css
/* .progress */
height: 16px;
background-color: #333333;
border-radius: 8px;
overflow: hidden;
margin-bottom: 0.75rem;

/* .progress-bar */
background: linear-gradient(90deg, #dc2626 0%, #1e3a8a 100%); /* défaut */
/* .bg-warning : linear-gradient(90deg, #f59e0b 0%, #d97706 100%) */
/* .bg-danger  : linear-gradient(90deg, #dc2626 0%, #b91c1c 100%) */
/* .bg-secondary : rgb(16,16,16), texte blanc */
font-size: 14px;
font-weight: bold;
line-height: 16px;
display: flex;
align-items: center;
justify-content: center;
```

---

## 12. Badges

```css
/* .badge */
padding: 0.5rem 0.75rem;
font-weight: 600;
border-radius: 8px;
font-size: 0.75rem;
text-transform: uppercase;
letter-spacing: 0.025em;

/* Variantes */
.bg-info      → #3b82f6, texte blanc
.bg-secondary → #64748b, texte blanc
.bg-dark      → #1a1a1a, texte blanc
.bg-success   → #dc2626, texte blanc  ← (rouge, non vert)
```

---

## 13. Boutons

```css
/* .btn-primary */
background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
color: white;
border-radius: 10px;
padding: 0.75rem 1.5rem;
font-weight: 600;
font-size: 1rem;
text-transform: uppercase;
letter-spacing: 0.025em;
box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

/* Hover */
background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); /* inversé */
transform: translateY(-2px);
box-shadow: (grande ombre);

/* .btn-outline-primary */
border: 2px solid #1e3a8a;
color: #1e3a8a;
background: white;
/* Hover : bg #1e3a8a, texte white */

/* .btn-outline-secondary */
border: 2px solid #e2e8f0;
color: #334155;
background: white;
/* Hover : bg #1a1a1a, texte white, border #1a1a1a */
```

---

## 14. Tableaux

```css
/* .table */
background: white;
border-radius: 12px;
overflow: hidden;
box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);

/* th */
background: #f8fafc;
color: #1a1a1a;
font-weight: 600;
font-size: 1rem;
text-transform: uppercase;
letter-spacing: 0.025em;
padding: 1rem;
border: none;

/* td */
padding: 1rem;
color: #1a1a1a;
font-weight: 500;
border-color: #e2e8f0;

/* Lignes impaires */
background: rgba(248, 250, 252, 0.5);

/* Hover */
background: rgba(30, 58, 138, 0.05);
```

---

## 15. Séparateurs entre cards de niveau

```css
/* Symboles + ou = entre cards */
width: 32px;
height: 32px;
border-radius: 10px;
background: rgba(255, 255, 255, 0.9);
backdrop-filter: blur(4px);
/* Symbole : + ou = */
color: #1a1a2e;
font-size: 18px;
font-weight: 800;
```

---

## 16. Overlay de chargement

```css
/* #loadingOverlay */
position: fixed;
top: 0; left: 0;
width: 100%; height: 100%;
background: rgba(27, 24, 24, 0.85);
z-index: 9999;
display: none; /* caché par défaut */

/* Spinner */
color: #1e3a8a;
width: 3rem; height: 3rem;
```

---

## 17. Animations

```css
/* Apparition des éléments */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
/* Durée : 0.6s ease-out */

/* Pulse sur succès */
@keyframes highlight-success {
  0%   { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.3); }
  50%  { box-shadow: 0 0 0 20px rgba(220, 38, 38, 0); }
  100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}
/* Durée : 1.5s ease */
```

---

## 18. Responsive

| Breakpoint | Changements |
|-----------|-------------|
| ≤ 1400px | Hauteurs réduites, big-number → 1.25rem, chart → 250px |
| ≤ 992px | Filtres sous le contenu, colonnes en 100% |
| ≤ 768px | Padding 1rem 0.5rem, big-number → 2rem, bouton padding réduit |

---

## 19. Tooltips

```css
/* .custom-tooltip */
background: rgba(0, 0, 0, 0.85);
color: white;
padding: 10px 15px;
border-radius: 8px;
font-size: 15px;
font-weight: 600;
box-shadow: 0 4px 20px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1) inset;
backdrop-filter: blur(4px);
border: 1px solid rgba(255, 255, 255, 0.2);
z-index: 999;

/* Flèche ::after */
border-left: 8px solid transparent;
border-right: 8px solid transparent;
border-top: 8px solid rgba(0, 0, 0, 0.85);
position: absolute;
bottom: -8px; left: 50%;
```
