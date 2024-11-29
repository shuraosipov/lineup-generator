<?php
// Check if a file is uploaded
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $uploadedFile = $_FILES['csv_file'];

    // Ensure file was uploaded without error
    if ($uploadedFile['error'] == UPLOAD_ERR_OK) {
        $csvFilePath = $uploadedFile['tmp_name'];
    } else {
        die("<h1>Error: File upload failed. Please try again.</h1>");
    }
} else {
    // Display file upload form if no file is uploaded
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Upload DKSalaries.csv File</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                margin-top: 50px;
            }
            .upload-box {
                border: 2px dashed #ccc;
                padding: 50px;
                width: 400px;
                margin: 0 auto;
                background-color: #f9f9f9;
            }
            input[type="file"] {
                margin: 20px 0;
            }
            button {
                background-color: #28a745;
                color: white;
                padding: 10px 20px;
                border: none;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <div class="upload-box">
            <h1>Please upload your DKSalaries.csv file</h1>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required>
                <br>
                <label for="num_lineups">Number of Lineups to Generate:</label>
                <input type="number" name="num_lineups" id="num_lineups" min="1" max="15000" value="15000" required>
                <br><br>
                <button type="submit">Upload and Generate Lineups</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop execution here if no file is uploaded
}

// Load players data from CSV
$playersData = array_map('str_getcsv', file($csvFilePath));
$players = [];

// Extract player data from CSV (assuming the CSV structure has headers)
$headers = array_map('trim', $playersData[0]);
array_shift($playersData); // Remove headers

// Debug: Print headers to verify the structure of the CSV file
echo "<h3>Debug: Headers from CSV</h3><pre>" . print_r($headers, true) . "</pre>";

// Function to validate a player row based on the expected headers
function isValidPlayerRow($row, $headers) {
    // Check if the number of fields in the row matches the number of headers
    if (count($headers) == count($row)) {
        $player = array_combine($headers, array_map('trim', $row));
        // Ensure that the required fields are present and valid
        return isset($player['Salary']) && is_numeric($player['Salary']) && 
               !empty($player['Name + ID']) && !empty($player['Position']);
    }
    return false;
}

// Loop through each row of player data and validate it before adding to players array
foreach ($playersData as $rowIndex => $row) {
    // Ensure the row is valid
    if (isValidPlayerRow($row, $headers)) {
        $player = array_combine($headers, array_map('trim', $row));
        $player['salary'] = (int) $player['Salary']; // Convert salary to integer
        $players[] = $player;
    } else {
        // Debug: Print problematic rows to help identify issues in the CSV
        echo "<h3>Debug: Skipping invalid row at index $rowIndex (missing required fields)</h3><pre>" . print_r($row, true) . "</pre>";
    }
}

// Check if players array is empty
if (empty($players)) {
    die("<h1>Error: No valid players found in the data file. Please check the CSV content.</h1>");
}

// DraftKings contest rules
$SALARY_CAP = 50000; // Define the maximum salary cap for a lineup
$ROSTER_SIZE = 6; // Define the roster size: 1 Captain and 5 FLEX players

// Number of lineups to generate (default to 15000, limit to avoid excessive processing)
$num_lineups = isset($_POST['num_lineups']) ? intval($_POST['num_lineups']) : 15000;
if ($num_lineups > 15000) {
    $num_lineups = 15000;
}

$lineups = [];

// Start time tracking for lineup generation
$start_time = microtime(true);

// Shuffle players list once for better performance and to avoid repeated random access
shuffle($players);

// Set time limit to 0 to avoid execution time errors for large lineup generations
set_time_limit(0);

// Generate unique lineups
$used_combinations = [];
for ($i = 0; $i < $num_lineups; $i++) {
    $lineup = [];
    $salary = 0;
    $player_ids = [];

    // Display progress for every 1000 lineups generated
    if ($i % 1000 == 0) {
        echo "<h3>Progress: Generated $i / $num_lineups lineups...</h3>";
        flush();
        ob_flush();
    }

    // Select a Captain (1.5x salary multiplier)
    foreach ($players as $potentialCaptain) {
        $captain_salary = $potentialCaptain['salary'] * 1.5; // Captain costs 1.5x

        // Check if adding the captain stays within the salary cap
        if ($salary + $captain_salary <= $SALARY_CAP) {
            $potentialCaptain['role'] = 'Captain';
            $lineup_salary = $captain_salary;
            $lineup[] = $potentialCaptain;
            $salary += $captain_salary;
            $player_ids[] = $potentialCaptain['Name + ID'];
            break;
        }
    }

    // Select 5 FLEX players to complete the lineup
    foreach ($players as $player) {
        // Ensure the player is not already in the lineup
        if (!in_array($player['Name + ID'], $player_ids) && $salary + $player['salary'] <= $SALARY_CAP) {
            $player['role'] = 'FLEX';
            $lineup[] = $player;
            $salary += $player['salary'];
            $player_ids[] = $player['Name + ID'];
        }

        // Stop adding players once the roster is full
        if (count($lineup) >= $ROSTER_SIZE) {
            break;
        }
    }

    // Ensure lineup is unique
    $lineup_key = implode('-', $player_ids);
    if (isset($used_combinations[$lineup_key])) {
        $i--;
        continue;
    }
    $used_combinations[$lineup_key] = true;

    // Add the generated lineup to the lineups list
    $lineups[] = [
        'players' => $lineup,
        'total_salary' => $salary
    ];
}

// Calculate the time taken to generate lineups
$end_time = microtime(true);
$execution_time = ($end_time - $start_time);

// Display the generated lineups and performance statistics
echo "<h1>Generated $num_lineups Lineups in " . round($execution_time, 2) . " seconds</h1>";
foreach ($lineups as $index => $lineupData) {
    echo "<h3>Lineup " . ($index + 1) . ":</h3>";
    echo "<ul>";
    foreach ($lineupData['players'] as $player) {
        echo "<li>" . htmlspecialchars($player['Name + ID']) . " (" . htmlspecialchars($player['Position']) . " - " . $player['role'] . ") - $" . number_format($player['salary'], 2) . "</li>";
    }
    echo "</ul>";
    echo "<p>Total Salary: $" . number_format($lineupData['total_salary'], 2) . "</p>";
}
?>

