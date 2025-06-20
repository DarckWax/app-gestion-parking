# Guide d'utilisation - ParkFinder

Guide complet pour utiliser le système de gestion de parking ParkFinder.

## Table des matières

1. [Première connexion](#première-connexion)
2. [Interface utilisateur](#interface-utilisateur)
3. [Interface administrateur](#interface-administrateur)
4. [Gestion des réservations](#gestion-des-réservations)
5. [Système de paiement](#système-de-paiement)
6. [Dépannage](#dépannage)

## Première connexion

### Accès à l'application
1. Ouvrir un navigateur web
2. Naviguer vers l'URL de l'application
3. Vous arrivez sur la page d'accueil avec les statistiques en temps réel

### Création d'un compte utilisateur
1. Cliquer sur "Créer un compte" dans la section connexion
2. Remplir le formulaire d'inscription :
   - Prénom (minimum 2 caractères)
   - Nom (minimum 2 caractères)
   - Adresse email valide
   - Numéro de téléphone (minimum 10 caractères)
   - Mot de passe (minimum 6 caractères)
   - Confirmation du mot de passe
3. Cliquer sur "Créer mon compte"
4. En cas de succès, retourner à l'accueil pour se connecter

### Connexion avec un compte existant
1. Dans la section "Connexion", saisir :
   - Adresse email
   - Mot de passe
2. Cliquer sur "Se connecter"
3. Redirection automatique selon votre rôle

### Comptes de test disponibles
**Administrateur :**
- Email : admin@parkingsystem.com
- Mot de passe : admin123

**Utilisateur standard :**
- Email : marie.martin@email.com
- Mot de passe : admin123

-Admin : Alexandre Martin

Email: admin@parkfinder.com
Mot de passe: admin123

-Utilisateurs :

Marie Dubois (marie.dubois@email.com / user123)

## Interface utilisateur

### Navigation principale
Une fois connecté en tant qu'utilisateur, vous avez accès à :
- **Mes réservations** : Gestion de vos réservations personnelles
- **Réserver une place** : Créer une nouvelle réservation
- **Déconnexion** : Fermer votre session

### Page d'accueil utilisateur
L'accueil affiche :
- Statistiques en temps réel des places de parking
- Boutons d'accès rapide aux fonctionnalités principales
- Informations sur les services disponibles

### Gestion des réservations utilisateur
1. Accéder à "Mes réservations"
2. Consulter la liste de vos réservations avec :
   - Code de réservation
   - Dates et heures
   - Place assignée
   - Statut de la réservation
   - Statut du paiement
   - Montant

## Interface administrateur

### Navigation administrateur
En tant qu'administrateur, vous avez accès à :
- **Tableau de bord** : Vue d'ensemble des statistiques
- **Utilisateurs** : Gestion des comptes utilisateurs
- **Places** : Gestion des places de parking
- **Réservations** : Gestion de toutes les réservations
- **Rapports** : Analyses et rapports détaillés

### Tableau de bord administrateur
Le tableau de bord présente :
- Statistiques globales en temps réel
- Graphiques de performance
- Indicateurs clés (revenus, taux d'occupation, etc.)
- Raccourcis vers les sections principales

### Gestion des utilisateurs
1. Accéder à "Utilisateurs"
2. Fonctionnalités disponibles :
   - **Voir** : Consulter la liste complète des utilisateurs
   - **Rechercher** : Filtrer par nom, email, rôle
   - **Créer** : Ajouter un nouvel utilisateur
   - **Modifier** : Éditer les informations d'un utilisateur
   - **Supprimer** : Retirer un utilisateur du système

#### Créer un utilisateur
1. Cliquer sur "Nouvel utilisateur"
2. Remplir le formulaire :
   - Informations personnelles
   - Coordonnées
   - Rôle (customer/admin)
   - Mot de passe
3. Valider la création

#### Modifier un utilisateur
1. Cliquer sur l'icône de modification
2. Modifier les champs souhaités
3. Sauvegarder les modifications

### Gestion des places de parking
1. Accéder à "Places"
2. Fonctionnalités disponibles :
   - **Voir** : Liste de toutes les places
   - **Filtrer** : Par type, statut, zone
   - **Créer** : Ajouter une nouvelle place
   - **Modifier** : Éditer une place existante
   - **Activer/Désactiver** : Changer le statut d'une place

#### Types de places
- **Standard** : Place normale
- **Handicapé** : Place réservée PMR
- **Électrique** : Place avec borne de recharge
- **Compact** : Place pour petits véhicules
- **Réservée** : Place à usage spécial

#### Statuts de places
- **Disponible** : Place libre
- **Occupée** : Place prise
- **Maintenance** : Place hors service
- **Réservée** : Place réservée

#### Créer une place
1. Cliquer sur "Nouvelle place"
2. Remplir les informations :
   - Numéro de place
   - Zone/Étage
   - Type de place
   - Prix par heure
   - Description
3. Définir si la place est active
4. Sauvegarder

### Gestion des réservations
1. Accéder à "Réservations"
2. Vue d'ensemble avec :
   - Statistiques des réservations
   - Filtres avancés
   - Liste complète des réservations

#### Filtres disponibles
- **Statut** : En attente, confirmée, active, terminée, annulée
- **Paiement** : En attente, payé, remboursé, échoué
- **Dates** : Période de début et fin
- **Recherche** : Par code, email, nom, plaque

#### Actions sur les réservations
- **Voir** : Consulter les détails complets
- **Modifier** : Changer le statut ou ajouter des notes
- **Supprimer** : Supprimer une réservation annulée

#### Créer une réservation
1. Cliquer sur "Nouvelle réservation"
2. Rechercher et sélectionner un utilisateur
3. Rechercher et sélectionner une place disponible
4. Définir les dates et heures
5. Le prix est calculé automatiquement
6. Sauvegarder la réservation

## Gestion des réservations

### Processus de réservation standard
1. **Sélection** : Choisir une place disponible
2. **Dates** : Définir début et fin de réservation
3. **Vérification** : Contrôler les informations et le prix
4. **Paiement** : Procéder au paiement sécurisé
5. **Confirmation** : Recevoir la confirmation de réservation

### Statuts des réservations
- **En attente** : Réservation créée, en attente de paiement
- **Confirmée** : Paiement effectué, réservation validée
- **Active** : Période de réservation en cours
- **Terminée** : Réservation achevée normalement
- **Annulée** : Réservation annulée par l'utilisateur ou l'admin
- **No-show** : Utilisateur ne s'est pas présenté

### Modification d'une réservation
1. Accéder aux détails de la réservation
2. Les modifications possibles dépendent du statut :
   - **En attente** : Toutes modifications possibles
   - **Confirmée** : Modifications limitées
   - **Active/Terminée** : Modifications restreintes aux notes

### Annulation d'une réservation
1. Sélectionner la réservation à annuler
2. Choisir le motif d'annulation
3. Confirmer l'annulation
4. Le remboursement est traité selon la politique

## Système de paiement

### Méthodes de paiement acceptées
- **PayPal** : Paiement via compte PayPal
- **Carte bancaire** : Simulation de paiement par carte

### Processus de paiement PayPal
1. Sélectionner "PayPal" comme méthode
2. Redirection vers l'interface PayPal sécurisée
3. Connexion à votre compte PayPal
4. Confirmer le paiement
5. Retour automatique vers l'application

### Processus de paiement par carte
1. Sélectionner "Carte bancaire"
2. Saisir les informations de carte :
   - Numéro de carte
   - Date d'expiration
   - Code CVV
   - Nom du porteur
3. Valider le paiement
4. Confirmation immédiate

### Statuts de paiement
- **En attente** : Paiement non effectué
- **Payé** : Paiement réussi
- **Échoué** : Paiement refusé
- **Remboursé** : Paiement remboursé

### Gestion des remboursements
Les remboursements sont traités automatiquement lors de l'annulation d'une réservation confirmée.

## Dépannage

### Problèmes de connexion
**Erreur "Identifiants incorrects"**
- Vérifier l'adresse email saisie
- Vérifier le mot de passe (sensible à la casse)
- Utiliser les comptes de test si nécessaire

**Erreur de base de données**
- Vérifier que MySQL est démarré
- Contrôler les paramètres de connexion
- Exécuter le script de création de base si nécessaire

### Problèmes de réservation
**Aucune place disponible**
- Modifier les dates de recherche
- Vérifier les filtres appliqués
- Contacter l'administrateur

**Erreur de paiement**
- Vérifier les informations de paiement
- Essayer une autre méthode de paiement
- Contacter le support technique

### Problèmes d'affichage
**Interface mal affichée**
- Vider le cache du navigateur (Ctrl+F5)
- Vérifier la compatibilité du navigateur
- Désactiver les extensions de navigateur

**Données non mises à jour**
- Actualiser la page
- Vérifier la connexion internet
- Redémarrer le navigateur

### Messages d'erreur communs
**"Session expirée"**
- Se reconnecter à l'application
- Vérifier les paramètres de cookies

**"Accès refusé"**
- Vérifier vos permissions
- Contacter un administrateur
- Se reconnecter avec le bon compte

**"Erreur technique"**
- Réessayer l'opération
- Signaler le problème à l'administrateur
- Vérifier les logs d'erreur

### Contact support
En cas de problème persistant :
1. Noter l'erreur exacte affichée
2. Noter les étapes qui ont mené à l'erreur
3. Contacter l'équipe technique
4. Fournir les détails de votre navigateur et système

---

**Version du guide :** 1.0  
**Dernière mise à jour :** 2024