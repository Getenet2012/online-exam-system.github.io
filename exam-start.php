<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$examId = $_GET['exam'] ?? '';
$userId = $_SESSION['user_id'];

// Check if exam exists and is published
$stmt = $pdo->prepare("SELECT e.*, ec.categoryID 
                      FROM exams e
                      JOIN exam_categories ec ON e.categoryID = ec.categoryID
                      WHERE e.examID = ? AND e.status = 'published'");
$stmt->execute([$examId]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    $_SESSION['error'] = 'Exam not found or not available';
    redirect('dashboard.php');
}

// Check authorization
$stmt = $pdo->prepare("SELECT 1 FROM user_exam_assignments 
                      WHERE userID = ? AND examID = ? LIMIT 1");
$stmt->execute([$userId, $examId]);
$isAuthorized = $stmt->fetch();

if (!$isAuthorized) {
    $_SESSION['error'] = 'You are not authorized to take this exam';
    redirect('dashboard.php');
}

// Handle exam session
try {
    $pdo->beginTransaction();

    // Check active session
    $stmt = $pdo->prepare("SELECT * FROM exam_sessions 
                          WHERE userID = ? AND examID = ? AND status = 'in_progress'
                          FOR UPDATE");
    $stmt->execute([$userId, $examId]);
    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check completed session
    $stmt = $pdo->prepare("SELECT 1 FROM exam_sessions 
                          WHERE userID = ? AND examID = ? AND status = 'graded'");
    $stmt->execute([$userId, $examId]);
    $completedSession = $stmt->fetch();

    if ($completedSession) {
        $_SESSION['error'] = 'You have already completed this exam';
        $pdo->commit();
        redirect('dashboard.php');
    }

    // Start new session if needed
    if (!$activeSession) {
        $sessionId = generateID();
        $startTime = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("INSERT INTO exam_sessions 
                              (sessionID, userID, examID, startTime, status) 
                              VALUES (?, ?, ?, ?, 'in_progress')");
        $stmt->execute([$sessionId, $userId, $examId, $startTime]);
        
        logActivity($userId, 'exam_start', "Started exam: {$exam['title']}");
        $activeSession = ['sessionID' => $sessionId, 'startTime' => $startTime];
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error starting exam session';
    redirect('dashboard.php');
}

// Get exam questions
$stmt = $pdo->prepare("SELECT q.* FROM questions q 
                      WHERE q.examID = ? AND q.isActive = TRUE ORDER BY RAND()");
$stmt->execute([$examId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    $_SESSION['error'] = 'No active questions found for this exam';
    redirect('dashboard.php');
}

// Process questions
foreach ($questions as &$question) {
    if ($question['type'] === 'multiple_choice') {
        $stmt = $pdo->prepare("SELECT * FROM question_options 
                              WHERE questionID = ? ORDER BY RAND()");
        $stmt->execute([$question['questionID']]);
        $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
unset($question);

// Calculate remaining time
try {
    $startTime = new DateTime($activeSession['startTime']);
    $endTime = clone $startTime;
    $endTime->add(new DateInterval("PT{$exam['duration']}M"));
    $now = new DateTime();
    $remaining = $now->diff($endTime);
    $remainingMinutes = ($remaining->h * 60) + $remaining->i;
    $remainingSeconds = $remaining->s;

    if ($now > $endTime) {
        redirect("exam-submit.php?session={$activeSession['sessionID']}");
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error calculating exam time';
    redirect('dashboard.php');
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-1 py-1 max-w-3xl relative">
    <div class="sticky top-0 z-50 bg-white py-4 shadow-sm border-b border-gray-200">
        <div class="container mx-auto flex justify-between items-center px-4">
            <h4 class="text-2xl font-bold text-gray-800 truncate max-w-xs"><?php echo htmlspecialchars($exam['title']); ?></h4>
            <div id="timer-container" class="bg-green-600 text-white px-3 py-2 rounded-lg shadow-xs flex items-center">
                <i class="fas fa-clock mr-2"></i> 
                <span id="timer" class="font-mono"><?php printf("%02d:%02d", $remainingMinutes, $remainingSeconds); ?></span>
            </div>
        </div>
    </div>
    
    <form id="examForm" action="exam-submit.php" method="POST">
        <input type="hidden" name="sessionID" value="<?php echo htmlspecialchars($activeSession['sessionID']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="space-y-8">
            <?php foreach ($questions as $index => $question): ?>
                <div class="bg-white rounded-lg shadow-sm p-6 question" id="q<?php echo $index + 1; ?>" <?php echo $index > 0 ? 'style="display:none;"' : ''; ?> data-question-id="<?php echo $question['questionID']; ?>">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Question <?php echo $index + 1; ?></h2>
                    
                    <p class="text-gray-700 mb-6 text-lg"><?php echo htmlspecialchars($question['text']); ?></p>
                    
                    <?php if (!empty($question['imagePath'])): ?>
                        <div class="mb-6">
                            <img src="admin/uploads/<?php echo htmlspecialchars($question['imagePath']); ?>" 
                                 alt="Question image" class="max-w-full h-auto rounded-lg border border-gray-200">
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="questions[]" value="<?php echo htmlspecialchars($question['questionID']); ?>">
                    
                    <?php if ($question['type'] === 'multiple_choice'): ?>
                        <div class="space-y-3">
                            <?php foreach ($question['options'] as $option): ?>
                                <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    <input type="radio" id="option_<?php echo $option['optionID']; ?>" 
                                           name="answers[<?php echo $question['questionID']; ?>]" 
                                           value="<?php echo htmlspecialchars($option['optionID']); ?>"
                                           class="h-5 w-5 text-primary focus:ring-primary border-gray-300">
                                    <label for="option_<?php echo $option['optionID']; ?>" class="ml-3 text-gray-700 cursor-pointer">
                                        <?php echo htmlspecialchars($option['text']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['type'] === 'true_false'): ?>
                        <div class="space-y-3">
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <input type="radio" id="true_<?php echo $question['questionID']; ?>" 
                                       name="answers[<?php echo $question['questionID']; ?>]" 
                                       value="true"
                                       class="h-5 w-5 text-primary focus:ring-primary border-gray-300">
                                <label for="true_<?php echo $question['questionID']; ?>" class="ml-3 text-gray-700 cursor-pointer">True</label>
                            </div>
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <input type="radio" id="false_<?php echo $question['questionID']; ?>" 
                                       name="answers[<?php echo $question['questionID']; ?>]" 
                                       value="false"
                                       class="h-5 w-5 text-primary focus:ring-primary border-gray-300">
                                <label for="false_<?php echo $question['questionID']; ?>" class="ml-3 text-gray-700 cursor-pointer">False</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between mt-8 pt-4 border-t border-gray-200">
                        <?php if ($index > 0): ?>
                            <button type="button" onclick="showQuestion(<?php echo $index; ?>)" 
                                    class="text-primary hover:text-green-700 font-medium flex items-center">
                                <i class="fas fa-arrow-left mr-1"></i> Previous
                            </button>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <?php if ($index < count($questions) - 1): ?>
                            <button type="button" onclick="showQuestion(<?php echo $index + 2; ?>)" 
                                    class="bg-primary hover:bg-green-700 text-white font-medium py-2 px-6 rounded flex items-center">
                                Next <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" id="submitBtn" onclick="validateExamSubmission()" 
                                    class="bg-primary hover:bg-green-700 text-white font-medium py-2 px-6 rounded flex items-center">
                                <i class="fas fa-paper-plane mr-1"></i> Submit Exam
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="fixed bottom-0 left-0 right-0 bg-white shadow-lg p-4 border-t border-gray-200 z-40">
            <div class="container mx-auto max-w-3xl flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Question <span id="current-question">1</span> of <?php echo count($questions); ?>
                </div>
                <div class="flex space-x-2 overflow-x-auto py-1 max-w-xs md:max-w-full">
                    <?php foreach ($questions as $index => $question): ?>
                        <button type="button" onclick="showQuestion(<?php echo $index + 1; ?>)" 
                                class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full border border-gray-300 hover:bg-gray-100 transition-colors question-nav <?php echo $index === 0 ? 'bg-primary text-white border-primary' : ''; ?>"
                                data-question-id="<?php echo $question['questionID']; ?>">
                            <?php echo $index + 1; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Unanswered Questions Modal -->
<div id="unansweredModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                <i class="fas fa-exclamation-circle text-yellow-600"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-3">Unanswered Questions</h3>
            <div class="mt-2">
                <p class="text-sm text-gray-500">
                    You have <span id="unansweredCount">0</span> unanswered questions. Would you like to review them before submitting?
                </p>
                <div id="unansweredList" class="mt-4 text-left max-h-40 overflow-y-auto"></div>
            </div>
            <div class="mt-4 flex justify-center space-x-3">
                <button type="button" onclick="hideUnansweredModal()" 
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none">
                    Review Questions
                </button>
                <button type="button" onclick="submitExamAnyway()" 
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none">
                    Submit Anyway
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Timer with forced submission at 30 seconds
let warningShown = false;
let timerInterval;
let unansweredQuestions = [];

function updateTimer() {
    const timerElement = document.getElementById('timer');
    const timerContainer = document.getElementById('timer-container');
    let [minutes, seconds] = timerElement.textContent.split(':').map(Number);
    const totalSeconds = (minutes * 60) + seconds;

    // Update timer display
    if (seconds === 0) {
        if (minutes === 0) {
            forceExamSubmit();
            return;
        }
        minutes--;
        seconds = 59;
    } else {
        seconds--;
    }
    timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

    // Visual warnings
    if (totalSeconds <= 30 && !warningShown) {
        warningShown = true;
        timerContainer.classList.remove('bg-green-600', 'bg-yellow-500');
        timerContainer.classList.add('bg-red-600', 'animate-pulse');
        showTimeWarningModal();
    } 
    else if (totalSeconds <= 120) {
        timerContainer.classList.remove('bg-green-600');
        timerContainer.classList.add('bg-yellow-500');
    }
}

function showTimeWarningModal() {
    const modal = document.createElement('div');
    modal.id = 'timeWarningModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 animate-bounce">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-3">Time Almost Up!</h3>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">
                        Only 30 seconds remaining! Please submit your exam NOW to avoid losing your answers.
                    </p>
                </div>
                <div class="mt-4 flex justify-center">
                    <button type="button" onclick="forceExamSubmit()" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Now
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Auto-submit after 20 seconds if user doesn't
    setTimeout(() => {
        if (document.getElementById('timeWarningModal')) {
            forceExamSubmit();
        }
    }, 20000);
}

function validateExamSubmission() {
    // Check for unanswered questions
    unansweredQuestions = [];
    const questionElements = document.querySelectorAll('.question');
    
    questionElements.forEach(question => {
        const questionId = question.dataset.questionId;
        const answerSelected = question.querySelector('input[type="radio"]:checked');
        
        if (!answerSelected) {
            unansweredQuestions.push({
                id: questionId,
                number: question.querySelector('h2').textContent.replace('Question ', '')
            });
        }
    });
    
    if (unansweredQuestions.length > 0) {
        showUnansweredModal();
    } else {
        confirmExamSubmission();
    }
}

function showUnansweredModal() {
    const modal = document.getElementById('unansweredModal');
    const countElement = document.getElementById('unansweredCount');
    const listElement = document.getElementById('unansweredList');
    
    countElement.textContent = unansweredQuestions.length;
    listElement.innerHTML = '';
    
    unansweredQuestions.forEach(question => {
        const item = document.createElement('div');
        item.className = 'py-1 flex items-center';
        item.innerHTML = `
            <span class="font-medium">Question ${question.number}</span>
            <button onclick="jumpToQuestion(${question.number})" 
                    class="ml-auto text-sm text-blue-600 hover:text-blue-800">
                Go to question
            </button>
        `;
        listElement.appendChild(item);
    });
    
    modal.classList.remove('hidden');
}

function hideUnansweredModal() {
    document.getElementById('unansweredModal').classList.add('hidden');
}

function jumpToQuestion(questionNumber) {
    hideUnansweredModal();
    showQuestion(questionNumber);
}

function submitExamAnyway() {
    hideUnansweredModal();
    confirmExamSubmission();
}

function confirmExamSubmission() {
    if (confirm('Are you sure you want to submit your exam?')) {
        forceExamSubmit();
    }
}

function forceExamSubmit() {
    // Remove modals if exist
    const timeModal = document.getElementById('timeWarningModal');
    if (timeModal) timeModal.remove();
    
    const unansweredModal = document.getElementById('unansweredModal');
    if (unansweredModal) unansweredModal.classList.add('hidden');
    
    // Disable submit button and show loading
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Submitting...';
    }
    
    // Submit form
    document.getElementById('examForm').submit();
}

// Start timer
updateTimer();
timerInterval = setInterval(updateTimer, 1000);

// Question navigation
function showQuestion(questionNum) {
    document.querySelectorAll('.question').forEach(q => q.style.display = 'none');
    document.getElementById('q' + questionNum).style.display = 'block';
    document.getElementById('current-question').textContent = questionNum;
    
    document.querySelectorAll('.question-nav').forEach((nav, index) => {
        if (index === questionNum - 1) {
            nav.classList.add('bg-primary', 'text-white', 'border-primary');
            nav.classList.remove('hover:bg-gray-100');
        } else {
            nav.classList.remove('bg-primary', 'text-white', 'border-primary');
            nav.classList.add('hover:bg-gray-100');
        }
    });
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Prevent accidental navigation
window.addEventListener('beforeunload', function(e) {
    if (!window.examSubmitted) {
        e.preventDefault();
        e.returnValue = 'Your exam progress will be lost if you leave this page. Are you sure?';
        return e.returnValue;
    }
});

// Handle form submission
document.getElementById('examForm').addEventListener('submit', function() {
    window.examSubmitted = true;
    clearInterval(timerInterval);
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Submitting...';
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const currentQuestion = parseInt(document.getElementById('current-question').textContent);
    const totalQuestions = <?php echo count($questions); ?>;
    
    if (e.key === 'ArrowLeft' && currentQuestion > 1) {
        showQuestion(currentQuestion - 1);
    } else if (e.key === 'ArrowRight' && currentQuestion < totalQuestions) {
        showQuestion(currentQuestion + 1);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>