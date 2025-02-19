<?php

$taskFile = 'todo.txt';
$trashFile = 'trash.txt';

// Function to load tasks from file
function loadTasks($filename)
{
    $tasks = [];
    if (file_exists($filename)) {
        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $index => $line) {
            list($task, $status, $priority, $category, $dueDate) = explode('|', $line);
            $tasks[] = [
                'task' => $task,
                'done' => $status === '1',
                'priority' => $priority,
                'category' => $category,
                'dueDate' => $dueDate
            ];
        }
    }
    return $tasks;
}

// Function to save tasks to file
function saveTasks($filename, $tasks)
{
    $data = [];
    foreach ($tasks as $task) {
        $data[] = implode('|', [
            $task['task'],
            $task['done'] ? '1' : '0',
            $task['priority'],
            $task['category'],
            $task['dueDate']
        ]);
    }
    file_put_contents($filename, implode("\n", $data));
}

// Load tasks
$tasks = loadTasks($taskFile);

// Handle form submissions for adding/editing tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) && !empty($_POST['task'])) {
        if (isset($_POST['edit_index']) && $_POST['edit_index'] !== '') {
            // Edit task
            $index = (int)$_POST['edit_index'];
            $tasks[$index] = [
                'task' => $_POST['task'],
                'done' => false,
                'priority' => $_POST['priority'],
                'category' => $_POST['category'],
                'dueDate' => $_POST['due_date']
            ];
        } else {
            // Add new task
            $tasks[] = [
                'task' => $_POST['task'],
                'done' => false,
                'priority' => $_POST['priority'],
                'category' => $_POST['category'],
                'dueDate' => $_POST['due_date']
            ];
        }
    }
    saveTasks($taskFile, $tasks);
    header("Location: index.php");
    exit();
}
if (isset($_GET['edit'])) {
    $index = (int)$_GET['edit'];
    if (isset($tasks[$index])) {
        $taskToEdit = $tasks[$index];
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('taskInput').value = '{$taskToEdit['task']}';
                document.getElementById('priorityInput').value = '{$taskToEdit['priority']}';
                document.getElementById('categoryInput').value = '{$taskToEdit['category']}';
                document.getElementById('dueDateInput').value = '{$taskToEdit['dueDate']}';
                document.getElementById('editIndexInput').value = '{$index}';
                document.getElementById('taskModal').style.display = 'flex';
                document.getElementById('modalTitle').innerText = 'Edit Task';
            });
        </script>";
    }
}
// Mark task as done
if (isset($_GET['done'])) {
    $index = (int)$_GET['done'];
    if (isset($tasks[$index])) {
        $tasks[$index]['done'] = !$tasks[$index]['done'];
        saveTasks($taskFile, $tasks);
    }
    header("Location: index.php");
    exit();
}
// Delete a task
if (isset($_GET['delete'])) {
    $index = (int)$_GET['delete'];
    if (isset($tasks[$index])) {
        // Save task to trash before deletion
        $taskToDelete = $tasks[$index];
        $taskLine = implode('|', [
            $taskToDelete['task'],
            $taskToDelete['done'] ? '1' : '0',
            $taskToDelete['priority'],
            $taskToDelete['category'],
            $taskToDelete['dueDate']
        ]);

        // Check if the trash file is empty
        if (filesize($trashFile) > 0) {
            file_put_contents($trashFile, "\n" . $taskLine, FILE_APPEND); // Add newline only if not empty
        } else {
            file_put_contents($trashFile, $taskLine, FILE_APPEND); // No newline needed if empty 
        }

        array_splice($tasks, $index, 1);
        saveTasks($taskFile, $tasks);
    }
    header("Location: index.php");
    exit();
}
if (isset($_GET['delete_forever'])) {
    $index = (int)$_GET['delete_forever'];
    $trashTasks = file($trashFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (isset($trashTasks[$index])) {
        array_splice($trashTasks, $index, 1);
        file_put_contents($trashFile, implode("\n", $trashTasks)); // Rewrite file without the deleted task
    }

    header("Location: index.php");
    exit();
}
// Restore task from trash
if (isset($_GET['restore'])) {
    $trash = file($trashFile, FILE_IGNORE_NEW_LINES);

    if ($_GET['restore'] === 'all') {
        foreach ($trash as $line) {
            list($task, $status, $priority, $category, $dueDate) = explode('|', $line);
            $tasks[] = [
                'task' => $task,
                'done' => $status === '1',
                'priority' => $priority,
                'category' => $category,
                'dueDate' => $dueDate
            ];
        }
        file_put_contents($trashFile, ""); // Clear trash
    } else {
        $index = (int)$_GET['restore'];
        if (isset($trash[$index])) {
            list($task, $status, $priority, $category, $dueDate) = explode('|', $trash[$index]);
            $tasks[] = [
                'task' => $task,
                'done' => $status === '1',
                'priority' => $priority,
                'category' => $category,
                'dueDate' => $dueDate
            ];
            // Remove restored task
            array_splice($trash, $index, 1);
            file_put_contents($trashFile, implode(PHP_EOL, $trash)); // Re-save trash file after task removal
        }
    }

    saveTasks($taskFile, $tasks);
    header("Location: index.php");
    exit();
}
if (isset($_GET['delete_all'])) {
    file_put_contents($trashFile, ""); // Empty the trash file
    header("Location: index.php");
    exit();
}

// Load deleted tasks
$deletedTasks = loadTasks($trashFile);

// Export tasks as CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tasks.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Task', 'Status', 'Priority', 'Category', 'Due Date']);
    foreach ($tasks as $task) {
        fputcsv($output, [$task['task'], $task['done'] ? 'Completed' : 'Pending', $task['priority'], $task['category'], $task['dueDate']]);
    }
    fclose($output);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Force App</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <a href="?export=csv" class="export-btn"><button id="exportBtn">Export as CSV</button></a>
            <h1 id="table-title">Task Force App</h1>
            <button id="addTaskBtn">+ Add Task</button>
        </div>
        <div class="task-table-container">
            <table class="task-table">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Task</th>
                        <th onclick="sortTable(1)">Category</th>
                        <th onclick="sortTable(2)">Priority</th>
                        <th onclick="sortTable(3)">Due Date</th>
                        <th class="no-arrow">Status</th>
                        <th class="no-arrow">Actions</th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $index => $task): ?>
                        <tr class="row priority-<?= strtolower($task['priority']) ?>">
                            <td class="<?= $task['done'] ? 'completed' : '' ?>">
                                <?= htmlspecialchars($task['task']) ?>
                            </td>
                            <td class="<?= $task['done'] ? 'completed' : '' ?>"><?= htmlspecialchars($task['category']) ?></td>
                            <td class="<?= $task['done'] ? 'completed' : '' ?>"><?= htmlspecialchars($task['priority']) ?></td>
                            <td class="<?= $task['done'] ? 'completed' : '' ?>"><?= htmlspecialchars($task['dueDate']) ?></td>
                            <td><?= $task['done'] ? '✅ Completed' : '⏳ Pending' ?></td>
                            <td class="actions">
                                <a href="?done=<?= $index ?>" class="mark-done" title="Finish task">✔</a>
                                <a href="?delete=<?= $index ?>" class="delete-task" title="Delete task">&times;</a>
                                <a href="?edit=<?= $index ?>" class="edit-task" title="Edit task">✏️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="trash-container">
            <table class="task-table">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Task</th>
                        <th onclick="sortTable(1)">Category</th>
                        <th onclick="sortTable(2)">Priority</th>
                        <th onclick="sortTable(3)">Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deletedTasks as $index => $task): ?>
                        <tr>
                            <td><?= htmlspecialchars($task['task']) ?></td>
                            <td><?= htmlspecialchars($task['category']) ?></td>
                            <td><?= htmlspecialchars($task['priority']) ?></td>
                            <td><?= htmlspecialchars($task['dueDate']) ?></td>
                            <td>
                                <a href="?restore=<?= $index ?>" class="restore-task">♻ Restore</a>
                                <a href="?delete_forever=<?= $index ?>" class="delete-forever-task" onclick="return confirmDeleteForever()"><ion-icon name="trash"></ion-icon> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="?restore=all" class="restore-all-btn">Restore All</a>
            <a href="?delete_all" class="delete-all-btn" onclick="return confirmDeleteAll()">Delete All</a>
        </div>
        <button id="trash-bin"><ion-icon name="trash"></ion-icon> Trash Bin </button>
    </div>

    <!-- Modal for adding/editing tasks -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Add/Edit Task</h2>
            <span class="close" id="closeModal">&times;</span>
            <form action="" method="POST">
                <input type="text" name="task" id="taskInput" placeholder="Task Name" required>
                <select name="priority" id="priorityInput">
                    <option value="High">🔥 High</option>
                    <option value="Medium">⚡ Medium</option>
                    <option value="Low">✅ Low</option>
                </select>
                <select name="category" id="categoryInput">
                    <option value="Work">💼 Work</option>
                    <option value="Personal">🏡 Personal</option>
                    <option value="Normal">🙂 Normal</option>
                    <option value="Important">❗ Important</option>
                    <option value="Urgent">⏳ Urgent</option>
                </select>
                <input type="date" name="due_date" id="dueDateInput" required>
                <button type="submit" name="add">Save Task</button>
                <input type="hidden" name="edit_index" id="editIndexInput">
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById("taskModal");
            const addTaskBtn = document.getElementById("addTaskBtn");
            const closeModal = document.getElementById("closeModal");
            const trashBin = document.getElementById("trash-bin");
            const trashContainer = document.querySelector(".trash-container")
            const taskTableContainer = document.querySelector(".task-table-container")
            const tableTitle = document.getElementById("table-title");
            const exportBtn = document.getElementById("exportBtn");
            const header = document.querySelector(".header");

            addTaskBtn.addEventListener("click", function() {
                modal.style.display = "flex";
                document.getElementById("editIndexInput").value = '';
                document.getElementById("taskInput").value = '';
                document.getElementById("priorityInput").value = 'Low';
                document.getElementById("categoryInput").value = 'Work';
                document.getElementById("dueDateInput").value = '';
                document.getElementById("modalTitle").innerText = 'Add Task'; // Set to Add Task for new task
            });

            // Close the modal and redirect back to the main page
            closeModal.addEventListener("click", function() {
                modal.style.display = "none";
                window.location.href = "index.php"; // Redirect to the main page after closing
            });

            trashBin.addEventListener("click", function() {
                if (trashContainer.style.display === "block") {
                    // Show Task Table, Hide Trash
                    taskTableContainer.style.display = "block";
                    addTaskBtn.style.display = "block";
                    header.style.display = "flex";
                    exportBtn.style.display = "block";
                    trashContainer.style.display = "none";
                    trashBin.innerHTML = '<ion-icon name="trash"></ion-icon> Trash Bin'; // Add the trash icon and text
                    trashBin.style.backgroundColor = "red"; // Set to a red-orange color for Trash Bin
                    trashBin.classList.remove("task-table-active");
                    tableTitle.textContent = "Task Force App";
                } else {
                    // Show Trash, Hide Task Table
                    taskTableContainer.style.display = "none";
                    header.style.display = "block";
                    addTaskBtn.style.display = "none";
                    exportBtn.style.display = "none";
                    trashContainer.style.display = "block";
                    trashBin.innerHTML = '<ion-icon name="clipboard"></ion-icon> Task Table'; // Add icon for Task Table
                    trashBin.style.backgroundColor = "#4CAF50";
                    trashBin.classList.add("task-table-active");
                    tableTitle.textContent = "Deleted Tasks";
                }
            });
            // Add hover effect
            trashBin.addEventListener("mouseover", function() {
                if (trashBin.classList.contains("task-table-active")) {
                    trashBin.style.backgroundColor = "#005404"; // Green when active
                } else {
                    trashBin.style.backgroundColor = "darkred"; // Red when not active
                }
            });

            trashBin.addEventListener("mouseout", function() {
                if (trashBin.classList.contains("task-table-active")) {
                    trashBin.style.backgroundColor = "#4CAF50"; // Green when active
                } else {
                    trashBin.style.backgroundColor = "red"; // Red when not active
                }
            });
            // Check if it's an edit or add operation and set the title accordingly
            if (document.getElementById("editIndexInput").value !== '') {
                document.getElementById("modalTitle").innerText = 'Edit Task'; // Set to Edit Task if editing
            }

            window.addEventListener("click", function(event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                    window.location.href = "index.php"; // Redirect to the main page if clicked outside the modal
                }
            });
        });
        document.querySelectorAll('.task-table th').forEach(header => {
            header.addEventListener('click', function() {
                const columnIndex = Array.from(this.parentNode.children).indexOf(this);

                // Check if the column is already sorted
                const isDescending = this.classList.contains('active');
                // Toggle the active class to change the arrow direction
                this.classList.toggle('active');

                // Optionally, handle sorting logic here
                sortTable(columnIndex, isDescending);
            });
        });

        function sortTable(columnIndex, isDescending) {
            const table = document.querySelector(".task-table");
            const rows = Array.from(table.rows).slice(1); // Skip the header row

            let sortedRows = rows.sort((rowA, rowB) => {
                const cellA = rowA.cells[columnIndex].innerText.toLowerCase();
                const cellB = rowB.cells[columnIndex].innerText.toLowerCase();

                if (cellA < cellB) return isDescending ? 1 : -1;
                if (cellA > cellB) return isDescending ? -1 : 1;
                return 0;
            });

            // Append the sorted rows back to the table body
            table.tBodies[0].append(...sortedRows);
        }

        function confirmDeleteAll() {
            return confirm("Are you sure you want to delete all tasks permanently?");
        }

        function confirmDeleteForever() {
            return confirm("Are you sure you want to delete this task forever? This cannot be undone.");
        }
    </script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>

</html>