# Deploy a webstore app to AWS Cloud, using IaaS

## Objective
Setup a web-server that simulates a basic e-commerce webstore and follows monolithic architecture, i.e. the entire web-server application is implemented and deployed as a single module, in the same EC2 instance. Both the database and the app are deployed using infrastructure as a service (IaaS).

## Steps:

### Part 1 - Setting up the Database Server

Follow the steps from https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-lamp-amazon-linux-2.html to setup the LAMP stack instance, which will be our database server. LAMP stands for Linux, Apache, MySQL, and PHP. Also, install phpMyAdmin. Detailed Instructions below:

   1.1 Log in to your AWS account. Go to AWS Services and select EC2  
   1.2 Click Launch Instances  
   1.3 Name - "LAMP Database Server"  
   1.4 Application and OS Images - Amazon Linux (AWS) 
      - AMI - Amazon Linux 2 AMI (HVM) - Kernel 5.10, SSD Volume Type (Free tier eligible)  
      - Architecture - 64 bit (x86)  
   1.5. Instance type - t2.micro  
   1.6. Key pair (login) - Create new key pair  
      - Key pair name - lamp  
      - Key pair type - RSA  
      - Private key file format - .pem  
      - Click Create Key pair and save it in your computer. Remember this .pem file's location as it will be required to SSH into the instance later. Do not lose this key pair file.  
   1.7. Network settings  
      - Check Allow SSH traffic from Anywhere (0.0.0.0/0)  
      - Check Allow HTTP traffic from the internet (anywhere)  
      - Click "Add security group rule" andd add a new rule to accept connections of Type "MYSQL/Aurora" and source type "Anywhere".  
   1.8. Leave other options to default settings.  
   1.9. Click Launch Instance button. It will take a few minutes to launch the instance.   
   1.10. Once it is successfully launched, you can see the Instance state as "Running" in the Instances page.  
   1.11. SSH into the created instance:  
       - Find the path to your key pair file (lamp.pem) that you downloaded.  
       - Find the :Public IPv4 DNS" of your instance from AWS console.  
       - In your computer, open command prompt or terminal and change directory to the key pair's location.  
       - To set permissions of your private key, enter the following command:  
          `chmod 400 lamp.pem`  
       - Use the following command in your terminal to connect to your instance.  
          `ssh -i /path/my-key-pair.pem` my-instance-user-name@my-instance-public-dns-name  
          - For example:  
          `ssh -i /Downloads/lamp.pem ec2-user@ec2-99-99-99-99.compute-1.amazonaws.com`
       - Type "yes" to continue connecting.  
       - Now, you are connected to your AWS instance from your terminal.  
   1.12. To update all software packages, enter the following command:  
       - `sudo yum update -y`  
   1.13. Install the lamp-mariadb10.2-php7.2 and php7.2 Amazon Linux Extras repositories to get the latest versions of the LAMP MariaDB and PHP packages for Amazon Linux 2.  
       - `sudo amazon-linux-extras install -y lamp-mariadb10.2-php7.2 php7.2`  
   1.14. Install the Apache web server, MariaDB, and PHP software packages.  
       - `sudo yum install -y httpd mariadb-server` 
   1.15. Start the Apache web server.  
       - `sudo systemctl start httpd`  
   1.16. Use the systemctl command to configure the Apache web server to start at each system boot.  
       - `sudo systemctl enable httpd`  
   1.17. Test your web server. Open a browser and go to the public DNS address (or the public IP address) of your instance.   
       - You should see a "Test Page" with APACHE logo.  
   1.18. To allow the `ec2-user` to manipulate files in the `/var/www/html` directory, you should add `ec2-user` to the `apache` group.  
       - `sudo usermod -a -G apache ec2-user`  
   1.19. Log out (use the exit command or close the terminal window)  
       - `exit`   
   1.20. Log back in using the ssh command.  
   1.21. To verify your membership in the apache group, reconnect to your instance, and then run the following command:  
       - `groups`  
       - Output should be `ec2-user adm wheel apache systemd-journal`  
   1.22. Change the group ownership of /var/www and its contents to the apache group.  
       - `sudo chown -R ec2-user:apache /var/www`  
   1.23. Add group write permissions and set the group ID.  
       - `sudo chmod 2775 /var/www && find /var/www -type d -exec sudo chmod 2775 {} \;`  
       - `find /var/www -type f -exec sudo chmod 0664 {} \;`  
   1.24. Once you have the required permissions, you can now create a PHP file in the /var/html/www directory.  
       - `echo "<?php phpinfo(); ?>" > /var/www/html/phpinfo.php`  
   1.25. To test the LAMP server, open a browser and enter the URL:  
       - `http://my.public.dns.amazonaws.com/phpinfo.php`  
       - For example,  
       `ec2-99-99-99-99.compute-1.amazonaws.com/phpinfo.php`  
       - You should see the PHP information page with PHP version and other details.  
   1.26. Delete the phpinfo.php file.  
       - `rm /var/www/html/phpinfo.php`  
   1.27. You have successfully setup a fully functional LAMP web server.  
   1.28. Start the MariaDB server.  
       - `sudo systemctl start mariadb`  
   1.29. Run mysql_secure_installation to secure the database server.  
       - `sudo mysql_secure_installation`  
   1.30. When prompted, type a password for the root account.  
       - Type the current root password. By default, the root account does not have a password set. Press Enter.  
       - Type Y to set a password (say `1234`), and type a secure password twice.   
       - Type Y to remove the anonymous user accounts.- Type Y to disable the remote root login.  
       - Type Y to remove the test database. 
       - Type Y to reload the privilege tables and save your changes.  
   1.31. Configure MariaDB server to start at every boot.  
       - `sudo systemctl enable mariadb`  
   1.32. Install the required dependencies for phpMyAdmin.  
       - `sudo yum install php-mbstring php-xml -y`  
   1.33. Restart Apache.  
       - `sudo systemctl restart httpd`  
   1.34. Restart php-fpm.  
       - `sudo systemctl restart php-fpm`  
   1.35. Navigate to the Apache document root at /var/www/html.  
       - `cd /var/www/html`  
   1.36. Use wget to download phpMyAdmin release directly to your instance.  
       - `wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz`  
   1.37. Create a `phpMyAdmin` folder and extract the downloaded package.  
       - `mkdir phpMyAdmin && tar -xvzf phpMyAdmin-latest-all-languages.tar.gz -C phpMyAdmin --strip-components 1`  
   1.38. Remove the downloaded tar file.  
       - `rm phpMyAdmin-latest-all-languages.tar.gz`  
   1.39. If the MySQL server is not running, start it now.  
       - `sudo systemctl start mariadb`  
   1.40. To open phpMyAdmin, open a browser and enter the URL:  
       - `ec2-99-99-99-99.compute-1.amazonaws.com/phpMyAdmin`  
       - You should see the phpMyAdmin login page.  
   1.41. Log into phpMyAdmin using the username `root` and password `1234` (or whatever password you set before)  
   1.42. In phpMyAdmin, create a new database called `Products`.   
   1.43. Configure the below tables within `Products`:  
       - `Inventory`  
       - `Orders`  
       - `Footwear`  
   1.44. Define `Inventory` to consist of the following two columns:  
       1. `ItemID` of data type bigint and length 20.  
       2. `Count` of data type int and length 11.  
   1.45. Define `Orders` to consist of following three columns:   
       1. `ItemID` of data type int and length 11.  
       2. `CusomterEmail` of data type varchar and length 1024.  
       3. `Quantity` of data type int and length 11.  
   1.46. Define `Footwear` to consist of the following four columns:   
       1. `Name` of data type varchar and length 256  
       2. `Cost` of data type decimal and length (10,0)   
       3. `Description` of data type varchar and length 4096. 
       4. `ItemID` of data type bigint and length 20.  
   1.47. Insert some data into the Footware and Inventory tables.  
   1.48. Enable remote connection to MySQL database on phpMyAdmin.  
       - Go to User accounts from the navigation bar in the phpMyAdmin home page.  
       - User name - "root"  
       - Host name - Any host - %  
       - Password - <password>  
       - Re-type - <password>  
       - Global privileges - Check all.  
       - Leave all other options to default.  
       - Click Go  
   1.49. The database server is now setup.  

### 2. Setting up the App server

The App server will be used to read/write from the database server for viewing the products and making purchases. The app server requires Linux, Apache, MySQL, and PHP. The only difference is that phpMyAdmin is not required as we will not host any database in this server. 
We can do this in two ways:

2.1. You can follow steps 1.1 to 1.31 again to create a new instance called the "App Server".  
2.2. Create an image of the Database server and launch a new instance using that image.  
   - From the Instances page, right click on the Database server Instance that we first created. From "Image and Templates", select "Create Image".
   - Image name - "LAMP DB Image"  
   - You can check the status of the image under AMIs page in the AWS console. 
   - It will take around 5-10 minutes to create the image.  
   - From the AMIs page, right click on the "LAMP DB Image" and select "Launch Instance from AMI".  
   - Name - "App Server"  
   - Key pair name - From the drop down, select the previously created key pair called "lamp". If you want, you can create a new key pair for the monolithic server and use that.   
   - Network Settings - Click "Edit".  
      - Under "Firewall (security groups)", click "Select existing security group" and select the security group that was used for our Database server (this can be found from the Database server's details).  
      - Alternatively, you can create a new security group for the monolithic server and set it up accordingly. Make sure to allow SSH, HTTP, and MySQL connections.   
   - Click "Launch Instance".  
2.3. Once the app server instance is up and running, you can connect to this instance using SSH from your terminal/command prompt by following step 11.   
   - Make sure to use the correct Public IPv4 DNS from the monolithic server instance.  
2.4 From this repo, copy the three scripts: WebStore/products.php, WebStore/index.php, WebStore/products.js to the location /var/www/html/ at the web server instance you created above. Now, you can send HTTP requests to products.php at over the Internet.  
2.5 Make the following modifications in products.php:  
   - Server name should be the Public IPv4 DNS address of your Database server instance. You can find it from the AWS console, e.g. ec2-99-99-99-99.compute-1.amazonaws.com   
   - `$servername = "<database-public-dns>";` 
   - `$username = "root"`  
   - `$password = "<password>"`  
   - `$database = "Products"`  
   - `$port = '3306';`  
   
### 3. Test the webstore

3.1 Make sure both the database server and app server instances are started and running.  
3.2 Open a web browser, and enter the URL <app-public-dns>/webstore.php to send an HTTP GET request to products.php.   
   - `http://ec2-88-88-88-88.compute-1.amazonaws.com/products.php`  
3.3 Verify that the list of products are displayed correctly.  
3.4 Buy some products from the webstore  
3.5 Verify that the item quantities are updated correctly in the database by logging into phpMyAdmin from the database server instance.  
