<!DOCTYPE html>
<html>
<head>
    <title>DFS Lineup Generator</title>
</head>
<body>
    <h1>Daily Fantasy Sports Lineup Generator</h1>
    <form action="generate_lineups.php" method="POST">
        <label for="num_lineups">Number of Lineups to Generate:</label>
        <input type="number" id="num_lineups" name="num_lineups" value="15000" min="1" max="15000">
        <br><br>
        <input type="submit" value="Generate Lineups">
    </form>
</body>
</html>

