
<?php

header('Content-Type: text/html; charset=utf-8');

$conn = mysqli_connect(
    $hostname = ini_get("mysqli.default_host"),
    $username = ini_get("mysqli.default_user"),
    $password = ini_get("mysqli.default_pw"),
    $database = "speedstats",
    $port = ini_get("mysqli.default_port"),
    $socket = ini_get("mysqli.default_socket")
);


$result = mysqli_query($conn, "select * from runs limit 100");


/*
while($row = mysqli_fetch_assoc($result)) {
    print_r($row);
}
*/

print <<<EOF

<p>Last Updated: 7/12/2022</p>


<form action=index.php>



<p>
<label for="games">Games:</label>
<textarea name=games></textarea>

<label for="platforms">+ Platforms:</label>
<textarea name=platforms></textarea>
</p>

<p>
<label for="players">Players:</label>
<textarea name=players></textarea>
</p>

<p>Separate each value in the input fields with a comma followed by a space. 
The specified games and platforms combine, rather than constricting eachother. 
Leave game / platform fields blank to request for all games.</p>

<label for="request-type">Request Type:</label>
<select name="request-type" id="request-type">
  <option value="pr">Player Rankings</option>
  <option value="runs">Runs by Player(s)</option>
  <option value="records">Most Valuable Records</option>
  <option value="leaderboards">Leaderboard Value</option>
  <option value="games">Game Value</option>
  <option value="dates"> Date Value</option>
</select>
<input type="submit" value="Submit">
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

if(isset($_REQUEST['request-type'])) {

    $requestType = $_REQUEST['request-type'];

    //$seriesin = "\"\"";
    $gamesin = "\"\"";
    $platformsin = "\"\"";
    $playersin = "playerName";

    $parameters = [];

    /*
    if(isset($_REQUEST['series'])) {
        $series = $_REQUEST['series'];
        $seriesarray = explode(", ", $series);
        $seriesin = str_repeat('?,', count($seriesarray) - 1) . '?';
    }
    */

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

    if($gamesin == "\"\"" && $platformsin == "\"\"") {
        $gamesin = "gameName"; //get all games if no games / platforms are specified
    }

    $sql = "";

    switch($requestType) {
        case "pr":
            if($gamesin == "gameName") { //use playerranks for all games table
                $sql = (
                    "select *
                    from playerranks
                    where playerName in ($playersin) 
                    order by Points desc
                    limit 1000;"
                );
            } else {
                $sql = (
                    "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                    from(
                    select playerName, sum(runValue) as Points
                    from runs
                    where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                    group by playerName 
                    order by Points desc
                    limit 1000
                    ) as t1;"
                );
            }
            break;
        case "runs":
            if(count($playersarray) == 1) {
                $sql = (
                    "select ROW_NUMBER() over (order by runValue desc) as 'Rank', t1.*
                    from(
                    select crlName, runPlace, runValue
                    from runs
                    where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                    order by runValue desc
                    limit 1000
                    ) as t1;"
                );
            } else {
                $sql = (
                    "select ROW_NUMBER() over (order by runValue desc) as 'Rank', t1.*
                    from(
                    select playerName, crlName, runPlace, runValue
                    from runs
                    where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                    order by runValue desc
                    limit 1000
                    ) as t1;"
                ); 
            } 
            break;
        case "records":
            $sql = (
                "select ROW_NUMBER() over (order by runValue desc) as 'Rank', t1.*
                from(
                select crlName, runValue
                from runs
                where runPlace = 1 and (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                order by runValue desc
                limit 1000
                ) as t1;"
            );
            break;
        case "leaderboards":
            $sql = (
                "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                from(
                select crlName, sum(runValue) as Points
                from runs
                where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                group by crlName
                order by Points desc
                limit 1000
                ) as t1;"
            );
            break;
        case "games":
            $sql = (
                "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                from(
                select gameName, sum(runValue) as Points
                from runs
                where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                group by gameName
                order by Points desc
                limit 1000
                ) as t1;"
            );
            break;
        case "dates":
            $sql = (
                "select ROW_NUMBER() over (order by Points desc) as 'Rank', t1.*
                from(
                select date, sum(runValue) as Points
                from runs
                where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin) 
                group by date
                order by Points desc
                limit 1000
                ) as t1;"
            );
            break;
        default:
            $sql = ("select * from runs
                where (gameName in ($gamesin) or platformName in ($platformsin)) and playerName in ($playersin)
                limit 0;"
            );
    }

    $stmt  = $conn->prepare($sql);
    if(count($parameters) > 0) {
        $types = str_repeat('s', count($parameters));
        $stmt->bind_param($types, ...$parameters);
    }
    $stmt->execute();
    $result = $stmt->get_result();


    printSQL($result);
    /*
    print "<table border=1>";

    
    
    while($row = mysqli_fetch_assoc($result)) {
        $rank = $row['Rank'];
        $playerName = htmlspecialchars($row['playerName']);
        $points = $row['Points'];
        print <<<EOF
        <tr><td>$rank</td><td>$playerName</td><td>$points</td>
            
        
        
        </tr>
        EOF;
    }
    print "</table>";
    */

}

?>
