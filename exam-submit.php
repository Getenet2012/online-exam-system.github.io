<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$sessionId = $_POST['sessionID'] ?? '';
$userId = $_SESSION['user_id'];

// Validate session
$stmt = $pdo->prepare("SELECT * FROM exam_sessions WHERE sessionID = ? AND userID = ? AND status = 'in_progress'");
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    $_SESSION['error'] = 'Invalid exam session';
    redirect('dashboard.php');
}

// Get exam details
$stmt = $pdo->prepare("SELECT * FROM exams WHERE examID = ?");
$stmt->execute([$session['examID']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

// Process answers
$questions = $_POST['questions'] ?? [];
$answers = $_POST['answers'] ?? [];
$totalQuestions = count($questions);
$correctAnswers = 0;
$totalPoints = 0;
$earnedPoints = 0;

try {
    $pdo->beginTransaction();
    
    // Record answers and calculate score
    foreach ($questions as $questionId) {
        $questionStmt = $pdo->prepare("SELECT * FROM questions WHERE questionID = ?");
        $questionStmt->execute([$questionId]);
        $question = $questionStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalPoints += $question['points'];
        $answerId = generateID();
        $userAnswer = $answers[$questionId] ?? '';
        $isCorrect = 0;
        $pointsEarned = 0;
        
        if ($question['type'] === 'multiple_choice') {
            // Check if selected option is correct
            $optionStmt = $pdo->prepare("SELECT isCorrect FROM question_options WHERE optionID = ?");
            $optionStmt->execute([$userAnswer]);
            $option = $optionStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($option && $option['isCorrect']) {
                $isCorrect = 1;
                $correctAnswers++;
                $pointsEarned = $question['points'];
                $earnedPoints += $question['points'];
            }
        } elseif ($question['type'] === 'true_false') {
            // For true/false, we assume the correct answer is stored in the question text
            $correctAnswer = strtolower(trim($question['text'])) === 'true' ? 'true' : 'false';
            
            if ($userAnswer === $correctAnswer) {
                $isCorrect = 1;
                $correctAnswers++;
                $pointsEarned = $question['points'];
                $earnedPoints += $question['points'];
            }
        } else {
            // For short answer, we'll mark it for manual review
            $isCorrect = 0; // Needs manual grading
            $pointsEarned = 0;
        }
        
        // Record the answer
        $answerStmt = $pdo->prepare("INSERT INTO answers (answerID, sessionID, questionID, text, isCorrect, pointsEarned) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
        $answerStmt->execute([$answerId, $sessionId, $questionId, $userAnswer, $isCorrect, $pointsEarned]);
    }
    
    // Calculate score percentage
    $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
    
    // Update exam session
    $updateStmt = $pdo->prepare("UPDATE exam_sessions SET endTime = NOW(), score = ?, status = 'graded' WHERE sessionID = ?");
    $updateStmt->execute([$score, $sessionId]);
    
    $pdo->commit();
    
    logActivity($userId, 'exam_submit', "Submitted exam: {$exam['title']} with score: {$score}%");
    redirect("exam-result.php?session=$sessionId");
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing your exam: ' . $e->getMessage();
    redirect('dashboard.php');
}
?>