<?php
$databaseServer = '<your-rds-endpoint>';
$username = '<your-db-username>';
$password = '<your-db-password>';
$database = "Products";
$port = '3306';

function burn_cpu() {
    // We use a hashing algorithm, which is a standard way to create a predictable but intense CPU load.
    // The random number ensures it cannot be cached.
    $string_to_hash = "a_very_long_and_complex_string_to_force_the_cpu_to_work_hard_" . random_int(1, 1000);
    $hash = '';
    // Hashing the string repeatedly in a loop is computationally very expensive.
    for ($i = 0; $i < 15000; $i++) {
        $hash = hash('sha256', $string_to_hash . $i);
    }
    return $hash;
}

// Create connection
// PHP 8.1+ makes mysqli throw on failure (blank 500 page). Report it instead.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($databaseServer, $username, $password, $database, $port);
if (!$conn) {
  http_response_code(500);
  echo "<h3>BuyProducts: database connection failed</h3>";
  echo "<p>Host: <code>" . htmlspecialchars($databaseServer) . "</code></p>";
  echo "<p>MySQL says: <code>" . htmlspecialchars(mysqli_connect_error()) . "</code></p>";
  exit;
}

function lockTable($tableName, $conn)
{
   $sqlCommand = "LOCK TABLE $tableName WRITE;";
   echo $sqlCommand;
   if($conn->query($sqlCommand)){
        echo "lock successfull\n";
   }
  else{
        echo "lock failed\n";
   }
}

function getItemCount($itemID, $conn) {
  echo 'In function\n';
  $sqlCommand = "SELECT * FROM Inventory WHERE ItemID='$itemID'";
  $result = $conn->query($sqlCommand);
  $result = mysqli_query($conn, $sqlCommand);
  $row = $result->fetch_row();
  return $row[1];
}

function unlockTable($tableName, $conn)
{
   $sqlCommand = "UNLOCK TABLES;";
   if($conn->query($sqlCommand))
   {
        echo "unlock successfull\n";
   }
  else{
        echo "unlock failed\n";
   }
}

function updateItemCount($itemID, $updatedQuantity, $conn) {
  echo 'in function';
  $sqlCommand = "UPDATE Inventory SET Count='$updatedQuantity' WHERE ItemID='$itemID'";
   if($conn->query($sqlCommand)){
        echo "updated item count successfully\n";
   }
  else{
        echo "failed to update item count\n";
   }
}

function addCustomerOrder($conn, $customerEmail, $itemID, $quantity){
        $sql = "INSERT INTO `Orders` (`ItemID`, `CustomerEmail`, `Quantity`) VALUES ($itemID,'$customerEmail',$quantity);";
        echo $sql;
        if($conn->query($sql)){
                echo "<br>Inserted new order successfully";
        }
        else {
                echo "<br>Unable to insert new order";
        }
}

burn_cpu();

$itemID = $_GET['itemID'];
$quantityToBuy = $_GET['quantity'];
$customerEmail = $_GET['customerEmail'];
unlockTable('Inventory', $conn);
$quantityInInventory = getItemCount($itemID, $conn);
if ($quantityInInventory > $quantityToBuy)
{
    lockTable('Inventory', $conn);
    echo 'updating quantity';
    updateItemCount($itemID, $quantityInInventory - $quantityToBuy, $conn);
    unlockTable('Inventory', $conn);
    lockTable('Orders', $conn);
    addCustomerOrder($conn, $customerEmail, $itemID, $quantityToBuy);
    unlockTable('Orders', $conn);
    echo "success";
}
else
{
    // Was unconditional in the original: printed "failure" even after a successful order.
    echo "failure";
}
?>