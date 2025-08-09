<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$user = getUserData($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        // Validate email
        if ($email !== $user['email']) {
            // Check if new email already exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND userID != ?");
            $stmt->execute([$email, $userId]);
            
            if ($stmt->fetch()) {
                $error = 'Email already in use by another account';
            }
        }
        
        if (!$error) {
            $updateData = [$name, $email, $userId];
            $passwordChanged = false;
            
            // Check if changing password
            if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'All password fields are required to change password';
                } elseif (!verifyPassword($currentPassword, $user['password'])) {
                    $error = 'Current password is incorrect';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'New password must be at least 8 characters long';
                } else {
                    $hashedPassword = hashPassword($newPassword);
                    $updateData[] = $hashedPassword;
                    $passwordChanged = true;
                }
            }
            
            if (!$error) {
                $sql = "UPDATE users SET name = ?, email = ?" . ($passwordChanged ? ", password = ?" : "") . " WHERE userID = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateData);
                
                // Update session if email changed
                if ($email !== $user['email']) {
                    $_SESSION['user_name'] = $name;
                }
                
                logActivity($userId, 'profile_update', 'Profile updated' . ($passwordChanged ? ' (password changed)' : ''));
                $success = 'Profile updated successfully!';
                
                // Refresh user data
                $user = getUserData($userId);
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Profile Settings</h1>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form action="profile.php" method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Full Name*</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email*</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password (leave blank to keep unchanged)</label>
                    <input type="password" id="current_password" name="current_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password (min 8 characters)</label>
                    <input type="password" id="new_password" name="new_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Profile
                </button>
            </div>
        </form>
        
      
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>