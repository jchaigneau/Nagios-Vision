<?php
declare(strict_types=1);

/*
 * PERSONNALISATION DU DASHBOARD
 *
 * Chaque entrée ci-dessous devient un onglet.
 *
 * hostgroups : groupes Nagios utilisés pour remplir l'onglet.
 * hosts      : noms d'hôtes exacts (optionnel).
 *
 * Un hôte peut apparaître dans plusieurs onglets si besoin.
 * L'ordre des onglets est celui de ce fichier.
 */
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
