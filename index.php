<?php
// Load the Game Titles and IDs from the provided JSON structure
$game_mapping_file = __DIR__ . '/games.json';  // Reference the current directory
$game_mappings = json_decode(file_get_contents($game_mapping_file), true);

// Fetch JSON Data from RPCN Stats API
$json_url = "";
$json_data = file_get_contents($json_url);
$data = json_decode($json_data, true);

// Get the total number of users from the API
$total_users = isset($data['num_users']) ? $data['num_users'] : 0;

// Initialize an array to store the merged player counts by game title
$title_player_counts = [];

// Function to normalize IDs
function normalize_id($id) {
    if (preg_match('/[A-Z0-9]+-[A-Z0-9]+/', $id, $matches)) {
        return substr($matches[0], strpos($matches[0], '-') + 1);
    }
    return preg_replace('/_00$/', '', $id);  // Remove _00 suffix
}
// Merge Player Counts from API Data
foreach ($game_mappings as $game_title => $ids) {
    $title_player_counts[$game_title] = 0;
    
    // Merge counts for title IDs (used in 'ticket_games')
    foreach ($ids['title_ids'] as $title_id) {
        $normalized_title_id = normalize_id($title_id);
        foreach ($data['ticket_games'] as $api_title_id => $count) {
            if (normalize_id($api_title_id) === $normalized_title_id) {
                $title_player_counts[$game_title] += $count;
            }
        }
    }  
    // Merge counts for communication IDs (used in 'psn_games')
    foreach ($ids['comm_ids'] as $comm_id) {
        if (!empty($comm_id)) {  // Handle cases where comm_id might be empty
            $normalized_comm_id = normalize_id($comm_id);
            foreach ($data['psn_games'] as $api_comm_id => $count) {
                if (normalize_id($api_comm_id) === $normalized_comm_id) {
                    $title_player_counts[$game_title] += $count;
                }
            }
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
    <title>Game Player Counts</title>
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