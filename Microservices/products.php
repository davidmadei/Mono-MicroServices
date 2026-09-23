<?php
// SET AFTER DEPLOYING DisplayProducts -- its Beanstalk URL + /displayProducts.php
$displayProductsMicroService = "http://<display-products-env-url>/displayProducts.php";
//$displayProductsMicroService = "http://localhost:8000/displayProducts";
// SET AFTER DEPLOYING BuyProducts -- its Beanstalk URL + /buyProducts.php
$buyProductsMicroService = "http://<buy-products-env-url>/buyProducts.php";
$action = 'view';
if (isset($_GET['action']))
{
   $action = $_GET['action'];
}

if ($action == 'view')
{
     $contents = file_get_contents($displayProductsMicroService);
     if($contents !== false)
     {
        echo $contents;
     }
     else
     {
        // Originally silent: a wrong URL or a down service produced an empty page.
        echo "<h3>RouteRequest could not reach DisplayProducts</h3><p>Tried: <code>"
           . htmlspecialchars($displayProductsMicroService) . "</code></p>";
     }     
}
else if($action == 'buy')
{
     // Original read this into $customerID, then forwarded the never-assigned $customerEmail,
     // so every order reached BuyProducts with an empty email. The monolith has no such bug --
     // it only exists at the seam between services.
     $customerEmail = $_GET['customerEmail'];
     $itemID = $_GET['itemID'];
     $quantityToBuy = $_GET['quantity'];

    $url = ($buyProductsMicroService).'?customerEmail='.urlencode($customerEmail).'&itemID='.urlencode($itemID).'&quantity='.urlencode($quantityToBuy);
    echo $url;
    //Once again, we use file_get_contents to GET the URL in question.
     $contents = file_get_contents($url);
     if($contents !== false)
     {
        echo $contents;
     }
     else
     {
        echo "<h3>RouteRequest could not reach BuyProducts</h3><p>Tried: <code>"
           . htmlspecialchars($url) . "</code></p>";
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
