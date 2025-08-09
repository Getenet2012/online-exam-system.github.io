<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Get user's completed exams
$stmt = $pdo->prepare("
    SELECT e.*, es.score, es.endTime, es.sessionID, ec.title as category_title 
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
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Exam History</h1>
    
    <?php if (empty($completedExams)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <p class="text-gray-600">You haven't completed any exams yet.</p>
            <a href="exams.php" class="text-primary font-bold hover:text-green-700 mt-2 inline-block">Browse Available Exams</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($completedExams as $exam): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo $exam['title']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-500"><?php echo $exam['category_title'] ?? 'Uncategorized'; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold"><?php echo $exam['score']; ?>%</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $passed = $exam['score'] >= $exam['passingScore'];
                                $statusClass = $passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                $statusText = $passed ? 'Passed' : 'Failed';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($exam['endTime'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="exam-result.php?session=<?php echo $exam['sessionID']; ?>" class="text-primary hover:text-green-700">
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

<?php require_once 'includes/footer.php'; ?>