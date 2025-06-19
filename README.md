# 🚗 Application Gestion Parking

Un système complet de gestion de parking développé en PHP pur avec une interface moderne et responsive.

![ParkFinder](https://img.shields.io/badge/PHP-8.2+-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange)
![License](https://img.shields.io/badge/License-MIT-green)

## 🌟 Fonctionnalités

- ✅ **Interface moderne** : Design responsive et interface utilisateur intuitive
- ✅ **Gestion des utilisateurs** : Système d'authentification avec rôles (admin/utilisateur)
- ✅ **Réservation en temps réel** : Consultation et réservation de places de parking
- ✅ **Paiement sécurisé** : Intégration PayPal en mode sandbox pour les tests
- ✅ **Tableau de bord** : Interface d'administration et utilisateur
- ✅ **Responsive** : Compatible mobile, tablette et desktop
- 🔄 **En développement** : Notifications, rapports avancés

## 🛠️ Technologies Utilisées

- **Backend** : PHP 8.2+ (pur, sans framework)
- **Base de données** : MySQL 8.0+
- **Frontend** : HTML5, CSS3, JavaScript (Vanilla)
- **Paiements** : PayPal SDK (mode sandbox)
- **Serveur** : Apache avec mod_rewrite
- **Dépendances** : Composer (vlucas/phpdotenv)

## 📋 Prérequis

- PHP 8.2 ou supérieur
- MySQL 8.0 ou supérieur
- Apache avec mod_rewrite activé
- Composer
- Extensions PHP : PDO, PDO_MySQL, JSON, Session

## 🚀 Installation

### 1. Cloner le projet

```bash
git clone https://github.com/DarckWax/app-gestion-parking.git
cd app-gestion-parking
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration de l'environnement

```bash
# Copier le fichier d'exemple
cp .env.example .env

# Éditer le fichier .env avec vos paramètres
```

### 4. Configuration de la base de données

```bash
# Créer la base de données MySQL
mysql -u root -p -e "CREATE DATABASE parking_management_system;"

# Importer le schéma
mysql -u root -p parking_management_system < database/parking_db.sql
```

### 5. Configuration du serveur web

Pointez votre serveur web vers le dossier `public/` du projet.

#### Apache Virtual Host (recommandé)

```apache
<VirtualHost *:80>
    ServerName parking.local
    DocumentRoot "C:/Web_FullStack/www/ParkFinder/public"
    
    <Directory "C:/Web_FullStack/www/ParkFinder/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Ou accès direct

```
http://localhost/ParkFinder/public/
```

## 🎯 Utilisation

### Accès administrateur

- **URL** : `http://localhost/ParkFinder/public/`
- **Email** : `admin@parkingsystem.com`
- **Mot de passe** : `admin123`

### Fonctionnalités principales

1. **Connexion utilisateur** : Système d'authentification sécurisé
2. **Tableau de bord** : Vue d'ensemble des réservations et statistiques
3. **Réservation** : Interface de réservation de places en temps réel
4. **Paiement** : Intégration PayPal sandbox pour les tests
5. **Administration** : Gestion des utilisateurs, places et réservations

## 📁 Structure du projet

```
app-gestion-parking/
├── database/                 # Scripts SQL
│   └── parking_db.sql       # Schéma de base de données
├── public/                  # Point d'entrée web
│   ├── assets/             # Ressources statiques (CSS, JS, images)
│   ├── index.php          # Fichier principal
│   ├── start.php          # Tests système
│   └── create-db.php      # Création automatique de la DB
├── .env.example           # Configuration d'exemple
├── .gitignore            # Fichiers ignorés par Git
├── composer.json         # Dépendances PHP
└── README.md            # Documentation
```

## 🔧 Configuration

### Variables d'environnement (.env)

```env
# Base de données
DB_HOST=localhost
DB_NAME=parking_management_system
DB_USER=root
DB_PASS=

# Application
APP_NAME="Parking Management System"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/ParkFinder

# PayPal (mode sandbox)
PAYPAL_CLIENT_ID=your_sandbox_client_id
PAYPAL_MODE=sandbox
```

## 🧪 Tests et Debug

### Test de l'installation

```bash
# Vérifier que PHP fonctionne
http://localhost/ParkFinder/public/test-minimal.php

# Tests système
http://localhost/ParkFinder/public/start.php

# Créer la base de données
http://localhost/ParkFinder/public/create-db.php

# Tester l'application
http://localhost/ParkFinder/public/
```

### Comptes de test

- **Admin** : admin@parkingsystem.com / admin123
- **PayPal Sandbox** : Utilisez les comptes de test PayPal

## 🐛 Dépannage

### Erreur 500 Internal Server Error

1. Vérifiez les logs Apache : `C:\wamp64\logs\apache_error.log`
2. Désactivez temporairement `.htaccess`
3. Vérifiez les permissions des dossiers
4. Assurez-vous que mod_rewrite est activé

### Problème de base de données

1. Vérifiez que MySQL est démarré
2. Vérifiez les identifiants dans `.env`
3. Utilisez le script automatique : `public/create-db.php`

### Composer

```bash
# Réinstaller les dépendances
rm -rf vendor/
composer install
```

## 📝 Changelog

### Version 1.0.0 (Actuelle)
- ✅ Interface d'accueil moderne
- ✅ Système d'authentification
- ✅ Base de données complète
- ✅ Intégration PayPal sandbox
- ✅ Design responsive

### À venir
- 🔄 Système de notifications
- 🔄 Rapports et statistiques avancés
- 🔄 API REST
- 🔄 Application mobile

## 🤝 Contribution

1. Fork le projet
2. Créez une branche feature (`git checkout -b feature/NouvelleFonctionnalite`)
3. Commitez vos changements (`git commit -m 'Ajout nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/NouvelleFonctionnalite`)
5. Ouvrez une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 👨‍💻 Auteur

**DarckWax** - [GitHub](https://github.com/DarckWax)

## 🙏 Remerciements

- Design inspiré par les meilleures pratiques UX/UI
- Intégration PayPal pour les paiements sécurisés
- Architecture simple et maintenable

---

## 📸 Screenshots

### Interface d'accueil
![Accueil](https://via.placeholder.com/800x400/667eea/ffffff?text=Interface+d%27accueil)

### Tableau de bord
![Dashboard](https://via.placeholder.com/800x400/3498db/ffffff?text=Tableau+de+bord)

### Réservation
![Reservation](https://via.placeholder.com/800x400/27ae60/ffffff?text=Système+de+réservation)

---

## 🔗 Liens utiles

- [Repository GitHub](https://github.com/DarckWax/app-gestion-parking)
- [Documentation PHP](https://www.php.net/docs.php)
- [PayPal Developer](https://developer.paypal.com/)
- [Composer](https://getcomposer.org/)

---

*Développé avec ❤️ pour simplifier la gestion de parking*
