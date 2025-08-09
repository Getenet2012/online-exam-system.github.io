<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Get all published exams
$stmt = $pdo->query("
    SELECT e.*, ec.title as category_title 
    FROM exams e 
    LEFT JOIN exam_categories ec ON e.categoryID = ec.categoryID
    WHERE e.status = 'published'
    ORDER BY e.createdAt DESC
");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Available Exams</h1>
    
    <?php if (empty($exams)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <p class="text-gray-600">No exams available at this time.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($exams as $exam): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo $exam['title']; ?></h3>
                        <?php if ($exam['category_title']): ?>
                            <p class="text-gray-600 text-sm mb-2">
                                <i class="fas fa-tag mr-1"></i> <?php echo $exam['category_title']; ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-gray-600 text-sm mb-2">
                            <i class="fas fa-clock mr-1"></i> Duration: <?php echo $exam['duration']; ?> minutes
                        </p>
                        <p class="text-gray-600 text-sm mb-4">
                            <i class="fas fa-star mr-1"></i> Passing Score: <?php echo $exam['passingScore']; ?>%
                        </p>
                        <p class="text-gray-700 mb-4"><?php echo substr($exam['description'], 0, 100); ?>...</p>
                        
                        <?php 
                        // Check if user has already taken this exam
                        $stmt = $pdo->prepare("SELECT * FROM exam_sessions WHERE userID = ? AND examID = ? AND status = 'graded'");
                        $stmt->execute([$_SESSION['user_id'], $exam['examID']]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result): ?>
                            <div class="bg-gray-100 p-3 rounded-lg mb-4">
                                <p class="text-gray-800 font-medium">
                                    Your Score: <?php echo $result['score']; ?>%
                                    <?php if ($result['score'] >= $exam['passingScore']): ?>
                                        <span class="text-green-600 ml-2"><i class="fas fa-check-circle"></i> Passed</span>
                                    <?php else: ?>
                                        <span class="text-red-600 ml-2"><i class="fas fa-times-circle"></i> Failed</span>
                                    <?php endif; ?>
                                </p>
                                <a href="exam-result.php?session=<?php echo $result['sessionID']; ?>" 
                                   class="text-primary hover:text-green-700 text-sm mt-1 inline-block">
                                    View Details
                                </a>
                            </div>
                        <?php else: ?>
                            <a href="exam-start.php?exam=<?php echo $exam['examID']; ?>" 
                               class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block">
                                Start Exam
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>