<?php

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

$getParametersCommand = escapeshellcmd('python3 getParameter.py');
$servername = trim(shell_exec($getParametersCommand));

//$servername = "<your-rds-endpoint>";
$username = "<your-db-username>";
$password = "<your-db-password>";

$database = "Products";
$port = '3306';

// Create connection
$conn = mysqli_connect($servername, $username, $password, $database, $port);

$currentTime = time();
// Check connection
if ($conn->connect_error) {
  echo "Connection failed";
  die("Connection failed: " . $conn->connect_error);
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
