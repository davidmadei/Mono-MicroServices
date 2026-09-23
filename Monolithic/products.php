<?php

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

function getItemCount($itemID, $conn) {
  echo 'In function\n';
  $sqlCommand = "SELECT * FROM Inventory WHERE ItemID='$itemID'";
  $result = $conn->query($sqlCommand);
  $result = mysqli_query($conn, $sqlCommand);
  $row = $result->fetch_row();
  return $row[1];
}

function lockTable($tableName, $conn){
   $sqlCommand = "LOCK TABLE $tableName WRITE;";
   echo $sqlCommand;
   if($conn->query($sqlCommand)){
        echo "lock successfull\n";
   }
  else{
        echo "lock failed\n";
   }
}

function unlockTable($tableName, $conn){
   $sqlCommand = "UNLOCK TABLES;";
   if($conn->query($sqlCommand)){
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

$servername = "<your-rds-endpoint>";
$username = "<your-db-username>";
$password = "<your-db-password>";

$database = "Products";
$port = '3306';

// ---------------------------------------------------------------------------
// PHP 8.1 changed mysqli's default error mode: mysqli_connect() now THROWS a
// mysqli_sql_exception on failure instead of returning false. So the
// "if ($conn->connect_error)" check below -- written for PHP 7 -- is dead code,
// and Beanstalk runs display_errors=Off, which turns every connection failure
// into a blank page and an HTTP 500 with no explanation anywhere nginx logs.
//
// Turning reporting off restores the return-false behaviour the code expects,
// and we print the real reason instead of dying silently.
// ---------------------------------------------------------------------------
// Is the mysqli extension even present? If the platform did not build PHP with
// it, mysqli_connect() is an undefined function -- a fatal error, blank 500, and
// nothing in nginx's log. Same symptom as a refused connection, different cause.
if (!function_exists('mysqli_connect')) {
  http_response_code(500);
  echo "<h3>PHP is missing the mysqli extension</h3>";
  echo "<p>Loaded extensions: <code>" . htmlspecialchars(implode(', ', get_loaded_extensions())) . "</code></p>";
  exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = mysqli_connect($servername, $username, $password, $database, $port);

$currentTime = time();
// Check connection
if (!$conn) {
  http_response_code(500);
  echo "<h3>Database connection failed</h3>";
  echo "<p>Host: <code>" . htmlspecialchars($servername) . "</code></p>";
  echo "<p>MySQL says: <code>" . htmlspecialchars(mysqli_connect_error()) . "</code></p>";
  echo "<p>Common causes: the RDS security group has no inbound rule for 3306 "
     . "from these instances, or the password is wrong.</p>";
  exit;
}


$action = 'view';
if (isset($_GET['action'])){
   $action = $_GET['action'];
}

if ($action == 'view'){
   $sql = "SELECT * FROM Footwear";
   $result = mysqli_query($conn, $sql); // First parameter is just return of "mysqli_connect()" function
   echo "<br>";
   echo "<table border='1'>"; 
   echo "<th>Product </th><th>Description</th><th>Cost($)</th><th>Count</th>";
   while ($row = mysqli_fetch_assoc($result))
   {
    echo "<tr>";
    echo "<td>" . strval($row["Name"]) . "</td>";
    echo "<td>" . strval($row["Description"]) . "</td>";
    echo "<td>" . strval($row["Cost"]) . "</td>";
    echo "<td><select class='productCount' data-productname='".strval($row["Name"])."' data-productid=".strval($row["ItemID"])." name='count'><option value=0>0</option><option value=1>1</option><option value=2>2</option></select></td>";
    echo "</tr>";
}
echo "</table>";
}
else if($action == 'buy'){
     // EXECUTE THE CPU_HEAVY TASK HERE
     burn_cpu();

     $itemID = $_GET['itemID'];
     $quantityToBuy = $_GET['quantity'];
     $customerEmail = $_GET['customerEmail'];
     unlockTable('Inventory', $conn);
     $quantityInInventory = getItemCount($itemID, $conn);
     if ($quantityInInventory > $quantityToBuy){
        lockTable('Inventory', $conn);
        echo 'updating quantity';
        updateItemCount($itemID, $quantityInInventory - $quantityToBuy, $conn);
        unlockTable('Inventory', $conn);
        lockTable('Orders', $conn);
        addCustomerOrder($conn, $customerEmail, $itemID, $quantityToBuy);
        unlockTable('Orders', $conn);
     }
}

?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="products.js"></script>
<br>
<label for="email">Enter your email:</label>
<input type="email" id="customerEmail" name="email">
<button type="button" id="buyProducts">Buy</button>
<div id="results" style="color: green;">