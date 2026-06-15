# PixelCraftLink

Plugin Spigot/Paper qui synchronise les joueurs (pseudo, UUID et IP) d'un serveur
Minecraft vers le panel **PixelCraft Studio**.

## Compilation

Nécessite Java 17+ et Maven.

```bash
cd minecraft-plugin
mvn clean package
```

Le `.jar` est généré dans `target/PixelCraftLink-1.0.0.jar`.

## Installation

1. Déposez le `.jar` dans le dossier `plugins/` du serveur, puis démarrez/rechargez.
2. Dans le panel, ouvrez un projet → onglet **Joueurs** (réservé aux admins).
3. Renseignez l'URL du panel une fois dans `config.yml` (champ `panel-url`), puis
   en console (ou en jeu en tant qu'op) :

   ```
   /pixellink ABCD-EFGH
   ```

   où `ABCD-EFGH` est l'**identifiant de liaison** affiché dans l'onglet Joueurs.

   Première fois sans toucher au `config.yml` ? Passez l'URL en même temps :

   ```
   /pixellink https://votre-panel/api/v1/plugin ABCD-EFGH
   ```

4. Le serveur échange l'identifiant contre un token (stocké automatiquement) et la
   liste des joueurs se remplit.

## Commandes

| Commande | Effet |
| --- | --- |
| `/pixellink <identifiant>` | Relie le serveur (URL lue dans `config.yml`) |
| `/pixellink <url> <identifiant>` | Relie en renseignant aussi l'URL du panel |
| `/pixellink status` | Affiche l'état de la liaison |
| `/pixellink sync` | Force une synchronisation des joueurs en ligne |

Permission requise : `pixelcraftlink.admin` (par défaut : op).

## Fonctionnement

- À la **connexion** d'un joueur → envoi `POST /players/join` (uuid, pseudo, ip).
- À la **déconnexion** → `POST /players/quit`.
- Toutes les `sync-interval` secondes (défaut 300) → `POST /players/sync` avec la
  liste complète des joueurs en ligne (les absents repassent hors-ligne).

L'authentification se fait via l'en-tête `X-Server-Token`. Régénérer le token
dans le panel invalide immédiatement l'ancien.
