# Formulaire Devis Manufa

Formulaire multi-étapes pour demandes de devis — streaming / direct.  
Sauvegarde automatique (LocalStorage) et envoi vers Notion ou webhook CRM.

Disponible en **HTML/JS** (GitHub Pages, Vercel) et en **PHP** (hébergement classique).

---

## Version PHP (recommandée pour Notion)

Tout le flux (formulaire + envoi Notion) tourne en PHP, sans Vercel.

### Fichiers PHP

| Fichier | Rôle |
|--------|------|
| `index.php` | Page formulaire ; `data-endpoint` est rempli automatiquement |
| `submit.php` | Reçoit le JSON, crée une page dans la base Notion |
| `config.php` | Charge `.env` et expose `notion_api_key()`, `notion_database_id()`, `submit_url()` |

### Configuration

1. **Copier les variables d’environnement**
   ```bash
   cp .env.example .env
   ```
   Éditer `.env` et renseigner :
   - `NOTION_API_KEY` — secret de l’intégration Notion
   - `NOTION_DATABASE_ID` — ID de la base (ex. `303f3f3c00fb801aaec6cfd6273491c2`)

2. **Notion**
   - Créer une intégration sur [notion.so/my-integrations](https://www.notion.so/my-integrations)
   - Dans la base : **•••** → **Ajouter des connexions** → lier l’intégration
   - La base doit avoir les propriétés : **Nom**, **Date événement**, **Notes**

3. **Lancer en local**
   ```bash
   php -S localhost:8000
   ```
   Puis ouvrir `http://localhost:8000/index.php`

4. **Hébergement**
   Déployer `index.php`, `submit.php`, `config.php`, `styles.css`, `script.js` et les assets.  
   Configurer les variables d’environnement (ou un `.env` hors du web root si possible).  
   L’URL de soumission est relative (`submit.php`) sauf si tu définis `FORM_BASE_URL` dans `.env`.

5. **Hébergement sur NAS** — Voir **[DEPLOY-NAS.md](DEPLOY-NAS.md)** (fichiers à copier, Synology, QNAP).

---

## Déployer sur GitHub Pages (version statique HTML/JS)

1. **Créer un dépôt sur GitHub**
   - Va sur [github.com/new](https://github.com/new)
   - Nom du dépôt : par ex. `manufa-devis` ou `form-manufa`
   - Public, sans README ni .gitignore (on en a déjà)

2. **Pousser le projet**
   Dans le dossier du projet :
   ```bash
   git init
   git add index.html styles.css script.js MANUFA-LOGO-1.webp README.md .gitignore .nojekyll
   git commit -m "Formulaire devis Manufa — prêt pour GitHub Pages"
   git branch -M main
   git remote add origin https://github.com/TON-USERNAME/TON-REPO.git
   git push -u origin main
   ```
   Remplace `TON-USERNAME` et `TON-REPO` par ton compte et le nom du dépôt.

3. **Activer GitHub Pages**
   - Sur la page du dépôt : **Settings** → **Pages**
   - **Source** : « Deploy from a branch »
   - **Branch** : `main` — **Folder** : `/ (root)`
   - Enregistre.

4. **URL du site**
   Après quelques minutes :  
   `https://TON-USERNAME.github.io/TON-REPO/`

## Configurer l’envoi des données

### Option A : Webhook CRM

Dans `index.html`, sur la div `.gmq-wrap`, mets l’URL de ton webhook dans `data-endpoint` :

```html
<div class="gmq-wrap" data-endpoint="https://ton-crm.com/webhook/lead">
```

### Option B : Envoyer vers Notion (Manufa)

Pour envoyer les réponses dans la [base Notion](https://www.notion.so/303f3f3c00fb801aaec6cfd6273491c2) :

1. **Intégration Notion**
   - Va sur [notion.so/my-integrations](https://www.notion.so/my-integrations)
   - Crée une intégration, récupère le **Secret** (NOTION_API_KEY).

2. **Base dans Notion**
   - La base doit avoir au minimum trois propriétés : **Titre** (Titre), **Date** (Date), **Contenu** (Texte).
   - ID de la base (dans l’URL) : `303f3f3c00fb801aaec6cfd6273491c2`.
   - Dans la base : **•••** → **Ajouter des connexions** → lie ton intégration.

3. **Déployer l’API**
   - Déploie ce projet sur [Vercel](https://vercel.com) (connexion GitHub puis import du repo).
   - Dans le projet Vercel : **Settings** → **Environment Variables** :
     - `NOTION_API_KEY` = le secret de l’intégration (ne jamais le committer dans le dépôt)
     - `NOTION_DATABASE_ID` = `303f3f3c00fb801aaec6cfd6273491c2`
   - Redéploie si besoin.

4. **Lier le formulaire**
   - Dans `index.html`, l’attribut `data-endpoint` pointe déjà vers l’API Notion avec un placeholder.
   - Remplace `YOUR-VERCEL-APP` par l’URL réelle de ton projet Vercel (ex. `manufa-form.vercel.app`) :
   ```html
   <div class="gmq-wrap" data-endpoint="https://manufa-form.vercel.app/api/notion">
   ```
   Une fois le projet déployé sur Vercel et les variables d’environnement renseignées, chaque soumission du formulaire enverra les données vers ta base Notion.

Sans `data-endpoint` rempli, le formulaire affiche un message à la soumission ; les données restent en LocalStorage.

## Fichiers inclus

| Fichier | Rôle |
|--------|------|
| `index.php` | Formulaire (PHP) — endpoint Notion injecté automatiquement |
| `submit.php` | Traitement POST → création page Notion |
| `config.php` | Config (`.env`, Notion, URL de soumission) |
| `index.html` | Formulaire statique (GitHub Pages) — à utiliser avec `data-endpoint` Vercel |
| `styles.css` | Styles |
| `script.js` | Étapes, LocalStorage, envoi (commun HTML/PHP) |
| `api/notion.js` | API Vercel (optionnel si tu restes en HTML + Vercel) |
| `vercel.json` | Config Vercel |
| `.env.example` | Exemple de variables pour PHP (copier en `.env`) |
| `MANUFA-LOGO-1.webp` | Logo |

## Licence

Usage libre pour Manufa.
