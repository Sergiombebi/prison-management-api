# Guide d'intégration frontend — API SGP

Ce guide décrit, étape par étape, comment consommer l'API backend SGP depuis le frontend (Vue.js ou autre). Tous les exemples utilisent le préfixe `{{base_url}} = http://127.0.0.1:8000/api/v1` en local.

La collection Postman `SGP-API.postman_collection.json` contient tous ces appels prêts à l'emploi et peut servir de référence exécutable en complément de ce document.

---

## 0. Règles générales

- **Tout est en JSON**, sauf un seul endroit : l'upload de photo (`POST /detenus/photos`), qui doit être en `multipart/form-data` parce qu'un fichier binaire ne peut pas être encodé en JSON.
- Toutes les routes protégées attendent le header :
  ```
  Authorization: Bearer <token>
  Accept: application/json
  ```
  et, pour les requêtes avec un corps JSON :
  ```
  Content-Type: application/json
  ```
- Les réponses qui renvoient un détenu (création, consultation, modification, suppression, restauration) ont **toujours exactement les mêmes clés** — pas besoin de gérer des formats différents selon l'endpoint appelé.
- Un détenu n'est **jamais supprimé** de la base. "Supprimer" = passer `est_present` à `false`. Un détenu inactif disparaît de la liste et ne peut plus être modifié tant qu'il n'est pas restauré.

---

## 1. Authentification

### 1.1 Connexion

```
POST /auth/login
```
```json
{
  "identifiant": "admin",
  "password": "password"
}
```
`identifiant` accepte indifféremment le nom d'utilisateur ou l'email.

Réponse `200` :
```json
{
  "user": { "id": 1, "nom": "...", "prenom": "...", "username": "...", "email": "...", "role": "admin", "est_actif": true },
  "token": "1|abcdef...",
  "token_type": "Bearer"
}
```
**Stockez `token`** (ex: dans un store Pinia + `localStorage`) et envoyez-le dans le header `Authorization: Bearer <token>` de toutes les requêtes suivantes.

Identifiants invalides → `422` avec `errors.identifiant`.

### 1.2 Récupérer l'utilisateur courant (ex: au rechargement de la page)

```
GET /auth/me
```
`200` avec les infos utilisateur, ou `401` si le token est invalide/expiré → redirigez vers l'écran de connexion.

### 1.3 Déconnexion

```
POST /auth/logout
```
Révoque le token côté serveur. Supprimez-le aussi côté client.

---

## 2. Créer un détenu

### Étape A (optionnelle) — Uploader les photos

Si vous avez une ou deux photos à joindre, uploadez-les **avant** de créer le détenu :

```
POST /detenus/photos          (multipart/form-data)
```
Champs :
- `photo_face` (fichier, optionnel)
- `photo_profil` (fichier, optionnel)
- au moins un des deux est obligatoire
- formats acceptés : jpg, jpeg, png, gif, bmp, webp, heic, heif — max 8 Mo chacune

Réponse `201` :
```json
{
  "data": {
    "photo_face": { "url": "https://res.cloudinary.com/...", "public_id": "sgp/detenus/face/xxxx" },
    "photo_profil": { "url": "https://res.cloudinary.com/...", "public_id": "sgp/detenus/profil/yyyy" }
  }
}
```
(la clé `photo_profil` n'apparaît que si vous avez envoyé ce fichier, et inversement)

Conservez `url` et `public_id` de chaque photo — vous les réutiliserez à l'étape B.

**Pourquoi cette étape séparée ?** Le fichier binaire ne peut pas voyager dans le même corps JSON que le reste du formulaire. C'est le seul endroit de toute l'API en `multipart/form-data`.

### Étape B — Créer la fiche détenu (JSON pur)

```
POST /detenus
```
```json
{
  "numero_ecrou": "2026-000123",
  "nom": "Mballa Jean",
  "sexe": "Masculin",
  "date_naissance": "1990-05-14",
  "lieu_naissance": "Yaoundé",
  "nationalite": "Cameroun",
  "profession": "Menuisier",
  "nom_pere": "Mballa Pierre",
  "nom_mere": "Ateba Marie",
  "numero_cni": "100200300",
  "contact_urgence_nom": "Ateba Paul",
  "contact_urgence_lien_parente": "Frère",
  "contact_urgence_telephone": "699999999",
  "photo_face_url": "https://res.cloudinary.com/...",
  "photo_face_public_id": "sgp/detenus/face/xxxx"
}
```

Champs obligatoires : `numero_ecrou` (unique), `nom`, `sexe` (`Masculin`/`Féminin`), `date_naissance`, `lieu_naissance`, `profession`, `nom_pere`, `nom_mere`. Tout le reste est optionnel.

`sexe` : valeurs exactes attendues `"Masculin"` ou `"Féminin"` (avec l'accent).

`photo_face_url`/`photo_face_public_id` (et l'équivalent `photo_profil_*`) : **toujours les deux ensemble ou aucun des deux** — n'envoyez pas l'un sans l'autre.

Réponse `201` avec la fiche complète créée (voir section 4 pour le détail des clés).

### Cas d'erreur à gérer

| Code | Cause | Ce que le frontend doit faire |
|---|---|---|
| `422` | Champ obligatoire manquant, format invalide, `numero_ecrou` déjà utilisé par un détenu **actif** | Afficher `errors.<champ>` sous chaque champ concerné |
| `409` avec `conflict` | `numero_cni`/`numero_passeport` déjà utilisé, mais par un détenu **désactivé** | Voir section 5 — proposer la restauration au lieu de bloquer l'agent |

---

## 3. Lister les détenus (pagination)

```
GET /detenus?page=1
```
10 détenus par page, uniquement les détenus **actifs** (`est_present=true`) — un détenu désactivé n'apparaît jamais dans cette liste.

Réponse `200` :
```json
{
  "data": [
    {
      "id": 1,
      "numero_ecrou": "2026-000123",
      "nom": "Mballa Jean",
      "sexe": "Masculin",
      "date_naissance": "1990-05-14",
      "lieu_naissance": "Yaoundé",
      "nationalite": "Cameroun",
      "profession": "Menuisier",
      "contact": "699999999",
      "statut_penal": "Détention provisoire",
      "date_incarceration": "2026-01-10",
      "motif_detention": "Vol qualifié",
      "type_mandat": "Mandat de dépôt",
      "categorie_penale": "prevenus",
      "cellule_actuelle": { "id": 3, "numero": "C1", "bloc": "A" },
      "est_present": true
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27, ... }
}
```
Utilisez `meta.last_page`/`meta.total` pour construire les contrôles de pagination, et `page=N` pour naviguer.

C'est une **vue résumée** (colonnes de l'ancienne liste C#) — pour tous les détails d'un détenu, voir section 4.

`statut_penal`/`date_incarceration`/`motif_detention`/`type_mandat` viennent du **mandat actif le plus pertinent** (jamais un mandat désactivé ou expiré). Si le détenu a plusieurs mandats actifs en même temps (DPAC) incarcérés à la même date, "Exécution de peine" est prioritaire dans le choix — un détenu qui purge une peine reste avant tout un condamné, même avec un autre mandat en parallèle.

`categorie_penale` est la même valeur que celle utilisée pour filtrer (voir 3.1), déjà calculée sur chaque ligne — pas besoin de la redéduire côté frontend.

`cellule_actuelle` (`id`/`numero`/`bloc`) est `null` si le détenu n'est affecté à aucune cellule.

### 3.1 Filtrer par catégorie pénale (Prévenus / Condamnés / Appellants / Cassationnaires / DPAC)

Reprend les 5 onglets de l'ancienne application, sous forme d'un simple filtre sur ce même listing — pas 5 endpoints séparés :

```
GET /detenus?categorie_penale=prevenus
```
Valeurs acceptées : `prevenus`, `condamnes`, `appellants`, `cassationnaires`, `dpac`. Une valeur invalide → `422`.

Le classement est recalculé à chaque appel à partir des mandats **actifs** de chaque détenu (mandat sans date d'expiration, ou dont la date d'expiration est encore dans le futur) :

| `categorie_penale` | Règle |
|---|---|
| `prevenus` | Tous les mandats actifs du détenu sont "Détention provisoire" |
| `condamnes` | Exactement un mandat actif, et c'est une "Exécution de peine" |
| `appellants` | Au moins un mandat actif "Appellant", et aucun "Exécution de peine" actif en parallèle |
| `cassationnaires` | Au moins un mandat actif "Cassationnaire", et aucun "Exécution de peine" actif en parallèle |
| `dpac` | Au moins 2 mandats actifs simultanés, dont au moins une "Exécution de peine" |

Un détenu peut changer de catégorie automatiquement en ajoutant un nouveau mandat (section 8) — ce n'est jamais assigné manuellement.

### 3.2 Rechercher un détenu

```
GET /detenus?search=Mballa
```
Recherche partielle (insensible à la casse) sur les 4 identifiants les plus utiles pour retrouver quelqu'un : `numero_ecrou`, `nom`, `numero_cni`, `numero_passeport`. Toujours paginé à 10/page, comme le listing normal — `search` est un filtre de plus sur la même requête, pas un endpoint différent.

Combinable avec `categorie_penale` dans le même appel :
```
GET /detenus?search=Mballa&categorie_penale=prevenus&page=2
```

### 3.3 Détenus sans cellule

```
GET /detenus?sans_cellule=1
```
Filtre les détenus présents qui n'ont **aucune cellule active** — jamais affecté, ou sorti d'une sanction en cellule disciplinaire (`POST /sanctions/{id}/terminer`, section 9.4) sans avoir été réaffecté depuis. Combinable avec `search`/`categorie_penale`/`page` comme les autres filtres.

Utile pour construire une vue "à loger" côté frontend : rien ne signale ces détenus ailleurs que ce filtre.

---

## 4. Consulter le détail d'un détenu

```
GET /detenus/{id}
```
Réponse `200` avec **tous** les champs, les photos, **tous ses mandats**, **toutes ses sanctions**, **son historique médical**, **ses visites reçues**, sa cellule actuelle, et qui l'a créé/modifié :
```json
{
  "data": {
    "id": 1, "numero_ecrou": "...", "nom": "...", "sexe": "...", "date_naissance": "...", "age": 36,
    "lieu_naissance": "...", "nationalite": "...", "langue": null, "ethnie": null, "religion": null,
    "profession": "...", "departement": null, "arrondissement": null, "residence": null,
    "statut_matrimonial": null, "nombre_enfants": null, "niveau_etudes": null,
    "numero_cni": "...", "numero_passeport": null, "nom_pere": "...", "nom_mere": "...",
    "contact_urgence": { "nom": "...", "lien_parente": "...", "telephone": "...", "adresse": null },
    "photo_face_url": "https://...", "photo_profil_url": null,
    "anthropometrie": null, "est_present": true,
    "mandas": [
      { "id": 1, "detenu_id": 1, "type_statut_penal": "Détention provisoire", "date_incarceration": "...", "...": "...", "est_actif": true }
    ],
    "cellule_actuelle": {
      "id": 5, "detenu_id": 1, "cellule": { "id": 3, "numero": "C1", "bloc": "A" },
      "date_affectation": "...", "date_fin": null, "est_active": true, "motif_affectation": null
    },
    "sanctions": [
      { "id": 1, "detenu_id": 1, "type_sanction": { "id": 1, "libelle": "Isolement" }, "motif": "...", "date_faute": "...", "date_debut": "...", "date_fin": null, "statut": "En cours", "est_actif": true, "cellule_disciplinaire": { "id": 4, "numero": "ISO1", "bloc": "ISOLEMENT" }, "cellule_origine": { "id": 3, "numero": "C1", "bloc": "A" } }
    ],
    "suivis_medicaux": [
      { "id": 1, "detenu_id": 1, "date_consultation": "...", "type_consultation": "Consultation générale", "nom_medecin": "Dr Ateba", "...": "..." }
    ],
    "visites": [
      { "id": 1, "detenu_id": 1, "date_visite": "...", "nom_visiteur": "...", "lien_parente": "...", "...": "..." }
    ],
    "created_by": { "id": 1, "nom": "Admin", "...": "..." },
    "updated_by": { "id": 1, "nom": "Admin", "...": "..." },
    "created_at": "...", "updated_at": "..."
  }
}
```
`cellule_actuelle` vaut `null` si le détenu n'est actuellement affecté à aucune cellule (jamais affecté, ou sorti — voir section 10).

`sanctions` liste l'historique complet (actives et terminées), `[]` si aucune. Même forme que `GET /detenus/{id}/sanctions` (section 9.4), sans `created_by`/`updated_by`/`affectation_disciplinaire_active` sur chaque ligne pour ne pas alourdir la fiche — appelez `GET /sanctions/{id}` si vous avez besoin de ce détail sur une sanction précise.

`suivis_medicaux` et `visites` listent l'historique complet, `[]` si aucun — même forme que `GET /detenus/{id}/suivis-medicaux` et `GET /detenus/{id}/visites` (section 11), sans le sous-objet `detenu` sur chaque ligne (redondant ici, vous êtes déjà sur sa fiche).

`id` inexistant → `404`.

---

## 5. Modifier un détenu

```
PUT /detenus/{id}
```
Même corps JSON que la création (tous les champs obligatoires doivent être renvoyés — c'est un remplacement complet, pas un patch partiel).

Pour **changer une photo** : uploadez d'abord la nouvelle via `POST /detenus/photos` (section 2, étape A), puis incluez la nouvelle `photo_face_url`/`photo_face_public_id` dans ce `PUT` — l'ancienne photo est automatiquement supprimée de Cloudinary. Ne rien envoyer pour ces champs conserve la photo actuelle.

### Détenu désactivé → `409`

```json
{ "message": "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{id}/restore avant de le modifier." }
```
Le frontend doit proposer un bouton "Restaurer" (section 7) plutôt que de laisser l'agent remplir un formulaire qui échouera.

---

## 6. Désactiver un détenu

```
DELETE /detenus/{id}
```
Ne supprime rien en base : passe `est_present` à `false`, **et fait de même sur tous ses mandats** (`est_actif=false`). Le détenu disparaît immédiatement de la liste (section 3).

Réponse `200` avec la fiche à jour (`est_present: false`).

---

## 7. Restaurer un détenu (réincarcération / erreur de désactivation)

```
POST /detenus/{id}/restore
```
Repasse `est_present` à `true` et **réactive tous ses mandats** (`est_actif=true`). Réponse `200` avec la fiche complète.

### Quand l'utiliser ?

**Cas 1 — détection automatique lors d'une création.** Si un agent tente de créer un détenu avec un `numero_cni` (ou `numero_passeport`) qui appartient déjà à un détenu **désactivé**, la création échoue avec `409` :
```json
{
  "message": "Un détenu désactivé existe déjà avec ce numéro de CNI. Vous pouvez restaurer son dossier (avec ses mandats) au lieu d'en créer un nouveau.",
  "conflict": {
    "field": "numero_cni",
    "value": "100200300",
    "detenu_id": 12,
    "numero_ecrou": "2024-000045",
    "nom": "Ancien Nom",
    "est_present": false,
    "restore_url": "/api/v1/detenus/12/restore"
  }
}
```
→ Le frontend doit afficher une alerte du type *"Cette personne (100200300) existe déjà, dossier n°2024-000045, actuellement inactif. Restaurer ce dossier au lieu d'en créer un nouveau ?"*, avec un bouton qui appelle `conflict.restore_url`.

Si le CNI/passeport correspond à un détenu **actif**, c'est un vrai doublon (probable erreur de saisie) → `422` classique, pas de proposition de restauration.

**Cas 2 — annuler une désactivation par erreur.** Rien n'empêche d'appeler `restore` directement depuis la fiche d'un détenu désactivé (ex: bouton "Restaurer" visible sur son écran de détail), sans passer par le conflit de création.

---

## 8. Créer un mandat (données d'incarcération/jugement/appel/cassation)

Un détenu peut avoir **plusieurs mandats au fil du temps** (nouvelle affaire, appel des mois plus tard...). Ce n'est **pas** un formulaire "tout en un" : chaque mandat est créé par un appel indépendant, autant de fois que nécessaire pour un même détenu.

```
POST /detenus/{id}/mandas
```

Le champ `type_statut_penal` détermine quels autres champs sont obligatoires :

| `type_statut_penal` | Champs communs | + Jugement | + Appel | + Cassation |
|---|---|---|---|---|
| `Détention provisoire` | ✅ obligatoires | — | — | — |
| `Exécution de peine` | ✅ obligatoires | ✅ obligatoires | — | — |
| `Appellant` | ✅ obligatoires | ✅ obligatoires | ✅ obligatoires | — |
| `Cassationnaire` | ✅ obligatoires | ✅ obligatoires | — | ✅ obligatoires |

**Champs communs** (toujours requis) : `date_incarceration`, `autorite_signataire`, `motif_detention`, `type_mandat`, `reference_mandat`, `date_signature_mandat`, `date_expiration_mandat`.
Optionnels : `objets_personnels`, `autorite_penitentiaire`, `etat_physique_arrivee`, `observations_statut`.

**Champs jugement** (requis si Exécution de peine/Appellant/Cassationnaire) : `date_jugement`, `reference_jugement`, `tribunal_jugement`, `motif_jugement`, `peine_prononcee`.

**Champs appel** (requis si Appellant) : `date_appel`, `tribunal_appel`, `decision_appel`. Optionnel : `observations_appel`.

**Champs cassation** (requis si Cassationnaire) : `date_cassation`, `tribunal_cassation`, `decision_cassation`. Optionnel : `observations_cassation`.

→ **Le frontend doit adapter dynamiquement le formulaire** : afficher/masquer les sections jugement/appel/cassation selon la valeur choisie dans `type_statut_penal`, exactement comme le faisait l'ancienne application C#.

Champ manquant pour le statut choisi → `422` avec le détail par champ.

Détenu désactivé → `409` (même logique que la modification, section 5) : il faut le restaurer avant de pouvoir lui ajouter un mandat.

### 8.1 Consulter, modifier ou désactiver un mandat précis

```
GET    /mandas/{id}
PUT    /mandas/{id}
DELETE /mandas/{id}
```

`GET` retourne le mandat avec l'identité du détenu associé (`data.detenu`).

`PUT` **fait évoluer un mandat existant** plutôt que d'en créer un nouveau — c'est le vrai usage courant : un mandat "Détention provisoire" reçoit un jugement et devient "Exécution de peine" ; un "Exécution de peine" fait l'objet d'un appel et devient "Appellant", etc. Mêmes règles de champs obligatoires que la création (section 8), selon `type_statut_penal`. Modifier un mandat **reclasse automatiquement** le détenu dans `/detenus?categorie_penale=...` (section 3.1) — rien à faire côté frontend pour ça, c'est recalculé à la volée. Bloqué (`409`) si le détenu associé est désactivé.

`DELETE` désactive le mandat (`est_actif=false`) sans jamais le supprimer, et sans toucher au détenu — utile pour corriger un mandat saisi par erreur.

---

## 9. Discipline : cellules, affectations, sanctions

### 9.1 Cellules

```
GET    /cellules?page=1        (paginé, 10/page)
POST   /cellules
GET    /cellules/{id}
PUT    /cellules/{id}
```
Réponse d'une cellule (listing, `GET /cellules`) :
```json
{
  "id": 1, "numero": "C1", "bloc": "A", "type_cellule": "Normale",
  "capacite_max": 3, "effectif_actuel": 2, "places_disponibles": 1
}
```
`effectif_actuel`/`places_disponibles` sont **toujours calculés à la volée**, jamais stockés — donc jamais de dérive possible. `PUT` rejette (`422`) toute réduction de capacité en dessous de l'effectif déjà présent.

Le **détail** d'une cellule (`GET /cellules/{id}`) ajoute la liste des occupants actuels, absente du listing pour ne pas alourdir chaque page :
```json
{
  "id": 1, "numero": "C1", "bloc": "A", "...": "...",
  "occupants": [
    { "detenu_id": 12, "numero_ecrou": "2026-000123", "nom": "Mballa Jean", "date_affectation": "2026-09-01T08:00:00+00:00" }
  ]
}
```

### 9.2 Affecter un détenu à une cellule

```
POST /detenus/{id}/affectations
GET  /detenus/{id}/affectations   (historique du détenu)
GET  /affectations                (fil global, paginé 20/page, plus récent d'abord)
```
```json
{ "cellule_id": 3, "motif_affectation": "Arrivée à l'établissement" }
```
Clôture automatiquement l'affectation active précédente (`date_fin`) au lieu de la supprimer — vrai historique, jamais de perte de trace. Rejette (`422`) si la cellule est déjà pleine ; ce contrôle est fait **avec verrouillage côté serveur**, donc même deux affectations envoyées en même temps ne peuvent pas faire déborder une cellule.

Rejette aussi (`422`, message *"Le détenu est déjà dans cette cellule"*) si le détenu est déjà affecté à cette cellule précise — évite un aller-retour inutile qui clôturerait puis rouvrirait l'affectation (et ferait perdre la vraie date d'arrivée dans l'historique).

`GET /affectations` (sans `{id}`) donne une vue d'ensemble tous détenus confondus — utile pour un fil "mouvements récents" sans ouvrir chaque dossier. Chaque ligne inclut `detenu` (`id`/`numero_ecrou`/`nom`) en plus de `cellule`.

### 9.3 Types de sanction

```
GET  /types-sanction
POST /types-sanction
PUT  /types-sanction/{id}
```
Le type de sanction n'est **plus du texte libre** : c'est une petite table de référence, modifiable sans redéploiement (contrairement à un enum figé dans le code). `GET /types-sanction` retourne tous les types (actifs et désactivés) — filtrez sur `est_actif` côté frontend pour n'afficher que les types utilisables dans un menu déroulant de création. `PUT` désactive un type (`est_actif=false`) au lieu de le supprimer, pour ne pas casser l'historique des sanctions qui l'utilisent déjà.

### 9.4 Sanctions

```
GET    /sanctions                     (liste globale, paginée 10/page)
GET    /detenus/{id}/sanctions        (toutes les sanctions d'un détenu)
POST   /detenus/{id}/sanctions
GET    /sanctions/{id}
PUT    /sanctions/{id}
DELETE /sanctions/{id}
POST   /sanctions/{id}/terminer
```
`type_sanction_id` référence un type actif de `/types-sanction` (un type désactivé est refusé, `422`). `statut` (`"À venir"`/`"En cours"`/`"Terminée"`) est **calculé à la lecture** à partir des dates — jamais stocké.

`GET /sanctions` accepte deux filtres combinables : `?detenu_id=12` et `?est_actif=1` (ou `0`). Chaque ligne inclut `detenu` (`id`/`numero_ecrou`/`nom`), triée par `date_debut` décroissante.

`GET /detenus/{id}/sanctions` retourne l'historique complet d'un détenu (actives et terminées), sans pagination — comme `GET /detenus/{id}/affectations`.

**Point important** : si `cellule_disciplinaire_id` est fourni à la création, le détenu est **réellement déplacé** dans cette cellule (nouvelle affectation créée, ancienne clôturée) — l'occupation des cellules reste toujours exacte. La cellule d'où il venait est mémorisée automatiquement (`cellule_origine` dans la réponse).

Pour **terminer** une sanction en cellule disciplinaire :
```
POST /sanctions/{id}/terminer
```
Libère la cellule disciplinaire et marque la sanction terminée. **Le détenu n'a alors plus de cellule assignée** — rien n'est automatique, c'est volontaire : le frontend doit ensuite appeler `POST /detenus/{id}/affectations` pour le remettre quelque part (la réponse de `terminer` rappelle la cellule d'origine suggérée). Ça évite qu'un retour automatique échoue silencieusement si la cellule d'origine est entre-temps devenue pleine.

`DELETE /sanctions/{id}` désactive juste la fiche (`est_actif=false`, ex: saisie par erreur) — ça ne touche jamais à la cellule disciplinaire ; utilisez `terminer` pour ça.

---

## 10. Sorties : libérations, décès, évasions, transferts

Un détenu peut quitter l'établissement de **4 façons différentes**, chacune avec son propre écran côté frontend et son propre endpoint côté backend — ce sont des événements distincts, pas un simple statut à changer :

```
POST /detenus/{id}/sorties/liberation-normale
POST /detenus/{id}/sorties/deces
POST /detenus/{id}/sorties/transfert
POST /detenus/{id}/sorties/evasion
```

Toutes les 4 créent une ligne dans l'historique des sorties (voir 10.5) et renvoient `201` avec la même forme de réponse (`SortieResource`) :
```json
{
  "data": {
    "id": 1, "detenu_id": 1,
    "mandas_id": null, "mandas": null,
    "type_sortie": "deces",
    "date_sortie": "2026-09-16",
    "motif": null, "destination": null, "cause": "Arrêt cardiaque", "observation": null,
    "sortie_definitive": true,
    "created_by": { "...": "..." }, "updated_by": { "...": "..." },
    "created_at": "...", "updated_at": "..."
  }
}
```

Détenu déjà désactivé (`est_present=false`) → `409`, même logique que pour modifier/sanctionner un détenu (section 5) : il faut d'abord le restaurer (section 7) si c'était une erreur, sinon c'est qu'il a déjà une sortie enregistrée.

### 10.1 Libération normale — attention, c'est liée à un **mandat précis**, pas au détenu

```json
POST /detenus/{id}/sorties/liberation-normale
{ "mandas_id": 12, "date_sortie": "2026-09-16", "motif": "Fin de peine", "observation": "..." }
```
`mandas_id` (obligatoire) : le mandat qui est libéré. Doit appartenir à ce détenu et être encore actif, sinon `422`.

**Pourquoi un mandat et pas juste le détenu ?** Un détenu peut avoir plusieurs mandats actifs en même temps (catégorie DPAC, section 3.1). Libérer un mandat ne fait sortir le détenu **que s'il ne lui reste aucun autre mandat actif après coup** :
- Un seul mandat actif → il est clôturé → le détenu sort réellement (`est_present` passe à `false`, cellule libérée, sanctions actives terminées).
- Plusieurs mandats actifs (DPAC) → seul le mandat visé est clôturé, le détenu **reste incarcéré** sur ses autres mandats (`est_present` ne change pas, rien d'autre n'est touché).

La réponse dit toujours explicitement ce qui s'est passé via `sortie_definitive` (`true`/`false`) — **le frontend doit lire ce champ**, pas juste supposer que "libération = sortie", pour afficher le bon message à l'agent (ex: *"Détenu libéré du dossier REF-2026-001, toujours incarcéré sur 1 autre mandat"* si `false`).

### 10.2 Décès

```json
POST /detenus/{id}/sorties/deces
{ "date_sortie": "2026-09-16", "cause": "Arrêt cardiaque", "observation": "..." }
```
`cause` obligatoire. Toujours une sortie définitive (`sortie_definitive: true`), quel que soit le nombre de mandats en cours : tous les mandats encore actifs du détenu sont clôturés.

### 10.3 Transfert vers un autre établissement

```json
POST /detenus/{id}/sorties/transfert
{ "date_sortie": "2026-09-16", "destination": "Prison Centrale de Yaoundé", "motif": "...", "observation": "..." }
```
`destination` obligatoire (nom de l'établissement d'accueil). Toujours définitif, comme le décès.

### 10.4 Évasion

```json
POST /detenus/{id}/sorties/evasion
{ "date_sortie": "2026-09-16", "cause": "...", "observation": "..." }
```
`cause`/`observation` facultatifs (les circonstances ne sont pas toujours connues au moment de la déclaration). Toujours définitif.

### 10.5 Historique et archive

```
GET /detenus/{id}/sorties          (historique des sorties de CE détenu)
GET /sorties                       (archive globale, tous détenus, paginée 20/page)
GET /sorties?type_sortie=deces     (filtrée par type)
```
Valeurs valides pour `type_sortie` : `liberation_normale`, `deces`, `transfert`, `evasion`. Valeur invalide → `422`.

`GET /detenus/{id}/sorties` a un intérêt même après une sortie définitive : si le détenu est un jour restauré (section 7, réincarcération), l'historique de ses sorties précédentes reste consultable.

### 10.6 Ce que ces endpoints ne sont PAS

`DELETE /detenus/{id}` (section 6) **reste un mécanisme séparé**, réservé à la correction administrative (dossier créé par erreur, doublon) — il n'enregistre aucune sortie dans `/sorties` et ne doit jamais être utilisé pour une vraie sortie de détenu. Les 4 endpoints ci-dessus sont les seuls à utiliser pour un événement réel (libération, décès, évasion, transfert).

---

## 11. Santé et visites

Deux modules distincts côté données, mais avec la même forme d'API que les sanctions/affectations : créer + lister par détenu + lister globalement. Aucune modification/suppression pour l'instant (pas demandé côté frontend).

### 11.1 Suivi médical (consultations à l'infirmerie)

```
GET  /suivis-medicaux                     (liste globale, non paginée, plus récente d'abord)
GET  /detenus/{id}/suivis-medicaux        (historique d'un détenu, pour l'onglet "Santé" du dossier)
POST /detenus/{id}/suivis-medicaux
```
```json
{
  "date_consultation": "2026-09-17",
  "type_consultation": "Consultation générale",
  "nom_medecin": "Dr Ateba",
  "temperature": "37,2",
  "tension_arterielle": "12/8",
  "poids": "70",
  "symptomes": "Toux persistante, fièvre légère",
  "diagnostic": "Bronchite",
  "medicaments_prescrits": "Amoxicilline 500mg",
  "duree_traitement": "7 jours",
  "date_suivi": "2026-09-24"
}
```
Obligatoires : `date_consultation`, `type_consultation`, `nom_medecin`, `symptomes`, `diagnostic`. Le reste est optionnel.

`type_consultation` est restreint à 5 valeurs fixes (reprises de l'ancienne application, pas une table administrable comme `types-sanction`) : `Consultation générale`, `Contrôle`, `Urgence`, `Spécialiste`, `Psychiatrique`. Valeur hors liste → `422`.

`temperature`/`tension_arterielle`/`poids` sont du **texte libre** (pas des nombres) pour accepter le format de saisie habituel (`"37,2"`, `"12/8"`) sans imposer de type numérique strict.

Détenu désactivé → `409`, même logique que pour une sanction (section 9.4) : il faut le restaurer d'abord (section 7).

### 11.2 Visites (parloir)

```
GET  /visites                     (liste globale, non paginée, plus récente d'abord)
GET  /detenus/{id}/visites        (historique des visites reçues par un détenu)
POST /detenus/{id}/visites
```
```json
{
  "date_visite": "2026-09-17",
  "heure_arrivee": "14:30",
  "duree_prevue_minutes": 30,
  "type_visite": "Parloir familial",
  "autorisation_prealable": true,
  "nom_visiteur": "Ateba Paul",
  "sexe_visiteur": "Masculin",
  "type_piece_identite": "Carte nationale d'identité",
  "numero_piece_identite": "100200300",
  "telephone_visiteur": "699999999",
  "lien_parente": "Frère/Sœur",
  "agent_controle": "Agent Mballa",
  "fouille_corporelle": true,
  "objets_deposes": "Téléphone portable"
}
```
Obligatoires : `date_visite`, `heure_arrivee`, `duree_prevue_minutes`, `type_visite`, `autorisation_prealable`, `nom_visiteur`, `sexe_visiteur`, `type_piece_identite`, `numero_piece_identite`, `lien_parente`, `agent_controle`. Optionnels : `telephone_visiteur`, `fouille_corporelle`, `objets_deposes`.

Champs à listes fixes (référentiels de l'ancienne app, `422` si valeur hors liste) :
| Champ | Valeurs acceptées |
|---|---|
| `duree_prevue_minutes` | `15`, `30`, `45`, `60` |
| `type_visite` | `Parloir familial`, `Parloir avocat`, `Salle spécialisée`, `Bureau administratif` |
| `sexe_visiteur` | `Masculin`, `Féminin` |
| `type_piece_identite` | `Carte nationale d'identité`, `Passeport`, `Carte consulaire`, `Attestation d'identité` |
| `lien_parente` | `Père/Mère`, `Époux/Épouse`, `Fils/Fille`, `Frère/Sœur`, `Oncle/Tante`, `Cousin/Cousine`, `Ami(e)`, `Avocat`, `Assistante sociale`, `Représentant consulaire`, `Autorités judiciaires`, `Autre` |

`heure_debut`/`heure_fin`/`observations_visite`/`observations_securite`/`lieu_visite`/`adresse_visiteur` existent dans la réponse mais **ne sont pas dans ce formulaire de création** — ce sont des champs à renseigner après coup (ex: au départ du visiteur), pas encore d'endpoint dédié pour ça.

Détenu désactivé → `409`, même logique que ci-dessus.

---

## 12. Tableau de bord

```
GET /tableau-de-bord
```
Réponse `200`, un seul objet (pas de pagination) :
```json
{
  "data": {
    "genere_le": "2026-09-17T08:12:53+00:00",
    "effectif": 245,
    "capacite_totale": 300,
    "taux_occupation": 81.7,
    "effectif_mois_precedent": 238,
    "visites_aujourdhui": 12,
    "sorties_prevues_mois_prochain": 5,
    "mandats_expires": 3,
    "sanctions_en_cours": 7,
    "mouvements": { "incarcerations": 18, "liberations": 9, "transferements": 2, "evasions": 0, "deces": 1 },
    "effectifs_par_categorie": { "Prevenu": 120, "Condamne": 80, "Appellant": 25, "Cassationnaire": 10, "Dpac": 10 },
    "population_derniers_mois": [
      { "label": "Avr.", "population": 220 },
      { "label": "Mai", "population": 228 },
      { "label": "Juin", "population": 231 },
      { "label": "Juil.", "population": 235 },
      { "label": "Août", "population": 238 },
      { "label": "Sept.", "population": 245 }
    ],
    "liberables_ce_mois": [
      { "numero_ecrou": "2026-000045", "nom": "Mballa Jean", "date_incarceration": "2024-03-10", "date_expiration": "2026-09-28", "statut": "Exécution de peine" }
    ]
  }
}
```

**Point d'attention sur `effectifs_par_categorie`** : les clés (`Prevenu`, `Condamne`, `Appellant`, `Cassationnaire`, `Dpac`) sont dans une **casse différente** de celle utilisée par le filtre `?categorie_penale=` sur `GET /detenus` (`prevenus`, `condamnes`, etc. — section 3.1). C'est fait exprès : ce sont les valeurs exactes attendues par le type frontend `CategoriePenale`, distinctes du slug utilisé en query string.

**Point d'attention sur `effectif_mois_precedent` et `population_derniers_mois`** : aucune table d'historique de la population n'existe en base. Ces deux valeurs sont **reconstituées** à partir de l'effectif actuel (`est_present=true`), en retirant les arrivées et en rajoutant les sorties définitives survenues depuis la date de référence. C'est une approximation cohérente, pas un relevé exact conservé jour par jour — à garder en tête si les chiffres semblent légèrement décalés après une correction manuelle de données anciennes.

`taux_occupation` = cellules occupées (affectations actives) / capacité totale des cellules × 100, arrondi à 1 décimale. Peut dépasser `100` (surpopulation).

`mouvements` couvre les 30 derniers jours. Une libération normale qui ne fait pas réellement sortir le détenu (DPAC, section 10.1) n'y est **pas** comptée — seules les sorties `sortie_definitive=true` sont des mouvements de population.

`liberables_ce_mois` liste tous les mandats actifs dont la date d'expiration tombe ce mois-ci (pas de limite côté API — limitez l'affichage à N lignes côté frontend si besoin).

---

## 13. Récapitulatif des codes d'erreur

| Code | Signification | Action frontend |
|---|---|---|
| `200` / `201` | Succès | — |
| `401` | Token absent/invalide/expiré | Rediriger vers la connexion |
| `404` | Ressource inexistante (mauvais id) | Afficher "introuvable" |
| `409` | Action bloquée par l'état actuel de la ressource (détenu désactivé, ou conflit CNI/passeport avec un détenu désactivé) | Lire `message` (et `conflict` s'il est présent) pour guider l'agent — généralement vers la restauration |
| `422` | Validation échouée | Afficher `errors.<champ>` sous chaque champ concerné |
| `502` | Le service de stockage des photos (Cloudinary) est indisponible | Afficher `message`, proposer de réessayer |

---

## 14. Récapitulatif de tous les endpoints

| Méthode | Route | Protégé | Description |
|---|---|---|---|
| `POST` | `/auth/login` | Non | Connexion (username ou email) |
| `GET` | `/auth/me` | Oui | Utilisateur courant |
| `POST` | `/auth/logout` | Oui | Déconnexion |
| `POST` | `/detenus/photos` | Oui | Upload d'1 ou 2 photos (multipart) |
| `POST` | `/detenus` | Oui | Créer un détenu (JSON) |
| `GET` | `/detenus?page=N` | Oui | Liste paginée des détenus actifs (+ `search`, `categorie_penale`, `sans_cellule`) |
| `GET` | `/detenus/{id}` | Oui | Détail complet (+ mandats, + sanctions, + suivis médicaux, + visites, + cellule actuelle) |
| `PUT` | `/detenus/{id}` | Oui | Modifier (JSON) |
| `DELETE` | `/detenus/{id}` | Oui | Désactiver (soft) |
| `POST` | `/detenus/{id}/restore` | Oui | Restaurer (+ ses mandats) |
| `POST` | `/detenus/{id}/mandas` | Oui | Ajouter un mandat au détenu |
| `GET` | `/mandas/{id}` | Oui | Détail d'un mandat (+ identité du détenu) |
| `PUT` | `/mandas/{id}` | Oui | Faire évoluer un mandat (reclasse le détenu automatiquement) |
| `DELETE` | `/mandas/{id}` | Oui | Désactiver un mandat (soft) |
| `GET` | `/cellules?page=N` | Oui | Liste paginée des cellules |
| `POST` | `/cellules` | Oui | Créer une cellule |
| `GET` | `/cellules/{id}` | Oui | Détail d'une cellule (+ occupants actuels) |
| `PUT` | `/cellules/{id}` | Oui | Modifier une cellule |
| `POST` | `/detenus/{id}/affectations` | Oui | Affecter le détenu à une cellule |
| `GET` | `/detenus/{id}/affectations` | Oui | Historique des affectations du détenu |
| `GET` | `/affectations` | Oui | Fil global des mouvements de cellule, paginé, plus récent d'abord |
| `GET` | `/sanctions` | Oui | Liste globale des sanctions, paginée (+ `detenu_id`, `est_actif`) |
| `GET` | `/detenus/{id}/sanctions` | Oui | Toutes les sanctions d'un détenu |
| `POST` | `/detenus/{id}/sanctions` | Oui | Créer une sanction |
| `GET` | `/sanctions/{id}` | Oui | Détail d'une sanction |
| `PUT` | `/sanctions/{id}` | Oui | Modifier une sanction |
| `DELETE` | `/sanctions/{id}` | Oui | Désactiver une sanction (soft) |
| `POST` | `/sanctions/{id}/terminer` | Oui | Terminer une sanction (libère la cellule disciplinaire) |
| `GET` | `/types-sanction` | Oui | Lister les types de sanction |
| `POST` | `/types-sanction` | Oui | Créer un type de sanction |
| `PUT` | `/types-sanction/{id}` | Oui | Modifier/désactiver un type de sanction |
| `POST` | `/detenus/{id}/sorties/liberation-normale` | Oui | Libérer un mandat précis (sort le détenu seulement si c'était son dernier mandat actif) |
| `POST` | `/detenus/{id}/sorties/deces` | Oui | Enregistrer un décès (sortie définitive) |
| `POST` | `/detenus/{id}/sorties/transfert` | Oui | Enregistrer un transfert vers un autre établissement (sortie définitive) |
| `POST` | `/detenus/{id}/sorties/evasion` | Oui | Enregistrer une évasion (sortie définitive) |
| `GET` | `/detenus/{id}/sorties` | Oui | Historique des sorties du détenu |
| `GET` | `/sorties?type_sortie=...` | Oui | Archive globale des sorties, paginée, filtrable par type |
| `GET` | `/suivis-medicaux` | Oui | Liste globale des consultations médicales |
| `GET` | `/detenus/{id}/suivis-medicaux` | Oui | Historique médical d'un détenu |
| `POST` | `/detenus/{id}/suivis-medicaux` | Oui | Enregistrer une consultation |
| `GET` | `/visites` | Oui | Liste globale des visites |
| `GET` | `/detenus/{id}/visites` | Oui | Historique des visites d'un détenu |
| `POST` | `/detenus/{id}/visites` | Oui | Enregistrer une visite |
| `GET` | `/tableau-de-bord` | Oui | Statistiques agrégées pour l'écran d'accueil |
