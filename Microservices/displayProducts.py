from flask import Flask
import mysql.connector

server_port = 8000
app = Flask(__name__)


@app.route('/displayProducts')
def displayProducts():
    # Creating connection object
    mydb = mysql.connector.connect(
        host = "localhost",
        user = "<your-db-username>",
        password = "<your-db-password>", 
        database = "Products"
    )

    print(mydb)
    
    html = "<br><table border='1'><th>Product </th><th>Description</th><th>Cost($)</th><th>Count</th>"

    cursor = mydb.cursor()
    cursor.execute("SELECT * FROM Footwear")
    for x in cursor:
        html += "<tr>"
        html +=  "<td>" + str(x[1]) + "</td>";
        html +=  "<td>" + str(x[2]) + "</td>";
        html +=  "<td>" + str(x[3]) + "</td>";
        html +=  "<td><select class='productCount' data-productname='" + str(x[1]) + "' data-productid=" +  str(x[0]) + " name='count'><option value=0>0</option><option value=1>1</option><option value=2>2</option></select></td>"
        html +=  "</tr>";

    return html


@app.route('/')
def index():
  return 'Home page.'


if __name__ == "__main__":
    app.run('0.0.0.0',port=server_port)
