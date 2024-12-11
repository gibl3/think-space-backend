<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once 'db-config.php';


$response = [
    'success' => false,
    'message' => ''
];

try {
    $pdo = connectToDatabase();

    // Get JSON data from the request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    switch ($data['action']) {
        case 'getNoteById':
            $response = getNoteById($pdo, $data['id']);
            break;

        case 'getAllNotes':
            $response = getAllNotes($pdo);
            break;

        case 'addNote':
            $response = addNote($pdo, $data['note']);
            break;

        case 'updateNote':
            $response = updateNote($pdo, $data['id'], $data['note']);
            break;

        case 'deleteNote':
            $response = deleteNote($pdo, $data['id']);
            break;

        case 'searchNotes':
            $response = searchNotes($pdo, $data['query']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Send the response back to the frontend
echo json_encode($response);
exit();

function getAllNotes($pdo)
{
    $userId = $_SESSION['user_id'];

    // Prepare and execute the query
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = :user_id ORDER BY updated_at DESC");
    $stmt->execute(['user_id' => $userId]);

    return [
        'notes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'success' => true
    ];
}

function getNoteById($pdo, $id)
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        return $note ? [
            'success' => true,
            'note' => $note
        ] : [
            'success' => false,
            'message' => 'Note not found'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "Database error: " . $e->getMessage()
        ];
    }
}


function addNote($pdo, $noteData)
{
    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, color, content, created_at, updated_at) VALUES (:userId, :title, :color, :content, :createdAt, :updatedAt)");

    $stmt->execute([
        ':userId' => $userId,
        ':title' => $noteData['title'],
        ':color' => $noteData['color'],
        ':content' => $noteData['content'],
        ':createdAt' => $noteData['createdAt'],
        ':updatedAt' => $noteData['updatedAt']
    ]);

    return [
        'id' => $pdo->lastInsertId(),
        'success' => true
    ];
}

function updateNote($pdo, $noteId, $noteData)
{
    $stmt = $pdo->prepare("UPDATE notes SET title = :title, color = :color, content = :content, updated_at = :updatedAt WHERE id = :id");
    $stmt->execute([
        ':id' => $noteId,
        ':title' => $noteData['title'],
        ':color' => $noteData['color'],
        ':content' => $noteData['content'],
        ':updatedAt' => $noteData['updatedAt']
    ]);

    return [
        'success' => true
    ];
}

function deleteNote($pdo, $noteId)
{
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = :id");
    $stmt->execute([':id' => $noteId]);

    return [
        'success' => true
    ];
}

function searchNotes($pdo, $query)
{
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = :userId AND (title LIKE :query OR content LIKE :query OR color LIKE :query) ORDER BY updated_at DESC");
    $stmt->execute([
        ':userId' => $userId,
        ':query' => "%{$query}%"
    ]);

    return [
        'notes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'success' => true
    ];
}
