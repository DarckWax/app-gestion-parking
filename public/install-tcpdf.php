<?php
/**
 * Installation automatique de TCPDF pour la génération de PDF
 * Ce script télécharge et installe TCPDF si nécessaire
 */

// Fonction pour télécharger TCPDF
function installTCPDF() {
    $tcpdfDir = __DIR__ . '/tcpdf';
    $tcpdfUrl = 'https://github.com/tecnickcom/TCPDF/archive/refs/heads/main.zip';
    $zipFile = __DIR__ . '/tcpdf.zip';
    
    try {
        // Créer le dossier si nécessaire
        if (!is_dir($tcpdfDir)) {
            mkdir($tcpdfDir, 0755, true);
        }
        
        // Télécharger TCPDF
        echo "Téléchargement de TCPDF...\n";
        $zipContent = file_get_contents($tcpdfUrl);
        
        if ($zipContent === false) {
            throw new Exception("Impossible de télécharger TCPDF");
        }
        
        file_put_contents($zipFile, $zipContent);
        
        // Extraire l'archive
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo(__DIR__);
            $zip->close();
            
            // Déplacer les fichiers au bon endroit
            $extractedDir = __DIR__ . '/TCPDF-main';
            if (is_dir($extractedDir)) {
                // Copier les fichiers essentiels
                copy($extractedDir . '/tcpdf.php', $tcpdfDir . '/tcpdf.php');
                
                // Copier le dossier include si nécessaire
                if (is_dir($extractedDir . '/include')) {
                    copyDirectory($extractedDir . '/include', $tcpdfDir . '/include');
                }
                
                // Copier le dossier fonts si nécessaire
                if (is_dir($extractedDir . '/fonts')) {
                    copyDirectory($extractedDir . '/fonts', $tcpdfDir . '/fonts');
                }
                
                // Nettoyer
                removeDirectory($extractedDir);
            }
            
            unlink($zipFile);
            
            echo "TCPDF installé avec succès !\n";
            return true;
            
        } else {
            throw new Exception("Impossible d'extraire l'archive TCPDF");
        }
        
    } catch (Exception $e) {
        echo "Erreur lors de l'installation de TCPDF: " . $e->getMessage() . "\n";
        
        // Créer une version minimale de TCPDF pour les tests
        createMinimalTCPDF($tcpdfDir);
        return false;
    }
}

// Fonction pour copier un dossier récursivement
function copyDirectory($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;
            
            if (is_dir($srcFile)) {
                copyDirectory($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }
}

// Fonction pour supprimer un dossier récursivement
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $dir . '/' . $file;
            
            if (is_dir($filePath)) {
                removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
    }
    
    rmdir($dir);
}

// Créer une version minimale de TCPDF pour les tests
function createMinimalTCPDF($tcpdfDir) {
    $minimalTCPDF = '<?php
/**
 * Version minimale de TCPDF pour ParkFinder
 * Cette version génère du HTML au lieu de PDF
 */
class TCPDF {
    private $content = "";
    private $title = "";
    private $author = "";
    private $creator = "";
    private $subject = "";
    
    public function __construct($orientation = "P", $unit = "mm", $format = "A4", $unicode = true, $encoding = "UTF-8", $diskcache = false) {
        // Constructor minimal
    }
    
    public function SetCreator($creator) {
        $this->creator = $creator;
    }
    
    public function SetAuthor($author) {
        $this->author = $author;
    }
    
    public function SetTitle($title) {
        $this->title = $title;
    }
    
    public function SetSubject($subject) {
        $this->subject = $subject;
    }
    
    public function setPrintHeader($print) {
        // Méthode vide
    }
    
    public function setPrintFooter($print) {
        // Méthode vide
    }
    
    public function SetMargins($left, $top, $right) {
        // Méthode vide
    }
    
    public function SetAutoPageBreak($auto, $margin = 0) {
        // Méthode vide
    }
    
    public function AddPage() {
        // Méthode vide
    }
    
    public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = "") {
        $this->content .= $html;
    }
    
    public function Output($name = "doc.pdf", $dest = "I") {
        if ($dest === "D") {
            // Forcer le téléchargement HTML
            header("Content-Type: text/html; charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"" . str_replace(".pdf", ".html", $name) . "\"");
        } else {
            header("Content-Type: text/html; charset=UTF-8");
        }
        
        echo "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>" . htmlspecialchars($this->title) . "</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .tcpdf-notice { 
            background: #FEF3C7; 
            border: 1px solid #F59E0B; 
            color: #92400E; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
    </style>
</head>
<body>
    <div class=\"tcpdf-notice\">
        <strong>⚠️ Mode de compatibilité PDF</strong><br>
        TCPDF complet n\'est pas installé. Ce document est généré en HTML.<br>
        Utilisez Ctrl+P pour imprimer ou sauvegarder en PDF.
    </div>
    " . $this->content . "
    <script>
        if (confirm(\"Voulez-vous imprimer ce document maintenant ?\")) {
            window.print();
        }
    </script>
</body>
</html>";
    }
}
?>';
    
    file_put_contents($tcpdfDir . '/tcpdf.php', $minimalTCPDF);
    echo "Version minimale de TCPDF créée pour les tests.\n";
}

// Vérifier si TCPDF est déjà installé
$tcpdfPaths = [
    __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/tcpdf/tcpdf.php'
];

$tcpdfFound = false;
foreach ($tcpdfPaths as $path) {
    if (file_exists($path)) {
        $tcpdfFound = true;
        echo "TCPDF trouvé: " . $path . "\n";
        break;
    }
}

if (!$tcpdfFound) {
    echo "TCPDF non trouvé. Installation automatique...\n";
    installTCPDF();
} else {
    echo "TCPDF est déjà installé et prêt à être utilisé.\n";
}

echo "\nInstallation terminée !\n";
echo "Vous pouvez maintenant générer des reçus PDF avec generate-receipt.php\n";
?>