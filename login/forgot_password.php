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
       .container-main {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100vh;
  width: 100%;
  overflow-x: hidden;
}




  .left-side {
            position: relative;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background: url('<?php echo $CFG->wwwroot;?>/pix/loginbg.png') no-repeat center center;
            background-size:cover;

        }
        :root {
  /* Primary brand blue (used for logos, highlights, buttons) */
  --primary:        #204070;
  /* Dark grey for body text */
  --text-dark:      #333333;
  /* Light grey for secondary text */
  --text-light:     #666666;
  /* Very pale blue background tint */
  --bg-light:       #f0f6fc;
}
 .left-bg-box {
  color: var(--text-dark);
  text-shadow: none; /* your background is now light enough */
}
.left-bg-box h2,
.typing-text,
.left-bg-box p,
.left-bg-box h4 {
  color: var(--text-dark);
}
.left-bg-box .banner-text {
  color: var(--primary);
}

        
        .logo-div {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInDown 1s ease-out forwards;
        }
        
        .logo-div img {
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
            width: 120px;
            height: auto;
        }
        
        .banner-text {
            font-weight: bold;
            color: #ffd166;
        }
        
        .typing-container {
            min-height: 80px;
        }
        
        .typing-text {
            font-size: 2.2rem;
            font-weight: 700;
        }
        
     
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        
        .cta-button {
            margin-top: 2rem;
        }
        
     .cta-button button {
  border-color: var(--primary);
  color: var(--primary);
  background: transparent;
}
.cta-button button:hover {
  background-color: var(--primary);
  color: white;
  box-shadow: 0 6px 20px rgba(32,64,112,0.3);
}

.right-side {
        display: flex;
        justify-content: center;
        align-items: center;
        background: #fff;
    }

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
        @media (max-width: 768px) {
        .container-main {
            display: block;
            align-content:center;
            background: url('{{config.wwwroot}}/theme/academi/pix/loginbg.png') no-repeat center center;
        }

        .left-side {
            display: none;
        }

        .right-side {
          padding: 2rem 2rem 2rem 3rem;
          background:transparent;
        }

        .login-form {
            width: 100%;
        }
    }

    .input-wrapper {
    position: relative;
    margin-bottom: 1.5rem;
    box-shadow: 1px 2px 5px 1px gray;
    border-radius: 8px;
    overflow: hidden;
}

.input-wrapper input {
    width: 100%;
    padding: 12px 12px 12px 40px; /* left padding to make space for icon */
    border: none;
    font-size: 16px;
    outline: none;
}

.input-icon {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    color: #204070;
    font-size: 16px;
}
   @media (max-width: 574px) {
    form input[type="text"] {
        max-width: 100%;
    }
        form input[type="password"] {
        max-width: 100%;
    }
}
@keyframes slideInLeft {
    from { transform: translateX(-100%); opacity: 0; }
    to   { transform: translateX(0);     opacity: 1; }
  }
  @keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);     opacity: 1; }
  }
  @keyframes fadeInUp {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);     opacity: 1; }
  }

  /* 2) Prepare initial state */
  .left-side,
  .right-side {
    opacity: 0;
  }
  .login-form {
    opacity: 0;
  }
  .input-wrapper {
    opacity: 0;
    transform: translateY(20px);
  }

  /* 3) Trigger animations */
  .left-side {
    animation: slideInLeft 0.8s ease-out forwards;
  }
  .right-side {
    animation: slideInRight 0.8s ease-out forwards;
  }
  .login-form {
    animation: slideInRight 0.8s ease-out forwards;

    
    
  }
  .input-wrapper {
    animation: fadeInUp 0.5s ease-out forwards;
    /* stagger each input field */
  }
  .input-wrapper:nth-of-type(1) { animation-delay: .6s; }
  .input-wrapper:nth-of-type(2) { animation-delay: 1.2s; }
  .forgot-pass {
    opacity: 0;
    animation: fadeInUp 0.2s ease-out forwards;
    animation-delay: 1.6s;
  }



  #page #page-header {
    max-width: none;
    display: none;
    /* margin-bottom: 15px; */
}

#region-main {
    float: none;
    padding: 0 0px 0px;
    border-radius: 10px;
}

#page.drawers .main-inner {
    max-width: 100%;
    background-color: #fff;
    margin-top: -2rem !important;
    margin-bottom: 0rem !important;
    padding:0px;
}

.pb-3, .py-3 {
    padding-bottom: 0rem !important;
}
#page-footer{
    display: none;
}
.header-main{
    display:none;
}
</style>
<nav id="header" class="  navbar navbar-light bg-faded navbar-static-top navbar-expand moodle-has-zindex" aria-label="Site navigation">
        <div class="container-fluid navbar-nav">
                    
        <nav class="nav navbar-nav hidden-md-down address-head">
            <span><i class="fa fa-phone"></i><span>Call us</span> : +91 8839833183</span>
            <span><i class="fa fa-envelope-o"></i><span>E-mail</span> : <a href="mailto:hbarsaiyan17@gmail.com">hbarsaiyan17@gmail.com</a></span>
        </nav>
    
    
                    
            <div id="usernavigation" class="navbar-nav ml-auto">
                
                    
    
                
    
    
        <div class="d-flex align-items-stretch usermenu-container" data-region="usermenu">
    
                  
                        <div class="usermenu">
                            
                        </div>
    
                </div>
                
                
            </div>
            <!-- search_box -->
        </div>
    </nav>
<div class="container-main">
    <!-- SVG Left Side (replace the placeholder with your SVG or an image) -->
    <div class="left-side">
            <div class="left-bg-box">
                 <div class="logo-div">
                    <img src="<?php echo $CFG->wwwroot; ?>/pix/logomain.png" width="100" alt="Logo" />
                </div>
                <h2 class="animate-left">Step In Skill Up Succeed<br />
                    with <span class="banner-text">AlogicData LMS</span>
                </h2>
                <div class="typing-container">
                    <div class="typing-text" id="typing-text"></div>
                </div>
                <p class="animate-left animate-delay-1" style="margin-bottom: 1.5rem;">Your personalized learning space – available anytime, anywhere.</p>
                <h4 class="animate-left animate-delay-2">Start Learning <span class="banner-text">Today</span></h4>
               
            </div>
    </div>
    <!-- Form Right Side -->
    <div class="right-side">
        <div class="forgot-password-card">
            <h2 class="forgot-title" style="color:#204070">Forgot Your Password?</h2>
            <p class="forgot-subtitle">Enter your registered email to reset your password</p>
            <?php
            // Display the forgot password form (only when no token is present)
            $mform = new login_forgot_password_form();
            $mform->display();
            ?>
            <div class="back-to-login">
                <a href="index.php ">Back to Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?>
 <script>
    

 const texts = [
                "Expand your knowledge",
                "Unlock your potential",
                "Learn at your own pace",
                "Join our community"
            ];
            let textIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            const typingSpeed = 100;
            const deleteSpeed = 50;
            const pauseBetween = 1500;
            
            const typingText = document.getElementById("typing-text");
            const cursor = document.getElementById("cursor");
            
            function type() {
                const currentText = texts[textIndex];
                
                if (isDeleting) {
                    // Delete characters
                    typingText.textContent = currentText.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    // Type characters
                    typingText.textContent = currentText.substring(0, charIndex + 1);
                    charIndex++;
                }
                
                // Determine timing and next action
                if (!isDeleting && charIndex === currentText.length) {
                    // Pause at end of typing
                    isDeleting = true;
                    setTimeout(type, pauseBetween);
                } else if (isDeleting && charIndex === 0) {
                    // Finished deleting, move to next text
                    isDeleting = false;
                    textIndex = (textIndex + 1) % texts.length;
                    setTimeout(type, typingSpeed);
                } else {
                    // Continue typing or deleting
                    setTimeout(type, isDeleting ? deleteSpeed : typingSpeed);
                }
            }
            
            // Start typing animation
            setTimeout(type, 1000);
    </script>