<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../services/send_mail.php';

session_start();

$step = $_SESSION['reset_step'] ?? 'email'; // email, verify, reset
$message = '';
$messageType = '';

// Si l'utilisateur est déjà connecté, redirection
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: ../app/Views/admin/index.php');
    } elseif ($_SESSION['user_role'] === 'manager') {
        header('Location: ../app/Views/manager/dashboard.php');
    } else {
        header('Location: ../app/Views/employee/dashboard.php');
    }
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_code':
                $email = trim($_POST['email']);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    // Vérifier si l'email existe
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        // Générer le code
                        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                        // Sauvegarder dans la DB
                        // On supprime d'abord les anciens codes pour cet email
                        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                        $stmt->execute([$email]);

                        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
                        $stmt->execute([$email, $code, $expiry]);

                        // Envoyer l'email
                        if (sendPasswordResetEmail($email, $code)) {
                            $_SESSION['reset_step'] = 'verify';
                            $_SESSION['reset_email'] = $email;
                            $step = 'verify';
                            $message = "Un code de vérification a été envoyé à votre adresse email.";
                            $messageType = "success";
                        } else {
                            $message = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
                            $messageType = "danger";
                        }
                    } else {
                        // Pour la sécurité, on ne dit pas si l'email n'existe pas, mais on fait semblant
                        // Ou on dit "Email introuvable" pour UX interne. Pour cet exercice, disons la vérité.
                        $message = "Aucun compte associé à cet email.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Format d'email invalide.";
                    $messageType = "danger";
                }
                break;

            case 'verify_code':
                $code = trim($_POST['code']);
                $email = $_SESSION['reset_email'] ?? '';

                if (empty($email)) {
                    $_SESSION['reset_step'] = 'email';
                    $step = 'email';
                    $message = "Session expirée. Veuillez recommencer.";
                    $messageType = "warning";
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expiry > NOW()");
                    $stmt->execute([$email, $code]);

                    if ($stmt->fetch()) {
                        $_SESSION['reset_step'] = 'reset';
                        $step = 'reset';
                        $message = "Code vérifié. Vous pouvez maintenant définir votre nouveau mot de passe.";
                        $messageType = "success";
                    } else {
                        $message = "Code invalide ou expiré.";
                        $messageType = "danger";
                    }
                }
                break;

            case 'reset_password':
                $password = $_POST['password'];
                $password_confirm = $_POST['password_confirm'];
                $email = $_SESSION['reset_email'] ?? '';

                if (empty($email)) {
                    $_SESSION['reset_step'] = 'email';
                    $step = 'email';
                    $message = "Session expirée. Veuillez recommencer.";
                    $messageType = "warning";
                } elseif (strlen($password) < 6) {
                    $message = "Le mot de passe doit contenir au moins 6 caractères.";
                    $messageType = "danger";
                } elseif ($password !== $password_confirm) {
                    $message = "Les mots de passe ne correspondent pas.";
                    $messageType = "danger";
                } else {
                    // Mettre à jour le mot de passe
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE email = ?");
                    $stmt->execute([$hashed_password, $email]);

                    // Supprimer le code utilisé
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->execute([$email]);

                    // Nettoyer la session
                    unset($_SESSION['reset_step']);
                    unset($_SESSION['reset_email']);

                    // Optionnel : Connexion automatique
                    // Récupérer l'utilisateur pour le login auto
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_nom'] = $user['nom'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];

                        // Redirection selon le rôle
                        if ($user['role'] === 'admin') {
                            header('Location: ../app/Views/admin/index.php');
                        } elseif ($user['role'] === 'manager') {
                            header('Location: ../app/Views/manager/dashboard.php');
                        } else {
                            header('Location: ../app/Views/employee/dashboard.php');
                        }
                        exit();
                    } else {
                        header('Location: login.php?message=password_reset');
                        exit();
                    }
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Frais de Déplacement</title>
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

        /* Overlay */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .container {
            position: relative;
            z-index: 2;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
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

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 25px 20px 10px;
        }

        .form-control {
            border-radius: 12px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .form-control:focus {
            background-color: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .input-group-text {
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header text-center">
                        <h4 class="mb-0 text-primary"><i class="bi bi-shield-lock"></i> Récupération</h4>
                        <p class="text-muted small mb-0 mt-2">Mot de passe oublié</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($step === 'email'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="send_code">
                                <div class="mb-4">
                                    <label for="email" class="form-label">Adresse Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="votre@email.com" required autofocus>
                                    </div>
                                    <div class="form-text">Nous vous enverrons un code de vérification.</div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Envoyer le code</button>
                                </div>
                            </form>
                        <?php elseif ($step === 'verify'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="verify_code">
                                <div class="mb-4 text-center">
                                    <p>Un code à 6 chiffres a été envoyé à
                                        <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                                    </p>
                                    <label for="code" class="form-label">Code de vérification</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-123"></i></span>
                                        <input type="text" class="form-control text-center" id="code" name="code"
                                            placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus
                                            style="font-size: 1.2rem; letter-spacing: 2px;">
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Vérifier le code</button>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="?step=email&reset=1" class="text-muted small"
                                        onclick="<?php $_SESSION['reset_step'] = 'email'; ?>">Renvoyer le code / Changer
                                        d'email</a>
                                </div>
                            </form>
                        <?php elseif ($step === 'reset'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="reset_password">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Nouveau mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Nouveau mot de passe" required autofocus>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
                                        <input type="password" class="form-control" id="password_confirm"
                                            name="password_confirm" placeholder="Confirmer le mot de passe" required>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Réinitialiser et se connecter</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center bg-white py-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Retour à la connexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>