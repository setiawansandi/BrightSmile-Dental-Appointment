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

        if ($password !== $confirm_password) {
            $errors[] = "passwordmismatch";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "emailinvalid";
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
                'phone' => $phone
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
            unset($_SESSION['form_data']); 
            header("Location: auth.php?signup=success");
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
                'email' => '',
                'password' => '',
                'confirm' => ''
            ];
            if (isset($_GET['signup_errors'])) {
                $signup_error_codes = explode(',', $_GET['signup_errors']);

                if (in_array('emailtaken', $signup_error_codes)) {
                    $signup_errors['email'] = 'This email address is already exist.';
                } elseif (in_array('emailinvalid', $signup_error_codes)) {
                    $signup_errors['email'] = 'Invalid email format entered.';
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
                    <div class="input-group">
                        <label for="first-name">First Name</label>
                        <input type="text" id="first-name" name="first_name" required value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>">
                    </div>
                    <div class="input-group">
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="last_name" required value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="input-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" required value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>">
                </div>
                <div class="input-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
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

    <script>
        const loginToggle = document.getElementById('login-toggle');
        const signupToggle = document.getElementById('signup-toggle');
        const loginForm = document.getElementById('login-form');
        const signupForm = document.getElementById('signup-form');

        const successMessage = document.querySelector('.form-success-message');
        
        const loginEmailSpan = document.getElementById('login-email-error');
        const loginEmailPHPError = loginEmailSpan.textContent.trim();

        const signupEmailSpan = document.getElementById('signup-email-error');
        const signupEmailPHPError = signupEmailSpan.textContent.trim();
        

        // Validation for login
        const loginEmailInput = document.getElementById('login-email');
        if (loginEmailInput) {
            const loginEmailGroup = loginEmailInput.closest('.input-group');

            loginEmailInput.addEventListener('blur', function () {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const email = loginEmailInput.value;

                if (email === "") {
                    loginEmailGroup.classList.remove('has-error');
                    loginEmailSpan.textContent = ""; 
                    return;
                } else if (!emailRegex.test(email)) {
                    loginEmailSpan.textContent = "Email is invalid";
                    loginEmailGroup.classList.add('has-error');
                    return;
                } else {
                    loginEmailGroup.classList.remove('has-error');
                    loginEmailSpan.textContent = "";
                }
            });
        }

        // Validation for register
        const signupEmailInput = document.getElementById('signup-email');
        if (signupEmailInput) {
            const signupEmailGroup = signupEmailInput.closest('.input-group');
            
            signupEmailInput.addEventListener('blur', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const email = signupEmailInput.value;

                if (email === "") {
                    signupEmailGroup.classList.remove('has-error');
                    signupEmailSpan.textContent = "";
                    return;
                } else if (!emailRegex.test(email)) {
                    signupEmailSpan.textContent = "Email is invalid";
                    signupEmailGroup.classList.add('has-error');
                    return;
                } else {
                    signupEmailGroup.classList.remove('has-error');
                    signupEmailSpan.textContent = "";
                }
            });
        }

        // Realtime password validation
        const signupPasswordInput = document.getElementById('signup-password');
        const reqList = document.getElementById('signup-req-list');
        
        if (signupPasswordInput && reqList) {
            const reqs = {
                length: document.getElementById('req-length'),
                lower: document.getElementById('req-lower'),
                upper: document.getElementById('req-upper'),
                number: document.getElementById('req-number'),
                symbol: document.getElementById('req-symbol')
            };

            signupPasswordInput.addEventListener('input', () => {
                const value = signupPasswordInput.value;

                if (value.length >= 8) {
                    reqs.length.classList.add('valid');
                } else {
                    reqs.length.classList.remove('valid');
                }

                if (/[a-z]/.test(value)) {
                    reqs.lower.classList.add('valid');
                } else {
                    reqs.lower.classList.remove('valid');
                }

                if (/[A-Z]/.test(value)) {
                    reqs.upper.classList.add('valid');
                } else {
                    reqs.upper.classList.remove('valid');
                }

                if (/[0-9]/.test(value)) {
                    reqs.number.classList.add('valid');
                } else {
                    reqs.number.classList.remove('valid');
                }

                if (/[^\p{L}\p{N}]/u.test(value)) {
                    reqs.symbol.classList.add('valid');
                } else {
                    reqs.symbol.classList.remove('valid');
                }
            });
        }

        // Sign up button toggle
        signupToggle.addEventListener('click', () => {
            const loginInputs = loginForm.querySelectorAll('input'); 
            loginInputs.forEach(input => input.value = ''); 
            
            loginForm.querySelectorAll('.input-group.has-error').forEach(group => {
                group.classList.remove('has-error');
            });
            loginForm.querySelectorAll('.error-message').forEach(span => {
                span.textContent = "";
            });

            signupForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
            signupToggle.classList.add('active');
            loginToggle.classList.remove('active');

            if (successMessage) {
                successMessage.style.display = 'none';
            }
        });

        // Login button toggle
        loginToggle.addEventListener('click', () => {
            const signupInputs = signupForm.querySelectorAll('input');
            signupInputs.forEach(input => input.value = '');

            signupForm.querySelectorAll('.input-group.has-error').forEach(group => {
                group.classList.remove('has-error');
            });
            signupForm.querySelectorAll('.error-message').forEach(span => {
                span.textContent = "";
            });

            loginForm.classList.remove('hidden');
            signupForm.classList.add('hidden');
            loginToggle.classList.add('active');
            signupToggle.classList.remove('active');

        });

        // Password toggle
        const passwordWrappers = document.querySelectorAll('.password-wrapper');
        passwordWrappers.forEach(wrapper => {
            const passwordInput = wrapper.querySelector('input');
            const eyeIcon = wrapper.querySelector('.eye-icon');
            const eyeSlashIcon = wrapper.querySelector('.eye-slash-icon');
            const toggle = wrapper.querySelector('.toggle-password');

            toggle.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                eyeIcon.classList.toggle('hidden');
                eyeSlashIcon.classList.toggle('hidden');
            });
        });

        // page load
        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.has('signup_errors')) {
            signupForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
            signupToggle.classList.add('active');
            loginToggle.classList.remove('active');
        } else if (urlParams.has('signup') && urlParams.get('signup') === 'success') {
            loginForm.classList.remove('hidden');
            signupForm.classList.add('hidden');
            loginToggle.classList.add('active');
            signupToggle.classList.remove('active');
        } else if (urlParams.has('login_error')) {
            loginForm.classList.remove('hidden');
            signupForm.classList.add('hidden');
            loginToggle.classList.add('active');
            signupToggle.classList.remove('active');
        }
    </script>

</body>
</html>