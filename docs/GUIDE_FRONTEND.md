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
      "est_present": true
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27, ... }
}
```
Utilisez `meta.last_page`/`meta.total` pour construire les contrôles de pagination, et `page=N` pour naviguer.

C'est une **vue résumée** (colonnes de l'ancienne liste C#) — pour tous les détails d'un détenu, voir section 4.

---

## 4. Consulter le détail d'un détenu

```
GET /detenus/{id}
```
Réponse `200` avec **tous** les champs, les photos, **tous ses mandats**, et qui l'a créé/modifié :
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
    "created_by": { "id": 1, "nom": "Admin", "...": "..." },
    "updated_by": { "id": 1, "nom": "Admin", "...": "..." },
    "created_at": "...", "updated_at": "..."
  }
}
```
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

---

## 9. Récapitulatif des codes d'erreur

| Code | Signification | Action frontend |
|---|---|---|
| `200` / `201` | Succès | — |
| `401` | Token absent/invalide/expiré | Rediriger vers la connexion |
| `404` | Ressource inexistante (mauvais id) | Afficher "introuvable" |
| `409` | Action bloquée par l'état actuel de la ressource (détenu désactivé, ou conflit CNI/passeport avec un détenu désactivé) | Lire `message` (et `conflict` s'il est présent) pour guider l'agent — généralement vers la restauration |
| `422` | Validation échouée | Afficher `errors.<champ>` sous chaque champ concerné |
| `502` | Le service de stockage des photos (Cloudinary) est indisponible | Afficher `message`, proposer de réessayer |

---

## 10. Récapitulatif de tous les endpoints

| Méthode | Route | Protégé | Description |
|---|---|---|---|
| `POST` | `/auth/login` | Non | Connexion (username ou email) |
| `GET` | `/auth/me` | Oui | Utilisateur courant |
| `POST` | `/auth/logout` | Oui | Déconnexion |
| `POST` | `/detenus/photos` | Oui | Upload d'1 ou 2 photos (multipart) |
| `POST` | `/detenus` | Oui | Créer un détenu (JSON) |
| `GET` | `/detenus?page=N` | Oui | Liste paginée des détenus actifs |
| `GET` | `/detenus/{id}` | Oui | Détail complet (+ mandats) |
| `PUT` | `/detenus/{id}` | Oui | Modifier (JSON) |
| `DELETE` | `/detenus/{id}` | Oui | Désactiver (soft) |
| `POST` | `/detenus/{id}/restore` | Oui | Restaurer (+ ses mandats) |
| `POST` | `/detenus/{id}/mandas` | Oui | Ajouter un mandat au détenu |
