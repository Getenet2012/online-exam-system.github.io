<?php
require_once 'config.php';

if (isLoggedIn()) {
    // Redirect based on user role
    if ($_SESSION['user_role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['userID'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET lastLogin = NOW() WHERE userID = ?");
            $updateStmt->execute([$user['userID']]);
            
            logActivity($user['userID'], 'login', 'User logged in');
            
            // Redirect based on role after successful login
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-primary py-4 px-6">
        <h2 class="text-white text-xl font-semibold">CPDi Online Exam System </h2>
    </div>
    <div class="p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" id="email" name="email" required
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Login
                </button>
                <a href="forgot-password.php" class="inline-block align-baseline font-bold text-sm text-primary hover:text-green-700">
                    Forgot Password?
                </a>
            </div>
        </form>
        
        <div class="mt-4 text-center">
            <p class="text-gray-600 text-sm">Don't have an account? <a href="register.php" class="text-primary font-bold">Register here</a></p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>