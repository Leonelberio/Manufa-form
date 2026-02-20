# Héberger le formulaire Manufa sur un NAS

Les clés Notion sont déjà dans `config.php`. Il suffit de copier les fichiers sur le NAS et de pointer le serveur web vers le dossier.

---

## 1. Fichiers à copier sur le NAS

Copie **tous** ces fichiers dans un même dossier (ex. `manufa-form` ou `web/manufa`) sur le NAS :

| Fichier        | Obligatoire |
|----------------|-------------|
| `index.php`    | Oui         |
| `submit.php`   | Oui         |
| `config.php`   | Oui         |
| `script.js`    | Oui         |
| `styles.css`   | Oui         |
| `MANUFA-LOGO-1.webp` | Oui  |
| `MANUFA LOGO MINI 1.png` | Oui (favicon + loader) |
| `.htaccess`              | Optionnel (Apache : page par défaut, protection `config.php`) |

Tu peux ignorer : `index.html`, `api/`, `vercel.json`, `.env.example`, `*.js` (tests), `.git`, `README.md`, etc.

---

## 2. Synology (Web Station + PHP)

1. **Activer Web Station**  
   Panneau de configuration → **Web Service** → **Web Station** : activer.

2. **Activer PHP**  
   Package Center → chercher **PHP** (ou **PHP 7.4 / 8.0**) → Installer.  
   Puis **Web Station** → **Paramètres** → **PHP** : sélectionner la version installée et l’activer pour le portail.

3. **Dossier web**  
   Par défaut le dossier web est `web` dans un volume partagé (ex. `volume1/web`).  
   Crée un sous-dossier, ex. `manufa`, et copie-y tous les fichiers listés ci-dessus.

4. **Hôte virtuel (optionnel)**  
   Web Station → **Paramètres** → **Hôte virtuel** : ajouter un hôte pointant vers `web/manufa` (ou le chemin que tu utilises). Tu peux définir un sous-domaine ou un port.

5. **Accès**  
   - Si tout est dans `web/manufa` :  
     `http://IP-DU-NAS/manufa/index.php`  
   - Ou l’URL de l’hôte virtuel si tu en as créé un.

---

## 3. QNAP

1. **Activer le serveur web**  
   App Center → **Web Server** (ou **Container Station** si tu préfères Docker).

2. **PHP**  
   Si le serveur web QNAP inclut PHP, active-le. Sinon, utilise **Container Station** et une image PHP (ex. `php:8.2-apache`) en montant le dossier du formulaire.

3. **Dossier**  
   En général le contenu web est dans `Web` ou `Qweb`. Crée un sous-dossier (ex. `manufa`) et copie les fichiers.

4. **URL**  
   `http://IP-DU-NAS:8080/manufa/index.php` (le port peut varier selon la config).

---

## 4. Autre NAS (serveur web + PHP)

- Place les fichiers dans le **répertoire document root** (www, htdocs, httpdocs, etc.).
- Vérifie que **PHP** est activé pour ce site/virtual host.
- Ouvre dans le navigateur :  
  `http://IP-OU-NOM-DU-NAS/index.php`

---

## 5. Synology : « Page introuvable » (404)

Si tu vois *« Désolé, la page que vous recherchez est introuvable »* :

**Cause :** Web Station sert un **dossier racine** (souvent le partage **`web`** à la racine du volume). Tes fichiers sont peut‑être dans **`home/web/manufa`** alors que la racine HTTP est un autre dossier.

**À faire :**

1. **Où est la racine web ?**  
   Panneau de configuration → **Web Service** → **Web Station** → onglet **Général** (ou **Paramètres**). Regarde quel **dossier de services web** (ou « emplacement du portail ») est choisi — souvent un partage nommé **`web`** (à la racine, pas dans `home`).

2. **Deux options :**

   **Option A — Déplacer les fichiers au bon endroit**  
   - Dans File Station, repère le partage utilisé comme racine (ex. **`web`** au même niveau que **`home`**, pas `home > web`).  
   - Crée dedans un dossier **`manufa`** et copie-y **tous** les fichiers du formulaire (`index.php`, `submit.php`, `config.php`, `script.js`, `styles.css`, les 2 images, `.htaccess` si tu veux).  
   - URL à utiliser :  
     `http://ton-nas.quickconnect.to/manufa/index.php`  
     (remplace `ton-nas` par ton QuickConnect ID, ou utilise l’IP du NAS).

   **Option B — Faire pointer Web Station vers ton dossier actuel**  
   - Web Station → **Paramètres** → **Hôte virtuel**.  
   - Crée un nouvel hôte virtuel :  
     - Nom / domaine : au choix (ex. `manufa` ou ton sous-domaine).  
     - **Dossier source** : choisis le dossier où sont tes fichiers, par ex. **`home/web/manufa`** (en parcourant l’arbre : `home` → `web` → `manufa`).  
   - Enregistre, puis ouvre l’URL indiquée pour cet hôte (ex. `http://ton-nas.quickconnect.to/manufa/`).

3. **Tester d’autres URLs** si tu ne trouves pas la config :  
   `http://ton-nas.quickconnect.to/web/manufa/index.php`  
   ou  
   `http://ton-nas.quickconnect.to/manufa/index.php`  
   selon l’endroit exact servi par Web Station.

4. **Si l’instance a été créée pour toi (accès limité)**  
   Demande à la personne qui gère le NAS :
   - **Quel dossier est la racine web ?** (ex. `web`, `home/web`, etc.) pour y mettre le dossier `manufa` au bon endroit.
   - **Quelle URL utiliser ?** (ex. `http://xxx.quickconnect.to/manufa/index.php` ou un sous-domaine dédié).
   Une fois que tes fichiers sont dans le bon dossier et l’URL connue, le formulaire fonctionnera sans changer de code.

---

## 6. Autres vérifications

- **Page blanche** : consulte les logs PHP du NAS (Web Station → Logs, ou logs Apache/Nginx).
- **404 sur `submit.php`** : vérifie que `submit.php` est bien dans le même dossier que `index.php` et que les URLs sont relatives (`submit.php`).
- **Notion ne reçoit rien** : les clés sont dans `config.php` ; vérifie que l’intégration Notion est bien connectée à la base (••• → Ajouter des connexions).

---

## 7. Accès depuis l’extérieur (optionnel)

Si tu veux que le formulaire soit accessible hors du réseau local :

- **Synology** : DSM → **Panneau de configuration** → **Accès externe** / **Router** : configurer la redirection de port (ex. 80 ou 443 vers le NAS) ou utiliser **QuickConnect**.
- Ensuite utilise l’URL externe, ex. `http://ton-dns.quickconnect.to/manufa/index.php`.

Tu peux définir `FORM_BASE_URL` dans un fichier `.env` sur le NAS (avec l’URL complète du site) si tu constates des soucis avec l’URL d’envoi du formulaire.
