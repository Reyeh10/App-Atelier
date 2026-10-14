<?php

namespace App\Services;

class PermissionService
{
    public static array $groups = [
        'Clients' => [
            'voir_clients'            => 'Voir les clients',
            'gerer_clients'           => 'Créer / modifier des clients Particulier',
            'gerer_clients_societe'   => 'Créer / modifier des clients Société',
            'gerer_clients_assurance' => 'Créer / modifier des clients Assurance',
            'gerer_compte_credit'     => 'Activer le compte crédit, définir le plafond, accorder/révoquer crédit sur facture',
        ],
        'Véhicules' => [
            'voir_vehicules'  => 'Voir les véhicules',
            'gerer_vehicules' => 'Créer / modifier les véhicules',
        ],
        'Ordres de réparation' => [
            'voir_ordres'        => 'Voir les ordres de réparation',
            'creer_ordres'       => 'Créer une nouvelle réception (OR) — formulaire garantie',
            'gerer_ordres'       => 'Modifier les OR, changer le statut, gérer la garantie',
            'affecter_technicien'=> 'Affecter un technicien à un OR',
            'valider_qualite'    => 'Valider le contrôle qualité',
            'valider_lavage'     => 'Valider le lavage du véhicule',
            'restituer_vehicule' => 'Restituer le véhicule au client',
        ],
        'Techniciens' => [
            'voir_techniciens'  => 'Voir la liste des techniciens',
            'gerer_techniciens' => 'Créer / modifier / désactiver / importer des techniciens',
        ],
        'Réception' => [
            'voir_dossiers'      => 'Voir les dossiers de réception (Panne / Accident / Service Rapide)',
            'creer_dossiers'     => 'Créer un dossier de réception',
            'supprimer_dossiers' => 'Supprimer un dossier de réception (bloqué si un OR a déjà été créé à partir de lui)',
            'voir_reservations'  => 'Voir les réservations (RDV Service Rapide)',
            'gerer_reservations' => 'Prendre, honorer ou annuler une réservation',
        ],
        'Dépannage' => [
            'voir_depannages'  => 'Voir les dépannages',
            'creer_depannages' => 'Créer un dépannage (pièces + main-d\'œuvre sans réception ni devis)',
        ],
        'Devis' => [
            'voir_devis'    => 'Voir les devis',
            'gerer_devis'   => 'Créer / modifier / supprimer les devis',
            'valider_devis' => 'Valider au client : marquer envoyé / accepté / refusé, uploader le devis signé',
        ],
        'Factures' => [
            'voir_factures'      => 'Voir les factures',
            'creer_factures'     => 'Créer une facture depuis un OR',
            'encaisser_factures' => 'Enregistrer les paiements (encaisser les factures)',
        ],
        'Bons de commande' => [
            'voir_bons_commande'  => 'Voir le suivi des bons de commande pièces (disponibilité fournisseur)',
            'gerer_bons_commande' => 'Marquer les bons de commande comme reçus (pièces arrivées au garage)',
        ],
        'Encaissements globaux' => [
            'voir_encaissements'  => 'Voir les encaissements globaux (comptes clients)',
            'gerer_encaissements' => 'Créer et valider les encaissements globaux',
        ],
        'Garantie' => [
            'voir_garanties'    => 'Voir les demandes de garantie',
            'traiter_garanties' => 'Approuver ou refuser les demandes de garantie',
        ],
        'Rapports' => [
            'voir_rapports'  => 'Voir les rapports et statistiques',
            'voir_activites' => 'Voir le journal d\'activités',
        ],
        'Utilisateurs' => [
            'voir_utilisateurs'  => 'Voir la liste des utilisateurs',
            'gerer_utilisateurs' => 'Créer / modifier / supprimer les utilisateurs et leurs permissions',
        ],
        'Paramètres atelier' => [
            'gerer_parametres_atelier' => 'Modifier les horaires, pauses et capacité Service Rapide de l\'atelier',
        ],
    ];

    public static array $roleDefaults = [
        'chef_garage' => [
            'voir_clients', 'gerer_clients', 'gerer_clients_societe', 'gerer_clients_assurance', 'gerer_compte_credit',
            'voir_vehicules', 'gerer_vehicules',
            'voir_ordres', 'creer_ordres', 'gerer_ordres', 'affecter_technicien', 'valider_qualite', 'valider_lavage', 'restituer_vehicule',
            'voir_techniciens', 'gerer_techniciens',
            'voir_dossiers', 'creer_dossiers', 'supprimer_dossiers', 'voir_reservations', 'gerer_reservations',
            'voir_depannages',
            'voir_devis', 'gerer_devis',
            'voir_factures', 'creer_factures', 'encaisser_factures',
            'voir_bons_commande', 'gerer_bons_commande',
            'voir_encaissements',
            'voir_garanties', 'traiter_garanties',
            'voir_rapports', 'voir_activites',
            'voir_utilisateurs',
            'gerer_parametres_atelier',
        ],
        'responsable_garantie' => [
            'voir_clients',
            'voir_vehicules',
            'voir_ordres',
            'voir_garanties', 'traiter_garanties',
        ],
        'receptionniste' => [
            'voir_clients', 'gerer_clients', 'gerer_clients_societe', 'gerer_clients_assurance',
            'voir_vehicules', 'gerer_vehicules',
            'voir_ordres', 'creer_ordres', 'restituer_vehicule',
            'voir_dossiers', 'creer_dossiers', 'voir_reservations', 'gerer_reservations',
            'voir_depannages', 'creer_depannages',
            'voir_devis',
        ],
        'caissier' => [
            'voir_clients', 'gerer_compte_credit',
            'voir_ordres', 'voir_depannages',
            'voir_factures', 'creer_factures', 'encaisser_factures',
            'voir_encaissements', 'gerer_encaissements',
        ],
    ];

    public static function all(): array
    {
        return array_merge(...array_values(static::$groups));
    }

    public static function defaultsForRole(string $role): array
    {
        return static::$roleDefaults[$role] ?? [];
    }
}
