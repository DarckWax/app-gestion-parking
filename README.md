# ParkFinder

Système de gestion de parking intelligent et moderne développé en PHP avec une architecture MVC.

## Description

ParkFinder est une application web complète qui permet la gestion efficace des places de parking, des réservations et des paiements. L'application offre une interface utilisateur intuitive et un panneau d'administration complet pour la gestion des opérations.

## Fonctionnalités

### Pour les utilisateurs
- Inscription et connexion sécurisées
- Consultation des places disponibles en temps réel
- Système de réservation intuitive
- Paiement en ligne sécurisé (PayPal, Carte bancaire)
- Gestion des réservations personnelles
- Interface responsive adaptée à tous les appareils

### Pour les administrateurs
- Tableau de bord complet avec statistiques
- Gestion des utilisateurs
- Gestion des places de parking
- Suivi des réservations
- Rapports et analyses détaillés
- Système de paiements avancé

## Technologies utilisées

- **Backend**: PHP 8+ avec architecture MVC
- **Base de données**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Sécurité**: Hachage des mots de passe, Protection CSRF, Validation des données
- **Paiements**: Intégration PayPal, Simulation de paiement par carte

## Prérequis

- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache/Nginx)
- Extension PHP: PDO, MySQLi, cURL

## Installation

### 1. Cloner le projet
```bash
git clone https://github.com/votre-username/parkfinder.git
cd parkfinder
```

### 2. Configuration de la base de données
1. Créer une base de données MySQL nommée `parking_management_system`
2. Importer le fichier SQL ou utiliser le script de création automatique
3. Configurer les paramètres de connexion dans `public/index.php`

### 3. Configuration du serveur web
- Pointer le DocumentRoot vers le dossier `public/`
- Configurer les règles de réécriture si nécessaire

### 4. Installation automatique
Accéder à `http://votre-domaine/create-db.php` pour créer automatiquement la structure de base de données avec des données de test.

## Structure du projet

```
parkfinder/
├── app/
│   ├── controllers/        # Contrôleurs MVC
│   ├── models/            # Modèles de données
│   ├── views/             # Vues (interfaces)
│   ├── core/              # Classes principales du framework
│   ├── middlewares/       # Middlewares de sécurité
│   └── utils/             # Utilitaires et helpers
├── public/
│   ├── assets/            # Ressources statiques (CSS, JS, images)
│   ├── admin-*.php        # Pages d'administration
│   ├── index.php          # Page d'accueil
│   └── create-db.php      # Script d'installation
├── config/
│   └── database.sql       # Structure de base de données
└── README.md
```

## Configuration

### Base de données
Modifier les paramètres de connexion dans `public/index.php`:
```php
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system',
    'db_user' => 'root',
    'db_pass' => ''
];
```

### PayPal (optionnel)
Pour activer les paiements PayPal en production:
1. Créer un compte développeur PayPal
2. Configurer les clés API dans les variables d'environnement
3. Modifier le mode de 'sandbox' à 'live'

## Comptes de test

Après installation, les comptes suivants sont disponibles:

**Administrateur:**
- Email: admin@parkingsystem.com
- Mot de passe: admin123

**Utilisateur standard:**
- Email: marie.martin@email.com
- Mot de passe: admin123

## Sécurité

L'application implémente plusieurs mesures de sécurité:
- Hachage sécurisé des mots de passe avec `password_hash()`
- Protection contre les injections SQL via PDO
- Validation et nettoyage des données d'entrée
- Protection CSRF pour les formulaires sensibles
- Sessions sécurisées avec cookies httpOnly

## API et endpoints

### Pages principales
- `/` - Page d'accueil et connexion
- `/admin-dashboard.php` - Tableau de bord administrateur
- `/admin-users.php` - Gestion des utilisateurs
- `/admin-spots.php` - Gestion des places
- `/admin-reservations.php` - Gestion des réservations

### API AJAX
- `/search-users.php` - Recherche d'utilisateurs
- `/search-spots.php` - Recherche de places
- Endpoints de paiement pour PayPal et cartes

## Développement

### Ajouter une nouvelle fonctionnalité
1. Créer le contrôleur dans `app/controllers/`
2. Créer le modèle dans `app/models/`
3. Créer la vue dans `app/views/` ou `public/`
4. Ajouter les routes nécessaires

### Standards de code
- Utiliser PSR-4 pour l'autoloading
- Respecter les conventions de nommage PHP
- Commenter le code complexe
- Valider toutes les entrées utilisateur

## Déploiement

### Environnement de production
1. Configurer HTTPS obligatoire
2. Désactiver l'affichage des erreurs PHP
3. Configurer les logs d'erreur
4. Optimiser la base de données
5. Configurer les sauvegardes automatiques

### Variables d'environnement
Créer un fichier `.env` pour la production:
```
DB_HOST=localhost
DB_NAME=parking_management_system
DB_USER=username
DB_PASS=password
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
```

## Support et maintenance

### Logs
Les logs d'erreur sont stockés dans les logs du serveur web. Pour le débogage, activer les logs détaillés dans l'application.

### Base de données
Effectuer des sauvegardes régulières de la base de données MySQL.

### Mises à jour
Vérifier régulièrement les mises à jour de sécurité PHP et MySQL.

## Contribution

1. Fork le projet
2. Créer une branche pour votre fonctionnalité
3. Committer vos changements
4. Pousser vers la branche
5. Ouvrir une Pull Request

## Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## Contact

Pour toute question ou support technique, contactez l'équipe de développement.

---

**Version:** 1.0.0  
**Dernière mise à jour:** 2024