<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: inloggen.php");
    exit();
}

require 'db/conn.php';
require 'sidebar.php';

class User {
    private $voornaam;
    private $achternaam;
    private $email;
    private $telefoon;
    private $password;
    private $role;

    public function __construct($voornaam, $achternaam, $email, $telefoon, $password, $role) {
        $this->voornaam = $this->cleanInput($voornaam);
        $this->achternaam = $this->cleanInput($achternaam);
        $this->email = $this->cleanInput($email);
        $this->telefoon = $this->cleanInput($telefoon);
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->role = $this->cleanInput($role);
    }

    private function cleanInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    public function saveToDatabase($pdo) {
        $sql = "INSERT INTO users (name, achternaam, email, telefoon, password, role) 
                VALUES (:voornaam, :achternaam, :email, :telefoon, :password, :role)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':voornaam' => $this->voornaam,
            ':achternaam' => $this->achternaam,
            ':email' => $this->email,
            ':telefoon' => $this->telefoon,
            ':password' => $this->password,
            ':role' => $this->role
        ]);
    }
}

class Klant {
    private $voornaam;
    private $achternaam;
    private $email;
    private $telefoon;
    private $password;
    private $bedrijfnaam;

    public function __construct($voornaam, $achternaam, $email, $telefoon, $password, $bedrijfnaam) {
        $this->voornaam = $this->cleanInput($voornaam);
        $this->achternaam = $this->cleanInput($achternaam);
        $this->email = $this->cleanInput($email);
        $this->telefoon = $this->cleanInput($telefoon);
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->bedrijfnaam = $this->cleanInput($bedrijfnaam);
    }

    private function cleanInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    public function saveToDatabase($pdo) {
        $sql = "INSERT INTO klant (voornaam, achternaam, email, telefoon, password, bedrijfnaam) 
                VALUES (:voornaam, :achternaam, :email, :telefoon, :password, :bedrijfnaam)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':voornaam' => $this->voornaam,
            ':achternaam' => $this->achternaam,
            ':email' => $this->email,
            ':telefoon' => $this->telefoon,
            ':password' => $this->password,
            ':bedrijfnaam' => $this->bedrijfnaam
        ]);
    }
}

//  form submission
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (isset($_POST['role'])) {
            $role = $_POST['role'];
            
            if ($role === 'klant') {
                $user = new Klant(
                    $_POST['voornaam'],
                    $_POST['achternaam'],
                    $_POST['email'],
                    $_POST['telefoon'],
                    $_POST['password'],
                    $_POST['bedrijfnaam'] ?? ''
                );
            } else {
                $user = new User(
                    $_POST['voornaam'],
                    $_POST['achternaam'],
                    $_POST['email'],
                    $_POST['telefoon'],
                    $_POST['password'],
                    $role
                );
            }
            
            $user->saveToDatabase($pdo);
            $message = "Registratie succesvol!";
        }
    } catch (Exception $e) {
        $message = "Fout bij registreren: " . $e->getMessage();
    }
}

// Form rendering functions
function showRoleSelector() {
    $currentRole = $_POST['role'] ?? '';
    echo '<form method="POST">
            <label for="role">Kies rol:</label>
            <select id="role" name="role" onchange="this.form.submit()">
                <option value="" disabled'.($currentRole ? '' : ' selected').'>Selecteer rol</option>
                <option value="user" '.($currentRole == 'user' ? 'selected' : '').'>User</option>
                <option value="admin" '.($currentRole == 'admin' ? 'selected' : '').'>Admin</option>
                <option value="klant" '.($currentRole == 'klant' ? 'selected' : '').'>Klant</option>
            </select>
          </form>';
}

function showRegistrationForm($role) {
    if (!$role) return;
    
    echo '<form method="POST">
            <input type="hidden" name="role" value="'.htmlspecialchars($role).'">
            <div>
                <label>Voornaam:</label>
                <input type="text" name="voornaam" required>
            </div>
            <div>
                <label>Achternaam:</label>
                <input type="text" name="achternaam" required>
            </div>
            <div>
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Telefoon:</label>
                <input type="text" name="telefoon" required>
            </div>
            <div>
                <label>Wachtwoord:</label>
                <input type="password" name="password" required>
            </div>';
    
    if ($role === 'klant') {
        echo '<div>
                <label>Bedrijf:</label>
                <input type="text" name="bedrijfnaam">
              </div>';
    }
    
    echo '<button type="submit">Registreren</button>
          </form>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Registratie</title>

</head>
<body>
    <h2>Registratieformulier</h2>
    
    <?php if ($message): ?>
        <div class="<?= strpos($message, 'succesvol') !== false ? 'message' : 'error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php showRoleSelector(); ?>
    
    <?php if (isset($_POST['role'])): ?>
        <?php showRegistrationForm($_POST['role']); ?>
    <?php endif; ?>
</body>
</html>