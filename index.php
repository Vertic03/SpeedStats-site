
<?php

header('Content-Type: text/html; charset=utf-8');

mb_internal_encoding('UTF-8');

$hostname = "localhost";
$username = "root";
$password = "";
$database = "SpeedStats";

$conn = mysqli_connect($hostname, $username, $password, $database);
mysqli_set_charset($conn, "utf8");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

print <<<EOF

<head>
    <title>SpeedStats</title>
    <link rel="stylesheet" type="text/css" href="style.css?v=1">
</head>

<form action=index.php>
<div class="grid-container">
<div>
<label for="series">Series:</label>
<textarea name=series></textarea>
</div>
<div>
<label for="games">Games:</label>
<textarea name=games></textarea>
</div>
<div>
<label for="platforms">Platforms:</label>
<textarea name=platforms></textarea>
</div>
<div>
<label for="players">Players:</label>
<textarea name=players></textarea>
</div>
</div>

<p>
Separate each value in the input fields with a comma followed by a space. 
The specified series, games, and platforms combine, rather than constricting eachother. 
Leave those fields blank to request for all games. <a href="https://www.desmos.com/calculator/bm0shjznnx">How Run Value is Calculated</a></p>

<label for="request-type">Request Type:</label>
<select name="request-type" id="request-type">
  <option value="pr">Player Rankings</option>
  <option value="runs">Runs by Player(s)</option>
  <option value="records">Most Valuable Records</option>
  <option value="leaderboards">Leaderboard Value</option>
  <option value="games">Game Value</option>
  <option value="dates"> Date Value</option>
</select>
<div class="center">
<input type="submit" value="Submit">
</div>
<p align="center">Created by: <a href="https://www.speedrun.com/users/Maximum">Maximum</a></p>
</form>


EOF;

function toInput(mixed $request) {
    $array = explode(", ", $request);
    return str_repeat('?,', count($array) - 1) . '?';
}

function printSQL($results) {
    echo "<table border =1>";
    while ($fieldInfo = mysqli_fetch_field($results)) {
        echo "<th> $fieldInfo->name </th>";
        $colNames[] = $fieldInfo->name;
    } 

    while ($row = mysqli_fetch_array($results)) {
        echo "<tr>";
        for ($i=0; $i<sizeof($colNames); $i++) {
            echo "<td>" . $row[$colNames[$i]] . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// default query
$sql = (
    "select *
    from playerRanks
    order by Points desc
    limit 1000;"
);
$parameters = [];

if(isset($_REQUEST['request-type'])) {

    $requestType = $_REQUEST['request-type'];

    $seriesin = "\"\"";
    $gamesin = "\"\"";
    $platformsin = "\"\"";
    $playersin = "Player";

    $playersarray = [];

    if(isset($_REQUEST['series']) && !empty($_REQUEST['series'])) {
        $series = $_REQUEST['series'];
        $seriesarray = explode(", ", $series);
        $seriesin = str_repeat('?,', count($seriesarray) - 1) . '?';
        $parameters = array_merge($parameters,$seriesarray);
    }

    if(isset($_REQUEST['games']) && !empty($_REQUEST['games'])) {
        $games = $_REQUEST['games'];
        $gamesarray = explode(", ", $games);
        $gamesin = str_repeat('?,', count($gamesarray) - 1) . '?';
        $parameters = array_merge($parameters,$gamesarray);
    }


    if(isset($_REQUEST['platforms']) && !empty($_REQUEST['platforms'])) {
        $platforms = $_REQUEST['platforms'];
        $platformsarray = explode(", ", $platforms);
        $platformsin = str_repeat('?,', count($platformsarray) - 1) . '?';
        $parameters = array_merge($parameters,$platformsarray);   
    }


    if(isset($_REQUEST['players']) && !empty($_REQUEST['players'])) {
        $players = $_REQUEST['players'];
        $playersarray = explode(", ", $players);
        $playersin = str_repeat('?,', count($playersarray) - 1) . '?';   
        $parameters = array_merge($parameters,$playersarray); 
    }

    if($seriesin == "\"\"" && $gamesin == "\"\"" && $platformsin == "\"\"") {
        $gamesin = "Game"; //get all games if no games / platforms are specified
    }

    switch($requestType) {
        case "pr":
            if($gamesin == "Game") { //use playerRanks for all games table
                $sql = (
                    "select *
                    from playerRanks
                    where Player in ($playersin) 
                    order by Points desc
                    limit 1000;"
                );
            } else {
                $sql = (
                    "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                    from(
                    select Player, round(sum(Value), 2) as Points
                    from runs
                    where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin) 
                    group by Player
                    order by Points desc
                    limit 1000
                    ) as t1;"
                );
            }
            break;
        case "runs":
            if(count($playersarray) == 1) {
                $sql = (
                    "select ROW_NUMBER() over (order by Value desc) as 'Rank', t1.*
                    from(
                    select Leaderboard, Place, Value
                    from runs
                    where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin) 
                    order by Value desc
                    limit 1000
                    ) as t1;"
                );
            } else {
                $sql = (
                    "select ROW_NUMBER() over (order by Value desc) as 'Rank', t1.*
                    from(
                    select Player, Leaderboard, Place, Value
                    from runs
                    where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin)
                    order by Value desc
                    limit 1000
                    ) as t1;"
                ); 
            } 
            break;
        case "records":
            $sql = (
                "select ROW_NUMBER() over (order by Value desc) as 'Rank', t1.*
                from(
                select Leaderboard, Player, max(Value) as Value
                from runs
                where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin)
                group by Leaderboard
                order by Value desc
                limit 1000
                ) as t1;"
            );
            break;
        case "leaderboards":
            $sql = (
                "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                from(
                select Leaderboard, round(sum(Value), 2) as Points
                from runs
                where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin)
                group by Leaderboard
                order by Points desc
                limit 1000
                ) as t1;"
            );
            break;
        case "games":
            $sql = (
                "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                from(
                select Game, round(sum(Value), 2) as Points
                from runs
                where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin) 
                group by Game
                order by Points desc
                limit 1000
                ) as t1;"
            );
            break;
        case "dates":
            $sql = (
                "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                from(
                select Date, round(sum(Value), 2) as Points
                from runs
                where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin)
                group by Date
                order by Points desc
                limit 1000
                ) as t1;"
            );
            break;
        default:
            $sql = ("select * from runs
                where (Series in ($seriesin) or Game in ($gamesin) or Platform in ($platformsin)) and Player in ($playersin)
                limit 0;"
            );
    }
}

if (!$stmt = $conn->prepare($sql)) {
    die("Prepare failed: " . $conn->error);
}

if(count($parameters) > 0) {
    $types = str_repeat('s', count($parameters));
    $stmt->bind_param($types, ...$parameters);
}

$stmt->execute();
$result = $stmt->get_result();

printSQL($result);
?>
