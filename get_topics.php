<?php
// get_topics.php – Returns topics filtered by class level

require_once 'topics_data.php';

$subject = $_GET['subject'] ?? '';
$class = $_GET['class'] ?? '';

// Determine which books to include
$include_book3 = true;
$include_book4 = ($class === 'Form 4');

$topics = [];

switch ($subject) {
    case 'Physics':
        $topics = array_merge($physics_book3);
        if ($include_book4) {
            $topics = array_merge($topics, $physics_book4);
        }
        break;
    case 'Biology':
        $topics = array_merge($biology_book3);
        if ($include_book4) {
            $topics = array_merge($topics, $biology_book4);
        }
        break;
    case 'Chemistry':
        $topics = array_merge($chemistry_book3);
        if ($include_book4) {
            $topics = array_merge($topics, $chemistry_book4);
        }
        break;
    case 'English':
        // Always show all papers (Paper I, II, III) regardless of class for English
        $topics = array_merge($english_paper1, $english_paper2, $english_paper3);
        break;
}

header('Content-Type: application/json');
echo json_encode($topics);
?>