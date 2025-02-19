<?php
$tasks = file('todo.txt', FILE_IGNORE_NEW_LINES);
$data = [];

foreach ($tasks as $line) {
    list($task, $status, $priority, $category, $dueDate) = explode('|', $line);
    $data[] = [
        'Task' => $task,
        'Completed' => $status,
        'Priority' => $priority,
        'Category' => $category,
        'Due Date' => $dueDate
    ];
}

if ($_GET['format'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tasks.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($data[0]));
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
} elseif ($_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
}
?>
