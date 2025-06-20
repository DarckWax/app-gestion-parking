<?php

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index()
    {
        try {
            $testQuery = $this->db->fetch("SELECT 'OK' as status");
            
            echo "<h1>🎉 ParkFinder - Système de Parking</h1>";
            echo "<h2>✅ Application fonctionne correctement!</h2>";
            echo "<p>Base de données: " . ($testQuery['status'] ?? 'Erreur') . "</p>";
            echo "<p>Heure: " . date('Y-m-d H:i:s') . "</p>";
            
            echo "<h3>Actions disponibles:</h3>";
            echo "<ul>";
            echo "<li><a href='/ParkFinder/public/debug.php'>🔍 Diagnostic système</a></li>";
            echo "<li><a href='#'>📝 Connexion (à venir)</a></li>";
            echo "<li><a href='#'>🚗 Réserver une place (à venir)</a></li>";
            echo "</ul>";
            
            echo "<style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
                h1 { color: #2c3e50; }
                h2 { color: #27ae60; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
                ul { list-style-type: none; }
                li { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
            </style>";
            
        } catch (\Exception $e) {
            echo "<h1>❌ Erreur dans HomeController</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    public function test()
    {
        echo "<h1>🧪 Page de Test</h1>";
        echo "<p>Si vous voyez cette page, le routing fonctionne!</p>";
        echo "<p><a href='/ParkFinder/'>← Retour accueil</a></p>";
    }
}
            ];
            
            // Vérifier si l'utilisateur est connecté pour personnaliser l'affichage
            $userReservations = [];
            if ($this->isAuthenticated()) {
                $userReservations = $this->reservationModel->getUserReservations(
                    $_SESSION['user_id'], 
                    null, 
                    3 // Dernières 3 réservations
                );
            }
            
            $this->view('home/index', [
                'title' => 'Accueil - ParkFinder',
                'stats' => $stats,
                'user_reservations' => $userReservations,
                'is_authenticated' => $this->isAuthenticated()
            ]);
            
        } catch (\Exception $e) {
            // En cas d'erreur de base de données, afficher quand même la page
            $this->view('home/index', [
                'title' => 'Accueil - ParkFinder',
                'stats' => [
                    'total_spots' => 0,
                    'available_spots' => 0,
                    'occupied_spots' => 0,
                    'recent_reservations' => 0
                ],
                'user_reservations' => [],
                'is_authenticated' => false,
                'db_error' => true
            ]);
        }
    }
}
