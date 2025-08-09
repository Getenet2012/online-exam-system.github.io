<?php
require_once 'config.php';

if (!isset($_SESSION['mfa_required'])) {
    redirect('login.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitizeInput($_POST['code']);
    $userId = $_SESSION['user_id'];
    
    $user = getUserData($userId);
    
    if (verifyMFACode($user['mfa_secret'], $code)) {
        unset($_SESSION['mfa_required']);
        logActivity($userId, 'mfa_success', 'MFA verification successful');
        redirect('dashboard.php');
    } else {
        $error = 'Invalid verification code';
        logActivity($userId, 'mfa_failed', 'MFA verification failed');
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-primary py-4 px-6">
        <h2 class="text-white text-xl font-semibold">Multi-Factor Authentication</h2>
    </div>
    <div class="p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="mb-4">
            <p class="text-gray-700 mb-4">Please enter the 6-digit code from your authenticator app:</p>
        </div>
        
        <form action="mfa-verify.php" method="POST">
            <div class="mb-4">
                <label for="code" class="block text-gray-700 text-sm font-bold mb-2">Verification Code</label>
                <input type="text" id="code" name="code" required maxlength="6" pattern="\d{6}"
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div class="flex justify-center">
                <button type="submit" class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Verify
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>