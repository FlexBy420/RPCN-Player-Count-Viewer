<?php
// Load the Game Titles and IDs from the provided JSON structure
$games_json = __DIR__ . '/games.json';  // Reference the current directory
$log_file = __DIR__ . '/log.txt'; // Log file for missing IDs
$api_url = ""; // RPCN API URL

function log_error($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] ERROR: $message" . PHP_EOL, FILE_APPEND);
}

// Load the JSON file
if (!file_exists($games_json)) {
    log_error("JSON file not found: $games_json", $log_file);
    die("Error: JSON file is missing. Check log for details.");
}

$game_mappings = json_decode(file_get_contents($games_json), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_error("Failed to decode JSON file: " . json_last_error_msg(), $log_file);
    die("Error: Failed to parse JSON file. Check log for details.");
}

// Fetch JSON Data from RPCN Stats API
$api_data = @file_get_contents($api_url);
if ($api_data === false) {
    log_error("Failed to fetch API data from $api_url", $log_file);
    die("Error: Unable to connect to API. Check log for details.");
}

$data = json_decode($api_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_error("Failed to decode API response: " . json_last_error_msg(), $log_file);
    die("Error: Failed to parse API response. Check log for details.");
}

// Get the total number of users from the API
$total_users = isset($data['num_users']) ? $data['num_users'] : 0;

// Initialize an array to store the merged player counts by game title
$title_player_counts = [];

// Function to normalize IDs
function normalize_id($id) {
    if (preg_match('/[A-Z0-9]+-[A-Z0-9]+/', $id, $matches)) {
        return substr($matches[0], strpos($matches[0], '-') + 1);
    }
    return preg_replace('/_\d+$/', '', $id);  // Remove _XX suffix
}

function log_missing_id($id, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entries = file_exists($log_file) ? file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $updated_entries = [];
    $found = false;

    foreach ($log_entries as $entry) {
        // Check if the current entry matches the missing ID
        if (strpos($entry, $id) !== false) {
            $found = true;

            if (preg_match('/x(\d+)$/', $entry, $matches)) {
                $count = (int)$matches[1] + 1;
            } else {
                $count = 2; // If there's no count, start from 2
            }

            // Update the entry with the new timestamp and count
            $updated_entries[] = "[$timestamp] UNKNOWN ID: $id x$count";
        } else {
            $updated_entries[] = $entry;
        }
    }
    if (!$found) {
        // Add a new entry if the ID was not found
        $updated_entries[] = "[$timestamp] UNKNOWN ID: $id";
    }
    file_put_contents($log_file, implode(PHP_EOL, $updated_entries) . PHP_EOL);
}

// Merge Player Counts from API Data
foreach ($game_mappings as $game_title => $ids) {
    $title_player_counts[$game_title] = 0;
    $comm_id_player_count = 0;  // Store player count from comm_ids if found

    // Check for comm_ids (prioritize comm_ids over title_ids)
    foreach ($ids['comm_ids'] as $comm_id) {
        if (!empty($comm_id)) {  // Handle cases where comm_id might be empty
            $normalized_comm_id = normalize_id($comm_id);
            if (!empty($data['psn_games'])) { 
                foreach ($data['psn_games'] as $api_comm_id => $count) {
                    if (normalize_id($api_comm_id) === $normalized_comm_id) {
                        $comm_id_player_count += $count;
                    }
                }
            }
        }
    }
    // If we have a count from comm_ids, use it and skip counting title_ids
    if ($comm_id_player_count > 0) {
        $title_player_counts[$game_title] = $comm_id_player_count;
    } else {
        // If no comm_ids found, count players based on title_ids
        foreach ($ids['title_ids'] as $title_id) {
            $normalized_title_id = normalize_id($title_id);
            if (!empty($data['ticket_games'])) { 
                foreach ($data['ticket_games'] as $api_title_id => $count) {
                    if (normalize_id($api_title_id) === $normalized_title_id) {
                        $title_player_counts[$game_title] += $count;
                    }
                }
            }
        }
    }
}

// Check for IDs in the API data that are not in the JSON file
if (!empty($data['psn_games'])) { 
    foreach ($data['psn_games'] as $api_comm_id => $count) {
        $found = false;
        foreach ($game_mappings as $ids) {
            foreach ($ids['comm_ids'] as $comm_id) {
                if (normalize_id($api_comm_id) === normalize_id($comm_id)) {
                    $found = true;
                    break 2;
                }
            }
        }
        if (!$found) {
            log_missing_id($api_comm_id, $log_file);
        }
    }
}

if (!empty($data['ticket_games'])) { 
    foreach ($data['ticket_games'] as $api_title_id => $count) {
        $found = false;
        foreach ($game_mappings as $ids) {
            foreach ($ids['title_ids'] as $title_id) {
                if (normalize_id($api_title_id) === normalize_id($title_id)) {
                    $found = true;
                    break 2;
                }
            }
        }
        if (!$found) {
            log_missing_id($api_title_id, $log_file);
        }
    }
}
// Sort the results by player count in descending order
arsort($title_player_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>RPCN Player Counts</title>
</head>
<body>
    <h1>Current Players in Games</h1>
    <table border="1">
        <thead>
        <tr>
                <td><strong>Total Users</strong></td>
                <td><?php echo htmlspecialchars($total_users); ?></td>
        </tr>
            <tr>
                <th>Game Title</th>
                <th>Current Players</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($title_player_counts as $game_title => $count): ?>
                <?php if ($count > 0): // Only show games with more than 0 players ?>
                    <tr>
                        <td><?php echo htmlspecialchars($game_title); ?></td>
                        <td><?php echo htmlspecialchars($count); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>