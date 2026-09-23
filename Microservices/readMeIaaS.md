# Deploy a Webstore app, based on micro-services architecture, to the AWS Cloud  
## Objective  
Setup a web-server that simulates a basic e-commerce webstore and follows micro-services architecture, i.e. the entire web-server application is implemented and deployed as multiple functional modules, with each module deployed in its own EC2 instance, using infrastructure as a service (IaaS).  

## Steps  
### 1. Deploy the Database Server  
Setup and install the database for the webstore. The steps are shared at https://github.ncsu.edu/ECECSC547-CloudComputing/Codebase/blob/master/WebStore/readMeIaaS.md. You can re-use the database server, if you had set one up for the monolithic app.  

### 2. Deploy the micro-services of the Webstore App    
2.1 Three micro-services will be deployed: 1) Route Requests, 2) Display Products Service, and 3) Buy Products Service. The Route Requests service is also the user-interface of the app.    
2.2 Launch three EC2 instances, using the amazon machine image (AMI) ID- . This AMI  has Apache, PHP, and Python dependencies pre-installed. Name these EC2 instances as follows: ``RouteRequests``, ``DisplayProducts``, and ``BuyProducts``.    
2.3 Copy products.php and products.js to the RouteRequest instance. Update the ``displayProductsMicroService`` and ``buyProductsMicroService`` variables in products.php to point to the DisplayProducts and the BuyProducts services repectively, e.g. set ``displayProductsMicroService`` variables to http://<Display-Products-IP>/displayProducts.php, and ``buyProductsMicroService`` to ``http://<Buy-Products-IP>/buyProducts.php``.  
2.4 Copy displayProducts.php to the DisplayProducts instance. Update the ``databaseServer``, ``username``, and ``password`` variables as per the database instance you setup in Step 1.   
2.5 Copy buyProducts.php to the BuyProducts instance. Update the ``databaseServer``, ``username``, and ``password`` variables as per the database instance you setup in Step 1.   
  
### 3. Test the Webstore App  
3.1  Note down the DNS of the RouteRequest EC-2 instance and navigate to the webstore URL: ``http://<RouteRequest-IP>/products.php``.    
3.2  Verify that the products are displayed correctly.  
3.3  Buy some products and confirm in the database that the orders are logged.  
