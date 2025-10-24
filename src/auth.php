<?php
session_start();

// Database
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'brightsmile';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Create database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SIGNUP LOGIC
    if (isset($_POST['signup_submit'])) {

        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $dob = $_POST['dob'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        $errors = [];

        if (!preg_match('/^[\p{L}\s]+$/u', $first_name) || !preg_match('/^[\p{L}\s]+$/u', $last_name)) {
            $errors[] = "nameinvalid";
        }

        if ($password !== $confirm_password) {
            $errors[] = "passwordmismatch";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "emailinvalid";
        }
        
        if (empty($phone) || !preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            $errors[] = "phoneinvalid";
        }

        if (strlen($password) < 8) {
        $errors[]="passwordshort";
        }

        if ( !preg_match('/[A-Z]/', $password)) {
        $errors[]="passwordnoupper";
        }

        if ( !preg_match('/[a-z]/', $password)) {
        $errors[]="passwordnolower";
        }

        if ( !preg_match('/[0-9]/', $password)) {
        $errors[]="passwordnonumber";
        }

        if ( !preg_match('/[^\p{L}\p{N}]/u', $password)) {
        $errors[]="passwordnosymbol";
        }

        $checkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmailStmt->bind_param("s", $email);
        $checkEmailStmt->execute();
        $checkResult = $checkEmailStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $errors[] = "emailtaken";
        }
        $checkEmailStmt->close();
        
        if (!empty($errors)) {
            $_SESSION['form_data'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'dob' => $dob,
                'phone' => $_POST['phone']
            ];

            header("Location: auth.php?signup_errors=" . implode(',', $errors));
            exit();
        } 
        
        // If no error proceeds to register
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

        $stmt = $conn->prepare(
            "INSERT INTO users (email, password_hash, first_name, last_name, dob, phone) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss", $email, $hashed_password, $first_name, $last_name, $dob, $phone);

        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id;

            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['user_email'] = $email;
            
            unset($_SESSION['form_data']); 

            $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->bind_param("i", $new_user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            header("Location: index.html");
            exit();
        } else {
            die("Database Error during registration: " . $stmt->error);
        }
        $stmt->close();
    }

    // LOGIN LOGIC
    if (isset($_POST['login_submit'])) {
        
        $email = $_POST['email'];
        $password = $_POST['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['login_attempt_email'] = $email; 
            header("Location: auth.php?login_error=emailinvalid");
            exit();
        }

        $stmt = $conn->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                unset($_SESSION['login_attempt_email']); 
                $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                header("Location: index.html");
                exit();
            } else {
                $_SESSION['login_attempt_email'] = $email; 
                header("Location: auth.php?login_error=wrongpassword");
                exit();
            }
        } else {
            $_SESSION['login_attempt_email'] = $email;
            header("Location: auth.php?login_error=nouser");
            exit();
        }
        $stmt->close();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Authentication</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/root.css">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
</head>
<body>
    <header class="navbar-container">
        <div class="general navbar">
            <a href="index.html" class="logo" aria-label="BrightSmile home">
                <img src="assets/icons/logo.svg" alt="Logo">
                <span>BrightSmile</span>
            </a>
            <div></div>
        </div>
    </header>

    <main class="form-wrapper">
        <div class="form-container">
            <div class="toggle-wrapper">
                <div class="toggle-container">
                    <button id="login-toggle" class="toggle-btn active">Login</button>
                    <button id="signup-toggle" class="toggle-btn">Sign up</button>
                </div>
            </div>

            <?php
            if (isset($_GET['signup']) && $_GET['signup'] == 'success') {
                echo '<div class="form-success-message"><p>Account created successfully! Please log in.</p></div>';
            }
            ?>

            <?php
            $login_errors = [
                'email' => '',
                'password' => ''
            ];
            if (isset($_GET['login_error'])) {
                $error = $_GET['login_error'];
                if ($error == 'nouser') {
                    $login_errors['email'] = 'This email does not exist.';
                } elseif ($error == 'emailinvalid') {
                    $login_errors['email'] = 'Invalid email format entered.';
                } elseif ($error == 'wrongpassword') {
                    $login_errors['password'] = 'Incorrect password. Please try again.';
                }
            }

            $login_email_attempt = $_SESSION['login_attempt_email'] ?? ''; 
            unset($_SESSION['login_attempt_email']);
            ?>
            
            <form id="login-form" method="POST" action="auth.php">
                <div class="input-group <?php echo !empty($login_errors['email']) ? 'has-error' : '' ?>">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required value="<?php echo htmlspecialchars($login_email_attempt); ?>">
                    <span class="error-message" id="login-email-error">
                        <?php echo $login_errors['email']; ?>
                    </span>
                </div>
                <div class="input-group <?php echo !empty($login_errors['password']) ? 'has-error' : '' ?>">
                    <label for="login-password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="login-password" name="password" required>
                        <button type="button" class="toggle-password">
                            <img src="assets/icons/eye-open.svg" alt="Show password" class="eye-icon">
                            <img src="assets/icons/eye-close.svg" alt="Hide password" class="eye-slash-icon hidden">
                        </button>
                    </div>
                    <span class="error-message" id="login-password-error">
                        <?php echo $login_errors['password']; ?>
                    </span>
                </div>
                <button type="submit" class="btn-base submit-btn" name="login_submit">Login</button>
            </form>

            <?php 
            $form_data = $_SESSION['form_data'] ?? []; 
            unset($_SESSION['form_data']);

            $signup_errors = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'password' => '',
                'confirm' => ''
            ];
            if (isset($_GET['signup_errors'])) {
                $signup_error_codes = explode(',', $_GET['signup_errors']);

                if (in_array('nameinvalid', $signup_error_codes)) {
                    $signup_errors['name'] = 'Must consist of letters only';
                }

                if (in_array('emailtaken', $signup_error_codes)) {
                    $signup_errors['email'] = 'This email address is already exist.';
                } elseif (in_array('emailinvalid', $signup_error_codes)) {
                    $signup_errors['email'] = 'Invalid email format entered.';
                }

                if (in_array('phoneinvalid', $signup_error_codes)) {
                    $signup_errors['phone'] = 'Please enter a valid phone number.';
                }

                $password_error_messages =[];

                if (in_array('passwordshort', $signup_error_codes)) {
                $password_error_messages[]='• Must be at least 8 characters long.';
                }

                if (in_array('passwordnoupper', $signup_error_codes)) {
                $password_error_messages[]='• Must include at least one uppercase letter.';
                }

                if (in_array('passwordnolower', $signup_error_codes)) {
                $password_error_messages[]='• Must include at least one lowercase letter.';
                }

                if (in_array('passwordnonumber', $signup_error_codes)) {
                $password_error_messages[]='• Must include at least one number.';
                }

                if (in_array('passwordnosymbol', $signup_error_codes)) {
                $password_error_messages[]='• Must include at least one symbol (e.g., !@#$).';
                }

                if (!empty($password_error_messages)) {
                    $signup_errors['password'] = implode('<br>', $password_error_messages);
                }

                if (in_array('passwordmismatch', $signup_error_codes)) {
                    $signup_errors['confirm'] = 'Passwords do not match. Please try again.';
                }
            }
            ?>

            <form id="signup-form" class="hidden" method="POST" action="auth.php">

                <div class="name-group">
                    <div class="input-group <?php echo !empty($signup_errors['name']) ? 'has-error' : '' ?>"> 
                        <label for="first-name">First Name</label>
                        <input type="text" id="first-name" name="first_name" required value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>">
                        <span class="error-message" id="signup-name-error">
                            <?php echo $signup_errors['name']; ?>
                        </span>
                    </div>
                    <div class="input-group <?php echo !empty($signup_errors['name']) ? 'has-error' : '' ?>"> 
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="last_name" required value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="input-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" required 
                            value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>"
                            max="<?php echo date('Y-m-d'); ?>"> 
                </div>

                <div class="input-group <?php echo !empty($signup_errors['phone']) ? 'has-error' : '' ?>">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" required value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                    <span class="error-message" id="signup-phone-error">
                        <?php echo $signup_errors['phone']; ?>
                    </span>
                </div>
                
                <div class="input-group <?php echo !empty($signup_errors['email']) ? 'has-error' : '' ?>">
                    <label for="signup-email">Email</label>
                    <input type="email" id="signup-email" name="email" required value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                    <span class="error-message" id="signup-email-error">
                        <?php echo $signup_errors['email']; ?>
                    </span>
                </div>
                <div class="input-group <?php echo !empty($signup_errors['password']) ? 'has-error' : '' ?>">
                    <label for="signup-password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="signup-password" name="password" required>
                        <button type="button" class="toggle-password">
                            <img src="assets/icons/eye-open.svg" alt="Show password" class="eye-icon">
                            <img src="assets/icons/eye-close.svg" alt="Hide password" class="eye-slash-icon hidden">
                        </button>
                    </div>

                    <div class="password-requirements" id="signup-req-list">
                        <ul>
                            <li id="req-length">Must be at least 8 characters long.</li>
                            <li id="req-lower">Must include at least one lowercase letter.</li>
                            <li id="req-upper">Must include at least one capital letter.</li>
                            <li id="req-number">Must include at least one number.</li>
                            <li id="req-symbol">Must include at least one symbol (e.g., !@#$).</li>
                        </ul>
                    </div>
                    </div>
                <div class="input-group <?php echo !empty($signup_errors['confirm']) ? 'has-error' : '' ?>">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password">
                            <img src="assets/icons/eye-open.svg" alt="Show password" class="eye-icon">
                            <img src="assets/icons/eye-close.svg" alt="Hide password" class="eye-slash-icon hidden">
                        </button>
                    </div>
                    <span class="error-message" id="signup-confirm-error">
                        <?php echo $signup_errors['confirm']; ?>
                    </span>
                </div>
                <button type="submit" class="btn-base submit-btn" name="signup_submit">Create Account</button>
            </form>
        </div>
    </main>

    <script src="js/auth.js" defer></script>

</body>
</html>

