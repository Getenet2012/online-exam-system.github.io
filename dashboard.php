<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Get user's upcoming exams (only assigned ones)
$stmt = $pdo->prepare("
    SELECT e.*, ec.title as category_title 
    FROM exams e 
    JOIN exam_categories ec ON e.categoryID = ec.categoryID
    JOIN user_exam_assignments uea ON e.examID = uea.examID
    WHERE e.status = 'published'
    AND uea.userID = ?
    AND e.examID NOT IN (
        SELECT examID FROM exam_sessions 
        WHERE userID = ? AND status = 'graded'
    )
    ORDER BY e.createdAt DESC
");
$stmt->execute([$userId, $userId]);
$upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's completed exams with proper score calculation
$stmt = $pdo->prepare("
    SELECT 
        e.*, 
        es.score as percentage_score, 
        es.endTime, 
        es.sessionID, 
        ec.title as category_title,
        (SELECT COUNT(*) FROM questions WHERE examID = e.examID) as total_questions,
        (SELECT COUNT(*) FROM answers a WHERE a.sessionID = es.sessionID AND a.isCorrect = 1) as correct_answers,
        (SELECT SUM(pointsEarned) FROM answers a WHERE a.sessionID = es.sessionID AND a.isCorrect = 1) as total_points
    FROM exam_sessions es
    JOIN exams e ON es.examID = e.examID
    LEFT JOIN exam_categories ec ON e.categoryID = ec.categoryID
    WHERE es.userID = ? AND es.status = 'graded'
    ORDER BY es.endTime DESC
");
$stmt->execute([$userId]);
$completedExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
    
    <!-- Upcoming Exams -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Upcoming Exams</h2>
        
        <?php if (empty($upcomingExams)): ?>
            <p class="text-gray-600">You have no upcoming exams at this time.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($upcomingExams as $exam): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <h3 class="font-bold text-lg text-gray-800 mb-2"><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <p class="text-gray-600 text-sm mb-2">
                            <i class="fas fa-clock mr-1"></i> Duration: <?php echo (int)$exam['duration']; ?> minutes
                        </p>
                        <?php if ($exam['category_title']): ?>
                            <p class="text-gray-600 text-sm mb-2">
                                <i class="fas fa-tag mr-1"></i> Category: <?php echo htmlspecialchars($exam['category_title']); ?>
                            </p>
                        <?php endif; ?>
                        <div class="mt-4">
                            <a href="exam-start.php?exam=<?php echo htmlspecialchars($exam['examID']); ?>" 
                               class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block">
                                Start Exam
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Completed Exams -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Completed Exams</h2>
        
        <?php if (empty($completedExams)): ?>
            <p class="text-gray-600">You haven't completed any exams yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score (Correct)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points Earned</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($completedExams as $exam): 
                            $percentage = round($exam['percentage_score'], 2);
                            $passed = $percentage >= $exam['passingScore'];
                        ?>
                            <tr class="<?php echo $passed ? 'bg-green-50' : 'bg-red-50'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($exam['title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-gray-500"><?php echo htmlspecialchars($exam['category_title'] ?? 'Uncategorized'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-gray-900 font-bold">
                                        <?php echo (int)$exam['correct_answers']; ?> out of <?php echo (int)$exam['total_questions']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-gray-900 font-bold">
                                        <?php echo (int)$exam['total_points']; ?> pts
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-gray-900 font-bold <?php echo $passed ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $percentage; ?>%
                                        <?php if ($passed): ?>
                                            <span class="ml-1 text-green-600">✓</span>
                                        <?php else: ?>
                                            <span class="ml-1 text-red-600">✗</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($exam['endTime'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="exam-result.php?session=<?php echo htmlspecialchars($exam['sessionID']); ?>" class="text-primary hover:text-green-700">
                                        <i class="fas fa-eye mr-1"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>