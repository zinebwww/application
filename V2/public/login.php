<?php
/**
 * Page de connexion globale pour tous les utilisateurs
 * Redirige vers l'espace approprié selon le rôle
 */

session_start();
require_once __DIR__ . '/../config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_nom'] = $user['nom'];

            // Redirection selon le rôle
            $redirectUrl = '';
            switch ($user['role']) {
                case 'employe':
                    $redirectUrl = '../app/Views/employee/dashboard.php';
                    break;
                case 'manager':
                    $redirectUrl = '../app/Views/manager/dashboard.php';
                    break;
                case 'admin':
                    $redirectUrl = '../app/Views/admin/index.php';
                    break;
                default:
                    $redirectUrl = '../app/Views/employee/dashboard.php';
            }

            // Utiliser un script JS pour définir sessionStorage avant la redirection
            echo "<script>
                sessionStorage.setItem('isLoggedIn', 'true');
                window.location.href = '$redirectUrl';
            </script>";
            exit();
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'employe':
            header('Location: ../app/Views/employee/dashboard.php');
            break;
        case 'manager':
            header('Location: ../app/Views/manager/dashboard.php');
            break;
        case 'admin':
            header('Location: ../app/Views/admin/index.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion des Frais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-image: url('img/login.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        /* Overlay to ensure text readability if image is bright */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            /* Dark overlay */
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            /* Slightly translucent */
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 2;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 2rem;
            color: #1a4f8b;
        }

        .brand-logo i {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
            color: #2563eb;
        }

        .brand-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-floating>.form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .form-floating>.form-control:focus {
            background-color: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-floating>label {
            color: #64748b;
        }

        .btn-login {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .form-check-input:checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .form-check-label {
            font-size: 0.8rem;
        }

        .forgot-password {
            color: #64748b;
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s;
        }

        .forgot-password:hover {
            color: #2563eb;
        }

        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="brand-logo">
            <i class="bi bi-wallet2"></i>
            <h1>GestionFrais</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="nom@exemple.com" required
                    autofocus>
                <label for="email">Email professionnel</label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe"
                    required>
                <label for="password">Mot de passe</label>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label text-muted" for="rememberMe">
                        Se souvenir de moi
                    </label>
                </div>
                <a href="forgot_password.php" class="forgot-password">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                Se connecter
            </button>
        </form>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> Gestion Frais. Tous droits réservés.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>