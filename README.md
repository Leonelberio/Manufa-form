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

Dans `index.html`, sur la div `.gmq-wrap`, mets l’URL de ton webhook CRM dans `data-endpoint` :

```html
<div class="gmq-wrap" data-endpoint="https://ton-crm.com/webhook/lead">
```

Sans cette URL, le formulaire affiche un message à la soumission ; les données restent en LocalStorage.

## Fichiers inclus

| Fichier | Rôle |
|--------|------|
| `index.html` | Page et formulaire |
| `styles.css` | Styles |
| `script.js` | Étapes, LocalStorage, envoi |
| `MANUFA-LOGO-1.webp` | Logo |
| `index.php` | Version PHP (pour hébergement avec PHP, hors GitHub Pages) |
| `start-server.bat` | Lancer un serveur PHP en local sous Windows |

## Licence

Usage libre pour Manufa.
