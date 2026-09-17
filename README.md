# Nagios Live Dashboard

Dashboard web léger pour **Nagios Core**, pensé pour afficher en temps réel l'état de l'infrastructure à partir des résultats retournés par Nagios.

L'objectif est volontairement simple : **pas de base de données, pas d'historique et pas de dépendance lourde**. Le dashboard lit les données de Nagios et les présente sous forme de cartes et d'onglets.

## ✨ Fonctionnalités

- Affichage en temps réel des hôtes et services Nagios
- Onglets personnalisables : Serveurs, ESX, Réseau, etc.
- Affichage sur **4 colonnes** sur grand écran
- Interface responsive pour tablette et mobile
- Jauges CPU / RAM lorsque les données sont disponibles
- Barres de stockage / datastore lorsque les données sont disponibles
- Affichage des services et de leur état (`OK`, `WARNING`, `CRITICAL`, `UNKNOWN`)
- Rafraîchissement automatique configurable
- Recherche d'un hôte
- Filtrage par état
- Les données absentes ne sont simplement **pas affichées**

## 🧩 Fonctionnement

Le dashboard est composé de deux parties principales :

```text
Nagios Core
    │
    └── status.dat
          │
          ▼
       api.php
          │
          ▼
        JSON
          │
          ▼
       app.js
          │
          ▼
    Interface Web
```

### `api.php`

Le fichier `api.php` lit le fichier `status.dat` de Nagios et récupère notamment :

- les hôtes
- leur état
- les services
- leur état
- le `plugin_output`
- les informations permettant d'extraire CPU, RAM et stockage

Les métriques affichées par le dashboard sont donc basées sur les **sorties des plugins Nagios**.

Le dashboard n'utilise pas de système d'historisation.

### `groups.php`

C'est le fichier à modifier pour personnaliser l'organisation du dashboard.

Exemple :

```php
return [
    'serveurs' => [
        'label' => 'Serveurs',
        'icon' => '🖥️',
        'hostgroups' => ['windows-servers'],
        'hosts' => [],
    ],

    'esx' => [
        'label' => 'ESX',
        'icon' => '🟦',
        'hostgroups' => ['esxi'],
        'hosts' => [],
    ],

    'reseau' => [
        'label' => 'Réseau',
        'icon' => '🌐',
        'hostgroups' => ['cisco-2960x'],
        'hosts' => [],
    ],
];
```

### Personnaliser les onglets

Pour ajouter un onglet, il suffit d'ajouter un bloc dans `groups.php`.

Les hôtes peuvent être sélectionnés à partir d'un **hostgroup Nagios** :

```php
'hostgroups' => ['windows-servers'],
```

ou directement par leur nom :

```php
'hosts' => [
    'SRV-APPLIS',
    'SRV-DC01',
],
```

Un hôte peut apparaître dans plusieurs onglets.

Cela permet de modifier l'organisation du dashboard **sans modifier le PHP ou le JavaScript**.

## 📁 Structure

```text
.
├── index.php       # Interface principale
├── api.php         # API locale qui lit Nagios
├── app.js          # Logique et affichage dynamique
├── style.css       # Mise en forme
├── config.php      # Configuration générale
└── groups.php      # Définition des onglets et des hôtes
```

## ⚙️ Installation

Le dashboard peut être placé dans le répertoire web du serveur.

Exemple avec Apache :

```bash
cp -a nagios-live /var/www/html/
```

Adapter ensuite les droits afin que le serveur web puisse lire le `status.dat` de Nagios.

Par exemple, avec ACL :

```bash
setfacl -m u:www-data:rx /usr/local/nagios/var
setfacl -m u:www-data:r /usr/local/nagios/var/status.dat
```

Si `api.php` doit également lire les fichiers de configuration Nagios pour déterminer les hostgroups :

```bash
setfacl -m u:www-data:rx /usr/local/nagios/etc
setfacl -m u:www-data:rx /usr/local/nagios/etc/objects
setfacl -m u:www-data:r /usr/local/nagios/etc/objects/*.cfg
```

## 🔎 Tester l'API

Avant d'ouvrir le dashboard, il est pratique de vérifier que l'API répond :

```bash
curl -s http://localhost/nagios-live/api.php | python3 -m json.tool
```

Si le JSON est correctement retourné, le dashboard peut normalement l'utiliser.

## 🔄 Rafraîchissement

Le dashboard interroge régulièrement `api.php`.

La fréquence de rafraîchissement peut être modifiée depuis l'interface.

Aucune donnée historique n'est enregistrée par le dashboard.

## 🔐 Sécurité

Le dashboard est prévu pour être utilisé sur une infrastructure Nagios interne.

`status.dat` et les fichiers de configuration Nagios peuvent contenir des informations sensibles. Il est donc recommandé de :

- protéger l'accès au dashboard avec Apache/Nginx
- ne pas exposer `api.php` directement sur Internet
- conserver les fichiers de configuration Nagios avec des permissions restrictives
- utiliser HTTPS si le dashboard est accessible depuis un réseau non maîtrisé

## 🛠️ Philosophie du projet

Le projet privilégie volontairement une architecture simple :

- Nagios reste la source de vérité
- les plugins Nagios font les checks
- le dashboard ne fait que lire et présenter les résultats
- aucune base de données
- aucun système de métriques supplémentaire obligatoire
- configuration des groupes séparée du code

L'idée est de pouvoir ajouter ou réorganiser des éléments du dashboard rapidement sans devoir modifier toute l'application.

---

## 📜 Licence

À adapter selon la licence choisie pour le projet.
