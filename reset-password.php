<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Validate token
$tokenData = validateToken($token);
if (!$tokenData) {
    $error = 'Invalid or expired token';
    $token = ''; // Clear token to prevent form submission
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $password = sanitizeInput($_POST['password']);
    $confirmPassword = sanitizeInput($_POST['confirm_password']);
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Both fields are required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        try {
            // Update password
            $hashedPassword = hashPassword($password);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $tokenData['email']]);
            
            // Delete the token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            
            // Get user for logging
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$tokenData['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            logActivity($user['userID'], 'password_reset', 'Password reset successful');
            $success = 'Password reset successfully! You can now login with your new password.';
            
            // Clear token to prevent form resubmission
            $token = '';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-primary py-4 px-6">
        <h2 class="text-white text-xl font-semibold">Reset Password</h2>
    </div>
    <div class="p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="text-primary font-bold hover:text-green-700">Go to Login Page</a>
            </div>
        <?php elseif ($token): ?>
            <form action="reset-password.php?token=<?php echo $token; ?>" method="POST">
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">New Password* (min 8 characters)</label>
                    <input type="password" id="password" name="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password*</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="flex items-center justify-center">
                    <button type="submit" class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center">
                <p class="text-gray-700">Please request a new password reset link.</p>
                <a href="forgot-password.php" class="text-primary font-bold hover:text-green-700 mt-2 inline-block">Forgot Password</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>