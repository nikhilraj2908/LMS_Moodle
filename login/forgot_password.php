<?php
require('../config.php');
require_once($CFG->libdir . '/authlib.php');
require_once(__DIR__ . '/lib.php');
require_once('forgot_password_form.php');
require_once('set_password_form.php');

global $PAGE, $OUTPUT, $SESSION, $CFG;

// ------------------------------------------------------------------
// Set up page metadata
// ------------------------------------------------------------------
$PAGE->set_url('/login/forgot_password.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$strforgotten = get_string('passwordforgotten');
$PAGE->set_title($strforgotten);
// You can set heading to the course fullname or a custom string:
// $PAGE->set_heading($COURSE->fullname);

// ------------------------------------------------------------------
// Redirect if an alternate URL is defined or if the user is already logged in
// ------------------------------------------------------------------
if (!empty($CFG->forgottenpasswordurl)) {
    redirect($CFG->forgottenpasswordurl);
}
if (isloggedin() && !isguestuser()) {
    redirect($CFG->wwwroot . '/index.php', get_string('loginalready'), 5);
}

// ------------------------------------------------------------------
// Get token and check for a token in session
// ------------------------------------------------------------------
$token = optional_param('token', false, PARAM_ALPHANUM);
$tokeninsession = !empty($SESSION->password_reset_token);

// ------------------------------------------------------------------
// Process token-based password reset BEFORE any UI is output
// ------------------------------------------------------------------
if ($token) {
    if (!$tokeninsession && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Save the token into the session and redirect to avoid duplicate processing.
        $SESSION->password_reset_token = $token;
        redirect($CFG->wwwroot . '/login/forgot_password.php');
    } else {
        // Token is present (from GET with session or POST); process password reset.
        core_login_process_password_set($token);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the submitted reset request (i.e. send the reset email)
    try {
        core_login_process_password_reset_request();
        echo "<p style='color: green; text-align: center;'>If the email exists, a reset link has been sent.</p>";
    } catch (Exception $e) {
        echo "<p style='color: red; text-align: center;'>Error sending email: " . $e->getMessage() . "</p>";
    }
    exit;
}

// ------------------------------------------------------------------
// Render the page UI only when no token is provided and no form was submitted
// ------------------------------------------------------------------
echo $OUTPUT->header();
?>

<!-- Custom UI: Your forgot password page design -->
<style>
    
    .path-login #page {
        background-image: url('../pix/BG_2.png');
        background-size: cover;
        background-position: top;
        background-repeat: no-repeat;
        width: 100%;
    }
    .forgot-password-container {
        
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        max-width: 100%;
    }
    .forgot-password-svg {
        flex: 1; /* Takes 50% of the space */
        display: flex;
        justify-content: center;
    }
    .forgot-password-form {
        flex: 1; /* Takes 50% of the space */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .forgot-password-card {
        width: 100%;
        max-width: 400px;
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    @media (max-width: 768px) {
        .forgot-password-container {
            flex-direction: column;
            text-align: center;
        }
        .forgot-password-svg {
            margin-bottom: 20px;
        }
    }
    #page.drawers {
        margin-top: 0rem;
    }
    body:not(.path-admin) #page.drawers {
        padding-top: 30px;
    }
</style>

<div class="forgot-password-container">
    <!-- SVG Left Side (replace the placeholder with your SVG or an image) -->
    <div class="forgot-password-svg">
      
    </div>

    <!-- Form Right Side -->
    <div class="forgot-password-form">
        <div class="forgot-password-card">
            <h2 class="forgot-title" style="color:#204070">Forgot Your Password?</h2>
            <p class="forgot-subtitle">Enter your registered email to reset your password</p>
            <?php
            // Display the forgot password form (only when no token is present)
            $mform = new login_forgot_password_form();
            $mform->display();
            ?>
            <div class="back-to-login">
                <a href="login.php">Back to Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?>
