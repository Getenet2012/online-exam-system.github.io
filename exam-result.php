<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$sessionId = $_GET['session'] ?? '';
$userId = $_SESSION['user_id'];

// Get exam session details
$stmt = $pdo->prepare("
    SELECT es.*, e.title as exam_title, e.passingScore 
    FROM exam_sessions es
    JOIN exams e ON es.examID = e.examID
    WHERE es.sessionID = ? AND es.userID = ? AND es.status = 'graded'
");
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    $_SESSION['error'] = 'Exam results not found';
    redirect('dashboard.php');
}

// Get all answers for this session
$stmt = $pdo->prepare("
    SELECT a.*, q.text as question_text, q.type as question_type, q.points as question_points 
    FROM answers a
    JOIN questions q ON a.questionID = q.questionID
    WHERE a.sessionID = ?
    ORDER BY a.answeredAt
");
$stmt->execute([$sessionId]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalQuestions = count($answers);
$correctAnswers = array_reduce($answers, function($carry, $item) {
    return $carry + ($item['isCorrect'] ? 1 : 0);
}, 0);
$score = $session['score'];
$passed = $score >= $session['passingScore'];

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Exam Results: <?php echo $session['exam_title']; ?></h1>
                
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            
            <div class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-green-800">Correct Answers</h3>
                <p class="text-2xl font-bold text-green-600"><?php echo $correctAnswers; ?>/<?php echo $totalQuestions; ?></p>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-blue-800">Your Score</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo $score; ?>%</p>
            </div>
        </div>
        
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Performance Summary</h3>
            <div class="bg-gray-100 rounded-lg p-4">
                <div class="relative pt-1">
                    <div class="flex mb-2 items-center justify-between">
                        <div>
                            <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-white bg-primary">
                                Your Progress
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-semibold inline-block text-primary">
                                <?php echo $score; ?>%
                            </span>
                        </div>
                    </div>
                    <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                        <div style="width:<?php echo $score; ?>%" 
                             class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center <?php echo $passed ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Question Review</h3>
            <div class="space-y-4">
                <?php foreach ($answers as $index => $answer): ?>
                    <div class="border border-gray-200 rounded-lg p-4 <?php echo $answer['isCorrect'] ? 'bg-green-50' : 'bg-red-50'; ?>">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium text-gray-800">Question <?php echo $index + 1; ?></h4>
                            <span class="text-sm <?php echo $answer['isCorrect'] ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $answer['isCorrect'] ? 'Correct' : 'Incorrect'; ?>
                            </span>
                        </div>
                        <p class="text-gray-700 mb-2"><?php echo $answer['question_text']; ?></p>
                        
                        <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                            <p class="text-gray-600 text-sm mb-1">Your answer: 
                                <?php 
                                $stmt = $pdo->prepare("SELECT text FROM question_options WHERE optionID = ?");
                                $stmt->execute([$answer['text']]);
                                $option = $stmt->fetch(PDO::FETCH_ASSOC);
                                echo $option ? $option['text'] : 'Not answered';
                                ?>
                            </p>
                        <?php elseif ($answer['question_type'] === 'true_false'): ?>
                            <p class="text-gray-600 text-sm mb-1">Your answer: <?php echo $answer['text'] === 'true' ? 'True' : 'False'; ?></p>
                        <?php else: ?>
                            <p class="text-gray-600 text-sm mb-1">Your answer: <?php echo $answer['text'] ?: 'Not answered'; ?></p>
                        <?php endif; ?>
                        
                        <p class="text-sm font-medium">Points: <?php echo $answer['pointsEarned']; ?>/<?php echo $answer['question_points']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="flex justify-end">
            <a href="dashboard.php" class="bg-primary hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>