<?php

$databaseServer = '<your-rds-endpoint>';
$username = '<your-db-username>';
$password = '<your-db-password>';
$database = "Products";
$port = '3306';

// Create connection
// PHP 8.1+ makes mysqli throw on failure (blank 500 page). Report it instead.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($databaseServer, $username, $password, $database, $port);
if (!$conn) {
  http_response_code(500);
  echo "<h3>DisplayProducts: database connection failed</h3>";
  echo "<p>Host: <code>" . htmlspecialchars($databaseServer) . "</code></p>";
  echo "<p>MySQL says: <code>" . htmlspecialchars(mysqli_connect_error()) . "</code></p>";
  exit;
}
$sql = "SELECT * FROM Footwear";
// Query all products using SQL
$result = mysqli_query($conn, $sql);
$htmlString = "";
$htmlString .= 
$htmlString .=  "<br>";
$htmlString .=  "<table border='1'>";
$htmlString .=  "<th>Product </th><th>Description</th><th>Cost($)</th><th>Count</th>";
while ($row = mysqli_fetch_assoc($result))
{
    $htmlString .=  "<tr>";
    $htmlString .=  "<td>" . strval($row["Name"]) . "</td>";
    $htmlString .=  "<td>" . strval($row["Description"]) . "</td>";
    $htmlString .=  "<td>" . strval($row["Cost"]) . "</td>";
    $htmlString .=  "<td><select class='productCount' data-productname='".strval($row["Name"])."' data-productid=".strval($row["ItemID"])." name='count'><option value=0>0</option><option value=1>1</option><option value=2>2</option></select></td>";
    $htmlString .=  "</tr>";
}
$htmlString .=  "</table>";
echo $htmlString;
?>