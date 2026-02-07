# Formulaire Devis Manufa

Formulaire multi-étapes (HTML, CSS, JS) pour demandes de devis — streaming / direct.  
Sauvegarde automatique dans le navigateur (LocalStorage) et envoi optionnel vers un webhook CRM.

## Déployer sur GitHub Pages

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

### Option B : Envoyer vers Notion (Site internet MANUFA)

Pour envoyer les réponses dans [Notion – Site internet MANUFA](https://www.notion.so/Site-internet-MANUFA-30038b0dfbe3809f9699f622dacf1ff4) :

1. **Intégration Notion**
   - Va sur [notion.so/my-integrations](https://www.notion.so/my-integrations)
   - Crée une intégration, récupère le **Secret** (NOTION_API_KEY).

2. **Base dans Notion**
   - Dans la page « Site internet MANUFA », crée une **base de données** (table en base pleine page ou en bloc).
   - Ajoute trois propriétés :
     - **Titre** (type Titre)
     - **Date** (type Date)
     - **Contenu** (type Texte)
   - Ouvre la base en « Pleine page », l’URL contient l’ID :  
     `https://www.notion.so/...?v=XXXX` → la partie avant `?` est l’ID (32 caractères). Copie cet **NOTION_DATABASE_ID**.
   - Dans la base : **•••** → **Ajouter des connexions** → lie ton intégration.

3. **Déployer l’API**
   - Déploie ce projet sur [Vercel](https://vercel.com) (connexion GitHub puis import du repo).
   - Dans le projet Vercel : **Settings** → **Environment Variables** :
     - `NOTION_API_KEY` = le secret de l’intégration
     - `NOTION_DATABASE_ID` = l’ID de la base (32 caractères)
   - Redéploie si besoin.

4. **Lier le formulaire**
   - Dans `index.html`, sur `.gmq-wrap`, mets l’URL de l’API en `data-endpoint` :
   ```html
   <div class="gmq-wrap" data-endpoint="https://TON-PROJET.vercel.app/api/notion">
   ```
   Remplace `TON-PROJET` par l’URL fournie par Vercel.

Sans `data-endpoint` rempli, le formulaire affiche un message à la soumission ; les données restent en LocalStorage.

## Fichiers inclus

| Fichier | Rôle |
|--------|------|
| `index.html` | Page et formulaire |
| `styles.css` | Styles |
| `script.js` | Étapes, LocalStorage, envoi |
| `api/notion.js` | API Vercel : envoi des données vers une base Notion |
| `vercel.json` | Config Vercel |
| `MANUFA-LOGO-1.webp` | Logo |
| `index.php` | Version PHP (pour hébergement avec PHP, hors GitHub Pages) |
| `start-server.bat` | Lancer un serveur PHP en local sous Windows |

## Licence

Usage libre pour Manufa.
