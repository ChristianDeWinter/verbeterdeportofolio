<?php
session_start();
include "../db/conn.php";

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../inloggen.php");
    exit();
}

// session data
$currentUserId = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
$currentUserName = htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8');

// messages
$failMessage = "";
$message = "";

//  adjust users 
class UserManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function AllUsersWithRole($role = 'user') {
        $sql = "SELECT user_id, name, role FROM users WHERE role = :role ORDER BY name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function CreateUser($name, $password, $role) {
        if (preg_match('/\d/', $name)) {
            return " Naam mag geen cijfers bevatten";
        }

        if (strlen($password) < 5) {
            return " Wachtwoord moet meer dan 4 tekens hebben";
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE name = :name");
        $stmt->execute([':name' => $name]);
        if ($stmt->fetchColumn() > 0) {
            return " Gebruikersnaam bestaat al";
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insert_sql = "INSERT INTO users (name, password, role) VALUES (:name, :password, :role)";
        $stmt = $this->pdo->prepare($insert_sql);
        $stmt->execute([':name' => $name, ':password' => $hashedPassword, ':role' => $role]);
        return "Nieuwe gebruiker aangemaakt";
    }

    public function updateUser($userId, $name, $password, $role) {
        if (preg_match('/\d/', $name)) {
            return " Naam mag geen cijfers bevatten!";
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE name = :name AND user_id != :user_id");
        $stmt->execute([':name' => $name, ':user_id' => $userId]);
        if ($stmt->fetchColumn() > 0) {
            return "Gebruikersnaam bestaat al";
        }

        if (!empty($password) && strlen($password) < 5) {
            return " Wachtwoord moet meer dan 4 tekens hebben.";
        }

        $sql = "UPDATE users SET name = :name, role = :role";
        $params = [':name' => $name, ':role' => $role, ':user_id' => $userId];

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = :password";
            $params[':password'] = $hashedPassword;
        }

        $sql .= " WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return "Gebruiker succesvol bijgewerkt";
    }

    public function deleteUser($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        return "Gebruiker succesvol verwijderd";
    }
}

// Process form submission 
$userManager = new UserManager($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $message = $userManager->CreateUser(
            htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'),
            $_POST['password'],
            htmlspecialchars($_POST['role'], ENT_QUOTES, 'UTF-8')
        );
        header('refresh: 2;');
    }

    if (isset($_POST['edit_user'])) {
        $message = $userManager->updateUser(
            intval($_POST['user_id']),
            htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'),
            $_POST['password'],
            htmlspecialchars($_POST['role'], ENT_QUOTES, 'UTF-8')
        );
    }

    if (isset($_POST['delete_user'])) {
        $message = $userManager->deleteUser(intval($_POST['user_id']));
        header('refresh: 2;');
    }
}

// Fetch Users for Display
$users = $userManager->AllUsersWithRole('user');
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/admin-index.css">
</head>
<body>
<div class="container">

    <?php if (!empty($message)): ?>
        <div class="new-gebruiker-bericht"><?= htmlspecialchars($message) ?></div>
    <?php elseif (!empty($failMessage)): ?>
        <div class="fout-gebruiker-bericht"><?= htmlspecialchars($failMessage) ?></div>
    <?php endif; ?>

    <?php include 'admin-header.php' ?>

    <div class="content">

        <div class="gebruikers-header-div">
            <div class="gebruikers-header-text1">Gebruikers</div>
             <!-- Open overlay -->
            <button class="new-gebruiker-btn" onclick="showCreateUserForm()">New</button>
        </div>

        <div class="naamNroleNacties">
            <div class="naamNroleNacties-naam">Naam</div>
            <div class="naamNroleNacties-role">Role</div>
            <div class="naamNroleNacties-acties">Acties</div>
        </div>

        <table class="gebruikers-tabel">
            <?php foreach ($users as $user): ?>
                <tr class="gebruikers-tr">
                    <td class="naamNuser-icon">
                        <img src="../img/user-icon.png" alt="icon" class="user-icon">
                        <?= htmlspecialchars($user['name']) ?>
                    </td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td class="gebruikers-action-icons">
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                            <button type="submit" name="delete_user" title="Verwijderen">🗑️</button>
                        </form>
                        <!--  Open overlay edit user form -->
                        <button onclick="editUser('<?= $user['user_id'] ?>', '<?= htmlspecialchars($user['name']) ?>', '<?= $user['role'] ?>')" title="Wijzigen">✏️</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

         <!-- Overlay for new create and edit forms -->
        <div id="userFormOverlay" style="display: none;">
            <div id="formContainer">
                <!-- new create usser form -->
                <div id="createUserForm" style="display: none;">
                    <h2>Nieuwe Gebruiker</h2>
                    <form method="POST" onsubmit="return validateForm(this)">
                        <input type="text" name="name" placeholder="Naam" required pattern="^[^\d]+$" title="Naam mag geen cijfers bevatten">
                        <input type="password" name="password" placeholder="Wachtwoord" required minlength="5" title="Wachtwoord moet meer dan 4 tekens bevatten">
                        <select name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button type="submit" name="create_user">Toevoegen</button>
                        <button type="button" onclick="closeUserForm()">Cancel</button>
                    </form>
                </div>

                <!-- Edit yser form -->
                <div id="editUserForm" style="display: none;">
                    <h2>Gebruiker Bewerken</h2>
                    <form method="POST" onsubmit="return validateForm(this)">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="text" name="name" id="edit_name" required pattern="^[^\d]+$" title="Naam mag geen cijfers bevatten">
                        <input type="password" name="password" placeholder="Nieuw wachtwoord" minlength="5" title="Wachtwoord moet meer dan 4 tekens bevatten">
                        <select name="role" id="edit_role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button type="submit" name="edit_user">Opslaan</button>
                        <button type="button" onclick="closeUserForm()">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../js/admin.js"></script>

</body>
</html>